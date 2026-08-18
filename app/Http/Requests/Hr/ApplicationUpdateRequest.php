<?php

namespace App\Http\Requests\Hr;

use App\Models\Application;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ApplicationUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('application')) === true;
    }

    public function rules(): array
    {
        /** @var Application $application */
        $application = $this->route('application');

        return [
            'status' => ['required', Rule::in(['active', 'rejected', 'withdrawn', 'hired'])],
            'current_stage_id' => [
                'nullable',
                Rule::exists('recruitment_stages', 'id')->where(
                    fn ($query) => $query
                        ->where('company_id', $application->job->company_id)
                        ->where('active', true),
                ),
            ],
            'notes' => ['nullable', 'string', 'max:2000'],
            'rejection_message' => ['nullable', 'required_if:status,rejected', 'string', 'max:2000'],
            'rejection_internal_notes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    public function messages(): array
    {
        return [
            'rejection_message.required_if' => 'Informe a mensagem que será apresentada ao candidato.',
            'rejection_message.max' => 'A mensagem ao candidato pode ter no máximo 2.000 caracteres.',
            'rejection_internal_notes.max' => 'A observação interna pode ter no máximo 5.000 caracteres.',
        ];
    }
}
