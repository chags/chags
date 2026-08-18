<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

class DemoUsersSeeder extends Seeder
{
    /** @var array<string, string> */
    private array $usersByRole = [
        'candidato' => 'Candidato Demonstração',
        'colaborador' => 'Colaborador Demonstração',
        'gestor' => 'Gestor Demonstração',
        'rh-analista' => 'Analista de RH Demonstração',
        'rh-gestor' => 'Gestor de RH Demonstração',
        'dp-analista' => 'Analista de DP Demonstração',
        'dp-gestor' => 'Gestor de DP Demonstração',
        'administrador' => 'Administrador Demonstração',
    ];

    public function run(): void
    {
        if (app()->environment('production')) {
            throw new RuntimeException('Usuários fictícios não podem ser criados em produção.');
        }

        $this->call(RolesAndPermissionsSeeder::class);

        foreach ($this->usersByRole as $role => $name) {
            $user = User::query()->updateOrCreate(
                ['email' => "{$role}@chags.local"],
                [
                    'name' => $name,
                    'password' => 'Demo@123456',
                    'email_verified_at' => now(),
                    'workos_id' => "local-demo-{$role}",
                    'avatar' => '',
                ],
            );

            $user->syncRoles([$role]);
        }
    }
}
