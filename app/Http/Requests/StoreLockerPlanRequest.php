<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreLockerPlanRequest extends FormRequest
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
            'years' => ['required', 'integer', 'min:1', 'max:100'],
            'file_size_mb' => ['nullable', 'integer', 'min:1', 'max:10000', 'required_if:tier,file'],
            'amount_cents' => ['required', 'integer', 'min:1'],
            'stripe_price_id' => ['nullable', 'string', 'max:255'],
            'create_stripe_price' => ['boolean'],
            'is_active' => ['boolean'],
        ];
    }
}
