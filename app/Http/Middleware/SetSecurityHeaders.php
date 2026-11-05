<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

class SetSecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $nonce = Vite::useCspNonce();

        $response = $next($request);

        $hot = Vite::isRunningHot() ? trim((string) file_get_contents(Vite::hotFile())) : null;
        $hotSources = $hot === null ? '' : ' '.$hot.' '.preg_replace('~^http~', 'ws', $hot);

        $response->headers->set('Content-Security-Policy', implode('; ', [
            "default-src 'self'",
            "script-src 'self' 'nonce-{$nonce}'".($hot === null ? '' : ' '.$hot),
            "style-src 'self' 'unsafe-inline'".($hot === null ? '' : ' '.$hot),
            "img-src 'self' data: blob:",
            "font-src 'self' data:".($hot === null ? '' : ' '.$hot),
            "connect-src 'self'".$hotSources,
            "frame-src 'none'",
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self'",
            "frame-ancestors 'none'",
        ]));
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        // Same-origin referrers are required by Sanctum; tokens in URLs never leave the site.
        $response->headers->set('Referrer-Policy', 'same-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=()');
        $response->headers->set('Cross-Origin-Resource-Policy', 'same-origin');

        if ($request->isSecure()) {
            $response->headers->set('Cross-Origin-Opener-Policy', 'same-origin');
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        if ($request->is('api/*') || $request->expectsJson()) {
            $response->headers->set('Cache-Control', 'no-store, private');
        }

        return $response;
    }
}
