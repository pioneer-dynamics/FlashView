<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateCliInstallationRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Jetstream\Jetstream;

class CliInstallationController extends Controller
{
    public function index(Request $request): Response
    {
        $installations = $request->user()
            ->tokens()
            ->where('type', 'cli')
            ->latest()
            ->get()
            ->map(fn ($token) => [
                'id' => $token->id,
                'name' => $token->name,
                'abilities' => $token->abilities,
                'last_used_at' => $token->last_used_at,
                'last_used_ago' => $token->last_used_at?->diffForHumans(),
                'created_at' => $token->created_at,
                'created_ago' => $token->created_at->diffForHumans(),
            ]);

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
