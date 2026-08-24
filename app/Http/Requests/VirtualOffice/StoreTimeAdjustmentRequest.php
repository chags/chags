<?php

namespace App\Http\Requests\VirtualOffice;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreTimeAdjustmentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('time-records.request-adjustment') ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'work_date' => ['required', 'date', 'before_or_equal:today'],
            'requested_entries' => ['required', 'array', 'min:1', 'max:4'],
            'requested_entries.*.type' => [
                'required',
                'distinct',
                Rule::in(['clock_in', 'break_start', 'break_end', 'clock_out', 'overtime_start', 'overtime_end']),
            ],
            'requested_entries.*.time' => ['required', 'date_format:H:i'],
            'reason' => ['required', 'string', 'min:10', 'max:1000'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            $entries = collect($this->input('requested_entries', []))->keyBy('type');
            $expectedOrder = ['clock_in', 'break_start', 'break_end', 'clock_out', 'overtime_start', 'overtime_end'];
            $times = collect($expectedOrder)->map(fn (string $type) => $entries->get($type)['time'] ?? null);

            $filledTimes = $times->filter()->values();
            if ($filledTimes->count() > 1 && $filledTimes->all() !== $filledTimes->sort()->values()->all()) {
                $validator->errors()->add('requested_entries', 'Os horários devem estar em ordem cronológica.');
            }
        }];
    }
}
