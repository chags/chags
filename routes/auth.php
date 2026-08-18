<?php

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Laravel\WorkOS\Http\Requests\AuthKitAuthenticationRequest;
use Laravel\WorkOS\Http\Requests\AuthKitLoginRequest;

Route::middleware(['guest'])->group(function () {
    Route::get('login', function (Request $request) {
        $workosConfigured = app()->environment('production')
            && filled(config('services.workos.client_id'))
            && filled(config('services.workos.secret'));

        if (! $workosConfigured && ! app()->environment('production')) {
            return inertia('auth/login-provisorio');
        }

        abort_unless($workosConfigured, 503, 'WorkOS is not configured.');

        return (new AuthKitLoginRequest($request))->redirect();
    })->name('login');

    Route::post('login', function (Request $request) {
        $workosConfigured = app()->environment('production')
            && filled(config('services.workos.client_id'))
            && filled(config('services.workos.secret'));

        if (app()->environment('production') || $workosConfigured) {
            abort(404);
        }

        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', $credentials['email'])->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            return back()->withErrors([
                'email' => 'Credenciais inválidas.',
            ])->withInput();
        }

        Auth::login($user, true);

        return redirect()->intended(route('dashboard'));
    });

    Route::get('authenticate', function (Request $request) {
        if (! app()->environment('production') || ! filled(config('services.workos.client_id')) || ! filled(config('services.workos.secret'))) {
            abort(404);
        }

        return tap(
            redirect()->intended(route('dashboard')),
            fn () => (new AuthKitAuthenticationRequest($request))->authenticate(),
        );
    });
});

Route::post('logout', function (Request $request) {
    if (Auth::check()) {
        Auth::logout();
    }

    return redirect('/');
})->middleware(['auth'])->name('logout');
