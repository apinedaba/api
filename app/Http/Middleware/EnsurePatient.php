<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsurePatient
{
    public function handle(Request $request, Closure $next)
    {
        if (auth()->guard('patient')->check()) {
            return $next($request);
        }

        return response()->json(['message' => 'No autorizado.'], 403);
    }
}