<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Patient;
use App\Support\PatientIdentity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class PatientAuthController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'identifier' => 'required|string',
            'password' => 'required',
        ]);

        ['email' => $email, 'phone' => $phone] = PatientIdentity::resolveIdentifier($request->identifier);

        $patient = PatientIdentity::findByEmailOrPhone($email, $phone);

        if (!$patient) {
            return response()->json([
                'rasson' => "Crea una cuenta para poder iniciar sesión",
                'message' => "¡Oh, No! aun no estas registrado.",
                'type' => "error",
            ], 404);
        }
        if ($patient && !Hash::check($request->password, $patient->password)) {
            return response()->json([
                'rasson' => "Los datos ingresados no son correctos",
                'message' => "¡Oh, No! algo esta mal.",
                'type' => "error",
                'check' => Hash::check($request->password, $patient->password)
            ], 400);
        }

        $token = $patient->createToken('patient_token')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => $patient,
        ], 200);
    }

    public function logout(Request $request)
    {
        $request->user()->tokens()->delete();

        return response()->json(['message' => 'Logged out'], 200);
    }
}
