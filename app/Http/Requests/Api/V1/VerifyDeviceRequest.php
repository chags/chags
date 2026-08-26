<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class VerifyDeviceRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'challenge_id' => ['required', 'exists:device_challenges,id'],
            'nonce' => ['required', 'string', 'max:200'],
            'device_id' => ['required', 'exists:api_devices,id'],
            'installation_id' => ['required', 'uuid'],
            'challenge_signature' => ['required', 'string', 'max:2000'],
            'app.version' => ['required', 'string', 'max:30'],
            'app.build' => ['required', 'string', 'max:30'],
            'app.package_name' => ['required', 'string', 'max:255'],
            'app.signing_digest' => ['nullable', 'string', 'max:255'],
            'device.platform' => ['required', Rule::in(['android', 'ios'])],
            'device.manufacturer' => ['nullable', 'string', 'max:100'],
            'device.model' => ['nullable', 'string', 'max:100'],
            'device.os_version' => ['nullable', 'string', 'max:50'],
            'device.security_patch' => ['nullable', 'date_format:Y-m-d'],
            'device.locale' => ['nullable', 'string', 'max:20'],
            'device.timezone' => ['nullable', 'timezone'],
            'device.biometric_available' => ['required', 'boolean'],
            'attestation.provider' => ['required', Rule::in(['play_integrity', 'app_attest', 'fake'])],
            'attestation.token' => ['required', 'string', 'max:10000'],
        ];
    }
}
