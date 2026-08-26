<?php

namespace App\Services\MobileApi;

use App\Contracts\AppAttestationVerifier;
use App\Models\ApiDevice;
use App\Models\DeviceChallenge;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

class DeviceRegistrationService
{
    public function __construct(
        private readonly DeviceChallengeService $challenges,
        private readonly DeviceSignatureVerifier $signatures,
        private readonly AppAttestationVerifier $attestations,
    ) {}

    public function register(User $user, DeviceChallenge $challenge, array $data, ?string $ip): ApiDevice
    {
        $this->challenges->consume($challenge, $user, $data['installation_id'], $data['nonce'], 'register');

        if (! $this->signatures->verify($data['public_key']['value'], $data['nonce'], $data['challenge_signature'])) {
            throw ValidationException::withMessages(['challenge_signature' => 'Assinatura do dispositivo inválida.']);
        }

        $attestation = $this->attestations->verify($data['attestation']['provider'], $data['attestation']['token'], $data['nonce']);
        if (! $attestation['valid']) {
            throw ValidationException::withMessages(['attestation.token' => 'Atestação do aplicativo inválida.']);
        }

        $existing = ApiDevice::query()->where('user_id', $user->id)->where('installation_id', $data['installation_id'])->first();
        $fingerprint = hash('sha256', $data['public_key']['value']);
        if ($existing && ! hash_equals($existing->key_fingerprint, $fingerprint)) {
            throw ValidationException::withMessages(['public_key.value' => 'A instalação apresentou uma nova chave e requer novo vínculo.']);
        }

        $requiresFace = config('mobile-api.faceio.enabled') && ! $user->first_app_access_completed_at;
        $now = now();

        return ApiDevice::query()->updateOrCreate(
            ['user_id' => $user->id, 'installation_id' => $data['installation_id']],
            [
                'name' => $data['device_name'] ?? null,
                'public_key' => $data['public_key']['value'],
                'key_algorithm' => $data['public_key']['algorithm'],
                'key_fingerprint' => $fingerprint,
                'platform' => $data['device']['platform'],
                'app_version' => $data['app']['version'],
                'app_build' => $data['app']['build'],
                'package_name' => $data['app']['package_name'],
                'signing_digest' => $data['app']['signing_digest'] ?? null,
                'manufacturer' => $data['device']['manufacturer'] ?? null,
                'model' => $data['device']['model'] ?? null,
                'os_version' => $data['device']['os_version'] ?? null,
                'security_patch' => Arr::get($data, 'device.security_patch'),
                'locale' => Arr::get($data, 'device.locale'),
                'timezone' => Arr::get($data, 'device.timezone'),
                'biometric_available' => Arr::get($data, 'device.biometric_available', false),
                'attestation_provider' => $data['attestation']['provider'],
                'attestation_status' => $attestation['status'],
                'risk_level' => $requiresFace ? 'medium' : 'low',
                'status' => $requiresFace ? 'face_verification_required' : 'trusted',
                'first_seen_at' => $existing?->first_seen_at ?? $now,
                'last_seen_at' => $now,
                'last_ip' => $ip,
            ],
        );
    }

    public function verify(User $user, ApiDevice $device, DeviceChallenge $challenge, array $data, ?string $ip): ApiDevice
    {
        abort_unless($device->user_id === $user->id && ! $device->revoked_at, 404);
        $this->challenges->consume($challenge, $user, $data['installation_id'], $data['nonce'], 'verify');

        if (! hash_equals($device->installation_id, $data['installation_id']) ||
            ! $this->signatures->verify($device->public_key, $data['nonce'], $data['challenge_signature'])) {
            $device->update(['status' => 'face_verification_required', 'risk_level' => 'high']);
            throw ValidationException::withMessages(['challenge_signature' => 'A identidade criptográfica do dispositivo mudou.']);
        }

        $attestation = $this->attestations->verify($data['attestation']['provider'], $data['attestation']['token'], $data['nonce']);
        if (! $attestation['valid']) {
            $device->update(['status' => 'blocked', 'attestation_status' => 'failed', 'risk_level' => 'high']);
            abort(403, 'Atestação do aplicativo inválida.');
        }

        $strongChange = $device->package_name !== $data['app']['package_name']
            || ($device->signing_digest && $device->signing_digest !== ($data['app']['signing_digest'] ?? null));
        $status = $strongChange ? 'face_verification_required' : 'trusted';
        $device->update([
            'app_version' => $data['app']['version'],
            'app_build' => $data['app']['build'],
            'os_version' => Arr::get($data, 'device.os_version'),
            'security_patch' => Arr::get($data, 'device.security_patch'),
            'locale' => Arr::get($data, 'device.locale'),
            'timezone' => Arr::get($data, 'device.timezone'),
            'biometric_available' => Arr::get($data, 'device.biometric_available', false),
            'attestation_status' => $attestation['status'],
            'risk_level' => $strongChange ? 'high' : 'low',
            'status' => $status,
            'last_seen_at' => now(),
            'last_ip' => $ip,
        ]);

        return $device->refresh();
    }
}
