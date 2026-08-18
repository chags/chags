<?php

namespace App\Http\Requests\Hr;

use App\Models\Position;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PositionRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Position|null $position */
        $position = $this->route('position');

        return $position
            ? $this->user()?->can('update', $position) === true
            : $this->user()?->can('create', Position::class) === true;
    }

    public function rules(): array
    {
        return [
            'company_id' => ['required', 'integer', 'exists:companies,id'],
            'department_id' => [
                'nullable',
                'integer',
                Rule::exists('departments', 'id')->where(
                    fn ($query) => $query
                        ->where('company_id', $this->integer('company_id'))
                        ->whereNull('deleted_at'),
                ),
            ],
            'title' => ['required', 'string', 'max:255'],
            'level' => ['nullable', Rule::in(['intern', 'junior', 'mid', 'senior', 'specialist', 'lead', 'manager'])],
            'code' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('positions')->where('company_id', $this->integer('company_id'))->ignore($this->route('position')),
            ],
            'description' => ['nullable', 'string', 'max:5000'],
            'active' => ['required', 'boolean'],
        ];
    }
}
