<?php

namespace App\Http\Requests\Locker;

use App\Models\Locker;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UnlockLockerRequest extends FormRequest
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
        $locker = Locker::where('account_id', $this->route('accountId'))->first();
        $isEcdsa = $locker && $locker->public_key !== null;

        return [
            'challenge_id' => $isEcdsa ? ['required', 'string', 'uuid'] : ['nullable'],
            'signature' => $isEcdsa ? ['required', 'string'] : ['nullable'],
            'verifier' => $isEcdsa ? ['nullable'] : ['required', 'string', 'size:64'],
        ];
    }
}
