<?php

use App\Models\Company;
use App\Models\Department;
use App\Models\HrAuditEvent;
use App\Models\Job;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

function hrCompany(): Company
{
    return Company::query()->create(['unit_type' => 'headquarters', 'unit_number' => '001', 'unit_name' => 'Matriz', 'name' => 'Chags', 'cnpj' => '11222333000181', 'address' => 'Rua A', 'address_number' => '1', 'district' => 'Centro', 'city' => 'São Paulo', 'state' => 'SP', 'postal_code' => '01001000', 'active' => true]);
}

test('an hr analyst can create and update a job with audit events', function () {
    $user = User::factory()->create();
    $user->assignRole('rh-analista');
    $company = hrCompany();
    $department = Department::query()->create(['company_id' => $company->id, 'name' => 'Tecnologia', 'slug' => 'tecnologia']);
    $payload = ['company_id' => $company->id, 'department_id' => $department->id, 'title' => 'Analista de Sistemas', 'description' => 'Atuação em sistemas corporativos.', 'benefits' => 'Plano de saúde e plano odontológico.', 'workplace_type' => 'hybrid', 'employment_type' => 'clt', 'status' => 'draft'];

    $this->actingAs($user)->postJson('/hr/jobs', $payload)->assertCreated();
    $job = Job::query()->sole();
    expect($job->slug)->toBe('analista-de-sistemas')
        ->and($job->benefits)->toBe('Plano de saúde e plano odontológico.');

    $this->actingAs($user)->putJson("/hr/jobs/{$job->id}", [...$payload, 'status' => 'published'])->assertOk();
    expect($job->fresh()->published_at)->not->toBeNull()
        ->and(HrAuditEvent::query()->where('auditable_id', $job->id)->count())->toBe(2);
});

test('only an hr manager can delete a job', function () {
    $analyst = User::factory()->create();
    $analyst->assignRole('rh-analista');
    $manager = User::factory()->create();
    $manager->assignRole('rh-gestor');
    $company = hrCompany();
    $department = Department::query()->create(['company_id' => $company->id, 'name' => 'RH', 'slug' => 'rh']);
    $job = Job::query()->create(['company_id' => $company->id, 'department_id' => $department->id, 'created_by' => $analyst->id, 'title' => 'Recrutador', 'slug' => 'recrutador', 'description' => 'Recrutamento.', 'workplace_type' => 'onsite', 'employment_type' => 'clt']);

    $this->actingAs($analyst)->deleteJson("/hr/jobs/{$job->id}")->assertForbidden();
    $this->actingAs($manager)->deleteJson("/hr/jobs/{$job->id}")->assertOk();
    expect($job->fresh()->trashed())->toBeTrue();
});

test('an hr analyst can upload a job image converted to webp', function () {
    Storage::fake('public');
    $analyst = User::factory()->create();
    $analyst->assignRole('rh-analista');
    $company = hrCompany();
    $department = Department::query()->create(['company_id' => $company->id, 'name' => 'Comercial', 'slug' => 'comercial']);
    $job = Job::query()->create(['company_id' => $company->id, 'department_id' => $department->id, 'created_by' => $analyst->id, 'title' => 'Consultor', 'slug' => 'consultor', 'description' => 'Atendimento.', 'workplace_type' => 'onsite', 'employment_type' => 'clt']);

    $this->actingAs($analyst)->postJson("/hr/jobs/{$job->id}/image", [
        'image' => UploadedFile::fake()->image('vaga.png', 800, 800),
    ])->assertCreated();

    expect($job->fresh()->image)->toEndWith('.webp');
    Storage::disk('public')->assertExists($job->fresh()->image);
});
