<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreSenderIdentityRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->planSupportsSenderIdentity() ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $isDomain = $this->input('type') === 'domain';

        return [
            'type' => ['required', 'in:domain,email'],
            'company_name' => $isDomain ? ['required', 'string', 'max:100'] : ['nullable', 'string', 'max:100'],
            'domain' => $isDomain
                ? ['required', 'string', 'max:255', 'regex:/^([a-zA-Z0-9]([a-zA-Z0-9\-]{0,61}[a-zA-Z0-9])?\.)+[a-zA-Z]{2,}$/']
                : ['prohibited'],
            'email' => ['prohibited'],
        ];
    }
}
