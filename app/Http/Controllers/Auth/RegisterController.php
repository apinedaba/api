<?php

namespace App\Http\Controllers\Auth;

use App\Models\Patient;
use App\Models\Vendedor;
use App\Models\Subscription;
use App\Models\Clinic;
use App\Models\ClinicMembership;
use App\Models\Organization;
use App\Models\User;
use App\Notifications\NuevoPacienteBienvenida;
use App\Notifications\NuevoPsicologoRegistrado;
use App\Support\PatientIdentity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use App\Http\Controllers\Controller;
use App\Services\SellerCommissionService;
use App\Services\OrganizationService;

class RegisterController extends Controller
{
    private const EMAIL_REGEX = '/^[^\s@]+@[^\s@]+\.[^\s@]+$/';
    private const MX_PHONE_REGEX = '/^\d{10}$/';

    private $registerValidationRules = [
        'name' => 'required|string|max:255',
        'email' => ['required', 'string', 'max:255', 'regex:' . self::EMAIL_REGEX, 'unique:users,email'],
        'contacto.telefono' => ['required', 'regex:' . self::MX_PHONE_REGEX],
        'account_type' => ['nullable', 'in:independent,clinic'],
        'clinic_name' => ['required_if:account_type,clinic', 'nullable', 'string', 'max:255'],
        'password' => 'required|string|min:6'
    ];

    public function registerUser(
        Request $request,
        SellerCommissionService $sellerCommissionService,
        OrganizationService $organizationService
    )
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

        $accountType = $request->input('account_type') === 'clinic' ? 'clinic' : 'independent';
        $user = DB::transaction(function () use ($request, $accountType, $organizationService, $vendedor, $sellerCommissionService, $sellerCode) {
            $user = User::create([
                'name' => trim((string) $request->name),
                'email' => mb_strtolower(trim((string) $request->email)),
                'contacto' => array_merge($request->contacto ?? [], [
                    'telefono' => preg_replace('/\D+/', '', (string) data_get($request->all(), 'contacto.telefono')),
                ]),
                'configurations' => [
                    'workspace_type' => $accountType === 'clinic' ? 'clinic' : 'independent',
                    'registration_mode' => $accountType === 'clinic' ? 'clinic_owner' : 'independent',
                ],
                'password' => Hash::make($request->password),
                'verification_code' => str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT),
                'code_expires_at' => now()->addMinutes(10),
            ]);

            $this->createInitialWorkspace($user, $accountType, $request, $organizationService);

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

            return $user->fresh();
        });

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

    private function createInitialWorkspace(
        User $user,
        string $accountType,
        Request $request,
        OrganizationService $organizationService
    ): void {
        $workspaceName = $accountType === 'clinic'
            ? trim((string) $request->input('clinic_name'))
            : ($user->name ?: "Consultorio {$user->id}");

        $organization = $organizationService->create($user, [
            'name' => $workspaceName,
            'type' => $accountType === 'clinic' ? Organization::TYPE_CLINIC : Organization::TYPE_INDIVIDUAL,
            'settings' => [
                'created_from' => 'registration',
                'account_type' => $accountType,
            ],
        ]);

        $configurations = $user->configurations ?? [];
        $configurations['active_organization_id'] = $organization->id;

        if ($accountType === 'clinic') {
            $clinic = Clinic::create([
                'owner_user_id' => $user->id,
                'name' => $workspaceName,
                'slug' => $this->uniqueClinicSlug($workspaceName),
                'account_type' => 'clinic',
                'status' => 'active',
                'description' => null,
                'base_psychologist_limit' => 6,
                'addon_psychologist_slots' => 0,
                'contact' => [
                    'telefono' => data_get($user->contacto, 'telefono'),
                    'email' => $user->email,
                ],
                'settings' => [
                    'created_from' => 'registration',
                    'base_plan' => true,
                    'unlimited_psychologists' => false,
                ],
            ]);

            ClinicMembership::updateOrCreate(
                [
                    'clinic_id' => $clinic->id,
                    'user_id' => $user->id,
                ],
                [
                    'role' => 'owner',
                    'is_primary' => true,
                    'can_manage_schedule' => true,
                    'can_manage_patients' => true,
                    'can_view_finance' => true,
                    'meta' => [
                        'allowed_modules' => ['*'],
                        'created_from' => 'registration',
                    ],
                ]
            );

            $configurations['clinic_id'] = $clinic->id;
            $configurations['clinic_name'] = $clinic->name;
        }

        $user->forceFill(['configurations' => $configurations])->save();
    }

    private function uniqueClinicSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'clinica';
        $slug = $base;
        $counter = 2;

        while (Clinic::where('slug', $slug)->exists()) {
            $slug = "{$base}-{$counter}";
            $counter++;
        }

        return $slug;
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

        if ($request->hasSession()) {
            Auth::guard('user_web')->login($user, true);
            $request->session()->regenerate();
        }

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
            'email' => ['nullable', 'string', 'max:255', 'regex:' . self::EMAIL_REGEX],
            'contacto.telefono' => ['nullable', 'regex:' . self::MX_PHONE_REGEX],
            'password' => 'required|string|min:6'
        ], [
            'name.required' => 'El nombre es obligatorio.',
            'password.required' => 'La contrasena es obligatoria.',
            'email.regex' => 'El correo debe tener un formato valido.',
            'contacto.telefono.regex' => 'El telefono debe tener exactamente 10 digitos y no incluir espacios.'
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

        if ($phone && !preg_match(self::MX_PHONE_REGEX, $phone)) {
            return response()->json([
                'message' => 'Ha ocurrido un error de validacion',
                'errors' => [
                    'contacto.telefono' => ['El telefono debe tener exactamente 10 digitos y no incluir espacios.'],
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

        if ($request->hasSession()) {
            Auth::guard('patient_web')->login($patient, true);
            $request->session()->regenerate();
        }

        return response()->json([
            'message' => 'El usuario se ha creado',
            'token' => $patient->createToken('patient_token')->plainTextToken,
            'user' => $patient,
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
