<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureVendedor
{
    public function handle(Request $request, Closure $next)
    {
        if (auth()->guard('vendedor_web')->check()) {
            return $next($request);
        }

        return redirect()->route('vendedor.login');
    }
}
