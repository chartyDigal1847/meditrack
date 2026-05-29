<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class ModuleCspMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $response   = $next($request);
        $portalUrl  = config('app.portal_url', 'https://deoris.test');
        $moduleUrl  = config('app.url', 'https://meditrack.deoris.test');

        $csp = implode('; ', [
            "default-src 'self'",
            "script-src 'self' 'unsafe-inline' " . $portalUrl . " https://cdnjs.cloudflare.com https://fonts.googleapis.com",
            "script-src-elem 'self' 'unsafe-inline' " . $portalUrl . " https://cdnjs.cloudflare.com https://fonts.googleapis.com",
            "style-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com https://fonts.googleapis.com https://fonts.gstatic.com",
            "font-src 'self' https://cdnjs.cloudflare.com https://fonts.gstatic.com",
            "img-src 'self' data:",
            "connect-src 'self' " . $moduleUrl . " " . $portalUrl,
            "frame-ancestors " . $portalUrl,
            "frame-src 'self'",
            "object-src 'none'",
        ]);

        $response->headers->set('Content-Security-Policy', $csp);

        return $response;
    }
}
