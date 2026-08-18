<?php

use Database\Seeders\RolesAndPermissionsSeeder;
use Spatie\Permission\Models\Role;

test('it creates the approved role and permission matrix idempotently', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(RolesAndPermissionsSeeder::class);

    expect(Role::findByName('candidato')->hasPermissionTo('applications.create'))->toBeTrue()
        ->and(Role::findByName('colaborador')->hasPermissionTo('employees.view-own'))->toBeTrue()
        ->and(Role::findByName('gestor')->hasPermissionTo('employees.view-team'))->toBeTrue()
        ->and(Role::findByName('rh-analista')->hasPermissionTo('jobs.publish'))->toBeTrue()
        ->and(Role::findByName('rh-gestor')->hasPermissionTo('admissions.approve'))->toBeTrue()
        ->and(Role::findByName('rh-gestor')->hasPermissionTo('jobs.publish'))->toBeTrue()
        ->and(Role::findByName('dp-analista')->hasPermissionTo('payroll.manage'))->toBeTrue()
        ->and(Role::findByName('dp-gestor')->hasPermissionTo('payroll.approve'))->toBeTrue()
        ->and(Role::findByName('dp-gestor')->hasPermissionTo('payroll.manage'))->toBeTrue()
        ->and(Role::findByName('administrador')->hasPermissionTo('users.view'))->toBeTrue()
        ->and(Role::findByName('administrador')->hasPermissionTo('payroll.view'))->toBeFalse();

    $this->assertDatabaseCount('roles', 9);
});
