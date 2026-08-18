<?php

use App\Models\AiProviderSetting;
use App\Models\Application;
use App\Models\ApplicationStageHistory;
use App\Models\Company;
use App\Models\Curriculum;
use App\Models\Department;
use App\Models\DiscAssessment;
use App\Models\HrAuditEvent;
use App\Models\Job;
use App\Models\RecruitmentStage;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->withoutVite();
    $this->seed(RolesAndPermissionsSeeder::class);
});

test('an hr analyst can list update and download a candidate resume', function () {
    Storage::fake('local');
    [$application, $company] = managedApplication();
    $analyst = User::factory()->create();
    $analyst->assignRole('rh-analista');
    $stage = RecruitmentStage::query()->create([
        'company_id' => $company->id,
        'name' => 'Entrevista com RH',
        'position' => 1,
        'type' => 'interview',
        'active' => true,
    ]);
    Storage::disk('local')->put($application->resume_path, 'curriculo');

    $this->actingAs($analyst)
        ->get('/hr/applications')
        ->assertOk()
        ->assertSee('Candidato CRUD');

    $this->actingAs($analyst)->putJson("/hr/applications/{$application->id}", [
        'status' => 'active',
        'current_stage_id' => $stage->id,
        'notes' => 'Perfil aprovado na triagem.',
    ])->assertOk()->assertJsonPath('message', 'Candidatura atualizada com sucesso.');

    expect($application->refresh()->current_stage_id)->toBe($stage->id)
        ->and(ApplicationStageHistory::query()->where('application_id', $application->id)->exists())->toBeTrue()
        ->and(HrAuditEvent::query()->where('event', 'application.updated')->exists())->toBeTrue();

    $this->actingAs($analyst)
        ->get("/hr/applications/{$application->id}/resume")
        ->assertOk()
        ->assertDownload('curriculo.pdf');
});

test('only an hr manager can delete an application', function () {
    [$application] = managedApplication();
    $analyst = User::factory()->create();
    $analyst->assignRole('rh-analista');
    $manager = User::factory()->create();
    $manager->assignRole('rh-gestor');

    $this->actingAs($analyst)
        ->deleteJson("/hr/applications/{$application->id}")
        ->assertForbidden();

    $this->actingAs($manager)
        ->deleteJson("/hr/applications/{$application->id}")
        ->assertOk();

    expect($application->fresh()->trashed())->toBeTrue()
        ->and(HrAuditEvent::query()->where('event', 'application.deleted')->exists())->toBeTrue();
});

test('rejection requires a public message and keeps internal notes private', function () {
    [$application] = managedApplication();
    $analyst = User::factory()->create();
    $analyst->assignRole('rh-analista');

    $this->actingAs($analyst)->putJson("/hr/applications/{$application->id}", [
        'status' => 'rejected',
        'current_stage_id' => $application->current_stage_id,
        'rejection_internal_notes' => 'Parecer confidencial do RH.',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors('rejection_message');

    $this->actingAs($analyst)->putJson("/hr/applications/{$application->id}", [
        'status' => 'rejected',
        'current_stage_id' => $application->current_stage_id,
        'rejection_message' => 'Agradecemos sua participação. Seguiremos com outros perfis.',
        'rejection_internal_notes' => 'Parecer confidencial do RH.',
    ])->assertOk();

    expect($application->refresh())
        ->status->toBe('rejected')
        ->rejection_message->toBe('Agradecemos sua participação. Seguiremos com outros perfis.')
        ->rejection_internal_notes->toBe('Parecer confidencial do RH.')
        ->rejected_at->not->toBeNull();
});

test('the application details expose the disc phase and its result to hr', function () {
    [$application] = managedApplication();
    $analyst = User::factory()->create();
    $analyst->assignRole('rh-analista');
    DiscAssessment::query()->create([
        'application_id' => $application->id,
        'candidate_id' => $application->candidate_id,
        'status' => 'completed',
        'questionnaire_version' => '1.0',
        'current_position' => 20,
        'd_score' => 15,
        'i_score' => 8,
        's_score' => 10,
        'c_score' => 7,
        'dominant_profile' => 'D',
        'result_snapshot' => ['label' => 'Perfil D — Dominância'],
        'started_at' => now()->subMinutes(10),
        'completed_at' => now(),
    ]);

    $this->actingAs($analyst)
        ->get('/hr/applications')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('applications.data.0.disc_assessment.status', 'completed')
            ->where('applications.data.0.disc_assessment.label', 'Perfil D — Dominância')
            ->where('applications.data.0.disc_assessment.scores.D', 15));
});

test('applications are searched filtered and paginated by the backend', function () {
    [$application] = managedApplication();
    $analyst = User::factory()->create();
    $analyst->assignRole('rh-analista');

    foreach (range(1, 15) as $index) {
        $candidate = User::factory()->create([
            'name' => "Candidato {$index}",
            'email' => "candidato{$index}@example.com",
        ]);
        Application::query()->create([
            'job_id' => $application->job_id,
            'candidate_id' => $candidate->id,
            'status' => $index === 15 ? 'rejected' : 'active',
            'source' => 'site',
            'applied_at' => now()->addSeconds($index),
        ]);
    }

    $this->actingAs($analyst)
        ->get('/hr/applications')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('applications.per_page', 15)
            ->where('applications.total', 16)
            ->has('applications.data', 15)
            ->where('applications.last_page', 2));

    $this->actingAs($analyst)
        ->get('/hr/applications?status=rejected&search=Candidato+15')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('applications.total', 1)
            ->where('applications.data.0.candidate.name', 'Candidato 15')
            ->where('filters.status', 'rejected')
            ->where('filters.search', 'Candidato 15'));
});

