<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCouponRequest extends FormRequest
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
            'discount_type' => ['required', 'in:percent,amount'],
            'discount_value' => ['required', 'numeric', 'min:1', Rule::when($this->input('discount_type') === 'percent', ['max:100'])],
            'duration' => ['required', 'in:once,forever,repeating'],
            'duration_in_months' => ['required_if:duration,repeating', 'nullable', 'integer', 'min:1', 'max:36'],
            'currency' => ['required_if:discount_type,amount', 'nullable', 'string', 'size:3'],
            'applies_to' => ['nullable', 'in:locker,secure_line,both'],
            'max_redemptions' => ['nullable', 'integer', 'min:1'],
            'max_redemptions_per_user' => ['nullable', 'integer', 'min:1'],
            'minimum_amount' => ['nullable', 'numeric', 'min:0'],
            'expires_at' => ['nullable', 'date', 'after:today'],
            'promo_code' => ['required', 'string', 'regex:/^[A-Z0-9_-]+$/i', 'min:3', 'max:20'],
        ];
    }
}
