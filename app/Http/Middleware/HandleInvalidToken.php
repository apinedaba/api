<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Auth\AuthenticationException;

class HandleInvalidToken
{
    public function handle($request, Closure $next)
    {
        try {
            return $next($request);
        } catch (AuthenticationException $e) {
            return response()->json(['message' => 'Token no válido o no proporcionado.'], 401);
        }
    }
}