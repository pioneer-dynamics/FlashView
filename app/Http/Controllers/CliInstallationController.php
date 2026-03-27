<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateCliInstallationRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CliInstallationController extends Controller
{
    public function update(UpdateCliInstallationRequest $request, int $tokenId): RedirectResponse
    {
        $request->user()
            ->tokens()
            ->where('type', 'cli')
            ->findOrFail($tokenId)
            ->update(['name' => $request->validated('name')]);

        return back();
    }

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
