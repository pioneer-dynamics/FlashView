<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class CliDeviceActivateRequest extends FormRequest
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
            'user_code' => ['required', 'string', 'regex:/^[A-Z0-9]{4}-[A-Z0-9]{4}$/'],
            'name' => ['nullable', 'string', 'max:255'],
        ];
    }
}
