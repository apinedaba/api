<?php

namespace App\Http\Controllers\Auth;

use App\Models\User;
use App\Models\Patient;
use App\Notifications\NuevoPsicologoRegistrado;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use OpenApi\Annotations as OA;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Auth\Events\Registered;
use App\Notifications\NuevoPacienteBienvenida;
use App\Models\Subscription;
use Carbon\Carbon;

class RegisterController extends Controller
{

    private $registerValidationRules = [
        'name' => 'required',
        'email' => 'required|email|unique:users,email',
        'password' => 'required'
    ];

    public function registerUser(Request $request)
    {
        $validateUser = Validator::make($request->all(), $this->registerValidationRules);

        if ($validateUser->fails()) {
            return response()->json([
                'message' => 'Ha ocurrido un error de validación',
                'errors' => $validateUser->errors()
            ], 400);
        }

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'contacto' => $request->contacto,
            'password' => Hash::make($request->password),
            'verification_code' => str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT),
            'code_expires_at' => now()->addMinutes(10),
        ]);

        // ✅ TRIAL DESACTIVADO: Usuario debe agregar tarjeta para acceder
        // 📝 Para reactivar trial automático, descomenta las líneas siguientes:
        /*
        Subscription::create([
            'user_id' => $user->id,
            'stripe_status' => 'trial',
            'trial_ends_at' => Carbon::now()->addDays(15),
        ]);
        */

        // Crear suscripción sin trial (requiere pago desde el inicio)
        Subscription::create([
            'user_id' => $user->id,
            'stripe_status' => 'inactive',
            'trial_ends_at' => null,
        ]);

        if ($user) {
            try {
                //code...
                $user->notify(new NuevoPsicologoRegistrado($user, true));
                event(new Registered($user));
            } catch (\Throwable $th) {
                Log::error($th->getMessage());
                //throw $th;
            }
        }
        return response()->json([
            'rasson' => "Perfecto, te registraste con exito",
            'message' => "¡Te haz registrado!",
            'type' => "success",
        ], 200);
    }
    /**
     * Verifica el código de registro.
     */
    public function verifyCode(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:users,email',
            'code' => 'required|string|digits:6',
        ]);

        $user = User::where('email', $request->email)->first();

        if ($user->hasVerifiedEmail()) {
            return response()->json(['message' => 'El correo ya ha sido verificado.'], 400);
        }

        if ($user->verification_code !== $request->code || now()->isAfter($user->code_expires_at)) {
            return response()->json(['message' => 'Código inválido o expirado.'], 422);
        }
        $user->markEmailAsVerified();
        $user->forceFill(['verification_code' => null, 'code_expires_at' => null])->save();

        return response()->json([
            'message' => '¡Correo verificado con éxito!',
            'type' => 'success',
            'token' => $user->createToken("user_token")->plainTextToken
        ]);
    }

    /**
     * Reenvía un nuevo código de verificación.
     */
    public function resendCode(Request $request)
    {
        $request->validate(['email' => 'required|email|exists:users,email']);
        $user = User::where('email', $request->email)->first();

        if ($user->hasVerifiedEmail()) {
            return response()->json(['message' => 'El correo ya ha sido verificado.'], 400);
        }

        $user->forceFill([
            'verification_code' => str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT),
            'code_expires_at' => now()->addMinutes(10),
        ])->save();

        $user->notify(new NuevoPsicologoRegistrado($user, false));

        return response()->json(['message' => 'Se ha enviado un nuevo código de verificación.']);
    }

    private $registerValidationRulesPatient = [
        'name' => 'required',
        'email' => 'required|email|unique:patients,email',
        [
            'email.unique' => 'Este correo ya está registrado. Si tu minder creoo tu cuenta revisa tu correo para obtener la contraseña.',
            'email.required' => 'El correo electrónico es obligatorio.',
            'name.required' => 'El nombre es obligatorio.',
            'password.required' => 'La contraseña es obligatoria.'
        ],
        'password' => 'required'
    ];
    public function registerPatient(Request $request)
    {
        $validateUser = Validator::make($request->all(), $this->registerValidationRulesPatient);

        if ($validateUser->fails()) {
            return response()->json([
                'message' => 'Ha ocurrido un error de validación',
                'errors' => $validateUser->errors()
            ], 400);
        }

        $patient = Patient::create([
            'name' => $request->name,
            'email' => $request->email,
            'contacto' => $request->contacto,
            'password' => Hash::make($request->password)
        ]);
        try {
            $patient->notify(new NuevoPacienteBienvenida($patient));
        } catch (\Throwable $th) {
            Log::error("Error al notificar nuevo paciente auto-registrado: " . $th->getMessage());
        }
        return response()->json([
            'message' => 'El usuario se ha creado',
            'token' => $patient->createToken("patient_token")->plainTextToken
        ], 200);
    }
    public function checkPatientEmail(Request $request)
    {
        // 1. Validamos que la petición contenga un email válido.
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        // 2. Buscamos en la tabla de 'patients' si el email existe.
        // El método ->exists() es muy eficiente porque devuelve true/false y detiene la búsqueda al encontrar el primer resultado.
        $patientExists = Patient::where('email', $request->email)->exists();

        // 3. Devolvemos la respuesta que el frontend espera.
        return response()->json([
            'exists' => $patientExists,
        ]);
    }
}
