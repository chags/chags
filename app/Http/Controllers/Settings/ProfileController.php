<?php

namespace App\Http\Controllers\Settings;

use App\Data\Images\ImageProcessingOptions;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ProfileUpdateRequest;
use App\Models\User;
use App\Services\Images\ImageProcessor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\WorkOS\Http\Requests\AuthKitAccountDeletionRequest;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirectResponse;

class ProfileController extends Controller
{
    /**
     * Show the user's profile settings page.
     */
    public function edit(Request $request): Response
    {
        return Inertia::render('settings/profile', [
            'status' => $request->session()->get('status'),
        ]);
    }

    /**
     * Update the user's profile settings.
     */
    public function update(ProfileUpdateRequest $request, ImageProcessor $processor): RedirectResponse
    {
        $data = $request->safe()->except('avatar');
        $user = $request->user();

        if ($request->hasFile('avatar')) {
            $image = $processor->process($request->file('avatar'), ImageProcessingOptions::logo());
            $path = "users/{$user->id}/avatar/{$image->filename}";
            $disk = Storage::disk('public');

            abort_unless($disk->put($path, $image->contents), 500, 'Não foi possível armazenar a foto.');

            $oldAvatar = $user->avatar;
            $data['avatar'] = '/storage/'.$path;

            if (is_string($oldAvatar) && str_starts_with($oldAvatar, '/storage/')) {
                $disk->delete(str($oldAvatar)->after('/storage/')->toString());
            }
        }

        $user->update($data);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Profile updated.')]);

        return to_route('profile.edit');
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): SymfonyRedirectResponse
    {
        if (app()->environment('production')) {
            return app(AuthKitAccountDeletionRequest::class)->delete(
                using: fn (User $user) => $user->delete(),
            );
        }

        $request->validate(['password' => ['required', 'string']]);

        if (! Hash::check((string) $request->string('password'), $request->user()->password)) {
            return back()->withErrors(['password' => __('auth.password')]);
        }

        $user = $request->user();

        Auth::logout();
        $user->delete();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
