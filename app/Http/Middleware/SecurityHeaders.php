<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

final class SecurityHeaders
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Content-Security-Policy', "default-src 'self'; style-src 'self'; img-src 'self' data:; frame-ancestors 'self'; base-uri 'self'; form-action 'self'");
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
        return $response;
    }
}
