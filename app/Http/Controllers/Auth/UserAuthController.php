<?php

namespace App\Http\Controllers\Auth;

use App\Events\NewNotification;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class UserAuthController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json([
                'rasson' => "Crea una cuenta para poder iniciar sesión",
                'message' => "¡Oh, No! aun no estas registrado.",
                'type' => "error",
            ], 404);
        }

        if (!Hash::check($request->password, $user->password)) {
            return response()->json([
                'rasson' => "Los datos ingresados no son correctos",
                'message' => "¡Oh, No! credenciales incorrectas.",
                'type' => "error",
            ], 401);
        }
        event(new NewNotification("user.{$user->id}", "Login correcto"));
        $token = $user->createToken('user_token')->plainTextToken;
        if ($request->hasSession()) {
            Auth::guard('user_web')->login($user, true);
            $request->session()->regenerate();
        }

        return response()->json([
            'token' => $token,
            'user' => $user,
        ], 200);
    }

    public function logout(Request $request)
    {
        $request->user()?->tokens()?->delete();
        if ($request->hasSession()) {
            Auth::guard('user_web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return response()->json(['message' => 'Logged out'], 200);
    }

    public function resendVerifyEmail(Request $request)
    {
        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json(['message' => 'Usuario no encontrado'], 404);
        }

        if ($user->hasVerifiedEmail()) {
            return response()->json(['message' => 'El correo ya está verificado'], 200);
        }

        $user->sendEmailVerificationNotification();

        return response()->json(['message' => 'Correo de verificación reenviado'], 200);
    }
}
