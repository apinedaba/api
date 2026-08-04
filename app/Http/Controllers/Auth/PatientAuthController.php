<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Patient;
use App\Models\GuardianAccount;
use App\Http\Controllers\GuardianAccountController;
use App\Support\PatientIdentity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class PatientAuthController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'identifier' => 'required|string',
            'password' => 'required',
            'account_type' => 'nullable|in:patient,guardian',
        ]);

        ['email' => $email, 'phone' => $phone] = PatientIdentity::resolveIdentifier($request->identifier);

        $requestedAccountType = $request->input('account_type', 'patient');
        $patient = $requestedAccountType === 'guardian' ? null : PatientIdentity::findByEmailOrPhone($email, $phone);

        if ($patient && Hash::check($request->password, $patient->password)) {
            $token = $patient->createToken('patient_token')->plainTextToken;
            if ($request->hasSession()) {
                Auth::guard('patient_web')->login($patient, true);
                $request->session()->regenerate();
            }
            return response()->json(['token' => $token, 'user' => $patient, 'account_type' => 'patient'], 200);
        }

        $guardian = $requestedAccountType === 'patient' || ! $email ? null : GuardianAccount::whereRaw('LOWER(email) = ?', [$email])->first();
        if ($guardian && Hash::check($request->password, $guardian->password)) {
            return response()->json([
                'token' => $guardian->createToken('guardian_token')->plainTextToken,
                'user' => app(GuardianAccountController::class)->sessionPayload($guardian),
                'account_type' => 'guardian',
            ]);
        }

        if (!$patient && !$guardian) {
            return response()->json([
                'rasson' => "Crea una cuenta para poder iniciar sesión",
                'message' => "¡Oh, No! aun no estas registrado.",
                'type' => "error",
            ], 404);
        }
        if (($patient || $guardian)) {
            return response()->json([
                'rasson' => "Los datos ingresados no son correctos",
                'message' => "¡Oh, No! algo esta mal.",
                'type' => "error",
                'check' => false,
            ], 400);
        }

        abort(401);
    }

    public function logout(Request $request)
    {
        $request->user()?->tokens()?->delete();
        if ($request->hasSession()) {
            Auth::guard('patient_web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return response()->json(['message' => 'Logged out'], 200);
    }
}