test('an hr analyst can retry resume screening with ai', function () {
    Storage::fake('local');
    Http::fake(['api.openai.com/*' => Http::response([
        'output' => [
            ['type' => 'reasoning', 'content' => []],
            [
                'type' => 'message',
                'content' => [[
                    'type' => 'output_text',
                    'text' => json_encode([
                        'score' => 86,
                        'recommendation' => 'advance',
                        'opinion' => 'Perfil aderente à vaga.',
                        'strengths' => ['PHP'],
                        'concerns' => [],
                        'matched_requirements' => ['Experiência com PHP'],
                        'missing_requirements' => [],
                    ]),
                ]],
            ],
        ],
    ])]);
    [$application] = managedApplication();
    Storage::disk('local')->put($application->resume_path, 'curriculo em pdf');
    AiProviderSetting::query()->create([
        'name' => 'OpenAI',
        'provider' => 'openai',
        'enabled' => true,
        'is_default' => true,
        'model' => 'gpt-5-mini',
        'api_key' => 'secret-key',
        'timeout' => 60,
        'max_output_tokens' => 4096,
        'temperature' => 0.2,
    ]);
    Curriculum::query()->create([
        'application_id' => $application->id,
        'extraction_status' => 'completed',
        'evaluation_status' => 'pending',
        'extracted_data' => ['skills' => ['PHP', 'Laravel']],
        'extracted_at' => now(),
    ]);
    $analyst = User::factory()->create();
    $analyst->assignRole('rh-analista');

    $this->actingAs($analyst)
        ->postJson("/hr/applications/{$application->id}/screen")
        ->assertOk()
        ->assertJsonPath('status', 'completed');

    $curriculum = Curriculum::query()->whereBelongsTo($application)->sole();
    expect($curriculum->score)->toBe(86)
        ->and($curriculum->evaluation_attempts)->toBe(1)
        ->and($curriculum->evaluation_status)->toBe('completed')
        ->and($curriculum->opinion)->toBe('Perfil aderente à vaga.');
});

function managedApplication(): array
{
    $company = Company::query()->create([
        'unit_type' => 'headquarters',
        'unit_number' => fake()->unique()->numerify('APP-###'),
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
    ]);
    $owner = User::factory()->create();
    $candidate = User::factory()->create([
        'name' => 'Candidato CRUD',
        'email' => fake()->unique()->safeEmail(),
    ]);
    $candidate->assignRole('candidato');
    $job = Job::query()->create([
        'company_id' => $company->id,
        'department_id' => $department->id,
        'created_by' => $owner->id,
        'title' => 'Analista de Sistemas',
        'slug' => 'analista-de-sistemas',
        'description' => 'Descrição',
        'workplace_type' => 'remote',
        'employment_type' => 'clt',
        'status' => 'published',
    ]);
    $application = Application::query()->create([
        'job_id' => $job->id,
        'candidate_id' => $candidate->id,
        'status' => 'active',
        'source' => 'site',
        'resume_path' => "candidate-resumes/{$candidate->id}/curriculo.pdf",
        'resume_original_name' => 'curriculo.pdf',
        'resume_mime_type' => 'application/pdf',
        'resume_size' => 100,
        'privacy_consent_at' => now(),
        'applied_at' => now(),
    ]);

    return [$application, $company];
}
