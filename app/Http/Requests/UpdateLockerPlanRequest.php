<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateLockerPlanRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'tier' => ['required', 'in:text,file'],
            'years' => ['required', 'integer', 'in:1,3,5'],
            'amount_cents' => ['required', 'integer', 'min:1'],
            'stripe_price_id' => ['nullable', 'string', 'max:255'],
            'is_active' => ['boolean'],
        ];
    }
}
