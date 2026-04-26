<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StorePlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isAdmin();
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', 'unique:plans,name'],
            'price_per_month' => ['required', 'numeric', 'min:0'],
            'price_per_year' => ['required', 'numeric', 'min:0'],
            'is_free_plan' => ['boolean', 'nullable'],
            'create_stripe_product' => ['required', 'boolean'],
            'stripe_product_id' => ['nullable', 'string', 'max:255'],
            'stripe_monthly_price_id' => ['nullable', 'string', 'max:255'],
            'stripe_yearly_price_id' => ['nullable', 'string', 'max:255'],
            'features' => ['required', 'array', 'min:1'],
            'features.*.order' => ['required', 'numeric'],
            'features.*.type' => ['required', 'in:feature,limit'],
            'features.*.config' => ['present', 'array'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
        ];
    }
}
