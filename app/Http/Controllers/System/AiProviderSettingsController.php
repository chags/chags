<?php

namespace App\Http\Controllers\System;

use App\Http\Controllers\Controller;
use App\Http\Requests\System\AiProviderSettingRequest;
use App\Models\AiProviderSetting;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Throwable;

class AiProviderSettingsController extends Controller
{
    public function store(AiProviderSettingRequest $request): JsonResponse
    {
        $data = $request->validated();

        if ($data['provider'] !== 'ollama' && blank($data['api_key'] ?? null)) {
            return response()->json(['message' => 'Informe a chave de API deste provedor.'], 422);
        }

        $setting = DB::transaction(function () use ($data) {
            if ($data['is_default']) {
                AiProviderSetting::query()->update(['is_default' => false]);
            }

            return AiProviderSetting::query()->create($data);
        });

        return response()->json([
            'message' => 'Provedor de IA cadastrado com sucesso.',
            'id' => $setting->id,
        ], 201);
    }

    public function update(AiProviderSettingRequest $request, AiProviderSetting $aiProviderSetting): JsonResponse
    {
        $data = $request->validated();

        if (blank($data['api_key'] ?? null)) {
            unset($data['api_key']);
        }

        if ($data['provider'] !== 'ollama' && blank($aiProviderSetting->api_key) && ! array_key_exists('api_key', $data)) {
            return response()->json(['message' => 'Informe a chave de API deste provedor.'], 422);
        }

        DB::transaction(function () use ($data, $aiProviderSetting) {
            if ($data['is_default']) {
                AiProviderSetting::query()
                    ->whereKeyNot($aiProviderSetting->getKey())
                    ->update(['is_default' => false]);
            }

            $aiProviderSetting->update($data);
        });

        return response()->json(['message' => 'Provedor de IA atualizado com sucesso.']);
    }

    public function destroy(AiProviderSetting $aiProviderSetting): JsonResponse
    {
        abort_unless(request()->user()?->can('system.settings.ai.update'), 403);

        $aiProviderSetting->delete();

        return response()->json(['message' => 'Provedor de IA removido com sucesso.']);
    }

    public function test(AiProviderSetting $aiProviderSetting): JsonResponse
    {
        abort_unless(request()->user()?->can('system.settings.ai.test'), 403);

        try {
            $response = $this->sendTestRequest($aiProviderSetting);

            if ($response->failed()) {
                $testedAt = now();
                $aiProviderSetting->update([
                    'last_tested_at' => $testedAt,
                    'last_test_succeeded' => false,
                ]);

                return response()->json([
                    'message' => 'O provedor recusou a conexão. Verifique a chave, o modelo e a URL configurados.',
                    'tested_at' => $testedAt->toIso8601String(),
                ], 422);
            }

            $testedAt = now();
            $aiProviderSetting->update([
                'last_tested_at' => $testedAt,
                'last_test_succeeded' => true,
            ]);

            return response()->json([
                'message' => 'Conexão com a IA realizada com sucesso.',
                'tested_at' => $testedAt->toIso8601String(),
            ]);
        } catch (Throwable $exception) {
            report($exception);

            $testedAt = now();
            $aiProviderSetting->update([
                'last_tested_at' => $testedAt,
                'last_test_succeeded' => false,
            ]);

            return response()->json([
                'message' => 'Não foi possível conectar ao provedor. Verifique a URL e tente novamente.',
                'tested_at' => $testedAt->toIso8601String(),
            ], 422);
        }
    }

    private function sendTestRequest(AiProviderSetting $setting)
    {
        $http = Http::acceptJson()->timeout($setting->timeout);

        return match ($setting->provider) {
            'openai' => $this->openAiTest($http, $setting),
            'anthropic' => $this->anthropicTest($http, $setting),
            'gemini' => $this->geminiTest($http, $setting),
            'ollama' => $this->ollamaTest($http, $setting),
            default => $this->compatibleTest($http, $setting),
        };
    }

    private function openAiTest(PendingRequest $http, AiProviderSetting $setting)
    {
        $headers = filled($setting->organization) ? ['OpenAI-Organization' => $setting->organization] : [];

        return $http->withToken($setting->api_key)
            ->withHeaders($headers)
            ->post(($setting->base_url ?: 'https://api.openai.com/v1').'/responses', [
                'model' => $setting->model,
                'input' => 'Responda apenas OK.',
                'max_output_tokens' => 16,
            ]);
    }

    private function anthropicTest(PendingRequest $http, AiProviderSetting $setting)
    {
        return $http->withHeaders([
            'x-api-key' => $setting->api_key,
            'anthropic-version' => '2023-06-01',
        ])->post(($setting->base_url ?: 'https://api.anthropic.com/v1').'/messages', [
            'model' => $setting->model,
            'max_tokens' => 16,
            'messages' => [['role' => 'user', 'content' => 'Responda apenas OK.']],
        ]);
    }

    private function geminiTest(PendingRequest $http, AiProviderSetting $setting)
    {
        $baseUrl = $setting->base_url ?: 'https://generativelanguage.googleapis.com/v1beta';

        return $http->withHeaders(['x-goog-api-key' => $setting->api_key])
            ->post($baseUrl.'/models/'.rawurlencode($setting->model).':generateContent', [
                'contents' => [['parts' => [['text' => 'Responda apenas OK.']]]],
                'generationConfig' => ['maxOutputTokens' => 16],
            ]);
    }

    private function ollamaTest(PendingRequest $http, AiProviderSetting $setting)
    {
        return $http->post(($setting->base_url ?: 'http://host.docker.internal:11434').'/api/chat', [
            'model' => $setting->model,
            'stream' => false,
            'messages' => [['role' => 'user', 'content' => 'Responda apenas OK.']],
        ]);
    }

    private function compatibleTest(PendingRequest $http, AiProviderSetting $setting)
    {
        $defaults = [
            'github_models' => 'https://models.github.ai/inference',
            'openrouter' => 'https://openrouter.ai/api/v1',
            'groq' => 'https://api.groq.com/openai/v1',
            'mistral' => 'https://api.mistral.ai/v1',
        ];
        $baseUrl = $setting->base_url ?: ($defaults[$setting->provider] ?? '');

        return $http->withToken($setting->api_key)
            ->post($baseUrl.'/chat/completions', [
                'model' => $setting->model,
                'max_tokens' => 16,
                'messages' => [['role' => 'user', 'content' => 'Responda apenas OK.']],
            ]);
    }
}
