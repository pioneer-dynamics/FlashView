<?php

namespace App\Http\Requests;

use App\Models\Secret;
use App\Rules\MessageLength;
use App\Rules\ValidExpiry;
use App\Rules\ValidFileSize;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSecretRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        if ($this->hasFile('file') && ! $this->user()) {
            return false;
        }

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
        $isFileSecret = $this->hasFile('file');

        return [
            'message' => [Rule::requiredIf(! $isFileSecret), 'nullable', 'string', 'min:1', new MessageLength($this->getUserType(), $this->getAllowedMessageLength())],
            'file' => $this->user()
                ? ['nullable', 'file', new ValidFileSize($this->getUserType())]
                : ['prohibited'],
            'file_original_name' => ($isFileSecret && $this->user())
                ? ['required', 'string', 'max:2048']
                : ['prohibited'],
            'file_size' => ($isFileSecret && $this->user())
                ? ['required', 'integer', 'min:1']
                : ['prohibited'],
            'file_mime_type' => ($isFileSecret && $this->user())
                ? ['required', 'string', 'in:'.implode(',', config('secrets.file_upload.allowed_mime_types'))]
                : ['prohibited'],
            'expires_in' => ['required', 'numeric', new ValidExpiry($this->getUserType())],
            'email' => $this->user() ? ['nullable', 'email'] : ['prohibited'],
            'include_sender_identity' => ['boolean', 'nullable'],
        ];
    }

    /**
     * Identify the type of user submitting the request.
     */
    public function getUserType(): string
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
