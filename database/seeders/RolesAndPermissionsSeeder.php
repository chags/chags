<?php

namespace Database\Seeders;

use App\Support\Authorization\RoleCatalog;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    /** @var array<string, list<string>> */
    private array $permissionsByRole = [
        'candidato' => [
            'jobs.view',
            'applications.view-own',
            'applications.create',
            'applications.update-own',
            'admissions.view-own',
            'admissions.submit-own',
            'employee-documents.upload-own',
        ],
        'colaborador' => [
            'intranet.access',
            'announcements.view',
            'policies.view',
            'employees.view-own',
            'employees.update-own',
            'employee-documents.view-own',
            'employee-documents.upload-own',
            'time-records.view-own',
            'vacations.view-own',
            'vacations.request',
            'benefits.view-own',
            'payroll.view-own',
        ],
        'gestor' => [
            'intranet.access',
            'announcements.view',
            'policies.view',
            'employees.view-own',
            'employees.update-own',
            'employees.view-team',
            'employee-documents.view-own',
            'time-records.view-own',
            'time-records.view-team',
            'time-records.approve',
            'vacations.view-own',
            'vacations.request',
            'vacations.view-team',
            'vacations.approve',
            'benefits.view-own',
            'payroll.view-own',
            'jobs.view',
            'applications.view',
            'applications.evaluate',
            'interviews.view',
        ],
        'rh-analista' => [
            'intranet.access',
            'announcements.view',
            'policies.view',
            'jobs.view',
            'jobs.create',
            'jobs.update',
            'jobs.publish',
            'jobs.close',
            'applications.view',
            'applications.update-status',
            'applications.evaluate',
            'interviews.view', 'interviews.create', 'interviews.update', 'interviews.cancel', 'interviews.send-invitations',
            'employees.view',
            'employees.create',
            'employees.update',
            'departments.view',
            'positions.view',
            'admissions.view',
            'admissions.update',
            'employee-documents.view',
            'employee-documents.manage',
            'vacations.view-team',
            'vacations.manage',
            'benefits.view',
            'benefits.manage',
        ],
        'rh-gestor' => [
            'admissions.approve',
            'applications.delete',
            'jobs.delete',
            'departments.manage',
            'employees.deactivate',
            'positions.manage',
            'vacations.approve',
        ],
        'dp-analista' => [
            'intranet.access',
            'announcements.view',
            'policies.view',
            'employees.view',
            'departments.view',
            'positions.view',
            'admissions.view',
            'admissions.update',
            'employee-documents.view',
            'employee-documents.manage',
            'time-records.view',
            'time-records.manage',
            'vacations.view-team',
            'vacations.manage',
            'benefits.view',
            'benefits.manage',
            'payroll.view',
            'payroll.manage',
        ],
        'dp-gestor' => [
            'admissions.approve',
            'employees.deactivate',
            'time-records.approve',
            'vacations.approve',
            'payroll.approve',
        ],
        'administrador' => [
            'system.settings.view',
            'system.settings.company.update',
            'system.settings.mail.update',
            'system.settings.mail.test',
            'system.settings.seo.update',
            'system.settings.appearance.update',
            'system.settings.turnstile.update',
            'system.settings.ai.update',
            'system.settings.ai.test',
            'users.view',
            'users.create',
            'users.update',
            'users.delete',
        ],
        'super-admin' => [],
    ];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (RoleCatalog::names() as $roleName) {
            Role::findOrCreate($roleName, 'web');
        }

        $resolved = [];

        foreach (collect($this->permissionsByRole)->flatten()->unique() as $permissionName) {
            $resolved[$permissionName] = Permission::findOrCreate($permissionName, 'web');
        }

        $inheritedPermissions = $this->permissionsByRole;
        $inheritedPermissions['rh-gestor'] = array_values(array_unique([
            ...$inheritedPermissions['rh-analista'],
            ...$inheritedPermissions['rh-gestor'],
        ]));
        $inheritedPermissions['dp-gestor'] = array_values(array_unique([
            ...$inheritedPermissions['dp-analista'],
            ...$inheritedPermissions['dp-gestor'],
        ]));

        foreach ($inheritedPermissions as $roleName => $permissionNames) {
            Role::findByName($roleName, 'web')->syncPermissions(
                array_map(fn (string $name) => $resolved[$name], $permissionNames),
            );
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
