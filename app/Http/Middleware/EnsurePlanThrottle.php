<?php

namespace App\Http\Middleware;

use App\Services\FeatureRegistry;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class EnsurePlanThrottle
{
    public function __construct(private readonly FeatureRegistry $registry) {}

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        $plan = $user->resolvePlan();
        $throttleData = $plan?->features['throttling'] ?? null;

        if (! $throttleData) {
            return $next($request);
        }

        $feature = $this->registry->get('throttling');
        $config = $throttleData['config'] ?? [];
        $cacheKey = "plan_throttle:{$user->id}";

        $count = (int) Cache::get($cacheKey, 0);

        if (! $feature->withinLimit($count + 1, $config)) {
            $perMinute = $config['per_minute'] ?? 0;
            $message = "Too many requests. Your plan allows {$perMinute} requests per minute.";

            if ($request->expectsJson()) {
                return response()->json(['message' => $message], 429);
            }

            abort(429, $message);
        }

        if (! Cache::add($cacheKey, 1, 60)) {
            Cache::increment($cacheKey);
        }

        return $next($request);
    }
}
