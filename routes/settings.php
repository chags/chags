<?php

use App\Http\Controllers\Settings\CepLookupController;
use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\System\AiProviderSettingsController;
use App\Http\Controllers\System\AppearanceSettingsController;
use App\Http\Controllers\System\CnpjLookupController;
use App\Http\Controllers\System\CompanyController;
use App\Http\Controllers\System\CompanyLogoController;
use App\Http\Controllers\System\MailSettingsController;
use App\Http\Controllers\System\SeoSettingsController;
use App\Http\Controllers\System\SystemSettingsController;
use App\Http\Controllers\System\TurnstileSettingsController;
use App\Http\Controllers\Users\UserAvatarController;
use App\Http\Controllers\Users\UserController;
use App\Http\Controllers\Users\UserImpersonationController;
use Illuminate\Support\Facades\Route;
use Laravel\WorkOS\Http\Middleware\ValidateSessionWithWorkOS;

$settingsMiddleware = ['auth'];
$workosConfigured = app()->environment('production')
    && filled(config('services.workos.client_id'))
    && filled(config('services.workos.secret'));

if ($workosConfigured) {
    $settingsMiddleware[] = ValidateSessionWithWorkOS::class;
}

Route::middleware($settingsMiddleware)->group(function () {
    Route::redirect('settings', '/settings/profile');

    Route::get('settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::get('settings/profile/cep/{cep}', CepLookupController::class)
        ->where('cep', '[0-9]{8}')
        ->middleware('throttle:20,1')
        ->name('profile.cep.lookup');
    Route::patch('settings/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('settings/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::inertia('settings/appearance', 'settings/appearance')->name('appearance.edit');

    Route::get('users', [UserController::class, 'index'])->name('users.index');
    Route::post('users', [UserController::class, 'store'])->name('users.store');
    Route::put('users/{user}', [UserController::class, 'update'])->name('users.update');
    Route::delete('users/{user}', [UserController::class, 'destroy'])->name('users.destroy');
    Route::post('users/{user}/avatar', UserAvatarController::class)->name('users.avatar.store');
    Route::post('users/{user}/impersonate', [UserImpersonationController::class, 'store'])
        ->name('impersonation.start');
    Route::post('impersonation/stop', [UserImpersonationController::class, 'destroy'])
        ->name('impersonation.stop');

    Route::prefix('settings/system')->name('system-settings.')->group(function () {
        Route::get('/', SystemSettingsController::class)
            ->middleware('can:system.settings.view')
            ->name('index');
        Route::post('companies', [CompanyController::class, 'store'])->name('companies.store');
        Route::get('companies/cnpj/{cnpj}', CnpjLookupController::class)
            ->where('cnpj', '[0-9]{14}')
            ->middleware(['can:system.settings.company.update', 'throttle:20,1'])
            ->name('companies.cnpj.lookup');
        Route::put('companies/{company}', [CompanyController::class, 'update'])->name('companies.update');
        Route::post('companies/{company}/logo', [CompanyLogoController::class, 'store'])->name('companies.logo.store');
        Route::put('mail', [MailSettingsController::class, 'update'])->name('mail.update');
        Route::post('mail/test', [MailSettingsController::class, 'test'])->middleware('throttle:5,1')->name('mail.test');
        Route::put('seo', [SeoSettingsController::class, 'update'])->name('seo.update');
        Route::put('appearance', [AppearanceSettingsController::class, 'update'])->name('appearance.update');
        Route::put('turnstile', [TurnstileSettingsController::class, 'update'])->name('turnstile.update');
        Route::post('ai/providers', [AiProviderSettingsController::class, 'store'])->name('ai.providers.store');
        Route::put('ai/providers/{aiProviderSetting}', [AiProviderSettingsController::class, 'update'])->name('ai.providers.update');
        Route::delete('ai/providers/{aiProviderSetting}', [AiProviderSettingsController::class, 'destroy'])->name('ai.providers.destroy');
        Route::post('ai/providers/{aiProviderSetting}/test', [AiProviderSettingsController::class, 'test'])
            ->middleware('throttle:5,1')
            ->name('ai.providers.test');
    });
});
