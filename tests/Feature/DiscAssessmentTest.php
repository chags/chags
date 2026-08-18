<?php

use App\Models\Application;
use App\Models\Company;
use App\Models\Department;
use App\Models\DiscAssessment;
use App\Models\DiscQuestion;
use App\Models\Job;
use App\Models\RecruitmentStage;
use App\Models\User;
use Database\Seeders\DiscQuestionsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->withoutVite();
    $this->seed([RolesAndPermissionsSeeder::class, DiscQuestionsSeeder::class]);
});

test('candidate completes disc once and receives dominant profile', function () {
    [$candidate, $application] = discApplication();
    $this->actingAs($candidate)->post("/candidato/candidaturas/{$application->id}/disc/iniciar", ['consent' => true])->assertRedirect();
    $questions = DiscQuestion::query()->with('options')->orderBy('position')->get();

    foreach ($questions as $question) {
        $option = $question->options->firstWhere('dimension', 'D');
        $this->actingAs($candidate)->putJson("/candidato/candidaturas/{$application->id}/disc/respostas/{$question->id}", ['option_id' => $option->id])->assertOk();
    }

    $this->actingAs($candidate)->postJson("/candidato/candidaturas/{$application->id}/disc/concluir")
        ->assertOk()->assertJsonPath('result.profile', 'D');
    $assessment = DiscAssessment::query()->sole();
    expect($assessment->status)->toBe('completed')->and($assessment->d_score)->toBe(20)->and($assessment->completed_at)->not->toBeNull();

    $first = $questions->first();
    $this->actingAs($candidate)->putJson("/candidato/candidaturas/{$application->id}/disc/respostas/{$first->id}", ['option_id' => $first->options->first()->id])->assertUnprocessable();
    $this->actingAs($candidate)->post("/candidato/candidaturas/{$application->id}/disc/iniciar", ['consent' => true])->assertUnprocessable();
});

test('candidate cannot use an option from another question or skip answers', function () {
    [$candidate, $application] = discApplication();
    $this->actingAs($candidate)->post("/candidato/candidaturas/{$application->id}/disc/iniciar", ['consent' => true]);
    $questions = DiscQuestion::query()->with('options')->orderBy('position')->take(2)->get();

    $this->actingAs($candidate)->putJson("/candidato/candidaturas/{$application->id}/disc/respostas/{$questions[0]->id}", ['option_id' => $questions[1]->options->first()->id])->assertUnprocessable();
    $this->actingAs($candidate)->postJson("/candidato/candidaturas/{$application->id}/disc/concluir")->assertUnprocessable();
});

test('disc is restricted to its phase and application owner', function () {
    [$candidate, $application] = discApplication();
    $other = User::factory()->create();
    $other->assignRole('candidato');
    $this->actingAs($other)->get("/candidato/candidaturas/{$application->id}/disc")->assertForbidden();
    $application->update(['current_stage_id' => null]);
    $this->actingAs($candidate)->get("/candidato/candidaturas/{$application->id}/disc")->assertForbidden();
});

function discApplication(): array
{
    $company = Company::query()->create(['unit_type' => 'headquarters', 'unit_number' => fake()->unique()->numerify('DISC-###'), 'unit_name' => 'Matriz', 'name' => 'Empresa', 'cnpj' => fake()->unique()->numerify('##############'), 'address' => 'Rua A', 'address_number' => '1', 'district' => 'Centro', 'city' => 'São Paulo', 'state' => 'SP', 'postal_code' => '01001000', 'active' => true]);
    $department = Department::query()->create(['company_id' => $company->id, 'name' => 'RH', 'slug' => fake()->unique()->slug()]);
    $owner = User::factory()->create();
    $candidate = User::factory()->create();
    $candidate->assignRole('candidato');
    $stage = RecruitmentStage::query()->create(['company_id' => $company->id, 'name' => 'Teste DISC', 'public_name' => 'Teste comportamental DISC', 'position' => 1, 'type' => 'assessment', 'active' => true, 'candidate_visible' => true, 'candidate_action' => 'disc']);
    $job = Job::query()->create(['company_id' => $company->id, 'department_id' => $department->id, 'created_by' => $owner->id, 'title' => 'Vaga DISC', 'slug' => fake()->unique()->slug(), 'description' => 'Descrição', 'workplace_type' => 'remote', 'employment_type' => 'clt', 'status' => 'published']);
    $application = Application::query()->create(['job_id' => $job->id, 'candidate_id' => $candidate->id, 'current_stage_id' => $stage->id, 'status' => 'active', 'source' => 'site', 'applied_at' => now()]);

    return [$candidate, $application];
}
