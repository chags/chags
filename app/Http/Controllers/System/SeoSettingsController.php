<?php

namespace App\Http\Controllers\System;

use App\Http\Controllers\Controller;
use App\Models\ApplicationSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SeoSettingsController extends Controller
{
    public function update(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('system.settings.seo.update'), 403);

        $data = $request->validate([
            'title' => ['nullable', 'string', 'max:70'],
            'description' => ['nullable', 'string', 'max:160'],
            'canonical_url' => ['nullable', 'url:http,https', 'max:2048'],
            'robots' => ['required', Rule::in(['index, follow', 'noindex, nofollow'])],
            'og_title' => ['nullable', 'string', 'max:95'],
            'og_description' => ['nullable', 'string', 'max:200'],
            'og_image_url' => ['nullable', 'url:http,https', 'max:2048'],
            'og_url' => ['nullable', 'url:http,https', 'max:2048'],
            'og_type' => ['required', Rule::in(['website'])],
            'og_locale' => ['required', 'regex:/^[a-z]{2}_[A-Z]{2}$/'],
            'ga4_measurement_id' => ['nullable', 'regex:/^G-[A-Z0-9]+$/', 'max:30'],
            'meta_pixel_id' => ['nullable', 'regex:/^[0-9]+$/', 'max:30'],
        ]);

        foreach ($data as $key => $value) {
            ApplicationSetting::query()->updateOrCreate(
                ['key' => "seo.{$key}"],
                ['value' => is_string($value) ? trim($value) : $value],
            );
        }

        return response()->json([
            'message' => 'Configurações de SEO e rastreamento atualizadas com sucesso.',
            'settings' => $data,
        ]);
    }
}
