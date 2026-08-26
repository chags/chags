<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class RequestWhatsAppCodeRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'phone' => ['required', 'string', 'max:30'],
            'device_installation_id' => ['required', 'uuid'],
        ];
    }
}
