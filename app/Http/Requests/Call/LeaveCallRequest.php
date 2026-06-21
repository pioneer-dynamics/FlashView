<?php

namespace App\Http\Requests\Call;

use Illuminate\Foundation\Http\FormRequest;

class LeaveCallRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'participant_id' => ['required', 'uuid'],
        ];
    }
}
