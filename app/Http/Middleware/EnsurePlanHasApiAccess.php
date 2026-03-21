<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePlanHasApiAccess
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->subscribed()) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'API access requires an active subscription with API support.',
                ], 403);
            }

            abort(403, 'API access requires an active subscription with API support.');
        }

        if (! $user->hasApiAccess()) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Your current plan does not include API access. Please upgrade to a plan with API support.',
                ], 403);
            }

            abort(403, 'Your current plan does not include API access.');
        }

        return $next($request);
    }
}
