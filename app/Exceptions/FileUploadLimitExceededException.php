<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class FileUploadLimitExceededException extends Exception implements Responsable
{
    public function __construct(string $message = 'You have reached the maximum number of active file secrets.')
    {
        parent::__construct($message);
    }

    public function report(): bool
    {
        return false;
    }

    public function toResponse(Request $request): RedirectResponse
    {
        return back()->withErrors(['file' => $this->getMessage()]);
    }
}
