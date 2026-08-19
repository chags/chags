<?php

namespace App\Http\Controllers\System;

use App\Http\Controllers\Controller;
use App\Models\AiProviderSetting;
use App\Models\ApplicationSetting;
use App\Models\Company;
use App\Models\MailSetting;
use App\Models\TurnstileSetting;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SystemSettingsController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $companies = Company::query()
            ->with('headquarters:id,unit_number,unit_name')
            ->orderByRaw("CASE WHEN unit_type = 'headquarters' THEN 0 ELSE 1 END")
            ->orderBy('unit_number')
            ->get()
            ->map(fn (Company $company) => [
                'id' => $company->id,
                'headquarters_id' => $company->headquarters_id,
                'headquarters' => $company->headquarters,
                'unit_type' => $company->unit_type->value,
                'unit_number' => $company->unit_number,
                'unit_name' => $company->unit_name,
                'name' => $company->name,
                'trade_name' => $company->trade_name,
                'cnpj' => $company->cnpj,
                'logo_url' => $company->logo_url,
                'address' => $company->address,
                'address_number' => $company->address_number,
                'address_complement' => $company->address_complement,
                'district' => $company->district,
                'city' => $company->city,
                'state' => $company->state,
                'postal_code' => $company->postal_code,
                'active' => $company->active,
            ]);

        $mail = MailSetting::query()->first();
        $seo = ApplicationSetting::query()
            ->where('key', 'like', 'seo.%')
            ->pluck('value', 'key');
        $turnstile = TurnstileSetting::query()->first();
        $aiProviders = AiProviderSetting::query()
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get()
            ->map(fn (AiProviderSetting $setting) => [
                'id' => $setting->id,
                'name' => $setting->name,
                'provider' => $setting->provider,
                'enabled' => $setting->enabled,
                'is_default' => $setting->is_default,
                'base_url' => $setting->base_url,
                'model' => $setting->model,
                'has_api_key' => filled($setting->api_key),
                'organization' => $setting->organization,
                'timeout' => $setting->timeout,
                'max_output_tokens' => $setting->max_output_tokens,
                'temperature' => (float) $setting->temperature,
                'last_tested_at' => $setting->last_tested_at?->toIso8601String(),
                'last_test_succeeded' => $setting->last_test_succeeded,
            ]);

        return Inertia::render('system-settings/index', [
            'companies' => $companies,
            'mailSettings' => $mail ? [
                'from_name' => $mail->from_name,
                'from_address' => $mail->from_address,
                'host' => $mail->host,
                'port' => $mail->port,
                'username' => $mail->username,
                'has_password' => filled($mail->password),
                'encryption' => $mail->encryption,
                'timeout' => $mail->timeout,
                'last_tested_at' => $mail->last_tested_at?->toIso8601String(),
            ] : null,
            'seoSettings' => [
                'title' => $seo->get('seo.title', ''),
                'description' => $seo->get('seo.description', ''),
                'canonical_url' => $seo->get('seo.canonical_url', config('app.url')),
                'robots' => $seo->get('seo.robots', 'index, follow'),
                'og_title' => $seo->get('seo.og_title', ''),
                'og_description' => $seo->get('seo.og_description', ''),
                'og_image_url' => $seo->get('seo.og_image_url', ''),
                'og_url' => $seo->get('seo.og_url', config('app.url')),
                'og_type' => $seo->get('seo.og_type', 'website'),
                'og_locale' => $seo->get('seo.og_locale', 'pt_BR'),
                'ga4_measurement_id' => $seo->get('seo.ga4_measurement_id', ''),
                'meta_pixel_id' => $seo->get('seo.meta_pixel_id', ''),
            ],
            'theme' => ApplicationSetting::query()->where('key', 'theme')->value('value') ?? 'forest',
            'turnstileSettings' => [
                'enabled' => $turnstile?->enabled ?? false,
                'site_key' => $turnstile?->site_key ?? '',
                'has_secret_key' => filled($turnstile?->secret_key),
            ],
            'aiProviders' => $aiProviders,
            'abilities' => [
                'companyUpdate' => $request->user()->can('system.settings.company.update'),
                'mailUpdate' => $request->user()->can('system.settings.mail.update'),
                'mailTest' => $request->user()->can('system.settings.mail.test'),
                'seoUpdate' => $request->user()->can('system.settings.seo.update'),
                'appearanceUpdate' => $request->user()->can('system.settings.appearance.update'),
                'turnstileUpdate' => $request->user()->can('system.settings.turnstile.update'),
                'aiUpdate' => $request->user()->can('system.settings.ai.update'),
                'aiTest' => $request->user()->can('system.settings.ai.test'),
            ],
        ]);
    }
}
