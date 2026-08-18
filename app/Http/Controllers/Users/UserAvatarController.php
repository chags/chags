<?php

namespace App\Http\Controllers\Users;

use App\Data\Images\ImageProcessingOptions;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Images\ImageProcessor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class UserAvatarController extends Controller
{
    public function __invoke(Request $request, User $user, ImageProcessor $processor): JsonResponse
    {
        abort_unless($request->user()->can('users.update'), 403);
        abort_if(
            $user->hasRole('super-admin') && ! $request->user()->hasRole('super-admin'),
            403,
            'Administradores não podem alterar um superadministrador.',
        );

        $request->validate([
            'avatar' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:2048', 'dimensions:min_width=36,min_height=36'],
        ]);

        $image = $processor->process($request->file('avatar'), ImageProcessingOptions::logo());
        $path = "users/{$user->id}/avatar/{$image->filename}";
        $disk = Storage::disk('public');

        abort_unless($disk->put($path, $image->contents), 500, 'Não foi possível armazenar a foto.');

        $oldAvatar = $user->avatar;
        $user->update(['avatar' => '/storage/'.$path]);

        if (is_string($oldAvatar) && str_starts_with($oldAvatar, '/storage/')) {
            $disk->delete(str($oldAvatar)->after('/storage/')->toString());
        }

        return response()->json([
            'message' => 'Foto atualizada com sucesso.',
            'avatar' => '/storage/'.$path,
        ], $oldAvatar ? 200 : 201);
    }
}
