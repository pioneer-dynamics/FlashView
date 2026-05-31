<?php

namespace App\Http\Requests\Locker;

use App\Models\Locker;
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
        $isFileLocker = $this->resolveIsFileLocker();

        return [
            'payload' => ['required', 'string', "max:{$maxHexLength}"],
            'storage_path' => $isFileLocker ? ['required', 'string'] : ['nullable', 'string'],
            'new_auth_verifier' => ['nullable', 'string', 'size:64'],
            'new_update_token' => ['nullable', 'string', 'size:64'],
        ];
    }

    private function resolveIsFileLocker(): bool
    {
        $accountId = $this->route('accountId');
        if (! $accountId) {
            return false;
        }

        $locker = Locker::where('account_id', $accountId)->first();

        return $locker?->isFileLocker() ?? false;
    }
}
