<?php

namespace App\Http\Requests\Auth;

use App\Rules\AllowedEnvironmentEmail;
use Illuminate\Foundation\Http\FormRequest;

class InitiateRegistrationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email:rfc,dns', 'max:255', new AllowedEnvironmentEmail],
        ];
    }
}
