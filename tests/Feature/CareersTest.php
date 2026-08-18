<?php

use App\Models\Application;
use App\Models\Company;
use App\Models\Curriculum;
use App\Models\Department;
use App\Models\Job;
use App\Models\TurnstileSetting;
use App\Models\User;
use App\Services\TurnstileVerifier;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->withoutVite();
    $this->seed(RolesAndPermissionsSeeder::class);
});

test('the careers page only displays published open jobs', function () {
    [$published, $draft] = careersJobs();
    $this->get('/trabalhe-conosco')->assertOk()->assertSee($published->title)->assertDontSee($draft->title);
    $this->get("/trabalhe-conosco/{$published->slug}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('job.benefits', 'Plano de saúde'));
});

test('a closed job remains visible but does not accept applications', function () {
    [, $job] = careersJobs();
    $job->update(['status' => 'closed']);

    $this->get('/trabalhe-conosco')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('jobs', 2)
            ->where('jobs.1.title', $job->title)
            ->where('jobs.1.accepting_applications', false));

    $this->get("/trabalhe-conosco/{$job->slug}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('job.accepting_applications', false));

    $this->post("/trabalhe-conosco/{$job->slug}/candidatar", candidateApplicationData('closed@example.com'))
        ->assertNotFound();
});

test('a visitor can create a candidate account and apply to a job', function () {
    Storage::fake('local');
    [$job] = careersJobs();
    $this->post("/trabalhe-conosco/{$job->slug}/candidatar", ['name' => 'Candidato Teste', 'email' => 'candidate@example.com', 'phone' => '11999999999', 'city' => 'São Paulo', 'state' => 'SP', 'password' => 'candidate-password', 'password_confirmation' => 'candidate-password', 'cover_letter' => 'Tenho interesse.', 'resume' => UploadedFile::fake()->create('curriculo.pdf', 100, 'application/pdf'), 'privacy_consent' => true])->assertRedirect();
    $user = User::query()->where('email', 'candidate@example.com')->sole();
    expect($user->hasRole('candidato'))->toBeTrue()
        ->and(Application::query()->whereBelongsTo($user, 'candidate')->whereBelongsTo($job)->exists())->toBeTrue();
    $application = Application::query()->sole();
    expect($application->privacy_consent_at)->not->toBeNull()
        ->and($application->privacy_consent_version)->toBe('2026-08-11');
    Storage::disk('local')->assertExists($application->resume_path);
    expect(Curriculum::query()->whereBelongsTo($application)->sole()->extraction_status)->toBe('failed');
});

test('privacy consent is mandatory and the privacy page is public', function () {
    [$job] = careersJobs();
    $this->get('/privacidade-e-lgpd')->assertOk();
    $this->post("/trabalhe-conosco/{$job->slug}/candidatar", [])->assertSessionHasErrors('privacy_consent');
});

test('career application validation messages are in portuguese', function () {
    [$job] = careersJobs();
    $data = candidateApplicationData('senha-curta@example.com');
    $data['password'] = '1234567';
    $data['password_confirmation'] = '1234567';

    $this->post("/trabalhe-conosco/{$job->slug}/candidatar", $data)
        ->assertSessionHasErrors([
            'password' => 'A senha deve ter pelo menos 8 caracteres.',
        ]);
});

test('turnstile is exposed on the job page and validated before applying', function () {
    Storage::fake('local');
    [$job] = careersJobs();
    TurnstileSetting::query()->create([
        'enabled' => true,
        'site_key' => 'public-key',
        'secret_key' => 'secret-key',
    ]);

    $this->get("/trabalhe-conosco/{$job->slug}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('turnstile.enabled', true)
            ->where('turnstile.siteKey', 'public-key'));

    Http::fakeSequence('challenges.cloudflare.com/*')
        ->push([
            'success' => false,
            'action' => 'career_application',
        ])
        ->push([
            'success' => true,
            'action' => 'career_application',
        ]);

    $this->post("/trabalhe-conosco/{$job->slug}/candidatar", [
        ...candidateApplicationData('blocked@example.com'),
        'turnstile_token' => 'invalid-token',
    ])->assertSessionHasErrors('turnstile_token');

    expect(User::query()->where('email', 'blocked@example.com')->exists())->toBeFalse();

    $this->post("/trabalhe-conosco/{$job->slug}/candidatar", [
        ...candidateApplicationData('approved@example.com'),
        'turnstile_token' => 'valid-token',
    ])->assertSessionHasNoErrors()->assertRedirect();

    expect(User::query()->where('email', 'approved@example.com')->exists())->toBeTrue();

    Http::assertSent(fn ($request) => $request->url() === 'https://challenges.cloudflare.com/turnstile/v0/siteverify'
        && $request['secret'] === 'secret-key'
        && $request['response'] === 'valid-token');
});

test('the official local turnstile key accepts its response without an action', function () {
    $originalEnvironment = app()->environment();
    app()->detectEnvironment(fn () => 'local');
    Http::fake([
        'challenges.cloudflare.com/*' => Http::response([
            'success' => true,
            'hostname' => 'example.com',
            'metadata' => ['result_with_testing_key' => true],
        ]),
    ]);

    try {
        expect(app(TurnstileVerifier::class)->verify(
            'XXXX.DUMMY.TOKEN.XXXX',
            config('services.turnstile.local_secret_key'),
            '127.0.0.1',
        ))->toBeTrue();
    } finally {
        app()->detectEnvironment(fn () => $originalEnvironment);
    }
});

function candidateApplicationData(string $email): array
{
    return [
        'name' => 'Candidato Teste',
        'email' => $email,
        'phone' => '11999999999',
        'city' => 'São Paulo',
        'state' => 'SP',
        'password' => 'candidate-password',
        'password_confirmation' => 'candidate-password',
        'resume' => UploadedFile::fake()->create('curriculo.pdf', 100, 'application/pdf'),
        'privacy_consent' => true,
    ];
}

function careersJobs(): array
{
    $company = Company::query()->firstOrCreate(['unit_number' => 'CAREER'], ['unit_type' => 'headquarters', 'unit_name' => 'Matriz', 'name' => 'Chags', 'cnpj' => '33444555000101', 'address' => 'Rua C', 'address_number' => '3', 'district' => 'Centro', 'city' => 'São Paulo', 'state' => 'SP', 'postal_code' => '01001000', 'active' => true]);
    $department = Department::query()->firstOrCreate(['company_id' => $company->id, 'slug' => 'tecnologia'], ['name' => 'Tecnologia']);
    $owner = User::factory()->create();
    $base = ['company_id' => $company->id, 'department_id' => $department->id, 'created_by' => $owner->id, 'description' => 'Descrição pública', 'benefits' => 'Plano de saúde', 'workplace_type' => 'remote', 'employment_type' => 'clt'];

    return [Job::query()->create([...$base, 'title' => 'Vaga Publicada', 'slug' => 'vaga-publicada', 'status' => 'published', 'published_at' => now()]), Job::query()->create([...$base, 'title' => 'Vaga Rascunho', 'slug' => 'vaga-rascunho', 'status' => 'draft'])];
}
