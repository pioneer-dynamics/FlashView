<?php

namespace App\Http\Requests\Locker;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class RenewLockerRequest extends FormRequest
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
            'verifier' => ['required', 'string', 'size:64'],
            'years' => ['required', 'integer', 'in:1,3,5'],
            'tier' => ['required', 'in:text,file'],
        ];
    }
}
