<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CorsMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        // Bloquear cualquier intento de acceso HTTP a archivos ocultos o variables de entorno (.env, .git, .sqlite)
        $path = rawurldecode($request->getPathInfo());
        if (preg_match('#(^|/)\.(env|git|htaccess|sqlite)|\.sqlite$#i', $path)) {
            abort(404);
        }

        $origin = $request->header('Origin');
        $configuredOrigin = (string) config('services.frontend.origin', 'http://localhost:3000');
        $isAllowedOrigin = ! is_string($origin)
            || $origin === ''
            || $origin === 'null'
            || $origin === $request->getSchemeAndHttpHost()
            || $origin === $configuredOrigin
            || (bool) preg_match('#^https?://(localhost|127\.0\.0\.1|\[::1\]|192\.168\.\d+\.\d+|10\.\d+\.\d+\.\d+|172\.(1[6-9]|2\d|3[0-1])\.\d+\.\d+)(:\d+)?$#i', $origin);

        if ($request->is('api/*') && ! $isAllowedOrigin) {
            return response()->json([
                'message' => 'Origen CORS no autorizado.',
            ], 403);
        }

        $allowedOriginHeader = is_string($origin) && $origin !== '' && $origin !== 'null' && $isAllowedOrigin
            ? $origin
            : $configuredOrigin;

        if ($request->isMethod('OPTIONS')) {
            return response('', 204)
                ->header('Access-Control-Allow-Origin', $allowedOriginHeader)
                ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS')
                ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, Accept, X-Requested-With')
                ->header('Vary', 'Origin');
        }

        $response = $next($request);
        $response->headers->set('Access-Control-Allow-Origin', $allowedOriginHeader);
        $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS');
        $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, Authorization, Accept, X-Requested-With');
        $response->headers->set('Vary', 'Origin');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        return $response;
    }
}
