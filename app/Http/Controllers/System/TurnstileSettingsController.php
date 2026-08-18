<?php

namespace App\Http\Controllers\System;

use App\Http\Controllers\Controller;
use App\Http\Requests\System\TurnstileSettingRequest;
use App\Models\TurnstileSetting;
use Illuminate\Http\JsonResponse;

class TurnstileSettingsController extends Controller
{
    public function update(TurnstileSettingRequest $request): JsonResponse
    {
        $setting = TurnstileSetting::query()->firstOrNew();
        $data = $request->validated();

        if (blank($data['secret_key'] ?? null)) {
            unset($data['secret_key']);
        }

        $setting->fill($data)->save();

        return response()->json([
            'message' => 'Configuração do Turnstile salva com sucesso.',
        ]);
    }
}
