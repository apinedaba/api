<?php

namespace App\Http\Controllers\Internal;

use App\Http\Controllers\Controller;
use App\Models\Subscription;
use App\Models\User;
use App\Models\ValidacionCedulaManual;
use App\Models\VendorAccountActivationToken;
use App\Notifications\VendorAccountReady;
use App\Services\AdminVendedoresClient;
use App\Services\OrganizationService;
use Cloudinary\Api\Upload\UploadApi;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class VendorOnboardingController extends Controller
{
    public function create(Request $request, AdminVendedoresClient $vendors, OrganizationService $organizations)
    {
        $this->assertIntegrationToken($request);
        $data = $request->validate([
            'vendedor_id' => ['required', 'integer'],
            'nombre' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'telefono' => ['required', 'regex:/^\\d{10}$/'],
            'numero_cedula' => ['required', 'string', 'max:20'],
            'institucion' => ['required', 'string', 'max:255'],
            'carrera' => ['required', 'string', 'max:255'],
            'fecha_expedicion' => ['required', 'date'],
            'archivo_cedula' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            'archivo_ine' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ]);

        $email = mb_strtolower(trim($data['email']));
        $cedula = strtoupper(str_replace(' ', '', trim($data['numero_cedula'])));
        if (User::whereRaw('LOWER(email) = ?', [$email])->exists()) {
            return response()->json(['message' => 'Ya existe una cuenta con este correo.'], 409);
        }
        if (ValidacionCedulaManual::where('request_key', hash('sha256', $cedula))->exists()) {
            return response()->json(['message' => 'Esta cédula ya está registrada o en revisión.'], 409);
        }

        try {
            $vendor = $vendors->vendor($data['vendedor_id']);
        } catch (\RuntimeException $exception) {
            return response()->json(['message' => 'El vendedor no está disponible para realizar este registro.'], 422);
        }

        // El CRM no almacena archivos: los transmite una sola vez y API los guarda en
        // el almacenamiento protegido de MindMeet.
        $cedulaUrl = $this->upload($request->file('archivo_cedula'), 'altas-vendedor/cedulas');
        $ineUrl = $this->upload($request->file('archivo_ine'), 'altas-vendedor/ine');
        $plainToken = Str::random(64);

        try {
            $user = DB::transaction(function () use ($data, $email, $cedula, $vendor, $cedulaUrl, $ineUrl, $plainToken, $organizations) {
                $user = User::create([
                    'name' => trim($data['nombre']),
                    'email' => $email,
                    'contacto' => ['telefono' => $data['telefono']],
                    'password' => Hash::make(Str::random(64)),
                    'identity_verification_status' => 'approved',
                    'cedula_selfie_url' => $cedulaUrl,
                    'ine_selfie_url' => $ineUrl,
                    'configurations' => [
                        'workspace_type' => 'independent',
                        'registration_mode' => 'vendor_assisted',
                        'vendor_onboarding' => [
                            'vendedor_id' => $vendor['id'],
                            'created_at' => now()->toIso8601String(),
                            'documents_verified_by_vendor' => true,
                        ],
                    ],
                ]);

                $organization = $organizations->create($user, [
                    'name' => $user->name ?: "Consultorio {$user->id}",
                    'settings' => ['created_from' => 'vendor_assisted_registration'],
                ]);
                $configurations = $user->configurations ?? [];
                $configurations['active_organization_id'] = $organization->id;
                $user->forceFill(['configurations' => $configurations])->save();

                ValidacionCedulaManual::create([
                    'user_id' => $user->id,
                    'numero_cedula' => $cedula,
                    'request_key' => hash('sha256', $cedula),
                    'nombre_completo' => $user->name,
                    'institucion' => $data['institucion'],
                    'carrera' => $data['carrera'],
                    'fecha_expedicion' => $data['fecha_expedicion'],
                    'archivo_cedula' => $cedulaUrl,
                    'estado' => 'aprobado',
                    'origen' => 'vendedor',
                    'vendedor_externo_id' => $vendor['id'],
                ]);

                $user->educacion = ['escuelas' => [[
                    'cedula' => $cedula,
                    'profesion' => $data['carrera'],
                    'institucion' => $data['institucion'],
                    'fecha_expedicion' => $data['fecha_expedicion'],
                    'status' => 'aprobado',
                ]]];
                $user->save();

                Subscription::firstOrCreate(['user_id' => $user->id], [
                    'stripe_id' => null, 'stripe_plan' => null, 'stripe_status' => 'init',
                    'trial_ends_at' => null, 'ends_at' => null,
                ]);

                VendorAccountActivationToken::where('user_id', $user->id)->whereNull('used_at')->delete();
                VendorAccountActivationToken::create([
                    'user_id' => $user->id,
                    'token_hash' => hash('sha256', $plainToken),
                    'expires_at' => now()->addHours(48),
                ]);

                return $user;
            });
        } catch (QueryException $exception) {
            Log::warning('Alta asistida por vendedor duplicada', ['error' => $exception->getMessage()]);
            return response()->json(['message' => 'El correo o la cédula ya se encuentran registrados.'], 409);
        }

        $vendors->registerReferral([
            'mindmeet_user_id' => $user->id,
            'nombre' => $user->name,
            'email' => $user->email,
            'telefono' => data_get($user->contacto, 'telefono'),
            'referral_code' => $vendor['qr_token'],
        ]);

        $base = rtrim(config('app.front_url_psicologo') ?: config('app.front_url'), '/');
        $activationUrl = $base . '/activar-cuenta?token=' . urlencode($plainToken);
        $user->notify(new VendorAccountReady($activationUrl));

        return response()->json([
            'message' => 'Cuenta creada y correo de activación enviado.',
            'mindmeet_user_id' => $user->id,
            'estado_comercial' => 'cuenta_lista',
        ], 201);
    }

    public function activate(Request $request)
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);
        $activation = VendorAccountActivationToken::query()
            ->where('token_hash', hash('sha256', $data['token']))
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->with('user')
            ->first();
        if (! $activation) {
            return response()->json(['message' => 'El enlace de activación no es válido o ya venció.'], 422);
        }

        DB::transaction(function () use ($activation, $data) {
            $activation->user->forceFill([
                'password' => Hash::make($data['password']),
                'email_verified_at' => now(),
            ])->save();
            $activation->forceFill(['used_at' => now()])->save();
        });

        return response()->json([
            'message' => 'Tu cuenta fue activada. Ya puedes iniciar sesión.',
            'email' => $activation->user->email,
        ]);
    }

    private function assertIntegrationToken(Request $request): void
    {
        $expected = (string) config('services.admin_vendedores.integration_token');
        if ($expected === '' || ! hash_equals($expected, (string) $request->header('X-Mindmeet-Integration-Token'))) {
            abort(401, 'Integración MindMeet no autorizada.');
        }
    }

    private function upload($file, string $folder): string
    {
        return (new UploadApi())->upload($file->getRealPath(), [
            'folder' => 'cedulas_profesionales/' . $folder,
            'resource_type' => 'auto',
            'type' => 'authenticated',
        ])['secure_url'];
    }
}
