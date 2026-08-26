<?php

use App\Models\InAppMessage;
use App\Models\InAppMessageRecipient;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    $this->withoutMiddleware(PreventRequestForgery::class);
});

function grantMessagePermission(User $user, string $permission): void
{
    $user->givePermissionTo(Permission::findOrCreate($permission, 'web'));
}

test('a user only sees their own active in-app messages', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    grantMessagePermission($user, 'messages.view-own');

    $message = InAppMessage::query()->create([
        'type' => 'administrative', 'status' => 'sent', 'title' => 'Minha mensagem',
        'body' => 'Conteúdo', 'audience' => 'user', 'published_at' => now(),
    ]);
    $own = $message->recipients()->create(['user_id' => $user->id]);
    $otherRecipient = $message->recipients()->create(['user_id' => $other->id]);

    $this->actingAs($user)->getJson('/mensagens/resumo')
        ->assertOk()
        ->assertJsonPath('unreadCount', 1)
        ->assertJsonPath('messages.0.id', $own->id);

    $this->actingAs($user)->patchJson("/mensagens/{$otherRecipient->id}/lida")->assertNotFound();
});

test('requesting an app code creates an encrypted private message', function () {
    $user = User::factory()->create([
        'phone' => '11999999999',
        'whatsapp_phone' => null,
        'whatsapp_phone_verified_at' => null,
    ]);
    grantMessagePermission($user, 'messages.view-own');

    $this->postJson('/api/v1/app-unlock/whatsapp/request', [
        'phone' => '+5511999999999',
        'device_installation_id' => fake()->uuid(),
    ])->assertAccepted();

    $message = InAppMessage::query()->where('type', 'app_unlock_code')->firstOrFail();
    $code = $message->sensitive_payload['code'];
    expect($code)->toMatch('/^\d{6}$/');
    expect((string) DB::table('in_app_messages')->where('id', $message->id)->value('sensitive_payload'))
        ->not->toContain($code);

    $this->actingAs($user)->getJson('/mensagens/resumo')
        ->assertOk()
        ->assertJsonPath('messages.0.code', $code);
});

test('personnel can send one administrative message to all users', function () {
    $personnel = User::factory()->create();
    User::factory()->count(2)->create();
    grantMessagePermission($personnel, 'messages.manage');
    grantMessagePermission($personnel, 'messages.send');

    $this->actingAs($personnel)->postJson('/personnel/mensagens', [
        'title' => 'Comunicado geral',
        'body' => 'Mensagem para todos.',
        'audience' => 'all',
        'send_now' => true,
    ])->assertCreated();

    $message = InAppMessage::query()->where('title', 'Comunicado geral')->firstOrFail();
    expect($message->status)->toBe('sent');
    expect(InAppMessageRecipient::query()->where('message_id', $message->id)->count())->toBe(3);
    expect($message->sensitive_payload)->toBeNull();
});

test('expired codes are not returned by the notification endpoint', function () {
    $user = User::factory()->create();
    grantMessagePermission($user, 'messages.view-own');
    $message = InAppMessage::query()->create([
        'type' => 'app_unlock_code', 'status' => 'sent', 'title' => 'Código',
        'body' => 'Expirado', 'sensitive_payload' => ['code' => '123456'],
        'audience' => 'user', 'published_at' => now()->subMinutes(10),
        'expires_at' => now()->subMinute(),
    ]);
    $message->recipients()->create(['user_id' => $user->id]);

    $this->actingAs($user)->getJson('/mensagens/resumo')
        ->assertOk()
        ->assertJsonPath('unreadCount', 0)
        ->assertJsonCount(0, 'messages');
});

test('a message can only be deleted after reading and records an audit event', function () {
    $user = User::factory()->create();
    grantMessagePermission($user, 'messages.view-own');
    $message = InAppMessage::query()->create([
        'type' => 'administrative', 'status' => 'sent', 'title' => 'Leia primeiro',
        'body' => 'Conteúdo', 'audience' => 'user', 'published_at' => now(),
    ]);
    $recipient = $message->recipients()->create(['user_id' => $user->id]);

    $this->actingAs($user)->deleteJson("/mensagens/{$recipient->id}")
        ->assertUnprocessable()
        ->assertJsonPath('message', 'Marque a mensagem como lida antes de excluí-la.');

    $this->actingAs($user)->patchJson("/mensagens/{$recipient->id}/lida")->assertOk();
    $this->actingAs($user)->deleteJson("/mensagens/{$recipient->id}")->assertOk();

    expect($recipient->refresh()->dismissed_at)->not->toBeNull();
    $this->assertDatabaseHas('in_app_message_audit_events', [
        'message_id' => $message->id,
        'recipient_id' => $recipient->id,
        'user_id' => $user->id,
        'event' => 'recipient_deleted',
    ]);
    $this->actingAs($user)->getJson('/mensagens/resumo')->assertJsonCount(0, 'messages');
});
