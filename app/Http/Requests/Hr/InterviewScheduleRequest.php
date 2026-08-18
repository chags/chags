<?php

namespace App\Http\Requests\Hr;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InterviewScheduleRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('interviews.create') === true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'application_id' => ['required', 'exists:applications,id'], 'organizer_id' => ['required', 'exists:users,id'],
            'format' => ['required', Rule::in(['online', 'presential', 'phone'])], 'title' => ['required', 'string', 'max:255'], 'starts_at' => ['required', 'date'], 'duration_minutes' => ['required', 'integer', 'min:15', 'max:240'], 'timezone' => ['required', 'timezone'],
            'location' => ['nullable', 'required_if:format,presential', 'string', 'max:255'], 'meeting_url' => ['nullable', 'required_if:format,online', 'url:http,https'], 'public_instructions' => ['nullable', 'string', 'max:3000'], 'internal_notes' => ['nullable', 'string', 'max:5000'], 'send_email' => ['nullable', 'boolean'],
        ];
    }
}
