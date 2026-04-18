<?php

namespace App\Http\Requests;

use App\Models\Secret;
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
        if ($this->user()) {
            return $this->user()->can('create', Secret::class);
        }

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
            'email' => $this->user() ? ['nullable', 'email'] : ['prohibited'],
            'include_sender_identity' => ['boolean', 'nullable'],
        ];
    }

    /**
     * Identify the type of user submitting the request.
     */
    private function getUserType(): string
    {
        if ($user = $this->user()) {
            return $user->subscribed() ? 'subscribed' : 'user';
        }

        return 'guest';
    }

    private function getAllowedMessageLength(): int
    {
        return $this->user()
            ? config('secrets.message_length.user')
            : config('secrets.message_length.guest');
    }

    public function attributes(): array
    {
        return [
            'expires_in' => 'expiry',
        ];
    }
}
