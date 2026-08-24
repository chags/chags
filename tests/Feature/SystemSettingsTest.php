<?php

use App\Models\AiProviderSetting;
use App\Models\ApplicationSetting;
use App\Models\Company;
use App\Models\TurnstileSetting;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->withoutVite()->withoutMiddleware(PreventRequestForgery::class);

    $permissions = collect([
        'system.settings.view',
        'system.settings.company.update',
        'system.settings.mail.update',
        'system.settings.mail.test',
        'system.settings.seo.update',
        'system.settings.appearance.update',
        'system.settings.turnstile.update',
        'system.settings.ai.update',
        'system.settings.ai.test',
    ])->map(fn (string $name) => Permission::findOrCreate($name));

    Role::findOrCreate('administrador')->syncPermissions($permissions);
});

test('an administrator can access system settings', function () {
    $user = User::factory()->create();
    $user->assignRole('administrador');

    $this->actingAs($user)->get('/settings/system')->assertOk();
});

test('an administrator can persist the global theme', function () {
    $user = User::factory()->create();
    $user->assignRole('administrador');

    $this->actingAs($user)
        ->putJson('/settings/system/appearance', ['theme' => 'forest'])
        ->assertOk()
        ->assertJsonPath('theme', 'forest');

    expect(ApplicationSetting::query()->where('key', 'theme')->value('value'))
        ->toBe('forest');

    $this->get('/settings/system')
        ->assertOk()
        ->assertSee('data-theme="forest"', false);
});

test('an administrator can configure global seo and tracking tags', function () {
    $user = User::factory()->create();
    $user->assignRole('administrador');

    $settings = [
        'title' => 'Carreiras Chags',
        'description' => 'Encontre oportunidades e acompanhe seu processo seletivo na Chags.',
        'canonical_url' => 'https://chags.com.br',
        'robots' => 'index, follow',
        'og_title' => 'Trabalhe na Chags',
        'og_description' => 'Conheça nossas oportunidades profissionais.',
        'og_image_url' => 'https://chags.com.br/storage/seo/social.webp',
        'og_url' => 'https://chags.com.br',
        'og_type' => 'website',
        'og_locale' => 'pt_BR',
        'ga4_measurement_id' => 'G-ABC1234567',
        'meta_pixel_id' => '123456789012345',
    ];

    $this->actingAs($user)
        ->putJson('/settings/system/seo', $settings)
        ->assertOk()
        ->assertJsonPath('message', 'Configurações de SEO e rastreamento atualizadas com sucesso.');

    $this->get('/')
        ->assertOk()
        ->assertSee('<meta name="description" content="'.$settings['description'].'">', false)
        ->assertSee('<meta property="og:title" content="'.$settings['og_title'].'">', false)
        ->assertSee('googletagmanager.com/gtag/js?id=G-ABC1234567', false)
        ->assertSee('facebook.com/tr?id=123456789012345', false);
});

test('a user without permission cannot access system settings', function () {
    $this->actingAs(User::factory()->create())
        ->get('/settings/system')
        ->assertForbidden();
});

test('an administrator can save encrypted turnstile settings', function () {
    $user = User::factory()->create();
    $user->assignRole('administrador');

    $this->actingAs($user)->putJson('/settings/system/turnstile', [
        'enabled' => true,
        'site_key' => 'public-site-key',
        'secret_key' => 'private-secret-key',
    ])->assertOk()->assertJsonPath(
        'message',
        'Configuração do Turnstile salva com sucesso.',
    );

    $setting = TurnstileSetting::query()->sole();

    expect($setting->enabled)->toBeTrue()
        ->and($setting->site_key)->toBe('public-site-key')
        ->and($setting->secret_key)->toBe('private-secret-key')
        ->and($setting->getRawOriginal('secret_key'))->not->toContain('private-secret-key');

    $this->actingAs($user)->putJson('/settings/system/turnstile', [
        'enabled' => true,
        'site_key' => 'new-public-site-key',
        'secret_key' => '',
    ])->assertOk();

    expect($setting->refresh()->secret_key)->toBe('private-secret-key');
});

test('an administrator can manage encrypted ai provider settings', function () {
    $user = User::factory()->create();
    $user->assignRole('administrador');

    $this->actingAs($user)->postJson('/settings/system/ai/providers', [
        'name' => 'OpenAI principal',
        'provider' => 'openai',
        'enabled' => true,
        'is_default' => true,
        'base_url' => null,
        'model' => 'gpt-5-mini',
        'api_key' => 'sk-secret-value',
        'organization' => null,
        'timeout' => 60,
        'max_output_tokens' => 4096,
        'temperature' => 0.2,
    ])->assertCreated();

    $setting = AiProviderSetting::query()->sole();

    expect($setting->api_key)->toBe('sk-secret-value')
        ->and($setting->getRawOriginal('api_key'))->not->toContain('sk-secret-value')
        ->and($setting->is_default)->toBeTrue();

    $response = $this->actingAs($user)->get('/settings/system');
    $response->assertOk();
    expect($response->getContent())->not->toContain('sk-secret-value');
});

