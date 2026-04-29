<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class CreatePipeSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'session_id' => ['required', 'string', 'regex:/^[0-9a-f]{32,40}$/', 'unique:pipe_sessions,session_id'],
            'transfer_mode' => ['required', 'string', 'in:relay,p2p'],
        ];
    }
}
