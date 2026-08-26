<?php

namespace App\Http\Requests\Hr;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InAppMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('messages.manage') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:150'],
            'body' => ['required', 'string', 'max:10000'],
            'audience' => ['required', Rule::in(['user', 'all'])],
            'user_id' => ['nullable', 'required_if:audience,user', 'integer', 'exists:users,id'],
            'scheduled_at' => ['nullable', 'date', 'after:now'],
            'expires_at' => ['nullable', 'date', 'after:now'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'scheduled_at.date' => 'Informe uma data válida para o agendamento.',
            'scheduled_at.after' => 'O agendamento deve ser feito para uma data futura.',
            'expires_at.date' => 'Informe uma data de expiração válida.',
            'expires_at.after' => 'A expiração deve ser definida para uma data futura.',
        ];
    }
}
