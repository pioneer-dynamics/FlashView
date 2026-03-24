<?php

namespace App\Http\Requests\Api;

use App\Models\Secret;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Vinkla\Hashids\Facades\Hashids;

class ShowSecretMetadataRequest extends FormRequest
{
    private ?Secret $secretRecord = null;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $secret = $this->getSecretRecord();

        if (! $secret) {
            return false;
        }

        return $this->user()->can('view', $secret);
    }

    /**
     * Resolve the secret record without triggering retrieval events.
     */
    public function getSecretRecord(): ?Secret
    {
        if ($this->secretRecord !== null) {
            return $this->secretRecord;
        }

        $decoded = Hashids::connection('Secret')->decode($this->route('secret'));
        $id = $decoded[0] ?? null;

        if (! $id) {
            return null;
        }

        return $this->secretRecord = Secret::withoutEvents(fn () => $this->user()->secrets()
            ->withoutGlobalScopes()
            ->where('id', $id)
            ->first());
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
