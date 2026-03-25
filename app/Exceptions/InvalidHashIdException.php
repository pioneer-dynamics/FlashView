<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class InvalidHashIdException extends Exception
{
    public function __construct(string $hashId = '')
    {
        parent::__construct("Invalid hash ID: {$hashId}");
    }

    public function render(Request $request): Response|JsonResponse
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => 'Not Found'], 404);
        }

        abort(404);
    }
}
