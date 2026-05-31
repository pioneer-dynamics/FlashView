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
        $isFileLocker = (bool) $this->get('is_file_locker', false);

        return [
            'payload' => ['required', 'string', "max:{$maxHexLength}"],
            'storage_path' => $isFileLocker ? ['required', 'string'] : ['nullable', 'string'],
        ];
    }
}
