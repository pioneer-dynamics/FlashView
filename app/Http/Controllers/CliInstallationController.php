<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CliInstallationController extends Controller
{
    public function destroy(Request $request, int $tokenId): RedirectResponse
    {
        $request->user()
            ->tokens()
            ->where('type', 'cli')
            ->findOrFail($tokenId)
            ->delete();

        return back();
    }
}
