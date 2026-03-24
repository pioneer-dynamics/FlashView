<?php

namespace App\Http\Requests\Api;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class RetrieveSecretRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * Any authenticated API user with the secrets:list ability can
     * retrieve a secret — the passphrase is the access control,
     * not ownership.
     */
    public function authorize(): bool
    {
        return $this->user()->tokenCan('secrets:list');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [];
    }
}
