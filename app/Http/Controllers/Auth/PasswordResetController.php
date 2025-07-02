<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Mail\SendPasswordResetCode;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;

class PasswordResetController extends Controller
{

    public function sendResetCode(Request $request)
    {
        $validator = Validator::make($request->all(), ['email' => 'required|email|exists:users,email']);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = User::where('email', $request->email)->first();
        $code = random_int(100000, 999999);

        DB::table('password_reset_tokens')->where('email', $request->email)->delete();
        DB::table('password_reset_tokens')->insert([
            'email' => $request->email,
            'token' => $code,
            'created_at' => now()
        ]);

        try {
            Mail::to($request->email)->send(new SendPasswordResetCode($code, $user->name));
        } catch (\Exception $e) {
            return response()->json(['message' => 'No se pudo enviar el correo de recuperación.'], 500);
        }

        return response()->json(['message' => 'Se ha enviado un código de recuperación a tu correo.'], 200);
    }

    public function verifyCode(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|exists:users,email',
            'code' => 'required|numeric',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $record = DB::table('password_reset_tokens')
            ->where('email', $request->email)
            ->where('token', $request->code)
            ->where('created_at', '>', now()->subMinutes(10)) // Validez de 10 minutos
            ->first();

        if (!$record) {
            return response()->json(['message' => 'El código es inválido o ha expirado.'], 400);
        }

        return response()->json(['message' => 'Código verificado correctamente.'], 200);
    }
    public function resetPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|exists:users,email',
            'code' => 'required|numeric',
            'password' => 'required|confirmed|min:8',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $record = DB::table('password_reset_tokens')
            ->where('email', $request->email)
            ->where('token', $request->code)
            ->first();

        if (!$record) {
            return response()->json(['message' => 'Token de reseteo inválido.'], 400);
        }

        $user = User::where('email', $request->email)->first();
        $user->password = Hash::make($request->password);
        $user->save();

        DB::table('password_reset_tokens')->where('email', $request->email)->delete();
        return response()->json(['message' => 'Tu contraseña ha sido actualizada con éxito.'], 200);
    }
}
