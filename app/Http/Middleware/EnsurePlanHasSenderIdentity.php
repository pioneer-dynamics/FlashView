<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePlanHasSenderIdentity
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
                    'message' => 'Sender Identity requires an active Prime subscription.',
                ], 403);
            }

            abort(403, 'Sender Identity requires an active Prime subscription.');
        }

        if (! $user->planSupportsSenderIdentity()) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Your current plan does not include Sender Identity. Please upgrade to Prime.',
                ], 403);
            }

            abort(403, 'Your current plan does not include Sender Identity. Please upgrade to Prime.');
        }

        return $next($request);
    }
}
