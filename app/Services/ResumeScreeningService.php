<?php

namespace App\Services;

use App\Models\AiProviderSetting;
use App\Models\Application;
use App\Models\Curriculum;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class ResumeScreeningService
{
    public function screen(Application $application): Curriculum
    {
        $curriculum = Curriculum::query()->where('application_id', $application->id)->firstOrFail();
        $curriculum->forceFill([
            'evaluation_status' => 'processing',
            'evaluation_attempts' => $curriculum->evaluation_attempts + 1,
            'evaluation_error' => null,
            'last_attempted_at' => now(),
        ])->save();

        try {
            if ($curriculum->extraction_status !== 'completed' || empty($curriculum->extracted_data)) {
                throw new RuntimeException('Extraia os dados do currículo antes de solicitar a avaliação pela IA.');
            }

            $provider = AiProviderSetting::query()
                ->where('enabled', true)
                ->orderByDesc('is_default')
                ->orderBy('id')
                ->firstOrFail();

            if ($provider->provider !== 'openai') {
                throw new RuntimeException('A avaliação de currículos está disponível inicialmente para o provedor OpenAI.');
            }

            $application->loadMissing('job');
            $result = $this->evaluateWithOpenAi($application, $curriculum, $provider);
            $curriculum->forceFill([
                'ai_provider_setting_id' => $provider->id,
                'status' => 'completed',
                'evaluation_status' => 'completed',
                'score' => max(0, min(100, (int) ($result['score'] ?? 0))),
                'recommendation' => $result['recommendation'] ?? 'review',
                'opinion' => $result['opinion'] ?? null,
                'summary' => $result['opinion'] ?? null,
                'strengths' => $result['strengths'] ?? [],
                'concerns' => $result['concerns'] ?? [],
                'matched_requirements' => $result['matched_requirements'] ?? [],
                'missing_requirements' => $result['missing_requirements'] ?? [],
                'model' => $provider->model,
                'evaluated_at' => now(),
                'processed_at' => now(),
                'evaluation_error' => null,
                'error_message' => null,
            ])->save();
        } catch (Throwable $exception) {
            report($exception);
            $curriculum->forceFill([
                'status' => 'failed',
                'evaluation_status' => 'failed',
                'evaluation_error' => Str::limit($exception->getMessage(), 1000),
                'error_message' => Str::limit($exception->getMessage(), 1000),
            ])->save();
        }

        return $curriculum->refresh();
    }

    private function evaluateWithOpenAi(Application $application, Curriculum $curriculum, AiProviderSetting $provider): array
    {
        $prompt = <<<'PROMPT'
Você é um analista de recrutamento. Avalie somente os dados estruturados extraídos do currículo contra a vaga. Não invente experiências e não use idade, gênero, raça, fotografia, estado civil ou outros atributos sensíveis. Produza nota inteira de 0 a 100, parecer profissional fundamentado e recomendação. Retorne SOMENTE JSON válido:
{"score":0,"recommendation":"advance|review|do_not_advance","opinion":"","strengths":[],"concerns":[],"matched_requirements":[],"missing_requirements":[]}
PROMPT;
        $context = json_encode([
            'vacancy' => [
                'title' => $application->job->title,
                'description' => $application->job->description,
                'requirements' => $application->job->requirements,
            ],
            'curriculum' => $curriculum->extracted_data,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $headers = filled($provider->organization) ? ['OpenAI-Organization' => $provider->organization] : [];
        $response = Http::acceptJson()
            ->withToken($provider->api_key)
            ->withHeaders($headers)
            ->timeout($provider->timeout)
            ->post(($provider->base_url ?: 'https://api.openai.com/v1').'/responses', [
                'model' => $provider->model,
                'input' => $prompt."\n\nDADOS PARA AVALIAÇÃO:\n".$context,
                'max_output_tokens' => $provider->max_output_tokens,
            ]);

        if ($response->failed()) {
            throw new RuntimeException("A IA recusou a avaliação do currículo (HTTP {$response->status()}).");
        }

        $text = $response->json('output_text');

        if (! is_string($text)) {
            $text = collect($response->json('output', []))
                ->flatMap(fn (array $output) => $output['content'] ?? [])
                ->first(fn (array $content) => ($content['type'] ?? null) === 'output_text')['text'] ?? null;
        }

        if (! is_string($text)) {
            $reason = $response->json('incomplete_details.reason');
            throw new RuntimeException($reason
                ? "A resposta da IA ficou incompleta ({$reason})."
                : 'A IA não retornou um parecer legível.');
        }

        $decoded = json_decode(preg_replace('/^```(?:json)?|```$/m', '', trim($text)), true);
        if (! is_array($decoded) || ! array_key_exists('score', $decoded) || ! isset($decoded['opinion'])) {
            throw new RuntimeException('A IA retornou uma avaliação em formato inválido.');
        }

        return $decoded;
    }
}
