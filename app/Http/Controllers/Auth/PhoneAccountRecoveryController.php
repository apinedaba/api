<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\PhoneAccountRecoveryService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;

class PhoneAccountRecoveryController extends Controller
{
    public function __construct(private readonly PhoneAccountRecoveryService $recovery)
    {
    }

    public function requestCode(Request $request, string $accountType)
    {
        $request->validate(['phone' => ['required', 'string', 'max:30']]);
        $this->recovery->request($accountType, $request->string('phone')->toString());

        return response()->json(['message' => 'Si los datos coinciden, recibirás un código por SMS.']);
    }

    public function verifyCode(Request $request, string $accountType)
    {
        $request->validate(['phone' => ['required', 'string', 'max:30'], 'code' => ['required', 'digits:6']]);
        $token = $this->recovery->verify($accountType, $request->string('phone')->toString(), $request->string('code')->toString());

        if (! $token) {
            return response()->json(['message' => 'El código es inválido, expiró o alcanzó el límite de intentos.'], 422);
        }

        return response()->json(['recovery_token' => $token]);
    }

    public function complete(Request $request, string $accountType)
    {
        $request->validate([
            'recovery_token' => ['required', 'string'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        try {
            $completed = $this->recovery->complete($accountType, $request->string('recovery_token')->toString(), $request->string('email')->toString(), $request->string('password')->toString());
        } catch (\DomainException $exception) {
            return response()->json(['message' => $exception->getMessage(), 'errors' => ['email' => [$exception->getMessage()]]], 422);
        }

        if (! $completed) {
            return response()->json(['message' => 'La sesión de recuperación expiró. Solicita un código nuevo.'], 422);
        }

        return response()->json(['message' => 'Tu correo y contraseña se actualizaron. Inicia sesión con tus nuevos datos.']);
    }
}
