<?php

namespace App\Http\Requests\System;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AiProviderSettingRequest extends FormRequest
{
    public const PROVIDERS = [
        'openai',
        'anthropic',
        'gemini',
        'github_models',
        'openrouter',
        'groq',
        'mistral',
        'ollama',
        'custom',
    ];

    public function authorize(): bool
    {
        return $this->user()?->can('system.settings.ai.update') === true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'enabled' => $this->boolean('enabled'),
            'is_default' => $this->boolean('is_default'),
            'base_url' => $this->filled('base_url') ? rtrim((string) $this->input('base_url'), '/') : null,
            'organization' => $this->filled('organization') ? $this->input('organization') : null,
        ]);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'provider' => ['required', Rule::in(self::PROVIDERS)],
            'enabled' => ['required', 'boolean'],
            'is_default' => ['required', 'boolean'],
            'base_url' => ['nullable', 'url:http,https', 'max:255'],
            'model' => ['required', 'string', 'max:150'],
            'api_key' => ['nullable', 'string', 'max:1000'],
            'organization' => ['nullable', 'string', 'max:150'],
            'timeout' => ['required', 'integer', 'min:5', 'max:300'],
            'max_output_tokens' => ['required', 'integer', 'min:1', 'max:1000000'],
            'temperature' => ['required', 'numeric', 'min:0', 'max:2'],
        ];
    }

    public function messages(): array
    {
        return [
            'provider.in' => 'Selecione um provedor de IA válido.',
            'base_url.url' => 'Informe uma URL válida iniciando com http:// ou https://.',
        ];
    }
}
