<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Monicahq\Cloudflare\Http\Middleware\TrustProxies as Middleware;

class TrustProxies extends Middleware
{
    protected $proxies = '*';

    protected $headers = 
        Request::HEADER_X_FORWARDED_FOR |
        Request::HEADER_X_FORWARDED_HOST |
        Request::HEADER_X_FORWARDED_PORT |
        Request::HEADER_X_FORWARDED_PROTO;

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->headers->has('x-forwarded-proto')) {
            $header = $request->headers->get('x-forwarded-proto');
            // Ensure it only has a single value (if there are multiple, take the first one)
            $request->headers->set('x-forwarded-proto', strtok($header, ','));
            $request->server->set('HTTPS', 'on');
        }
    
        return parent::handle($request, $next);
    }
}
