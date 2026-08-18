<?php

namespace App\Http\Requests\Users;

use App\Models\User;
use App\Support\Authorization\RoleCatalog;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UserRequest extends FormRequest
{
    public function authorize(): bool
    {
        $ability = $this->route('user') ? 'users.update' : 'users.create';

        return $this->user()?->can($ability) === true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'cpf' => preg_replace('/\D/', '', (string) $this->input('cpf')) ?: null,
            'phone' => preg_replace('/\D/', '', (string) $this->input('phone')) ?: null,
            'postal_code' => preg_replace('/\D/', '', (string) $this->input('postal_code')) ?: null,
            'state' => strtoupper((string) $this->input('state')) ?: null,
        ]);
    }

    public function rules(): array
    {
        /** @var User|null $user */
        $user = $this->route('user');

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($user)],
            'cpf' => ['nullable', 'digits:11', Rule::unique('users')->ignore($user), $this->validCpf(...)],
            'phone' => ['nullable', 'digits_between:10,11'],
            'birth_date' => ['nullable', 'date', 'before_or_equal:today'],
            'gender' => ['nullable', Rule::in(['female', 'male', 'non_binary', 'not_informed'])],
            'postal_code' => ['nullable', 'digits:8'],
            'address' => ['nullable', 'string', 'max:255'],
            'address_number' => ['nullable', 'string', 'max:30'],
            'address_complement' => ['nullable', 'string', 'max:255'],
            'district' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'state' => ['nullable', 'string', 'size:2'],
            'password' => [$user ? 'nullable' : 'required', 'string', 'min:8', 'confirmed'],
            'role' => ['required', Rule::in(RoleCatalog::names())],
        ];
    }

    private function validCpf(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null) {
            return;
        }

        $cpf = (string) $value;

        if (preg_match('/^(\d)\1{10}$/', $cpf)) {
            $fail('O CPF informado é inválido.');

            return;
        }

        for ($digit = 9; $digit < 11; $digit++) {
            $sum = 0;

            for ($index = 0; $index < $digit; $index++) {
                $sum += ((int) $cpf[$index]) * (($digit + 1) - $index);
            }

            $expected = (10 * $sum) % 11;
            $expected = $expected === 10 ? 0 : $expected;

            if ((int) $cpf[$digit] !== $expected) {
                $fail('O CPF informado é inválido.');

                return;
            }
        }
    }
}
