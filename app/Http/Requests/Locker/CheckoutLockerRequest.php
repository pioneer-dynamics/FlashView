<?php

namespace App\Http\Requests\Locker;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class CheckoutLockerRequest extends FormRequest
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
            'tier' => ['required', 'in:text,file'],
            'years' => ['required', 'integer', 'in:1,3,5'],
        ];
    }
}
