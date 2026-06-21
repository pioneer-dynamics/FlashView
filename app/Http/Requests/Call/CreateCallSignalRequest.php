<?php

namespace App\Http\Requests\Call;

use Illuminate\Foundation\Http\FormRequest;

class CreateCallSignalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'from_participant_id' => ['required', 'uuid'],
            'to_participant_id' => ['required', 'uuid', 'different:from_participant_id'],
            'type' => ['required', 'string', 'in:offer,answer,ice-candidate,key-exchange'],
            'payload' => ['required', 'array'],
        ];
    }
}
