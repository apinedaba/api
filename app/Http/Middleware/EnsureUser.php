<?php

namespace App\Http\Middleware;

use Closure;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EnsureUser
{
    public function handle(Request $request, Closure $next)
    {
        if (Auth::guard('user_web')->check()) {
            $user = Auth::guard('user_web')->user();
            $request->setUserResolver(fn () => $user);
            return $next($request);
        }

        if (auth()->guard('user')->check()) {
            $user = auth()->guard('user')->user();
            $request->setUserResolver(fn () => $user);
            return $next($request);
        }

        if ($request->user() instanceof User) {
            return $next($request);
        }

        return response()->json(['message' => 'No autorizado.'], 403);
    }
}
