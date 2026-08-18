<?php

use App\Models\User;
use Database\Seeders\DemoUsersSeeder;

test('it creates one idempotent demo user for each non super admin role', function () {
    $this->seed(DemoUsersSeeder::class);
    $this->seed(DemoUsersSeeder::class);

    $expectedRoles = [
        'candidato',
        'colaborador',
        'gestor',
        'rh-analista',
        'rh-gestor',
        'dp-analista',
        'dp-gestor',
        'administrador',
    ];

    foreach ($expectedRoles as $role) {
        $user = User::query()->where('email', "{$role}@chags.local")->sole();

        expect($user->hasRole($role))->toBeTrue();
    }

    $this->assertDatabaseCount('users', count($expectedRoles));
});
