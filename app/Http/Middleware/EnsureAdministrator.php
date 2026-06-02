<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureAdministrator
{
    /**
     * Middleware para verificar que el usuario autenticado es administrador.
     * 
     * Verifica autenticación en el guard 'web' (administrators model)
     */
    public function handle(Request $request, Closure $next)
    {
        // Verificar que el usuario esté autenticado en el guard 'web' (administrador)
        if (!auth('web')->check()) {
            return response()->json(['message' => 'No autenticado.'], 401);
        }

        // Verificar que sea un administrador
        // Puedes agregar lógica aquí si tienes un campo 'role' o 'is_admin'
        // Por ahora, solo verifica que esté en el guard web (Administrator model)

        return $next($request);
    }
}
