<?php

namespace App\Http\Requests\TimeManagement;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreHolidayRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('time-records.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'company_id' => ['nullable', 'integer', 'exists:companies,id'],
            'name' => ['required', 'string', 'max:150'],
            'holiday_date' => ['required', 'date'],
            'scope' => ['required', Rule::in(['national', 'state', 'municipal', 'company'])],
            'state' => ['nullable', 'string', 'size:2'],
            'city' => ['nullable', 'string', 'max:100'],
            'starts_at' => ['nullable', 'date_format:H:i'],
            'ends_at' => ['nullable', 'date_format:H:i'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($this->filled('starts_at') xor $this->filled('ends_at')) {
                $validator->errors()->add('starts_at', 'Informe o início e o fim do feriado parcial.');
            }

            if ($this->filled('starts_at') && $this->input('starts_at') >= $this->input('ends_at')) {
                $validator->errors()->add('ends_at', 'O fim deve ser posterior ao início.');
            }
        }];
    }
}
