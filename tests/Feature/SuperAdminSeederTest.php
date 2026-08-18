<?php

use App\Models\User;
use Database\Seeders\SuperAdminSeeder;

test('it creates an idempotent super admin user', function () {
    config([
        'auth.super_admin.name' => 'System Administrator',
        'auth.super_admin.email' => 'admin@example.com',
        'auth.super_admin.password' => 'a-secure-test-password',
    ]);

    $this->seed(SuperAdminSeeder::class);
    $this->seed(SuperAdminSeeder::class);

    $user = User::query()->where('email', 'admin@example.com')->sole();

    expect($user)
        ->name->toBe('System Administrator')
        ->hasRole('super-admin')->toBeTrue();

    $this->assertDatabaseCount('users', 1);
    $this->assertDatabaseCount('roles', 9);
    $this->assertDatabaseHas('roles', ['name' => 'administrador', 'guard_name' => 'web']);
    $this->assertDatabaseHas('roles', ['name' => 'candidato', 'guard_name' => 'web']);
    $this->assertDatabaseHas('roles', ['name' => 'colaborador', 'guard_name' => 'web']);
    $this->assertDatabaseHas('roles', ['name' => 'rh-analista', 'guard_name' => 'web']);
    $this->assertDatabaseHas('roles', ['name' => 'dp-analista', 'guard_name' => 'web']);
});
