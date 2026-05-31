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

        return [
            'account_id' => ['required', 'string', 'size:10', 'regex:/^\d{10}$/', 'unique:lockers,account_id'],
            'credit_token' => ['required', 'string', 'exists:locker_credits,token'],
            'payload' => ['required', 'string', "max:{$maxHexLength}"],
            'auth_verifier' => ['required', 'string', 'size:64'],
            'tier' => ['required', 'in:text,file'],
            'storage_path' => ['required_if:tier,file', 'nullable', 'string'],
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
