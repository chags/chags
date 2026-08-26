<?php

namespace App\Services\MobileApi;

use App\Models\DeviceChallenge;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class DeviceChallengeService
{
    /** @return array{challenge: DeviceChallenge, nonce: string} */
    public function create(User $user, string $installationId, string $purpose, ?string $ip): array
    {
        $nonce = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        $challenge = DeviceChallenge::query()->create([
            'user_id' => $user->id,
            'installation_id' => $installationId,
            'purpose' => $purpose,
            'nonce_hash' => hash('sha256', $nonce),
            'expires_at' => now()->addSeconds(config('mobile-api.device.challenge_ttl_seconds')),
            'request_ip' => $ip,
        ]);

        return compact('challenge', 'nonce');
    }

    public function consume(DeviceChallenge $challenge, User $user, string $installationId, string $nonce, string $purpose): void
    {
        $valid = $challenge->user_id === $user->id
            && hash_equals($challenge->installation_id, $installationId)
            && $challenge->purpose === $purpose
            && ! $challenge->consumed_at
            && $challenge->expires_at->isFuture()
            && hash_equals($challenge->nonce_hash, hash('sha256', $nonce));

        if (! $valid) {
            throw ValidationException::withMessages(['challenge_id' => 'Desafio inválido ou expirado.']);
        }

        $challenge->update(['consumed_at' => now()]);
    }
}
