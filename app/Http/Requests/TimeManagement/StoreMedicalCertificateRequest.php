<?php

namespace App\Http\Requests\TimeManagement;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreMedicalCertificateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return ($this->user()?->tracks_time ?? false)
            && ($this->user()?->can('medical-certificates.submit') ?? false);
    }

    public function rules(): array
    {
        return [
            'starts_on' => ['required', 'date'],
            'ends_on' => ['required', 'date', 'after_or_equal:starts_on'],
            'starts_at' => ['nullable', 'date_format:H:i'],
            'ends_at' => ['nullable', 'date_format:H:i'],
            'reason' => ['required', 'string', 'min:10', 'max:1000'],
            'document' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($this->filled('starts_at') xor $this->filled('ends_at')) {
                $validator->errors()->add('starts_at', 'Informe o início e o fim do afastamento parcial.');
            }

            if ($this->filled('starts_at') && $this->input('starts_at') >= $this->input('ends_at')) {
                $validator->errors()->add('ends_at', 'O fim deve ser posterior ao início.');
            }
        }];
    }
}
