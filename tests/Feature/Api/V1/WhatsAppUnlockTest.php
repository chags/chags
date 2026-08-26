<?php

use App\Contracts\WhatsAppMessageSender;
use App\Models\User;
use App\Services\MobileApi\FakeWhatsAppMessageSender;

test('a registered whatsapp number receives a code and unlocks the api', function () {
    config()->set('mobile-api.faceio.enabled', false);
    $user = User::factory()->create([
        'whatsapp_phone' => '+5511999999999',
        'whatsapp_phone_verified_at' => now(),
        'tracks_time' => true,
    ]);
    $installationId = fake()->uuid();

    $request = $this->postJson('/api/v1/app-unlock/whatsapp/request', [
        'phone' => '(11) 99999-9999',
        'device_installation_id' => $installationId,
    ])->assertAccepted()
        ->assertJsonPath('message', 'Se o telefone estiver cadastrado, enviaremos um código.');

    $sender = app(WhatsAppMessageSender::class);
    expect($sender)->toBeInstanceOf(FakeWhatsAppMessageSender::class);
    $code = $sender->codeFor('+5511999999999');
    expect($code)->toMatch('/^\d{6}$/');

    $token = $this->postJson('/api/v1/app-unlock/whatsapp/verify', [
        'challenge_id' => $request->json('data.challenge_id'),
        'device_installation_id' => $installationId,
        'code' => $code,
    ])->assertOk()
        ->assertJsonPath('data.app_unlocked', true)
        ->json('data.access_token');

    $this->withToken($token)
        ->getJson('/api/v1/me')
        ->assertOk()
        ->assertJsonPath('data.id', $user->id)
        ->assertJsonPath('data.whatsapp_phone', '+5511*****9999');
});

test('an unknown whatsapp number receives the same public response', function () {
    $response = $this->postJson('/api/v1/app-unlock/whatsapp/request', [
        'phone' => '+5511888888888',
        'device_installation_id' => fake()->uuid(),
    ])->assertAccepted();

    $response->assertJsonStructure(['message', 'data' => ['challenge_id', 'expires_in', 'resend_after']]);
    $this->assertDatabaseHas('whatsapp_unlock_challenges', ['user_id' => null]);
});

test('a whatsapp code can only be used once', function () {
    User::factory()->create([
        'whatsapp_phone' => '+5511977777777',
        'whatsapp_phone_verified_at' => now(),
    ]);
    $installationId = fake()->uuid();
    $challengeId = $this->postJson('/api/v1/app-unlock/whatsapp/request', [
        'phone' => '+5511977777777',
        'device_installation_id' => $installationId,
    ])->json('data.challenge_id');
    $code = app(WhatsAppMessageSender::class)->codeFor('+5511977777777');
    $payload = [
        'challenge_id' => $challengeId,
        'device_installation_id' => $installationId,
        'code' => $code,
    ];

    $this->postJson('/api/v1/app-unlock/whatsapp/verify', $payload)->assertOk();
    $this->postJson('/api/v1/app-unlock/whatsapp/verify', $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors('code');
});
