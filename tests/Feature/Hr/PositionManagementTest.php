<?php

use App\Models\Company;
use App\Models\Department;
use App\Models\HrAuditEvent;
use App\Models\Position;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->withoutVite();
    $this->seed(RolesAndPermissionsSeeder::class);
});

test('an hr manager can create update and delete positions with audit', function () {
    $manager = User::factory()->create();
    $manager->assignRole('rh-gestor');
    [$company, $department] = positionStructure();

    $this->actingAs($manager)->postJson('/hr/positions', [
        'company_id' => $company->id,
        'department_id' => $department->id,
        'title' => 'Analista de Sistemas',
        'level' => 'senior',
        'code' => 'ti-001',
        'description' => 'Atuação em sistemas corporativos.',
        'active' => true,
    ])->assertCreated();

    $position = Position::query()->sole();
    expect($position->code)->toBe('TI-001');

    $this->actingAs($manager)->putJson("/hr/positions/{$position->id}", [
        'company_id' => $company->id,
        'department_id' => $department->id,
        'title' => 'Especialista de Sistemas',
        'level' => 'specialist',
        'code' => 'TI-001',
        'description' => null,
        'active' => true,
    ])->assertOk();

    $this->actingAs($manager)
        ->deleteJson("/hr/positions/{$position->id}")
        ->assertOk();

    expect($position->fresh()->trashed())->toBeTrue()
        ->and(HrAuditEvent::query()->where('auditable_type', $position->getMorphClass())->count())->toBe(3);
});

test('an hr analyst can view but cannot manage positions', function () {
    $analyst = User::factory()->create();
    $analyst->assignRole('rh-analista');
    [$company] = positionStructure();

    $this->actingAs($analyst)->get('/hr/positions')->assertOk();
    $this->actingAs($analyst)->postJson('/hr/positions', [
        'company_id' => $company->id,
        'title' => 'Cargo bloqueado',
        'active' => true,
    ])->assertForbidden();
});

test('positions are filtered and paginated', function () {
    $manager = User::factory()->create();
    $manager->assignRole('rh-gestor');
    [$company, $department] = positionStructure();

    foreach (range(1, 16) as $index) {
        Position::query()->create([
            'company_id' => $company->id,
            'department_id' => $department->id,
            'title' => "Cargo {$index}",
            'code' => "C-{$index}",
            'active' => $index !== 16,
        ]);
    }

    $this->actingAs($manager)
        ->get('/hr/positions')
        ->assertInertia(fn (Assert $page) => $page
            ->where('positions.total', 16)
            ->has('positions.data', 15)
            ->where('positions.last_page', 2));

    $this->actingAs($manager)
        ->get('/hr/positions?status=inactive&search=Cargo+16')
        ->assertInertia(fn (Assert $page) => $page
            ->where('positions.total', 1)
            ->where('positions.data.0.title', 'Cargo 16'));
});

function positionStructure(): array
{
    $company = Company::query()->create([
        'unit_type' => 'headquarters',
        'unit_number' => fake()->unique()->numerify('POS-###'),
        'unit_name' => 'Matriz',
        'name' => 'Chags',
        'cnpj' => fake()->unique()->numerify('##############'),
        'address' => 'Rua A',
        'address_number' => '1',
        'district' => 'Centro',
        'city' => 'São Paulo',
        'state' => 'SP',
        'postal_code' => '01001000',
        'active' => true,
    ]);
    $department = Department::query()->create([
        'company_id' => $company->id,
        'name' => 'Tecnologia',
        'slug' => 'tecnologia',
        'active' => true,
    ]);

    return [$company, $department];
}
