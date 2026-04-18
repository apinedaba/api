<?php

namespace App\Http\Controllers\Auth;

use App\Models\Patient;
use App\Models\Vendedor;
use App\Models\Subscription;
use App\Models\User;
use App\Notifications\NuevoPacienteBienvenida;
use App\Notifications\NuevoPsicologoRegistrado;
use App\Support\PatientIdentity;
use Carbon\Carbon;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use OpenApi\Annotations as OA;
use App\Http\Controllers\Controller;
use App\Services\SellerCommissionService;

class RegisterController extends Controller
{
    private $registerValidationRules = [
        'name' => 'required',
        'email' => 'required|email|unique:users,email',
        'password' => 'required'
    ];

    public function registerUser(Request $request, SellerCommissionService $sellerCommissionService)
    {
        $validateUser = Validator::make($request->all(), $this->registerValidationRules);

        if ($validateUser->fails()) {
            return response()->json([
                'message' => 'Ha ocurrido un error de validacion',
                'errors' => $validateUser->errors()
            ], 400);
        }

        $sellerCode = $request->input('vendedor_qr_token')
            ?: $request->input('referral_code')
            ?: $request->input('v');
        $vendedor = $sellerCode
            ? Vendedor::where('qr_token', $sellerCode)->where('status', 'active')->first()
            : null;

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'contacto' => $request->contacto,
            'password' => Hash::make($request->password),
            'verification_code' => str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT),
            'code_expires_at' => now()->addMinutes(10),
        ]);

        if ($vendedor) {
            Subscription::firstOrCreate(
                ['user_id' => $user->id],
                [
                    'stripe_id' => null,
                    'stripe_plan' => null,
                    'stripe_status' => 'init',
                    'trial_ends_at' => null,
                    'ends_at' => null,
                ]
            );

            $sellerCommissionService->registerReferral($vendedor, $user, $sellerCode);
        }

        if ($user) {
            try {
                $user->notify(new NuevoPsicologoRegistrado($user, true));
                // event(new Registered($user));
            } catch (\Throwable $th) {
                Log::error($th->getMessage());
            }
        }
        return response()->json([
            'rasson' => 'Perfecto, te registraste con exito',
            'message' => 'Te has registrado',
            'type' => 'success',
        ], 200);
    }

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
            return response()->json(['message' => 'Codigo invalido o expirado.'], 422);
        }
        $user->markEmailAsVerified();
        $user->forceFill(['verification_code' => null, 'code_expires_at' => null])->save();

        return response()->json([
            'message' => 'Correo verificado con exito',
            'type' => 'success',
            'token' => $user->createToken('user_token')->plainTextToken
        ]);
    }

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

        return response()->json(['message' => 'Se ha enviado un nuevo codigo de verificacion.']);
    }

    public function registerPatient(Request $request)
    {
        $data = $request->all();
        $attributes = PatientIdentity::buildPatientAttributes($data);
        $email = $attributes['email'];
        $phone = $attributes['phone'];

        $validateUser = Validator::make($data, [
            'name' => 'required|string|max:255',
            'email' => 'nullable|email',
            'contacto.telefono' => 'nullable|string|max:20',
            'password' => 'required|string|min:6'
        ], [
            'name.required' => 'El nombre es obligatorio.',
            'password.required' => 'La contrasena es obligatoria.'
        ]);

        if ($validateUser->fails()) {
            return response()->json([
                'message' => 'Ha ocurrido un error de validacion',
                'errors' => $validateUser->errors()
            ], 400);
        }

        if (!$email && !$phone) {
            return response()->json([
                'message' => 'Ha ocurrido un error de validacion',
                'errors' => [
                    'identifier' => ['Debes ingresar un correo o un telefono.'],
                ]
            ], 400);
        }

        if ($phone && strlen($phone) < 10) {
            return response()->json([
                'message' => 'Ha ocurrido un error de validacion',
                'errors' => [
                    'contacto.telefono' => ['El telefono debe tener al menos 10 digitos.'],
                ]
            ], 400);
        }

        if (PatientIdentity::findByEmailOrPhone($email, $phone)) {
            return response()->json([
                'message' => 'Ha ocurrido un error de validacion',
                'errors' => [
                    'identifier' => ['Ya existe una cuenta con ese correo o telefono.'],
                ]
            ], 400);
        }

        $patient = Patient::create([
            'name' => $attributes['name'],
            'email' => $email,
            'phone' => $phone,
            'contacto' => $attributes['contacto'],
            'password' => Hash::make($request->password)
        ]);
        try {
            $patient->notify(new NuevoPacienteBienvenida($patient));
        } catch (\Throwable $th) {
            Log::error('Error al notificar nuevo paciente auto-registrado: ' . $th->getMessage());
        }
        return response()->json([
            'message' => 'El usuario se ha creado',
            'token' => $patient->createToken('patient_token')->plainTextToken
        ], 200);
    }

    public function checkPatientEmail(Request $request)
    {
        $data = $request->all();
        $email = PatientIdentity::normalizeEmail($request->input('email'));
        $phone = PatientIdentity::normalizePhone($request->input('phone', data_get($data, 'contacto.telefono')));

        return response()->json([
            'exists' => PatientIdentity::findByEmailOrPhone($email, $phone) !== null,
        ]);
    }
}
