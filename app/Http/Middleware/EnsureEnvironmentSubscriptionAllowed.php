<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureEnvironmentSubscriptionAllowed
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('access.enabled')) {
            return $next($request);
        }

        $user = $request->user();

        if (! $user) {
            abort(403, 'Subscription upgrades are restricted on this environment.');
        }

        $allowedEmails = config('access.allowed_emails', []);

        if (! empty($allowedEmails) && in_array($user->email, $allowedEmails)) {
            return $next($request);
        }

        abort(403, 'Subscription upgrades are restricted on this environment. Contact the team to request access.');
    }
}
