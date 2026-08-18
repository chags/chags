<?php

namespace App\Http\Requests\System;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MailSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('system.settings.mail.update') === true;
    }

    public function rules(): array
    {
        return [
            'from_name' => ['required', 'string', 'max:255'],
            'from_address' => ['required', 'email', 'max:255'],
            'host' => ['required', 'string', 'max:255'],
            'port' => ['required', 'integer', 'between:1,65535'],
            'username' => ['nullable', 'string', 'max:255'],
            'password' => ['nullable', 'string', 'max:1000'],
            'encryption' => ['nullable', Rule::in(['tls', 'ssl'])],
            'timeout' => ['required', 'integer', 'between:1,300'],
        ];
    }
}
