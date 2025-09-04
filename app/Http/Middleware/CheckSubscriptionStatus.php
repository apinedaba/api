<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckSubscriptionStatus
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user && $user->subscription && $user->subscription->stripe_status === 'active') {
            return $next($request);
        }
        return response()->json(['error' => 'Acceso denegado. Se requiere una suscripción activa.'], 403);
    }
}
