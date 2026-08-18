<?php

namespace App\Http\Controllers\System;

use App\Http\Controllers\Controller;
use App\Models\ApplicationSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AppearanceSettingsController extends Controller
{
    public function update(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('system.settings.appearance.update'), 403);

        $data = $request->validate([
            'theme' => ['required', Rule::in(['light', 'dark', 'forest'])],
        ]);

        ApplicationSetting::query()->updateOrCreate(
            ['key' => 'theme'],
            ['value' => $data['theme']],
        );

        return response()->json([
            'message' => 'Tema global atualizado com sucesso.',
            'theme' => $data['theme'],
        ]);
    }
}
