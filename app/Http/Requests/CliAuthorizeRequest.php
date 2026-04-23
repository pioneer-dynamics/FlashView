<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CliAuthorizeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'port' => ['required_without:redirect_uri', 'nullable', 'integer', 'min:1024', 'max:65535'],
            'redirect_uri' => ['required_without:port', 'nullable', Rule::in(config('auth.allowed_redirect_uris', []))],
            'state' => ['required', 'string', 'min:16'],
            'name' => ['nullable', 'string', 'max:255'],
            'token_id' => ['nullable', 'integer'],
            'client_type' => ['nullable', 'string', Rule::in(['cli', 'mobile'])],
        ];
    }
}
