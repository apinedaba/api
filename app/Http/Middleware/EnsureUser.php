<?php

namespace App\Http\Middleware;

use Closure;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\PersonalAccessToken;

class EnsureUser
{
    public function handle(Request $request, Closure $next)
    {
        if ($request->bearerToken()) {
            $accessToken = PersonalAccessToken::findToken($request->bearerToken());
            $user = $accessToken?->tokenable;

            if (! $user instanceof User) {
                return response()->json(['message' => 'No autorizado.'], 401);
            }

            $accessToken->forceFill(['last_used_at' => now()])->save();
            $request->setUserResolver(fn() => $user);

            return $next($request);
        }

        if (Auth::guard('user_web')->check()) {
            $user = Auth::guard('user_web')->user();
            $request->setUserResolver(fn() => $user);
            return $next($request);
        }

        if (auth()->guard('user')->check()) {
            $user = auth()->guard('user')->user();
            $request->setUserResolver(fn() => $user);
            return $next($request);
        }

        if ($request->user() instanceof User) {
            return $next($request);
        }

        return response()->json(['message' => 'No autorizado.'], 403);
    }
}
