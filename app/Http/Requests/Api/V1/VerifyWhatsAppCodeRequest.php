<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class VerifyWhatsAppCodeRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'challenge_id' => ['required', 'string', 'exists:whatsapp_unlock_challenges,id'],
            'device_installation_id' => ['required', 'uuid'],
            'code' => ['required', 'digits:6'],
        ];
    }
}
