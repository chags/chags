<?php

use App\Models\ApiDevice;
use App\Models\User;
use Carbon\CarbonImmutable;

function apiTokenFor(User $user, array $claims = []): string
{
    return auth('api')->claims(['app_unlocked' => true, ...$claims])->fromUser($user);
}

function ecCredentials(string $nonce): array
{
    $key = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']);
    openssl_pkey_export($key, $privateKey);
    $publicKey = openssl_pkey_get_details($key)['key'];
    openssl_sign($nonce, $signature, $privateKey, OPENSSL_ALGO_SHA256);

    return [$publicKey, base64_encode($signature)];
}

test('an authenticated user registers a cryptographically signed device', function () {
    config()->set('mobile-api.faceio.enabled', false);
    $user = User::factory()->create(['tracks_time' => true, 'first_app_access_completed_at' => now()]);
    $token = apiTokenFor($user);
    $installationId = fake()->uuid();
    $challenge = $this->withToken($token)->postJson('/api/v1/devices/challenges', [
        'installation_id' => $installationId,
        'platform' => 'android',
        'purpose' => 'register',
    ])->assertCreated()->json('data');
    [$publicKey, $signature] = ecCredentials($challenge['nonce']);

    $response = $this->withToken($token)->postJson('/api/v1/devices/register', [
        'challenge_id' => $challenge['challenge_id'],
        'nonce' => $challenge['nonce'],
        'installation_id' => $installationId,
        'device_name' => 'Telefone de teste',
        'public_key' => ['algorithm' => 'ES256', 'value' => $publicKey],
        'challenge_signature' => $signature,
        'app' => ['version' => '1.0.0', 'build' => '1', 'package_name' => 'com.chags.ponto'],
        'device' => [
            'platform' => 'android', 'manufacturer' => 'Google', 'model' => 'Pixel',
            'os_version' => '16', 'locale' => 'pt-BR', 'timezone' => 'America/Sao_Paulo',
            'biometric_available' => true,
        ],
        'attestation' => ['provider' => 'fake', 'token' => 'valid-test-attestation'],
    ])->assertCreated()
        ->assertJsonPath('data.status', 'trusted')
        ->assertJsonPath('data.trusted', true);

    $this->assertDatabaseHas('api_devices', [
        'id' => $response->json('data.id'),
        'user_id' => $user->id,
        'installation_id' => $installationId,
        'status' => 'trusted',
    ]);
});

test('mobile time punch uses server time and mobile source', function () {
    CarbonImmutable::setTestNow('2026-08-25 11:00:00');
    $user = User::factory()->create(['tracks_time' => true]);
    $device = ApiDevice::query()->create([
        'user_id' => $user->id,
        'installation_id' => fake()->uuid(),
        'public_key' => 'test', 'key_fingerprint' => hash('sha256', fake()->uuid()),
        'platform' => 'android', 'app_version' => '1', 'app_build' => '1',
        'package_name' => 'com.chags.ponto', 'attestation_provider' => 'fake',
        'attestation_status' => 'verified', 'risk_level' => 'low', 'status' => 'trusted',
        'first_seen_at' => now(), 'last_seen_at' => now(),
    ]);

    $this->withToken(apiTokenFor($user))
        ->withHeaders(['X-Device-ID' => $device->id, 'Idempotency-Key' => fake()->uuid()])
        ->postJson('/api/v1/time-punch')
        ->assertCreated()
        ->assertJsonPath('data.registered_type', 'clock_in');

    $this->assertDatabaseHas('time_entries', ['user_id' => $user->id, 'type' => 'clock_in', 'source' => 'mobile']);
    CarbonImmutable::setTestNow();
});
