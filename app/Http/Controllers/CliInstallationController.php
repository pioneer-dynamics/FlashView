<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateCliInstallationRequest;
use App\Http\Resources\CliInstallationResource;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Jetstream\Jetstream;

class CliInstallationController extends Controller
{
    public function index(Request $request): Response
    {
        $tokens = $request->user()
            ->tokens()
            ->where('type', 'cli')
            ->latest()
            ->get();

        $installations = CliInstallationResource::collection($tokens)->resolve();

        return Inertia::render('CliInstallations/Index', [
            'installations' => $installations,
            'availablePermissions' => Jetstream::$permissions,
        ]);
    }

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
