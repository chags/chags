<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateDeviceChallengeRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'installation_id' => ['required', 'uuid'],
            'platform' => ['required', Rule::in(['android', 'ios'])],
            'purpose' => ['required', Rule::in(['register', 'verify', 'sensitive_action'])],
        ];
    }
}
