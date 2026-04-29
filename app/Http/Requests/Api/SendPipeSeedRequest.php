<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class SendPipeSeedRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'receiver_device_id' => ['required', 'string', 'size:7'],
            'encrypted_seed' => ['required', 'string', 'max:4096'],
        ];
    }
}
