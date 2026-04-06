<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class StegoPageRequest extends FormRequest
{
    /**
     * The stego page is always accessible — guests need it to extract hidden messages.
     * Embedding is separately gated via canEmbed().
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Determine whether the current user can embed secrets into stego images.
     * Requires authentication and a plan that supports steganography.
     */
    public function canEmbed(): bool
    {
        return Gate::allows('embed-stego');
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [];
    }
}
