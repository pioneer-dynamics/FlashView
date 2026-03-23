<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateNotificationPreferencesRequest;
use Illuminate\Http\RedirectResponse;

class NotificationPreferencesController extends Controller
{
    public function update(UpdateNotificationPreferencesRequest $request): RedirectResponse
    {
        $request->user()->update($request->validated());

        return back();
    }
}
