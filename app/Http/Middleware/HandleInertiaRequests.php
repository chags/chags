<?php

namespace App\Http\Middleware;

use App\Models\ApplicationSetting;
use App\Models\Company;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $request->user(),
                'impersonation' => [
                    'active' => $request->session()->has('impersonation.original_user_id'),
                ],
                'abilities' => [
                    'candidatePortal' => $request->user()?->can('applications.view-own') ?? false,
                    'systemSettingsView' => $request->user()?->can('system.settings.view') ?? false,
                    'usersView' => $request->user()?->can('users.view') ?? false,
                    'hrView' => ($request->user()?->can('jobs.view') ?? false)
                        || ($request->user()?->can('employees.view') ?? false),
                    'virtualOfficeView' => $request->user()?->can('intranet.access') ?? false,
                    'tracksTime' => $request->user()?->tracks_time ?? false,
                    'personnelView' => $request->user()?->can('time-records.manage') ?? false,
                    'timeApprovalsView' => ($request->user()?->can('time-records.approve') ?? false)
                        || ($request->user()?->can('medical-certificates.review') ?? false),
                    'medicalCertificateSubmit' => $request->user()?->can('medical-certificates.submit') ?? false,
                    'messagesViewOwn' => $request->user()?->can('messages.view-own') ?? false,
                    'messagesManage' => $request->user()?->can('messages.manage') ?? false,
                ],
            ],
            'companyBrand' => fn () => $this->companyBrand(),
            'seo' => fn () => $this->seoSettings(),
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }

    /** @return array{name: string, unit: string, logoUrl: ?string}|null */
    private function companyBrand(): ?array
    {
        if (! Schema::hasTable('companies')) {
            return null;
        }

        $company = Company::query()
            ->where('active', true)
            ->orderByRaw("CASE WHEN unit_type = 'headquarters' THEN 0 ELSE 1 END")
            ->orderBy('id')
            ->first();

        if (! $company) {
            return null;
        }

        return [
            'name' => $company->trade_name ?: $company->name,
            'unit' => $company->unit_name,
            'logoUrl' => $company->logo_url,
        ];
    }

    /** @return array<string, string> */
    private function seoSettings(): array
    {
        if (! Schema::hasTable('application_settings')) {
            return [];
        }

        return ApplicationSetting::query()
            ->where('key', 'like', 'seo.%')
            ->pluck('value', 'key')
            ->mapWithKeys(fn (?string $value, string $key) => [str($key)->after('seo.')->toString() => $value ?? ''])
            ->all();
    }
}
