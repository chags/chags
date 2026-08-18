<?php

use App\Models\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->withoutMiddleware(PreventRequestForgery::class);
});

test('profile page is displayed', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->get(route('profile.edit'));

    $response->assertOk();
});

test('profile information can be updated', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->patch('/settings/profile', [
            'name' => 'Updated Name',
            'cpf' => '529.982.247-25',
            'birth_date' => '1990-05-20',
            'phone' => '(11) 99999-9999',
            'gender' => 'not_informed',
            'postal_code' => '01001-000',
            'address' => 'Praça da Sé',
            'address_number' => '100',
            'district' => 'Sé',
            'city' => 'São Paulo',
            'state' => 'sp',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('profile.edit'));

    $user->refresh();

    expect($user->name)->toBe('Updated Name')
        ->and($user->cpf)->toBe('52998224725')
        ->and($user->phone)->toBe('11999999999')
        ->and($user->postal_code)->toBe('01001000')
        ->and($user->state)->toBe('SP');
});

test('profile photo is converted to webp', function () {
    Storage::fake('public');
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post('/settings/profile?_method=PATCH', [
            'name' => $user->name,
            'avatar' => UploadedFile::fake()->image('profile.png', 36, 36),
        ])
        ->assertRedirect(route('profile.edit'));

    $avatar = $user->refresh()->avatar;

    expect($avatar)->toStartWith('/storage/users/')->toEndWith('.webp');
    Storage::disk('public')->assertExists(str($avatar)->after('/storage/')->toString());
});

test('user can delete their account', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->delete(route('profile.destroy'), [
            'password' => 'password',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect('/');

    $this->assertGuest();
    expect($user->fresh())->toBeNull();
});
