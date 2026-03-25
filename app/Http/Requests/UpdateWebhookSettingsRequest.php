<?php

namespace App\Http\Requests;

use App\Rules\NotPrivateUrl;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateWebhookSettingsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->planSupportsWebhook();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'webhook_url' => ['nullable', 'url:https', 'max:2048', new NotPrivateUrl],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'webhook_url.url' => 'The webhook URL must be a valid HTTPS URL.',
        ];
    }
}
