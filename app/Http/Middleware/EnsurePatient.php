<?php

namespace App\Http\Middleware;

use Closure;
use App\Models\Patient;
use App\Models\GuardianAccount;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EnsurePatient
{
    public function handle(Request $request, Closure $next)
    {
        $authenticated = $request->user();
        if ($authenticated instanceof GuardianAccount) {
            if ($request->is('api/patient/logout') || $request->is('patient/logout')) return $next($request);
            $patientId = $request->header('X-Represented-Patient-Id');
            $relation = $authenticated->patients()->wherePivot('status', 'active')
                ->when($patientId, fn ($query) => $query->where('patients.id', $patientId))->first();
            if (! $relation) return response()->json(['message' => 'Selecciona un familiar vinculado a tu cuenta.', 'requires_patient_selection' => true], 409);
            $request->attributes->set('guardian_account', $authenticated);
            $request->attributes->set('guardian_patient_permissions', $relation->pivot);
            $request->setUserResolver(fn () => $relation);
            return $next($request);
        }

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
