<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureMinderAccess
{
    public function handle(Request $request, Closure $next)
    {
        $user = auth()->guard('user')->user();

        if ($user && $user->identity_verification_status === 'approved' && $user->activo) {
            return $next($request);
        }

        return response()->json([
            'message' => 'Acceso restringido. Tu perfil debe estar verificado para acceder a Comunidad Minder.',
        ], 403);
    }
}
