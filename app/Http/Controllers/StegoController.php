<?php

namespace App\Http\Controllers;

use App\Http\Requests\StegoPageRequest;
use Inertia\Inertia;
use Inertia\Response;

class StegoController extends Controller
{
    public function index(StegoPageRequest $request): Response
    {
        return Inertia::render('Secret/StegoPage', [
            'canUseStego' => $request->canEmbed(),
        ]);
    }
}
