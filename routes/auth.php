<?php

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Laravel\WorkOS\Http\Requests\AuthKitAuthenticationRequest;
use Laravel\WorkOS\Http\Requests\AuthKitLoginRequest;
use Laravel\WorkOS\Http\Requests\AuthKitLogoutRequest;

Route::middleware(['guest'])->group(function () {
    Route::get('login', function (Request $request) {
        $isLocalHost = in_array($request->getHost(), ['localhost', '127.0.0.1', '::1'], true);
        $workosConfigured = filled(config('services.workos.client_id'))
            && filled(config('services.workos.client_secret'))
            && filled(config('services.workos.project_id'));

        if ($isLocalHost && ! $workosConfigured) {
            return inertia('auth/login-provisorio');
        }

        return (new AuthKitLoginRequest($request))->redirect();
    })->name('login');

    Route::post('login', function (Request $request) {
        $isLocalHost = in_array($request->getHost(), ['localhost', '127.0.0.1', '::1'], true);
        $workosConfigured = filled(config('services.workos.client_id'))
            && filled(config('services.workos.client_secret'))
            && filled(config('services.workos.project_id'));

        if (! $isLocalHost || $workosConfigured) {
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
        if (! filled(config('services.workos.client_id')) || ! filled(config('services.workos.client_secret')) || ! filled(config('services.workos.project_id'))) {
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
