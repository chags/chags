<?php

namespace App\Http\Requests\Hr;

use App\Models\Department;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DepartmentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        /** @var Department|null $department */
        $department = $this->route('department');

        return $department ? $this->user()?->can('update', $department) === true : $this->user()?->can('create', Department::class) === true;
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
            'parent_id' => ['nullable', 'integer', Rule::exists('departments', 'id')->where('company_id', $this->integer('company_id')), Rule::notIn([$this->route('department')?->id])],
            'name' => ['required', 'string', 'max:255'],
            'active' => ['required', 'boolean'],
        ];
    }
}
