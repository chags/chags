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
}
