<?php

namespace App\Http\Requests\Api;

use App\Rules\MessageLength;
use App\Rules\ValidExpiry;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreSecretRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'message' => ['required', 'string', 'min:1', new MessageLength($this->getUserType(), $this->getAllowedMessageLength())],
            'expires_in' => ['required', 'numeric', new ValidExpiry($this->getUserType())],
        ];
    }

    private function getUserType(): string
    {
        if ($this->user()->subscribed()) {
            return 'subscribed';
        }

        return 'user';
    }

    private function getAllowedMessageLength(): int
    {
        return config('secrets.message_length.user');
    }

    public function attributes(): array
    {
        return [
            'expires_in' => 'expiry',
        ];
    }
}
