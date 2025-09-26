<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Mail\SendPasswordResetCode;
use App\Models\User;
use App\Models\Patient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use App\Models\PasswordResetTokensPatient; // Asegúrate de importar el modelo correcto

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

    public function sendResetCodePatient(Request $request)
    {
        $validator = Validator::make($request->all(), ['email' => 'required|email|exists:patients,email']);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $patient = Patient::where('email', $request->email)->first();
        $code = random_int(100000, 999999);

        // Usar el modelo PasswordResetTokensPatient
        PasswordResetTokensPatient::where('email', $request->email)->delete();
        PasswordResetTokensPatient::create([
            'email' => $request->email,
            'token' => $code,
            'created_at' => now()
        ]);

        try {
            Mail::to($request->email)->send(new SendPasswordResetCode($code, $patient->name));
        } catch (\Exception $e) {
            return response()->json(['message' => 'No se pudo enviar el correo de recuperación.'], 500);
        }

        return response()->json(['message' => 'Se ha enviado un código de recuperación a tu correo.'], 200);
    }

    public function verifyCodePatient(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|exists:patients,email',
            'code' => 'required|numeric',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $record = PasswordResetTokensPatient::where('email', $request->email)
            ->where('token', $request->code)
            ->where('created_at', '>', now()->subMinutes(10))
            ->first();

        if (!$record) {
            return response()->json(['message' => 'El código es inválido o ha expirado.'], 400);
        }

        return response()->json(['message' => 'Código verificado correctamente.'], 200);
    }

    public function resetPasswordPatient(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|exists:patients,email',
            'code' => 'required|numeric',
            'password' => 'required|confirmed|min:8',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $record = PasswordResetTokensPatient::where('email', $request->email)
            ->where('token', $request->code)
            ->first();

        if (!$record) {
            return response()->json(['message' => 'Token de reseteo inválido.'], 400);
        }

        $patient = Patient::where('email', $request->email)->first();
        $patient->password = Hash::make($request->password);
        $patient->save();

        PasswordResetTokensPatient::where('email', $request->email)->delete();
        return response()->json(['message' => 'Tu contraseña ha sido actualizada con éxito.'], 200);
    }
}
