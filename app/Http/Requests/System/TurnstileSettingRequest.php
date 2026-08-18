<?php

namespace App\Http\Requests\System;

use App\Models\TurnstileSetting;
use Illuminate\Foundation\Http\FormRequest;

class TurnstileSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('system.settings.turnstile.update') === true;
    }

    public function rules(): array
    {
        $hasSecret = filled(TurnstileSetting::query()->value('secret_key'));

        return [
            'enabled' => ['required', 'boolean'],
            'site_key' => ['required_if:enabled,true', 'nullable', 'string', 'max:255'],
            'secret_key' => [
                $this->boolean('enabled') && ! $hasSecret ? 'required' : 'nullable',
                'string',
                'max:1000',
            ],
        ];
    }
}
