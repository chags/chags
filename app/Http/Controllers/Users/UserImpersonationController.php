<?php

namespace App\Http\Controllers\Users;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class UserImpersonationController extends Controller
{
    public function store(Request $request, User $user): RedirectResponse
    {
        $impersonator = $request->user();

        abort_unless($impersonator?->hasRole('super-admin'), 403);
        abort_if($request->session()->has('impersonation.original_user_id'), 422, 'Uma impersonação já está ativa.');
        abort_if($impersonator->is($user), 422, 'Você não pode impersonar sua própria conta.');

        $request->session()->put('impersonation.original_user_id', $impersonator->getKey());
        $request->session()->put('impersonation.started_at', now()->toIso8601String());

        Auth::guard('web')->login($user);
        $request->session()->regenerate();

        Log::notice('Impersonação de usuário iniciada.', [
            'impersonator_id' => $impersonator->getKey(),
            'impersonated_user_id' => $user->getKey(),
            'ip' => $request->ip(),
        ]);

        return to_route('dashboard');
    }

    public function destroy(Request $request): RedirectResponse
    {
        $originalUserId = $request->session()->get('impersonation.original_user_id');
        abort_unless(is_numeric($originalUserId), 403);

        $originalUser = User::query()->findOrFail((int) $originalUserId);
        abort_unless($originalUser->hasRole('super-admin'), 403);

        $impersonatedUserId = $request->user()?->getKey();

        Auth::guard('web')->login($originalUser);
        $request->session()->forget([
            'impersonation.original_user_id',
            'impersonation.started_at',
        ]);
        $request->session()->regenerate();

        Log::notice('Impersonação de usuário encerrada.', [
            'impersonator_id' => $originalUser->getKey(),
            'impersonated_user_id' => $impersonatedUserId,
            'ip' => $request->ip(),
        ]);

        return to_route('users.index');
    }
}
