<?php

namespace App\Http\Requests\Call;

use Illuminate\Foundation\Http\FormRequest;

class JoinCallSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'signature' => ['required', 'string'],
            'public_key' => ['nullable', 'string', 'max:512'],
        ];
    }
}
