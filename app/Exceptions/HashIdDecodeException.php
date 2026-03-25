<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class HashIdDecodeException extends Exception
{
    public function __construct(string $hashId = '', ?\Throwable $previous = null)
    {
        parent::__construct("Failed to decode hash ID: {$hashId}", 0, $previous);
    }

    public function render(Request $request): Response|JsonResponse
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => 'Not Found'], 404);
        }

        abort(404);
    }
}
