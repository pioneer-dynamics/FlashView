<?php

namespace App\Http\Requests\Locker;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateLockerRequest extends FormRequest
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
        $maxHexLength = config('lockers.limits.text_max_bytes') * 2 + 92 + 32;

        return [
            'payload' => ['required', 'string', "max:{$maxHexLength}"],
            'storage_path' => ['nullable', 'string'],
            'new_auth_verifier' => ['nullable', 'string', 'size:64'],
            'new_update_token' => ['nullable', 'string', 'size:64'],
            'new_wrapped_file_key' => ['nullable', 'string', 'max:512'],
            'new_public_key' => ['nullable', 'string'],
            'new_auth_mode' => ['sometimes', 'in:passphrase,key_file,combined'],
            'new_key_file_count' => [
                'nullable',
                'prohibited_if:new_auth_mode,passphrase',
                'required_if:new_auth_mode,key_file',
                'required_if:new_auth_mode,combined',
                'integer', 'min:1', 'max:20',
            ],
        ];
    }
}
