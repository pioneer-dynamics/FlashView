<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreSecureLineProductRequest extends FormRequest
{
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
            'name' => ['required', 'string', 'max:255'],
            'duration_minutes' => ['required', 'integer', 'min:1', 'max:1440'],
            'max_participants' => ['required', 'integer', 'min:2', 'max:100'],
            'amount_cents' => ['required', 'integer', 'min:1'],
            'stripe_price_id' => ['nullable', 'string', 'max:255'],
            // Omitting 'required' is intentional — omitting the field defaults to false via $request->boolean()
            'create_stripe_price' => ['boolean'],
            'is_active' => ['boolean'],
        ];
    }
}
