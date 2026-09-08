<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

class CspMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        // En local on laisse passer sans CSP strict (Vite HMR, etc.)
        if (app()->isLocal()) {
            return $next($request);
        }

        $nonce = base64_encode(random_bytes(16));

        // Partage via View factory — requis par oliweb/statamic-csp-nonce
        view()->share('csp_nonce', $nonce);

        // Ajoute le nonce aux balises <script> générées par Vite
        Vite::useCspNonce($nonce);

        $response = $next($request);

        // Pas de header CSP sur les réponses binaires (ZIP, images…)
        if (! $response instanceof \Illuminate\Http\Response) {
            return $response;
        }

        $directives = [
            "default-src 'none'",
            "script-src 'self' 'nonce-{$nonce}'",
            "style-src 'self' 'nonce-{$nonce}'",
            "img-src 'self' data: blob:",
            "font-src 'self'",
            "connect-src 'self'",
            "frame-src 'self'",        // iframe /cap-frame (laravel-cap)
            "worker-src 'none'",       // workers uniquement dans l'iframe Cap
            "frame-ancestors 'none'",
            "base-uri 'self'",
            "form-action 'self'",
        ];

        $response->headers->set(
            'Content-Security-Policy',
            implode('; ', $directives)
        );

        return $response;
    }
}
