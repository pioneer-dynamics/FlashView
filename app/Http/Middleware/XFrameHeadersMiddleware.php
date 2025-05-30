<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class XFrameHeadersMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        /**
         * This middleware was created to prevent OWASP warnings, like:
         *
         * The X-Frame-Options header is not set in the HTTP response, meaning the page can potentially be loaded into
         * an attacker-controlled frame. This could lead to clickjacking, where an attacker adds an invisible layer on
         * top of the legitimate page to trick users into clicking on a malicious link or taking a harmful action.
         *
         * The X-Frame-Options allows three values: DENY, SAMEORIGIN and ALLOW-FROM. It is recommended to use DENY,
         * which prevents all domains from framing the page or SAMEORIGIN, which allows framing only by the same site.
         * DENY and SAMEORGIN are supported by all browsers. Using ALLOW-FROM is not recommended because not all browsers support it.
         *
         * For more information, access: https://cheatsheetseries.owasp.org/cheatsheets/Clickjacking_Defense_Cheat_Sheet.html
         */
        $response = $next($request);

        $response->headers->set('X-Frame-Options', 'SAMEORIGIN', false);

        return $response;
    }
}
