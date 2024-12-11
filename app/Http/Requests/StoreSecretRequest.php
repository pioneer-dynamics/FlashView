<?php

namespace App\Http\Requests;

use App\Rules\MessageLength;
use App\Rules\ValidExpiry;
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
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'message' => ['required', 'string', 'min:1', new MessageLength($this->getUserType(), $this->getAllowedMessageLength())],
            'expires_in' => ['required', 'numeric', new ValidExpiry($this->getUserType())],
            'email' => $this->user()?->id ? ['nullable', 'email'] : ['prohibited'],
        ];
    }

    /**
     * Identify the type of user submitting the request
     */
    private function getUserType(): string
    {
        if($user = request()->user()) {
            if($user->subscribed()) {
                return 'subscribed';
            }
            else {
                return 'user';
            }
        }
        else {
            return 'guest';
        }
    }

    private function getAllowedMessageLength()
    {
        if($this->user()) {
            return config('secrets.message_length.user');
        }
        else {
            return config('secrets.message_length.guest');
        }
    }

    public function attributes()
    {
        return [
            'expires_in' => 'expiry',
        ];
    }
}
