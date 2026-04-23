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
            ->whereIn('type', ['cli', 'mobile'])
            ->findOrFail($tokenId)
            ->delete();

        return back();
    }
}
