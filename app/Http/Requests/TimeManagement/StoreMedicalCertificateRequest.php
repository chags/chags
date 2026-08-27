<?php

namespace App\Http\Requests\TimeManagement;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use Illuminate\Validation\Rule;

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
            'type' => ['required', Rule::in(['medical_certificate', 'absence_declaration'])],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['required', 'date', 'after_or_equal:starts_on'],
            'starts_at' => ['nullable', 'date_format:H:i', 'required_if:type,absence_declaration', 'prohibited_if:type,medical_certificate'],
            'ends_at' => ['nullable', 'date_format:H:i', 'required_if:type,absence_declaration', 'prohibited_if:type,medical_certificate'],
            'reason' => ['required', 'string', 'min:10', 'max:1000'],
            'document' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($this->input('type') === 'absence_declaration' && $this->input('starts_on') !== $this->input('ends_on')) {
                $validator->errors()->add('ends_on', 'A declaração por horas deve começar e terminar na mesma data.');
            }

            if ($this->filled('starts_at') && $this->input('starts_at') >= $this->input('ends_at')) {
                $validator->errors()->add('ends_at', 'O fim deve ser posterior ao início.');
            }
        }];
    }
}
