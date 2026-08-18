<?php

namespace App\Http\Requests\Hr;

use App\Models\Job;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class JobRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        /** @var Job|null $job */
        $job = $this->route('job');

        return $job
            ? $this->user()?->can('update', $job) === true
            : $this->user()?->can('create', Job::class) === true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'company_id' => ['required', 'integer', 'exists:companies,id'],
            'department_id' => [
                'required',
                Rule::exists('departments', 'id')->where('company_id', $this->integer('company_id')),
            ],
            'position_id' => [
                'nullable',
                Rule::exists('positions', 'id')->where('company_id', $this->integer('company_id')),
            ],
            'hiring_manager_id' => ['nullable', 'integer', 'exists:users,id'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:20000'],
            'requirements' => ['nullable', 'string', 'max:20000'],
            'benefits' => ['nullable', 'string', 'max:20000'],
            'workplace_type' => ['required', Rule::in(['onsite', 'hybrid', 'remote'])],
            'employment_type' => ['required', Rule::in(['clt', 'pj', 'internship', 'temporary', 'apprentice'])],
            'city' => ['nullable', 'string', 'max:255'],
            'state' => ['nullable', 'string', 'size:2'],
            'status' => ['required', Rule::in(['draft', 'published', 'paused', 'closed'])],
            'closes_at' => ['nullable', 'date', 'after_or_equal:today'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['state' => $this->filled('state') ? strtoupper($this->string('state')->toString()) : null]);
    }
}
