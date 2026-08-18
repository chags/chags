<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use RuntimeException;
use Spatie\Permission\Models\Role;

class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(RolesAndPermissionsSeeder::class);

        $email = config('auth.super_admin.email');
        $password = config('auth.super_admin.password');

        if (! is_string($email) || ! is_string($password) || $email === '' || $password === '') {
            throw new RuntimeException(
                'Configure SUPER_ADMIN_EMAIL and SUPER_ADMIN_PASSWORD before running SuperAdminSeeder.',
            );
        }

        $user = User::query()->firstOrNew(['email' => $email]);

        $user->fill([
            'name' => config('auth.super_admin.name', 'Super Admin'),
            'password' => $password,
            'email_verified_at' => $user->email_verified_at ?? now(),
            'workos_id' => $user->workos_id ?? 'local-super-admin-'.Str::uuid(),
            'avatar' => $user->avatar ?? '',
        ]);

        $user->save();

        $role = Role::findOrCreate('super-admin', 'web');

        $user->syncRoles([$role]);

    }
}
