<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeadersMiddleware
{
    /**
     * Add security headers to prevent clickjacking.
     *
     * X-Frame-Options: DENY — prevents all framing (legacy browsers).
     * Content-Security-Policy: frame-ancestors 'none' — prevents all framing (modern browsers).
     *
     * If a broader CSP policy is introduced later, the frame-ancestors directive
     * should be consolidated into it rather than set as a separate header.
     *
     * @see https://cheatsheetseries.owasp.org/cheatsheets/Clickjacking_Defense_Cheat_Sheet.html
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('Content-Security-Policy', "frame-ancestors 'none'");

        return $response;
    }
}
