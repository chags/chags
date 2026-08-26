<?php

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Laravel\WorkOS\Http\Requests\AuthKitAuthenticationRequest;
use Laravel\WorkOS\Http\Requests\AuthKitLoginRequest;
use Laravel\WorkOS\Http\Requests\AuthKitLogoutRequest;
use Laravel\WorkOS\User as WorkOSUser;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use WorkOS\Exception\WorkOSException;

Route::middleware(['guest'])->group(function () {
    Route::get('login', function (AuthKitLoginRequest $request) {
        $workosConfigured = app()->environment('production')
            && filled(config('services.workos.client_id'))
            && filled(config('services.workos.secret'));

        if (! $workosConfigured && ! app()->environment('production')) {
            return inertia('auth/login-provisorio');
        }

        abort_unless($workosConfigured, 503, 'WorkOS is not configured.');

        return $request->redirect();
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

    Route::get('authenticate', function (AuthKitAuthenticationRequest $request) {
        if (! app()->environment('production') || ! filled(config('services.workos.client_id')) || ! filled(config('services.workos.secret'))) {
            abort(404);
        }

        if (! $request->filled('code')) {
            return response()->view('auth.workos-error', status: 422);
        }

        try {
            $request->authenticate(
                findUsing: fn (WorkOSUser $workosUser) => User::query()
                    ->where('workos_id', $workosUser->id)
                    ->orWhere('email', $workosUser->email)
                    ->first(),
                updateUsing: function (User $user, WorkOSUser $workosUser): User {
                    $user->forceFill([
                        'workos_id' => $workosUser->id,
                        'avatar' => $workosUser->avatar ?? $user->avatar ?? '',
                    ])->save();

                    return $user;
                },
            );
        } catch (WorkOSException $exception) {
            report($exception);

            return response()->view('auth.workos-error', status: 422);
        } catch (HttpExceptionInterface $exception) {
            if ($exception->getStatusCode() !== 403) {
                throw $exception;
            }

            report($exception);

            return response()->view('auth.workos-error', status: 403);
        }

        return redirect()->intended(route('dashboard'));
    });
});

Route::post('logout', function (AuthKitLogoutRequest $request) {
    $workosConfigured = app()->environment('production')
        && filled(config('services.workos.client_id'))
        && filled(config('services.workos.secret'));

    if ($workosConfigured) {
        return $request->logout('/');
    }

    Auth::logout();
    $request->session()->invalidate();
    $request->session()->regenerateToken();

    return redirect('/');
})->middleware(['auth'])->name('logout');
