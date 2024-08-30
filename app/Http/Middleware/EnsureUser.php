<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureUser
{
    public function handle(Request $request, Closure $next)
    {
        if (auth()->guard('user')->check()) {
            return $next($request);
        }

        return response()->json(['message' => 'No autorizado.'], 403);
    }
}
