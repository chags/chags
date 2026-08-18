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
                'appearanceUpdate' => $request->user()->can('system.settings.appearance.update'),
                'turnstileUpdate' => $request->user()->can('system.settings.turnstile.update'),
                'aiUpdate' => $request->user()->can('system.settings.ai.update'),
                'aiTest' => $request->user()->can('system.settings.ai.test'),
            ],
        ]);
    }
}
