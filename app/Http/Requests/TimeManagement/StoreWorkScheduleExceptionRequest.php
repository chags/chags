<?php

namespace App\Http\Requests\TimeManagement;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreWorkScheduleExceptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('time-records.approve') ?? false;
    }

    public function rules(): array
    {
        return [
            'user_id' => ['required', 'integer', Rule::exists('users', 'id')->where('tracks_time', true)],
            'work_date' => ['required', 'date'],
            'type' => ['required', Rule::in(['custom_schedule', 'hour_bank_leave'])],
            'start_time' => ['required_if:type,custom_schedule', 'nullable', 'date_format:H:i'],
            'break_start_time' => ['nullable', 'date_format:H:i'],
            'break_end_time' => ['nullable', 'date_format:H:i'],
            'end_time' => ['required_if:type,custom_schedule', 'nullable', 'date_format:H:i'],
            'reason' => ['required', 'string', 'min:10', 'max:1000'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($this->input('type') !== 'custom_schedule') {
                return;
            }

            $times = collect([
                $this->input('start_time'),
                $this->input('break_start_time'),
                $this->input('break_end_time'),
                $this->input('end_time'),
            ])->filter()->values();

            if ($times->count() > 1 && $times->all() !== $times->sort()->values()->all()) {
                $validator->errors()->add('start_time', 'Os horários da jornada devem estar em ordem cronológica.');
            }

            if ($this->filled('break_start_time') xor $this->filled('break_end_time')) {
                $validator->errors()->add('break_start_time', 'Informe o início e o fim do intervalo.');
            }
        }];
    }
}
