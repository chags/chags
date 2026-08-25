<?php

namespace App\Http\Requests\TimeManagement;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReviewTimeAdjustmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('time-records.approve') ?? false;
    }

    public function rules(): array
    {
        return [
            'decision' => ['required', Rule::in(['approve', 'reject'])],
            'notes' => [Rule::requiredIf($this->input('decision') === 'reject'), 'nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return ['notes.required' => 'Informe o motivo da rejeição.'];
    }
}
