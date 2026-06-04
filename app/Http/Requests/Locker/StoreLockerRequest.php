<?php

namespace App\Http\Requests\Locker;

use App\Models\LockerCredit;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreLockerRequest extends FormRequest
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
        $isEcdsa = $this->filled('public_key');

        return [
            'account_id' => ['required', 'string', 'size:10', 'regex:/^\d{10}$/', 'unique:lockers,account_id'],
            'credit_token' => ['required', 'string', 'exists:locker_credits,token'],
            'payload' => ['required', 'string', "max:{$maxHexLength}"],
            'public_key' => ['nullable', 'string'],
            'auth_challenge' => $isEcdsa ? ['nullable'] : ['required', 'string', 'size:64'],
            'auth_verifier' => $isEcdsa ? ['nullable'] : ['required', 'string', 'size:64'],
            'update_token' => $isEcdsa ? ['nullable'] : ['required', 'string', 'size:64'],
            'tier' => ['required', 'in:text,file'],
            'storage_path' => ['required_if:tier,file', 'nullable', 'string'],
            'wrapped_file_key' => ['nullable', 'string', 'max:512'],
            'auth_mode' => ['sometimes', 'in:passphrase,key_file,combined'],
            'key_file_count' => [
                'nullable',
                'prohibited_if:auth_mode,passphrase',
                'required_if:auth_mode,key_file',
                'required_if:auth_mode,combined',
                'integer', 'min:1', 'max:20',
            ],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $credit = LockerCredit::where('token', $this->input('credit_token'))->first();
            if ($credit) {
                if ($credit->isUsed()) {
                    $validator->errors()->add('credit_token', 'This credit token has already been used.');
                } elseif ($credit->tier !== $this->input('tier')) {
                    $validator->errors()->add('tier', 'Tier does not match the credit token.');
                }
            }
        });
    }
}
