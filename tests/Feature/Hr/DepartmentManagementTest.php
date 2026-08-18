<?php

use App\Models\Company;
use App\Models\Department;
use App\Models\HrAuditEvent;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->withoutVite();
    $this->seed(RolesAndPermissionsSeeder::class);
});

test('an hr manager can manage departments with audit events', function () {
    $manager = User::factory()->create();
    $manager->assignRole('rh-gestor');
    $company = Company::query()->create(['unit_type' => 'headquarters', 'unit_number' => '002', 'unit_name' => 'Matriz', 'name' => 'Chags', 'cnpj' => '22333444000191', 'address' => 'Rua B', 'address_number' => '2', 'district' => 'Centro', 'city' => 'São Paulo', 'state' => 'SP', 'postal_code' => '01001000', 'active' => true]);

    $this->actingAs($manager)->postJson('/hr/departments', ['company_id' => $company->id, 'name' => 'Tecnologia', 'active' => true])->assertCreated();
    $department = Department::query()->sole();
    $this->actingAs($manager)->putJson("/hr/departments/{$department->id}", ['company_id' => $company->id, 'name' => 'Tecnologia da Informação', 'active' => true])->assertOk();
    $this->actingAs($manager)->deleteJson("/hr/departments/{$department->id}")->assertOk();

    expect($department->fresh()->trashed())->toBeTrue()
        ->and(HrAuditEvent::query()->count())->toBe(3);
});

test('an hr analyst can view but cannot manage departments', function () {
    $analyst = User::factory()->create();
    $analyst->assignRole('rh-analista');

    $this->actingAs($analyst)->get('/hr/departments')->assertOk();
    $this->actingAs($analyst)->postJson('/hr/departments', ['company_id' => 1, 'name' => 'Bloqueado', 'active' => true])->assertForbidden();
});
