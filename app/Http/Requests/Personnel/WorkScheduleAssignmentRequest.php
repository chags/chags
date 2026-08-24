<?php

namespace App\Http\Requests\Personnel;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class WorkScheduleAssignmentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('time-records.manage') ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'work_schedule_group_id' => ['required', 'integer', 'exists:work_schedule_groups,id'],
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'valid_from' => ['required', 'date'],
        ];
    }
}
