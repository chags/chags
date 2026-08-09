<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

test('local hosts can use a local fallback login page when WorkOS is not configured', function () {
    config([
        'services.workos.client_id' => null,
        'services.workos.client_secret' => null,
        'services.workos.project_id' => null,
        'services.workos.redirect_url' => null,
    ]);

    $this->withServerVariables(['HTTP_HOST' => 'localhost'])
        ->get('/login')
        ->assertOk()
        ->assertSee('Login provisório');
});

test('local hosts can authenticate with email and password', function () {
    config([
        'services.workos.client_id' => null,
        'services.workos.client_secret' => null,
        'services.workos.project_id' => null,
        'services.workos.redirect_url' => null,
    ]);

    $user = User::factory()->create([
        'email' => 'local@example.com',
        'password' => Hash::make('password'),
    ]);

    $this->withServerVariables(['HTTP_HOST' => 'localhost'])
        ->post('/login', [
            'email' => 'local@example.com',
            'password' => 'password',
        ])
        ->assertRedirect('/dashboard');

    $this->assertAuthenticatedAs($user);
});
