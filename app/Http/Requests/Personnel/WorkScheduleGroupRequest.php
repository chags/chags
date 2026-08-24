<?php

namespace App\Http\Requests\Personnel;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class WorkScheduleGroupRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $timeFields = ['start_time', 'break_start_time', 'break_end_time', 'end_time'];
        $days = collect($this->input('days', []))->map(function ($day) use ($timeFields) {
            foreach ($timeFields as $field) {
                if (isset($day[$field]) && is_string($day[$field])) {
                    $day[$field] = substr($day[$field], 0, 5);
                }
            }

            return $day;
        })->all();

        $this->merge(['days' => $days]);
    }

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
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('work_schedule_groups', 'name')
                    ->where(fn ($query) => $query->where('schedule_type', $this->input('schedule_type')))
                    ->ignore($this->route('group')),
            ],
            'description' => ['nullable', 'string', 'max:1000'],
            'schedule_type' => ['required', Rule::in(['5x2', '6x1', '12x36', 'custom'])],
            'weekly_minutes' => ['required', 'integer', 'min:1', 'max:10080'],
            'entry_tolerance_minutes' => ['required', 'integer', 'min:0', 'max:60'],
            'daily_tolerance_minutes' => ['required', 'integer', 'min:0', 'max:120'],
            'operational_window_minutes' => ['required', 'integer', 'min:0', 'max:120'],
            'daily_overtime_limit_minutes' => ['required', 'integer', 'min:0', 'max:120'],
            'requires_overtime_approval' => ['required', 'boolean'],
            'cycle_start_date' => ['nullable', 'date', Rule::requiredIf($this->input('schedule_type') === '12x36')],
            'active' => ['required', 'boolean'],
            'days' => ['required', 'array', 'min:1'],
            'days.*.day_index' => ['required', 'integer', 'min:1', 'max:31', 'distinct'],
            'days.*.label' => ['required', 'string', 'max:50'],
            'days.*.is_workday' => ['required', 'boolean'],
            'days.*.start_time' => ['nullable', 'date_format:H:i'],
            'days.*.break_start_time' => ['nullable', 'date_format:H:i'],
            'days.*.break_end_time' => ['nullable', 'date_format:H:i'],
            'days.*.end_time' => ['nullable', 'date_format:H:i'],
            'days.*.expected_minutes' => ['required', 'integer', 'min:0', 'max:1440'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.unique' => 'Já existe um grupo com este nome e este tipo de escala.',
        ];
    }
}
