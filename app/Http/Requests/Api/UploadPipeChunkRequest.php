<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class UploadPipeChunkRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $maxPayloadLength = (int) ceil(config('pipe.max_chunk_size_bytes') * 4 / 3 + 100);

        return [
            'chunk_index' => ['required', 'integer', 'min:0'],
            'payload' => ['required', 'string', 'max:'.$maxPayloadLength],
        ];
    }
}
