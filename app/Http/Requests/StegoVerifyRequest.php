<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StegoVerifyRequest extends FormRequest
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
            'ciphertext' => ['required', 'string'],
            'verified_identity' => ['required', 'array'],
            'verified_identity.type' => ['required', 'string'],
            'verified_identity.company_name' => ['nullable', 'string'],
            'verified_identity.domain' => ['nullable', 'string'],
            'verified_identity.email' => ['nullable', 'string'],
            'signature' => ['required', 'string'],
        ];
    }
}
