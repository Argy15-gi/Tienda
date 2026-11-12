<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureApiToken
{
    public function handle(Request $request, Closure $next): Response
    {
        Auth::forgetGuards();

        // Seguridad: solo se acepta el token en el header Authorization: Bearer (nunca en la URL)
        $plainToken = (string) $request->bearerToken();

        // Rechazo temprano sin tocar la BD si el formato del token no es de 60 caracteres alfanuméricos
        if (strlen($plainToken) !== 60 || ! ctype_alnum($plainToken)) {
            return response()->json([
                'message' => 'No autenticado. Debes iniciar sesión para acceder a esta ruta.',
            ], 401);
        }

        $hashed = hash('sha256', $plainToken);
        $user = User::query()->where('api_token', $hashed)->first();

        if (! $user) {
            return response()->json([
                'message' => 'Tu sesión expiró o el token no es válido. Inicia sesión nuevamente.',
            ], 401);
        }

        Auth::setUser($user);
        $request->setUserResolver(fn () => $user);

        return $next($request);
    }
}
