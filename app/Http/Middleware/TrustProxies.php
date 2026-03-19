<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Monicahq\Cloudflare\Http\Middleware\TrustProxies as Middleware;

class TrustProxies extends Middleware
{
    protected $headers =
        Request::HEADER_X_FORWARDED_FOR |
        Request::HEADER_X_FORWARDED_HOST |
        Request::HEADER_X_FORWARDED_PORT |
        Request::HEADER_X_FORWARDED_PROTO;

    public function __construct()
    {
        // Support additional trusted proxies (e.g., AWS ALB) via config.
        // On Laravel Cloud, set TRUSTED_PROXIES=* to trust all proxies, or use specific CIDR ranges.
        if ($additional = config('laravelcloudflare.trusted_proxies')) {
            $this->proxies = $additional === '*'
                ? '*'
                : array_map('trim', explode(',', $additional));
        }
    }

    /**
     * Handle an incoming request.
     *
     * Note: CF-Connecting-IP is trusted unconditionally when replace_ip=true.
     * Origin access must be restricted to Cloudflare to prevent IP spoofing.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->headers->has('x-forwarded-proto')) {
            $header = $request->headers->get('x-forwarded-proto');
            $request->headers->set('x-forwarded-proto', strtok($header, ','));
            $request->server->set('HTTPS', 'on');
        }

        return parent::handle($request, $next);
    }
}
