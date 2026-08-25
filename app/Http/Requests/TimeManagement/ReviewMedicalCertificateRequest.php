<?php

namespace App\Http\Requests\TimeManagement;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReviewMedicalCertificateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('medical-certificates.review') ?? false;
    }

    public function rules(): array
    {
        return [
            'decision' => ['required', Rule::in(['approve', 'reject'])],
            'notes' => [Rule::requiredIf($this->input('decision') === 'reject'), 'nullable', 'string', 'max:1000'],
        ];
    }
}
