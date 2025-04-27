<?php

namespace App\Http\Requests;

use App\Models\Secret;
use Illuminate\Foundation\Http\FormRequest;
use Vinkla\Hashids\Facades\Hashids;

class BurnSecretRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('delete', $this->getSecretRecordWithoutBurning($this->secret));
    }

    private function getSecretRecordWithoutBurning($secret)
    {
        return Secret::withoutEvents(fn () => Secret::withoutGlobalScopes()->where('user_id', $this->user()->id)->where('id', $this->getId($secret))->first());
    }

    private function getId($secret)
    {
        return Hashids::connection('Secret')->decode($secret)[0];
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            //
        ];
    }
}
