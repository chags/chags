<?php

use App\Models\User;
use App\Models\WorkScheduleGroup;
use Database\Seeders\SuperAdminSeeder;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->withoutVite()->withoutMiddleware(PreventRequestForgery::class);
    $this->seed(SuperAdminSeeder::class);
});

test('administrators can access user management and create administrators', function () {
    $administrator = User::factory()->create();
    $administrator->assignRole('administrador');

    $this->actingAs($administrator)->get('/users')->assertOk();
    $this->actingAs($administrator)->postJson('/users', [
        'name' => 'New Administrator',
        'email' => 'new-admin@example.com',
        'cpf' => '52998224725',
        'phone' => '11999999999',
        'birth_date' => '1990-05-20',
        'gender' => 'not_informed',
        'postal_code' => '01001000',
        'address' => 'Praça da Sé',
        'address_number' => '100',
        'district' => 'Sé',
        'city' => 'São Paulo',
        'state' => 'SP',
        'password' => 'secure-password',
        'password_confirmation' => 'secure-password',
        'role' => 'administrador',
    ])->assertCreated();

    $user = User::query()->where('email', 'new-admin@example.com')->sole();

    expect($user->hasRole('administrador'))->toBeTrue()
        ->and($user->postal_code)->toBe('01001000')
        ->and($user->city)->toBe('São Paulo');
});

test('a time tracking user must have an active work schedule', function () {
    $administrator = User::factory()->create();
    $administrator->assignRole('administrador');
    $user = User::factory()->create();
    $user->assignRole('colaborador');
    $group = WorkScheduleGroup::query()->create([
        'name' => 'Comercial', 'schedule_type' => '6x1', 'weekly_minutes' => 2640,
        'entry_tolerance_minutes' => 10, 'daily_tolerance_minutes' => 10,
        'operational_window_minutes' => 10, 'daily_overtime_limit_minutes' => 120,
        'requires_overtime_approval' => true, 'active' => true,
    ]);

    $payload = [
        'name' => $user->name,
        'email' => $user->email,
        'role' => 'colaborador',
        'tracks_time' => true,
    ];

    $this->actingAs($administrator)
        ->putJson("/users/{$user->id}", $payload)
        ->assertJsonValidationErrors('work_schedule_group_id');

    $this->actingAs($administrator)
        ->putJson("/users/{$user->id}", [...$payload, 'work_schedule_group_id' => $group->id])
        ->assertOk();

    expect($user->fresh()->tracks_time)->toBeTrue();
    $this->assertDatabaseHas('work_schedule_assignments', [
        'user_id' => $user->id,
        'work_schedule_group_id' => $group->id,
        'active' => true,
    ]);
});

test('a super admin can upload a user photo converted to webp', function () {
    Storage::fake('public');
    $superAdmin = User::query()->where('email', config('auth.super_admin.email'))->sole();
    $user = User::factory()->create();
    $user->assignRole('administrador');

    $this->actingAs($superAdmin)
        ->postJson("/users/{$user->id}/avatar", [
            'avatar' => UploadedFile::fake()->image('avatar.png', 36, 36),
        ])
        ->assertCreated();

    $avatar = $user->refresh()->avatar;

    expect($avatar)->toStartWith('/storage/users/')->toEndWith('.webp');
    Storage::disk('public')->assertExists(str($avatar)->after('/storage/')->toString());
});

test('administrators cannot assign or modify super admin accounts', function () {
    $administrator = User::factory()->create();
    $administrator->assignRole('administrador');
    $superAdmin = User::query()->where('email', config('auth.super_admin.email'))->sole();

    $this->actingAs($administrator)->postJson('/users', [
        'name' => 'Forbidden Super Admin',
        'email' => 'forbidden@example.com',
        'password' => 'secure-password',
        'password_confirmation' => 'secure-password',
        'role' => 'super-admin',
    ])->assertForbidden();

    $this->actingAs($administrator)->putJson("/users/{$superAdmin->id}", [
        'name' => $superAdmin->name,
        'email' => $superAdmin->email,
        'role' => 'super-admin',
    ])->assertForbidden();
});

test('a user cannot delete their own account through user management', function () {
    $superAdmin = User::query()->where('email', config('auth.super_admin.email'))->sole();

    $this->actingAs($superAdmin)
        ->deleteJson("/users/{$superAdmin->id}")
        ->assertUnprocessable();

    expect($superAdmin->fresh())->not->toBeNull();
});

test('a super admin can impersonate a user and return to the original account', function () {
    $superAdmin = User::query()->where('email', config('auth.super_admin.email'))->sole();
    $user = User::factory()->create();
    $user->assignRole('administrador');

    $this->actingAs($superAdmin)
        ->post("/users/{$user->id}/impersonate")
        ->assertRedirect('/dashboard')
        ->assertSessionHas('impersonation.original_user_id', $superAdmin->id);

    $this->assertAuthenticatedAs($user);

    $response = $this->post('/impersonation/stop');

    $response->assertRedirect('/users');
    $response->assertSessionMissing('impersonation.original_user_id');

    $this->assertAuthenticatedAs($superAdmin);
});

test('an administrator cannot impersonate another user', function () {
    $administrator = User::factory()->create();
    $administrator->assignRole('administrador');
    $user = User::factory()->create();

    $this->actingAs($administrator)
        ->post("/users/{$user->id}/impersonate")
        ->assertForbidden();

    $this->assertAuthenticatedAs($administrator);
});
