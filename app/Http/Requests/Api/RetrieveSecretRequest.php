<?php

namespace App\Http\Requests\Api;

use App\Models\Secret;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class RetrieveSecretRequest extends FormRequest
{
    private ?Secret $secretRecord = null;

    /**
     * Determine if the user is authorized to make this request.
     *
     * Any authenticated API user with the secrets:list ability can
     * retrieve a secret — the passphrase is the access control,
     * not ownership.
     */
    public function authorize(): bool
    {
        return $this->user()->tokenCan('secrets:list');
    }

    /**
     * Resolve the secret record without triggering the retrieved event.
     *
     * The secret must be active (not expired, message not null).
     * Loading with withoutEvents prevents premature consumption
     * so the message is only consumed in the controller after
     * authorization succeeds.
     */
    public function getSecretRecord(): ?Secret
    {
        if ($this->secretRecord !== null) {
            return $this->secretRecord;
        }

        $id = Secret::decodeHashId($this->route('secret'));

        if (! $id) {
            return null;
        }

        return $this->secretRecord = Secret::withoutEvents(
            fn () => Secret::query()
                ->where('id', $id)
                ->whereNotNull('message')
                ->where('expires_at', '>=', now())
                ->first()
        );
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [];
    }
}
