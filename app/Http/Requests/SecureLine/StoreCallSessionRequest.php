<?php

namespace App\Http\Requests\SecureLine;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreCallSessionRequest extends FormRequest
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
            'credit_token' => ['required', 'string', 'max:64'],
            'public_key' => ['required', 'string', 'regex:/^[A-Za-z0-9+\/]+=*$/'],
            'key_salt' => ['required', 'string', 'max:64', 'regex:/^[A-Za-z0-9+\/]+=*$/'],
        ];
    }
}
