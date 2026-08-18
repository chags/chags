<?php

use App\Models\Application;
use App\Models\Company;
use App\Models\Curriculum;
use App\Models\Department;
use App\Models\Job;
use App\Models\RecruitmentStage;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->withoutVite();
    $this->seed(RolesAndPermissionsSeeder::class);
});

test('a candidate can log in through the institutional portal', function () {
    $candidate = User::factory()->create(['email' => 'candidate@portal.test', 'password' => 'candidate-password']);
    $candidate->assignRole('candidato');

    $this->post('/candidato/entrar', [
        'email' => 'candidate@portal.test',
        'password' => 'candidate-password',
    ])->assertRedirect('/candidato');

    $this->assertAuthenticatedAs($candidate);
});

test('a guest is redirected to the institutional candidate login', function () {
    $this->get('/candidato')->assertRedirect('/candidato/entrar');

    $this->get('/candidato/entrar')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('candidate/auth/login'));
});

test('a candidate sees cards only for their own applications', function () {
    [$candidate, $application] = candidateApplication();
    [$otherCandidate] = candidateApplication();

    $this->actingAs($candidate)
        ->get('/candidato')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('candidate/index')
            ->has('applications', 1)
            ->where('applications.0.id', $application->id));

    expect($otherCandidate->id)->not->toBe($candidate->id);
});

test('a candidate cannot access another candidate application', function () {
    [$candidate] = candidateApplication();
    [, $otherApplication] = candidateApplication();

    $this->actingAs($candidate)
        ->get("/candidato/candidaturas/{$otherApplication->id}")
        ->assertForbidden();
});

test('an unpublished job card is disabled and its application cannot be opened', function () {
    [$candidate, $application] = candidateApplication();
    $application->job->update(['status' => 'paused']);

    $this->actingAs($candidate)
        ->get('/candidato')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('applications.0.job.status', 'paused'));

    $this->actingAs($candidate)
        ->get("/candidato/candidaturas/{$application->id}")
        ->assertForbidden();
});

test('a closed job remains available for an existing candidate to follow', function () {
    [$candidate, $application] = candidateApplication();
    $application->job->update(['status' => 'closed']);

    $this->actingAs($candidate)
        ->get('/candidato')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('applications.0.job.status', 'closed'));

    $this->actingAs($candidate)
        ->get("/candidato/candidaturas/{$application->id}")
        ->assertOk();
});

test('the candidate timeline exposes public stages without confidential evaluation data', function () {
    [$candidate, $application, $company] = candidateApplication();
    $stage = RecruitmentStage::query()->create([
        'company_id' => $company->id,
        'name' => 'Entrevista interna RH',
        'public_name' => 'Conversa com nossa equipe',
        'public_description' => 'Momento para conhecermos melhor sua experiência.',
        'position' => 1,
        'type' => 'interview',
        'candidate_visible' => true,
        'active' => true,
    ]);
    $application->update(['current_stage_id' => $stage->id]);
    Curriculum::query()->create([
        'application_id' => $application->id,
        'extraction_status' => 'completed',
        'evaluation_status' => 'completed',
        'extracted_data' => ['personal' => ['email' => 'private@example.com']],
        'score' => 42,
        'opinion' => 'Parecer interno confidencial',
        'evaluation_error' => 'Erro interno confidencial',
        'evaluated_at' => now(),
    ]);

    $response = $this->actingAs($candidate)
        ->get("/candidato/candidaturas/{$application->id}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('candidate/applications/show')
            ->where('application.timeline.2.name', 'Conversa com nossa equipe')
            ->where('application.timeline.2.status', 'current'));

    expect($response->getContent())
        ->not->toContain('Parecer interno confidencial')
        ->not->toContain('private@example.com')
        ->not->toContain('Erro interno confidencial');
});

test('a rejection stops the timeline and exposes only the candidate message', function () {
    [$candidate, $application, $company] = candidateApplication();
    $rhStage = RecruitmentStage::query()->create([
        'company_id' => $company->id,
        'name' => 'Entrevista com RH',
        'public_name' => 'Entrevista com RH',
        'position' => 1,
        'type' => 'interview',
        'candidate_visible' => true,
        'active' => true,
    ]);
    RecruitmentStage::query()->create([
        'company_id' => $company->id,
        'name' => 'Entrevista técnica',
        'public_name' => 'Entrevista técnica',
        'position' => 2,
        'type' => 'interview',
        'candidate_visible' => true,
        'active' => true,
    ]);
    $application->update([
        'current_stage_id' => $rhStage->id,
        'status' => 'rejected',
        'rejected_at' => now(),
        'rejection_message' => 'Agradecemos sua participação. Seguiremos com outros perfis.',
        'rejection_internal_notes' => 'Nota interna confidencial.',
    ]);

    $response = $this->actingAs($candidate)
        ->get("/candidato/candidaturas/{$application->id}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('application.timeline.2.status', 'rejected')
            ->where('application.timeline.3.status', 'blocked')
            ->where('application.timeline.4.status', 'rejected')
            ->where('application.timeline.4.description', 'Agradecemos sua participação. Seguiremos com outros perfis.'));

    expect($response->getContent())->not->toContain('Nota interna confidencial.');
});

function candidateApplication(): array
{
    $company = Company::query()->create([
        'unit_type' => 'headquarters',
        'unit_number' => fake()->unique()->numerify('PORTAL-###'),
        'unit_name' => 'Matriz',
        'name' => 'Empresa Portal',
        'cnpj' => fake()->unique()->numerify('##############'),
        'address' => 'Rua A',
        'address_number' => '10',
        'district' => 'Centro',
        'city' => 'São Paulo',
        'state' => 'SP',
        'postal_code' => '01001000',
        'active' => true,
    ]);
    $department = Department::query()->create([
        'company_id' => $company->id,
        'name' => 'Tecnologia',
        'slug' => fake()->unique()->slug(),
    ]);
    $owner = User::factory()->create();
    $candidate = User::factory()->create();
    $candidate->assignRole('candidato');
    $job = Job::query()->create([
        'company_id' => $company->id,
        'department_id' => $department->id,
        'created_by' => $owner->id,
        'title' => 'Analista de Sistemas',
        'slug' => fake()->unique()->slug(),
        'description' => 'Descrição da vaga',
        'workplace_type' => 'remote',
        'employment_type' => 'clt',
        'status' => 'published',
    ]);
    $application = Application::query()->create([
        'job_id' => $job->id,
        'candidate_id' => $candidate->id,
        'status' => 'active',
        'source' => 'site',
        'applied_at' => now(),
    ]);

    return [$candidate, $application, $company];
}
