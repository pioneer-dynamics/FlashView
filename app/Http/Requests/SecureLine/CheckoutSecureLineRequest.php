<?php

namespace App\Http\Requests\SecureLine;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CheckoutSecureLineRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'product_id' => [
                'required',
                'integer',
                Rule::exists('secure_line_products', 'id')
                    ->where('is_active', true)
                    ->whereNotNull('stripe_price_id')
                    ->whereNull('deleted_at'),
            ],
        ];
    }
}
