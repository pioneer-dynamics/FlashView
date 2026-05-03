<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class CreatePipeSignalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'role' => ['required', 'string', 'in:sender,receiver'],
            'type' => ['required', 'string', 'in:offer,answer,ice-candidate'],
            'payload' => ['required', 'array'],
        ];
    }
}
