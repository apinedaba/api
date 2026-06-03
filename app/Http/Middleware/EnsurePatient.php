<?php

namespace App\Http\Middleware;

use Closure;
use App\Models\Patient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EnsurePatient
{
    public function handle(Request $request, Closure $next)
    {
        if (Auth::guard('patient_web')->check()) {
            $patient = Auth::guard('patient_web')->user();
            $request->setUserResolver(fn () => $patient);
            return $next($request);
        }

        if (auth()->guard('patient')->check()) {
            $patient = auth()->guard('patient')->user();
            $request->setUserResolver(fn () => $patient);
            return $next($request);
        }

        if ($request->user() instanceof Patient) {
            return $next($request);
        }

        return response()->json(['message' => 'No autorizado.'], 403);
    }
}
