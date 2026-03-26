<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

class NotificationSettingsController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('NotificationSettings/Index');
    }
}