test('only one ai provider remains the default', function () {
    $user = User::factory()->create();
    $user->assignRole('administrador');

    foreach ([['OpenAI', 'openai', 'gpt-5-mini'], ['Gemini', 'gemini', 'gemini-2.5-flash']] as [$name, $provider, $model]) {
        $this->actingAs($user)->postJson('/settings/system/ai/providers', [
            'name' => $name,
            'provider' => $provider,
            'enabled' => true,
            'is_default' => true,
            'model' => $model,
            'api_key' => 'secret-'.$provider,
            'timeout' => 60,
            'max_output_tokens' => 4096,
            'temperature' => 0.2,
        ])->assertCreated();
    }

    expect(AiProviderSetting::query()->where('is_default', true)->count())->toBe(1)
        ->and(AiProviderSetting::query()->where('is_default', true)->sole()->provider)->toBe('gemini');
});

test('an administrator can test an ai provider connection', function () {
    Http::fake(['api.openai.com/*' => Http::response(['output' => []])]);
    $user = User::factory()->create();
    $user->assignRole('administrador');
    $setting = AiProviderSetting::query()->create([
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

    $this->actingAs($user)
        ->postJson("/settings/system/ai/providers/{$setting->id}/test")
        ->assertOk()
        ->assertJsonPath('message', 'Conexão com a IA realizada com sucesso.');

    expect($setting->refresh()->last_tested_at)->not->toBeNull()
        ->and($setting->last_test_succeeded)->toBeTrue();
    Http::assertSent(fn ($request) => $request->url() === 'https://api.openai.com/v1/responses'
        && $request->hasHeader('Authorization', 'Bearer secret-key'));
});

test('a failed ai connection persists its status and test time', function () {
    Http::fake(['api.openai.com/*' => Http::response(['error' => 'invalid key'], 401)]);
    $user = User::factory()->create();
    $user->assignRole('administrador');
    $setting = AiProviderSetting::query()->create([
        'name' => 'OpenAI',
        'provider' => 'openai',
        'enabled' => true,
        'is_default' => true,
        'model' => 'gpt-5-mini',
        'api_key' => 'invalid-key',
        'timeout' => 60,
        'max_output_tokens' => 4096,
        'temperature' => 0.2,
    ]);

    $this->actingAs($user)
        ->postJson("/settings/system/ai/providers/{$setting->id}/test")
        ->assertUnprocessable();

    expect($setting->refresh()->last_tested_at)->not->toBeNull()
        ->and($setting->last_test_succeeded)->toBeFalse();
});

test('an administrator can create headquarters and a branch', function () {
    $user = User::factory()->create();
    $user->assignRole('administrador');

    $headquarters = [
        'unit_type' => 'headquarters',
        'unit_number' => '0001',
        'unit_name' => 'Matriz São Paulo',
        'headquarters_id' => null,
        'name' => 'Empresa Exemplo Ltda',
        'trade_name' => 'Empresa Exemplo',
        'cnpj' => '11.222.333/0001-81',
        'address' => 'Avenida Principal',
        'address_number' => '100',
        'address_complement' => null,
        'district' => 'Centro',
        'city' => 'São Paulo',
        'state' => 'SP',
        'postal_code' => '01001-000',
        'active' => true,
    ];

    $this->actingAs($user)->postJson('/settings/system/companies', $headquarters)->assertCreated();
    $matrix = Company::query()->sole();

    $this->actingAs($user)->postJson('/settings/system/companies', [
        ...$headquarters,
        'unit_type' => 'branch',
        'unit_number' => '0002',
        'unit_name' => 'Filial Centro',
        'headquarters_id' => $matrix->id,
        'cnpj' => '11.222.333/0002-62',
    ])->assertCreated();

    expect(Company::query()->count())->toBe(2)
        ->and(Company::query()->where('unit_type', 'branch')->sole()->headquarters_id)->toBe($matrix->id);
});

test('company logo upload is converted to webp', function () {
    Storage::fake('public');
    $user = User::factory()->create();
    $user->assignRole('administrador');
    $company = Company::query()->create([
        'unit_type' => 'headquarters',
        'unit_number' => '0001',
        'unit_name' => 'Matriz',
        'name' => 'Empresa Exemplo Ltda',
        'cnpj' => '11222333000181',
        'address' => 'Avenida Principal',
        'address_number' => '100',
        'district' => 'Centro',
        'city' => 'São Paulo',
        'state' => 'SP',
        'postal_code' => '01001000',
        'active' => true,
    ]);

    $this->actingAs($user)->postJson(
        "/settings/system/companies/{$company->id}/logo",
        ['logo' => UploadedFile::fake()->image('logo.png', 36, 36)],
    )->assertCreated()->assertJsonPath('message', 'Logomarca atualizada com sucesso.');

    $path = $company->refresh()->logo;

    expect($path)->toEndWith('.webp');
    Storage::disk('public')->assertExists($path);

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertOk()
        ->assertSee('rel="icon" href="/storage/'.$path.'"', false)
        ->assertSee('rel="apple-touch-icon" href="/storage/'.$path.'"', false);
});
