<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

class StegoController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Secret/StegoPage');
    }
}
