<?php

namespace App\Http\Requests\System;

use App\Enums\CompanyUnitType;
use App\Models\Company;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CompanyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('system.settings.company.update') === true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'cnpj' => preg_replace('/\D/', '', (string) $this->input('cnpj')),
            'postal_code' => preg_replace('/\D/', '', (string) $this->input('postal_code')),
            'state' => strtoupper((string) $this->input('state')),
            'headquarters_id' => $this->input('unit_type') === CompanyUnitType::Headquarters->value
                ? null
                : $this->input('headquarters_id'),
        ]);
    }

    public function rules(): array
    {
        /** @var Company|null $company */
        $company = $this->route('company');

        return [
            'unit_type' => ['required', Rule::enum(CompanyUnitType::class)],
            'unit_number' => ['required', 'string', 'max:30', Rule::unique('companies')->ignore($company)],
            'unit_name' => ['required', 'string', 'max:255'],
            'headquarters_id' => [
                Rule::requiredIf($this->input('unit_type') === CompanyUnitType::Branch->value),
                'nullable',
                Rule::exists('companies', 'id')->where('unit_type', CompanyUnitType::Headquarters->value),
            ],
            'name' => ['required', 'string', 'max:255'],
            'trade_name' => ['nullable', 'string', 'max:255'],
            'cnpj' => ['required', 'digits:14', Rule::unique('companies')->ignore($company), $this->validCnpj(...)],
            'address' => ['required', 'string', 'max:255'],
            'address_number' => ['required', 'string', 'max:30'],
            'address_complement' => ['nullable', 'string', 'max:255'],
            'district' => ['required', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:255'],
            'state' => ['required', 'string', 'size:2'],
            'postal_code' => ['required', 'digits:8'],
            'active' => ['required', 'boolean'],
        ];
    }

    private function validCnpj(string $attribute, mixed $value, Closure $fail): void
    {
        $cnpj = (string) $value;

        if (strlen($cnpj) !== 14 || preg_match('/^(\d)\1{13}$/', $cnpj)) {
            $fail('O CNPJ informado é inválido.');

            return;
        }

        for ($digit = 12; $digit < 14; $digit++) {
            $sum = 0;
            $weight = $digit - 7;

            for ($index = 0; $index < $digit; $index++) {
                $sum += ((int) $cnpj[$index]) * $weight;
                $weight = $weight === 2 ? 9 : $weight - 1;
            }

            $expected = $sum % 11 < 2 ? 0 : 11 - ($sum % 11);

            if ((int) $cnpj[$digit] !== $expected) {
                $fail('O CNPJ informado é inválido.');

                return;
            }
        }
    }
}
