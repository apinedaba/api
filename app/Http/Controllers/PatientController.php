<?php

namespace App\Http\Controllers;

use App\Http\Controllers\PatientUserController;
use App\Models\Patient;
use App\Models\PatientUser;
use App\Models\Expediente;
use App\Notifications\PatientAssignedEmailNotification;
use App\Notifications\PatientConsentSignedNotification;
use App\Notifications\SendEmail;
use App\Services\WhatsApp\PatientInvitationWhatsAppNotifier;
use App\Support\PatientIdentity;
use Cloudinary\Api\Upload\UploadApi;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use App\Models\User;
use Cloudinary\Configuration\Configuration;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\JsonResponse;

class PatientController extends Controller
{
    protected $_patient;

    public function __construct()
    {
        $this->_patient = new PatientUserController;
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    function verifyPatient(Request $request)
    {
        $user = Auth::user();
        $data = $request->all();
        $email = PatientIdentity::normalizeEmail($request->input('email'));
        $phone = PatientIdentity::normalizePhone(
            $request->input('phone', data_get($data, 'contacto.telefono'))
        );

        if (!$email && !$phone) {
            return response()->json([
                'enlace' => false,
                'type' => 'info',
                'status' => 'missing identifier',
                'data' => ['patient' => null],
            ], 200);
        }

        $patient = PatientIdentity::findByEmailOrPhone($email, $phone);

        if ($patient) {
            $existingLink = PatientUser::where('user', $user->id)
                ->where('patient', $patient->id)
                ->exists();
            if ($existingLink) {
                return response()->json([
                    'enlace' => true,
                    'message' => 'El paciente ya se encuentra enlazado a su cuenta.',
                    'type' => 'info',
                    'data' => ['patient' => $patient]  // Puedes devolver el ID del paciente si lo necesitas
                ], 200);
            }
            return response()->json([
                'enlace' => false,
                'type' => 'info',
                'status' => 'ok',
                'data' => ['patient' => $patient]  // Puedes devolver el ID del paciente si lo necesitas
            ], 200);
        } else {
            return response()->json([
                'enlace' => false,
                'type' => 'info',
                'status' => 'not found',
                'data' => ['patient' => $patient]
            ], 200);
        }
    }

    public function getAllPatients()
    {
        $patients = Patient::query()
            ->where('registration_source', 'website')
            ->with(['connections.user:id,name,email,image', 'expediente'])
            ->latest()
            ->get()
            ->map(function (Patient $patient) {
                $patient->setAttribute('registered_at', optional($patient->created_at)->toIso8601String());

                return $patient;
            });

        return Inertia::render('Pacientes', [
            'pacientes' => $patients,
            'status' => session('status'),
        ]);
    }

    public function getNumberPatient()
    {
        $patientsCount = Patient::count();
        return new JsonResponse(['count' => $patientsCount], 200);
    }

    public function getPatientById($id)
    {
        $patients = Patient::with('connections')->with('connections.user')->find($id);
        return Inertia::render('Pacientes/Paciente', [
            'paciente' => $patients,
            'status' => session('status'),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $data = $request->all();
        $attributes = PatientIdentity::buildPatientAttributes($data);
        $email = $attributes['email'];
        $telefono = $attributes['phone'];
        $birthDate = data_get($data, 'relevantes.fechaNac');
        $isMinor = false;
        if ($birthDate) {
            try {
                $isMinor = Carbon::parse($birthDate)->age < 18;
            } catch (\Throwable) {
                $isMinor = false;
            }
        }
        $legalRepresentative = collect($request->input('relationships', []))
            ->first(fn ($relationship) => filter_var(data_get($relationship, 'es_representante_legal'), FILTER_VALIDATE_BOOLEAN));
        $patient = PatientIdentity::findByEmailOrPhone($email, $telefono);
        $isNewPatient = $patient === null;
        $initialPassword = null;

        $validationRules = [
            'email' => ['nullable', 'email'],
            'relevantes.fechaNac' => ['nullable', 'date', 'before_or_equal:' . now()->subYears(2)->toDateString()],
            'contacto.telefono' => ['nullable', 'string', 'max:20'],
            'organization_id' => ['nullable', 'exists:organizations,id'],
            'relationships' => ['nullable', 'array'],
            'relationships.*.nombre' => ['required_with:relationships', 'string', 'max:255'],
            'relationships.*.parentesco' => ['required_with:relationships', 'string', 'max:100'],
            'relationships.*.telefono' => ['nullable', 'string', 'max:20'],
            'relationships.*.correo' => ['nullable', 'email', 'max:255'],
            'relationships.*.identificacion' => ['nullable', 'string', 'max:255'],
            'relationships.*.es_contacto_emergencia' => ['required_with:relationships', 'boolean'],
            'relationships.*.es_representante_legal' => ['nullable', 'boolean'],
        ];

        if (!$email && !$telefono && !$isMinor) {
            return response()->json([
                'rasson' => 'Debes ingresar al menos un correo o un telefono para identificar al paciente.',
                'message' => 'Error al agregar paciente',
                'type' => 'error'
            ], 400);
        }

        if ($isMinor && (!$legalRepresentative
            || blank(data_get($legalRepresentative, 'nombre'))
            || blank(data_get($legalRepresentative, 'parentesco'))
            || (blank(data_get($legalRepresentative, 'telefono')) && blank(data_get($legalRepresentative, 'correo'))))) {
            return response()->json([
                'rasson' => 'Los pacientes menores requieren nombre, parentesco y un medio de contacto de su representante legal.',
                'message' => 'Falta el representante legal',
                'type' => 'error',
            ], 422);
        }

        if ($telefono && strlen($telefono) < 10) {
            return response()->json([
                'rasson' => 'El telefono debe tener al menos 10 digitos.',
                'message' => 'Error al agregar paciente',
                'type' => 'error'
            ], 400);
        }

        if ($isNewPatient) {
            $validationRules['name'] = 'required|string|max:255';
        }

        $validateUser = Validator::make($data, $validationRules, [
            'relevantes.fechaNac.before_or_equal' => 'El paciente debe tener al menos 2 años cumplidos.',
            'relevantes.fechaNac.date' => 'La fecha de nacimiento no es válida.',
        ]);

        if ($validateUser->fails()) {
            return response()->json([
                'rasson' => $validateUser->errors()->first(),
                'message' => 'Error al agregar paciente',
                'type' => 'error'
            ], 400);
        }

        if ($isNewPatient) {
            $passwordSeed = $request->input('password') ?: $telefono ?: $email ?: Str::random(20);
            $initialPassword = $passwordSeed;
            $historiaClinica = array_merge($request->input('historiaClinica', []) ?? [], [
                'clinical_intake' => $request->input('clinical_intake', data_get($data, 'historiaClinica.clinical_intake', [])),
            ]);
            $data = array_merge($data, $attributes, [
                'organization_id' => $request->input('organization_id') ?: $request->attributes->get('active_organization')?->id,
                'registration_source' => 'professional',
                'password' => Hash::make($passwordSeed),
                'historiaClinica' => $historiaClinica,
            ]);

            $patient = new Patient();
            $patient->fill($data);
            $patient->save();
        } else {
            $dirty = false;
            $organizationId = $request->input('organization_id') ?: $request->attributes->get('active_organization')?->id;

            if (!$patient->organization_id && $organizationId) {
                $patient->organization_id = $organizationId;
                $dirty = true;
            }

            if (!$patient->email && $email) {
                $patient->email = $email;
                $dirty = true;
            }

            if (!$patient->phone && $telefono) {
                $patient->phone = $telefono;
                $dirty = true;
            }

            $contacto = array_merge($patient->contacto ?? [], ['telefono' => $telefono ?: data_get($patient->contacto, 'telefono')]);
            if (($patient->contacto ?? []) !== $contacto) {
                $patient->contacto = $contacto;
                $dirty = true;
            }

            if ($request->has('relationships') && is_array($request->input('relationships'))) {
                $patient->relationships = $request->input('relationships');
                $dirty = true;
            }

            if ($dirty) {
                $patient->save();
            }
        }

        $this->saveInitialClinicalIntake($request, $patient);
        $this->saveConsentFromRequest($request, $patient);

        $consentUrl = null;
        if ($request->boolean('generate_consent_link')) {
            $token = Str::random(72);
            $consent = array_merge($patient->consentimiento ?? [], [
                'status' => 'pending',
                'type' => 'pending',
                'public_token' => $token,
                'public_generated_by' => auth()->id(),
                'public_generated_at' => now()->toIso8601String(),
                'public_expires_at' => now()->addDays(30)->toIso8601String(),
                'source' => 'mindmeet_consent_v1',
            ]);
            $patient->consentimiento = $consent;
            $patient->save();
            $baseUrl = rtrim((string) (config('app.front_url_psicologo') ?: $request->headers->get('origin') ?: config('app.front_url')), '/');
            $consentUrl = "{$baseUrl}/consentimiento/{$token}";
        }

        $user = Auth::user();

        $existingLink = PatientUser::where('user', $user->id)
            ->where('patient', $patient->id)
            ->exists();

        if ($existingLink) {
            return response()->json(
                [
                    'rasson' => 'El paciente ya se encuentra enlazado a su cuenta.',
                    'message' => 'Paciente ya agregado',
                    'type' => 'info',
                    'data' => ['patient_id' => $patient->id],
                    'consent_url' => $consentUrl,
                ],
                200
            );
        }

        $enlace = $this->_patient->enlacePacienteProfesional($patient->id);

        if (isset($enlace['message'])) {
            return response()->json($enlace, 400);
        }

        if ($enlace) {
            $send = $this->sendNotificacionEmailByUser($user, $patient, $enlace, $initialPassword);

            if ($this->shouldSendPatientWhatsApp($request)) {
                app(PatientInvitationWhatsAppNotifier::class)
                    ->send($user, $patient, $initialPassword, 'user.patient.store');
            }

            $successMessage = $isNewPatient
                ? 'El paciente se creó y quedó activo en tu directorio. Se le envió un correo para iniciar sesión en su portal de paciente.'
                : 'El paciente existente fue enlazado y quedó activo en tu directorio. Se le envió un correo para iniciar sesión en su portal de paciente.';

            return response()->json(
                [
                    'rasson' => $successMessage,
                    'message' => 'Paciente agregado',
                    'type' => 'success',
                    'data' => $enlace,
                    'consent_url' => $consentUrl,
                ],
                200
            );
        }

        return response()->json([
            'rasson' => 'Error desconocido al intentar finalizar el proceso de enlace del paciente.',
            'message' => 'Error al agregar paciente',
            'type' => 'error'
        ], 500);
    }

    public function updateRelationships(Request $request, $id)
    {
        if ($this->patientArchivedForCurrentUser($id)) {
            return response()->json([
                'message' => 'Paciente archivado. Reactivalo para modificar sus relaciones.',
                'type' => 'error',
            ], 423);
        }

        $patient = Patient::findOrFail($id);

        $validated = $request->validate([
            'relationships' => 'array',
            'relationships.*.nombre' => 'required|string',
            'relationships.*.parentesco' => 'required|string',
            'relationships.*.correo' => 'nullable|email',
            'relationships.*.telefono' => 'nullable|string|max:20',
            'relationships.*.whatsapp' => 'nullable|string|max:20',
            'relationships.*.identificacion' => 'nullable|string|max:255',
            'relationships.*.es_contacto_emergencia' => 'required|boolean',
            'relationships.*.es_representante_legal' => 'nullable|boolean',
            'relationships.*.representative_historical' => 'nullable|boolean',
        ]);

        $currentRelationships = collect($patient->relationships ?? []);
        $submittedRelationships = collect($validated['relationships']);
        $fingerprint = fn ($relationship) => mb_strtolower(implode('|', [
            trim((string) data_get($relationship, 'nombre')), trim((string) data_get($relationship, 'parentesco')),
            trim((string) data_get($relationship, 'correo')), preg_replace('/\D+/', '', (string) data_get($relationship, 'telefono')),
            trim((string) data_get($relationship, 'identificacion')),
        ]));
        $protectedRepresentatives = $currentRelationships->filter(fn ($relationship) =>
            filter_var(data_get($relationship, 'es_representante_legal'), FILTER_VALIDATE_BOOLEAN)
            || filter_var(data_get($relationship, 'representative_historical'), FILTER_VALIDATE_BOOLEAN));
        foreach ($protectedRepresentatives as $representative) {
            abort_unless($submittedRelationships->contains(fn ($item) => $fingerprint($item) === $fingerprint($representative)), 422, 'Un representante legal registrado no puede editarse ni eliminarse. Agrega uno nuevo para reemplazarlo.');
        }
        $currentFingerprints = $currentRelationships->map($fingerprint);
        $newRepresentatives = $submittedRelationships->filter(fn ($relationship) =>
            filter_var(data_get($relationship, 'es_representante_legal'), FILTER_VALIDATE_BOOLEAN)
            && ! $currentFingerprints->contains($fingerprint($relationship)));
        abort_if($newRepresentatives->count() > 1, 422, 'Solo puedes agregar un representante legal nuevo a la vez.');
        $requiresNewSignature = $newRepresentatives->isNotEmpty() && $protectedRepresentatives->isNotEmpty();
        if ($newRepresentatives->isNotEmpty()) {
            $newFingerprint = $fingerprint($newRepresentatives->first());
            $submittedRelationships = $submittedRelationships->map(function ($relationship) use ($fingerprint, $newFingerprint) {
                if ($fingerprint($relationship) === $newFingerprint) return array_merge($relationship, ['es_representante_legal' => true, 'representative_historical' => false]);
                if (filter_var(data_get($relationship, 'es_representante_legal'), FILTER_VALIDATE_BOOLEAN) || filter_var(data_get($relationship, 'representative_historical'), FILTER_VALIDATE_BOOLEAN)) {
                    return array_merge($relationship, ['es_representante_legal' => false, 'representative_historical' => true]);
                }
                return $relationship;
            });
        }
        $patient->relationships = $submittedRelationships->values()->all();
        if ($requiresNewSignature && ! empty($patient->consentimiento)) {
            $patient->consentimiento = array_merge($patient->consentimiento, [
                'status' => 'pending', 'type' => 'pending', 'document_kind' => 'minor_therapy_authorization',
                'signature_data_url' => null, 'signed_patient_name' => null, 'signed_at' => null,
                'public_token' => null, 'public_expires_at' => null, 'updated_at' => now()->toIso8601String(),
                'requires_new_signature_reason' => 'legal_representative_changed',
            ]);
        }
        $patient->save();

        return response()->json(
            [
                'rasson' => 'Actualizacion de relaciones exitosa',
                'message' => 'Modificacion exitosa',
                'type' => 'success',
                'relationships' => $patient->relationships,
                'consentimiento' => $patient->consentimiento,
                'requires_new_signature' => $requiresNewSignature,
            ],
            200
        );
    }

    public function updateConsent(Request $request, $id)
    {
        if ($this->patientArchivedForCurrentUser($id)) {
            return response()->json([
                'message' => 'Paciente archivado. Reactivalo para modificar el consentimiento.',
                'type' => 'error',
            ], 423);
        }

        $patient = Patient::findOrFail($id);
        $patientUser = PatientUser::where('patient', $patient->id)
            ->where('user', auth()->id())
            ->firstOrFail();

        $this->saveConsentFromRequest($request, $patient, true);
        $freshPatient = $patient->fresh();
        $freshPatient->setAttribute('patient_user', $patientUser->fresh());

        return response()->json([
            'message' => 'Consentimiento actualizado',
            'type' => 'success',
            'patient' => $freshPatient,
        ]);
    }

    public function generateConsentLink(Request $request, $id): JsonResponse
    {
        if ($this->patientArchivedForCurrentUser($id)) {
            return response()->json([
                'message' => 'Paciente archivado. Reactivalo para generar el enlace de consentimiento.',
                'type' => 'error',
            ], 423);
        }

        $patient = Patient::findOrFail($id);
        PatientUser::where('patient', $patient->id)
            ->where('user', auth()->id())
            ->firstOrFail();

        $validated = $request->validate([
            'consent_content' => ['nullable', 'string', 'max:30000'],
            'professional_signature_data_url' => ['nullable', 'string', 'max:2000000'],
            'consent_document_kind' => ['nullable', 'in:informed_consent,minor_therapy_authorization'],
            'consent_signed_by_name' => ['nullable', 'string', 'max:255'],
            'consent_signer_role' => ['nullable', 'string', 'max:100'],
        ]);

        $content = trim((string) ($validated['consent_content'] ?? data_get($patient->consentimiento, 'content', '')));
        $professionalSignature = $validated['professional_signature_data_url'] ?? data_get($patient->consentimiento, 'professional_signature_data_url');
        $documentKind = $validated['consent_document_kind'] ?? 'informed_consent';
        $representative = collect($patient->relationships ?? [])
            ->first(fn ($relationship) => filter_var(data_get($relationship, 'es_representante_legal'), FILTER_VALIDATE_BOOLEAN));
        if ($documentKind === 'minor_therapy_authorization') {
            abort_unless($representative && filled(data_get($representative, 'nombre')), 422, 'Registra primero al representante legal del menor.');
            $validated['consent_signed_by_name'] = data_get($representative, 'nombre');
            $validated['consent_signer_role'] = data_get($representative, 'parentesco') ?: 'Representante legal';
        }
        if ($content === '' || ! $professionalSignature) {
            return response()->json([
                'message' => 'Agrega el texto del consentimiento y la firma del profesional antes de generar el enlace.',
                'type' => 'error',
            ], 422);
        }

        $token = Str::random(72);
        $consent = array_merge($patient->consentimiento ?? [], [
            'status' => data_get($patient->consentimiento, 'status', 'pending'),
            'type' => data_get($patient->consentimiento, 'type', 'pending'),
            'public_token' => $token,
            'public_generated_by' => auth()->id(),
            'public_generated_at' => now()->toIso8601String(),
            'public_expires_at' => now()->addDays(30)->toIso8601String(),
            'source' => 'mindmeet_consent_v1',
            'content' => $content,
            'document_kind' => $documentKind,
            'expected_signer_name' => $validated['consent_signed_by_name'] ?? null,
            'signer_role' => $validated['consent_signer_role'] ?? null,
            'professional_signature_data_url' => $professionalSignature,
            'professional_signed_by' => auth()->id(),
            'professional_signed_name' => data_get(auth()->user(), 'contacto.publicName') ?: auth()->user()?->name,
            'professional_signed_at' => now()->toIso8601String(),
            'updated_at' => now()->toIso8601String(),
        ]);

        $patient->consentimiento = $consent;
        $patient->save();

        $frontUrl = rtrim((string) config('app.front_url_psicologo'), '/');
        $originUrl = rtrim((string) $request->headers->get('origin'), '/');
        $fallbackUrl = rtrim((string) config('app.front_url'), '/');
        $baseUrl = $frontUrl ?: $originUrl ?: $fallbackUrl;

        return response()->json([
            'message' => 'Enlace de consentimiento generado',
            'type' => 'success',
            'token' => $token,
            'url' => "{$baseUrl}/consentimiento/{$token}",
            'expires_at' => $consent['public_expires_at'],
        ]);
    }

    public function showPublicConsent(string $token): JsonResponse
    {
        $patient = Patient::query()
            ->where('consentimiento->public_token', $token)
            ->firstOrFail();

        $consent = $patient->consentimiento ?? [];
        $representative = collect($patient->relationships ?? [])
            ->first(fn ($relationship) => filter_var(data_get($relationship, 'es_representante_legal'), FILTER_VALIDATE_BOOLEAN));
        $isMinorAuthorization = data_get($consent, 'document_kind') === 'minor_therapy_authorization';
        if ($this->publicConsentExpired($consent)) {
            return response()->json([
                'message' => 'Este enlace de consentimiento expiro. Solicita uno nuevo a tu psicologo.',
                'type' => 'error',
            ], 410);
        }

        $professionalId = data_get($consent, 'public_generated_by');
        $professional = $professionalId
            ? \App\Models\User::query()->select('id', 'name', 'contacto', 'configurations')->find($professionalId)
            : null;

        return response()->json([
            'patient' => [
                'name' => $patient->name,
            ],
            'professional' => $professional ? [
                'name' => data_get($professional->contacto, 'publicName') ?: $professional->name,
                'document_logo_url' => data_get($professional->configurations, 'expediente_logo_url'),
            ] : null,
            'consent' => [
                'status' => data_get($consent, 'status', 'pending'),
                'signed_at' => data_get($consent, 'signed_at'),
                'expires_at' => data_get($consent, 'public_expires_at'),
                'content' => data_get($consent, 'content'),
                'document_kind' => data_get($consent, 'document_kind', 'informed_consent'),
                'expected_signer_name' => $isMinorAuthorization
                    ? data_get($representative, 'nombre')
                    : data_get($consent, 'expected_signer_name'),
                'signer_role' => $isMinorAuthorization
                    ? (data_get($representative, 'parentesco') ?: 'Representante legal')
                    : data_get($consent, 'signer_role'),
                'professional_signature_data_url' => data_get($consent, 'professional_signature_data_url'),
                'professional_signed_name' => data_get($consent, 'professional_signed_name'),
                'professional_signed_at' => data_get($consent, 'professional_signed_at'),
            ],
        ]);
    }

    public function signPublicConsent(Request $request, string $token): JsonResponse
    {
        $request->validate([
            'consent_signature_data_url' => ['required', 'string', 'max:2000000'],
            'patient_name' => ['nullable', 'string', 'max:255'],
            'signer_role' => ['nullable', 'string', 'max:100'],
        ]);

        $patient = Patient::query()
            ->where('consentimiento->public_token', $token)
            ->firstOrFail();

        $consent = $patient->consentimiento ?? [];
        if ($this->publicConsentExpired($consent)) {
            return response()->json([
                'message' => 'Este enlace de consentimiento expiro. Solicita uno nuevo a tu psicologo.',
                'type' => 'error',
            ], 410);
        }

        $signedName = $request->input('patient_name');
        $signerRole = $request->input('signer_role', data_get($consent, 'signer_role'));
        if (data_get($consent, 'document_kind') === 'minor_therapy_authorization') {
            $representative = collect($patient->relationships ?? [])
                ->first(fn ($relationship) => filter_var(data_get($relationship, 'es_representante_legal'), FILTER_VALIDATE_BOOLEAN));
            abort_unless($representative && filled(data_get($representative, 'nombre')), 422, 'El expediente no tiene un representante legal registrado.');
            $signedName = data_get($representative, 'nombre');
            $signerRole = data_get($representative, 'parentesco') ?: 'Representante legal';
        }

        $patient->consentimiento = array_merge($consent, [
            'status' => 'signed',
            'type' => 'digital',
            'signature_data_url' => $request->input('consent_signature_data_url'),
            'signed_patient_name' => $signedName,
            'signer_role' => $signerRole,
            'signed_at' => now()->toIso8601String(),
            'public_signed_at' => now()->toIso8601String(),
            'updated_at' => now()->toIso8601String(),
        ]);
        $patient->save();
        $this->notifyConsentSigned($patient);

        return response()->json([
            'message' => 'Consentimiento firmado correctamente',
            'type' => 'success',
            'consent' => [
                'status' => 'signed',
                'signed_at' => data_get($patient->consentimiento, 'signed_at'),
            ],
        ]);
    }

    protected function notifyConsentSigned(Patient $patient): void
    {
        $consent = $patient->consentimiento ?? [];
        $professionalIds = collect([data_get($consent, 'public_generated_by')])
            ->filter()
            ->merge(
                PatientUser::where('patient', $patient->id)
                    ->where('activo', true)
                    ->pluck('user')
            )
            ->unique()
            ->values();

        User::whereIn('id', $professionalIds)->get()->each(function (User $professional) use ($patient, $consent) {
            $professional->notify(new PatientConsentSignedNotification($patient, $consent));
        });
    }

    public function sendNotificacionEmailByUser($user, $patient, $enlace, ?string $initialPassword = null)
    {
        if ($enlace) {
            try {
                // code...
                $patient->notify(new PatientAssignedEmailNotification($user, $patient, $enlace, $initialPassword));
                return true;
            } catch (\Throwable $th) {
                Log::error($th->getMessage());
                // throw $th;
            }
        }
    }

    private function shouldSendPatientWhatsApp(Request $request): bool
    {
        return $request->boolean('send_whatsapp')
            || $request->boolean('send_whatsapp_patient')
            || $request->boolean('enviar_whatsapp_paciente')
            || $request->boolean('contacto.enviar_whatsapp_paciente');
    }

    public function sendInvitacion($id)
    {
        $patient = Patient::findOrFail($id);
        $content = "
            <p>Espero que estés teniendo una muy buena semana.</p>

            <p>
                Quiero contarte que ya tienes disponible en tu perfil de
                <strong>MindMeet</strong> la función de <strong>Diario</strong>,
                una herramienta pensada para acompañarte entre sesiones y apoyar
                tu proceso de una forma más consciente y cercana.
            </p>

            <p>
                Durante nuestras consultas trabajamos temas muy importantes,
                pero es normal que, en el día a día, surjan pensamientos,
                emociones o situaciones que luego pueden ser difíciles de
                recordar con claridad. El Diario te permite:
            </p>

            <ul>
                <li><strong>Registrar lo que sientes en el momento</strong>, cuando la emoción está presente.</li>
                <li><strong>Identificar patrones</strong>, reconociendo qué situaciones influyen en tu estado emocional.</li>
                <li><strong>Preparar nuestras sesiones</strong>, anotando ideas o temas que te gustaría trabajar con mayor profundidad.</li>
            </ul>

            <p>
                Si tú lo decides, también podemos usar este espacio para
                <strong>dar continuidad al proceso entre sesiones</strong>.
                Yo podré revisar tus entradas antes de vernos para llegar mejor
                preparado, e incluso dejarte algunas reflexiones o preguntas que
                nos ayuden a avanzar con mayor claridad y fluidez hacia tus objetivos.
            </p>

            <p>
                No es necesario escribir textos largos. A veces, unas cuantas
                palabras o frases son más que suficientes. La intención es que
                sea un espacio <strong>seguro, libre y completamente tuyo</strong>.
            </p>

            <p>
                Si tienes alguna duda sobre cómo acceder o utilizar el Diario
                dentro de MindMeet, con gusto puedo ayudarte.
            </p>

            <p>
                Nos vemos en nuestra próxima sesión ✨
            </p>
        ";
        $patient->notify(new SendEmail('Tu diario en MindMeet', $content, $patient));
        return response()->json([
            'rasson' => 'Invitacion enviada exitosamente',
            'message' => 'Invitacion enviada exitosamente',
            'type' => 'success',
        ], 200);
    }

    /**
     * Display the specified resource.
     */
    public function show(Patient $patient)
    {
        $user = auth()->user();
        $isPatientToUser = PatientUser::where('patient', $patient->id)->where('user', $user->id)->first();
        if ($isPatientToUser) {
            $patient->setAttribute('patient_user', $isPatientToUser);
            return response()->json($patient, 200);
        }
        return response()->json([
            'rasson' => 'El paciente no pertenece al usuario',
            'message' => 'El paciente no pertenece al usuario',
            'type' => 'error'
        ], 401);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Patient $patient)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function uploadAvatar(Request $request)
    {
        $request->validate([
            'photo' => 'required|string',
        ]);

        $imageData = base64_decode($request->input('photo'));
        if ($imageData === false) {
            return response()->json(['error' => 'Formato Base64 inválido'], 400);
        }

        $tempFilePath = tempnam(sys_get_temp_dir(), 'photo') . '.jpg';
        if (file_put_contents($tempFilePath, $imageData) === false) {
            return response()->json(['error' => 'No se pudo guardar el archivo temporal'], 500);
        }

        try {
            $cloudName = config('cloudinary.cloud_name');
            $apiKey = config('cloudinary.api_key');
            $apiSecret = config('cloudinary.api_secret');
            $cloudinaryUrl = config('cloudinary.url');

            if ($cloudName && $apiKey && $apiSecret) {
                Configuration::instance()->init([
                    'cloud' => [
                        'cloud_name' => $cloudName,
                        'api_key'    => $apiKey,
                        'api_secret' => $apiSecret,
                    ],
                ]);
            } elseif ($cloudinaryUrl) {
                // Intentar inicializar directamente con la URL; si la SDK no la interpreta,
                // parseamos la URL y creamos la configuración manualmente.
                try {
                    Configuration::instance()->init($cloudinaryUrl);
                } catch (\Throwable $inner) {
                    $parts = parse_url($cloudinaryUrl);
                    $parsedKey = $parts['user'] ?? null;
                    $parsedSecret = $parts['pass'] ?? null;
                    $parsedCloud = $parts['host'] ?? null;

                    if ($parsedKey && $parsedSecret && $parsedCloud) {
                        Configuration::instance()->init([
                            'cloud' => [
                                'cloud_name' => $parsedCloud,
                                'api_key'    => $parsedKey,
                                'api_secret' => $parsedSecret,
                            ],
                        ]);
                    } else {
                        @unlink($tempFilePath);
                        Log::error('Cloudinary: CLOUDINARY_URL presente pero no parseable: ' . $cloudinaryUrl);
                        return response()->json(['error' => 'Cloudinary no está configurado correctamente'], 500);
                    }
                }
            } else {
                @unlink($tempFilePath);
                Log::error('Cloudinary no está configurado correctamente: faltan credenciales (cloud_name/api_key/api_secret) y CLOUDINARY_URL');
                return response()->json(['error' => 'Cloudinary no está configurado'], 500);
            }

            $result = (new UploadApi)->upload($tempFilePath, ['folder' => 'ProfilePhotos']);
            unlink($tempFilePath);

            $patient = $request->user();
            $patient->update(['image' => $result['secure_url']]);

            return response()->json(['url' => $result['secure_url']]);
        } catch (\Exception $e) {
            @unlink($tempFilePath);
            Log::error('Error al subir avatar de paciente: ' . $e->getMessage());
            return response()->json(['error' => 'Error al subir la foto'], 500);
        }
    }

    public function updateFromUser(Request $request)
    {
        $patient = $request->user();

        $validated = $request->validate([
            // contacto
            'contacto.telefono'       => 'nullable|string|max:20',
            'contacto.whatsapp'       => 'nullable|string|max:20',
            'contacto.fijo'           => 'nullable|string|max:20',
            // relevantes (solo los que el paciente puede editar)
            'relevantes.ocupacion'    => 'nullable|string|max:255',
            'relevantes.genero'       => 'nullable|string|max:50',
            'relevantes.sexualidad'   => 'nullable|string|max:50',
            'relevantes.estadoCivil'  => 'nullable|string|max:50',
            // dirección
            'address.cp'              => 'nullable|string|max:10',
            'address.calle'           => 'nullable|string|max:255',
            'address.numExt'          => 'nullable|string|max:20',
            'address.numInt'          => 'nullable|string|max:20',
            'address.colonia'         => 'nullable|string|max:255',
            'address.municipio'       => 'nullable|string|max:255',
            'address.estado'          => 'nullable|string|max:100',
        ]);

        $contacto   = array_merge($patient->contacto   ?? [], $request->input('contacto',   []) ?? []);
        $relevantes = array_merge($patient->relevantes ?? [], $request->input('relevantes', []) ?? []);
        $address    = array_merge($patient->address    ?? [], $request->input('address',    []) ?? []);
        $phone = PatientIdentity::normalizePhone(data_get($contacto, 'telefono'));

        if ($phone) {
            $contacto['telefono'] = $phone;
        }

        $patient->update([
            'phone' => $phone ?: $patient->phone,
            'contacto'   => $contacto,
            'relevantes' => $relevantes,
            'address'    => $address,
        ]);

        return response()->json([
            'ok'        => true,
            'contacto'  => $contacto,
            'relevantes' => $relevantes,
            'address'   => $address,
        ]);
    }
    public function update(Request $request, Patient $patient)
    {
        if ($this->patientArchivedForCurrentUser($patient->id)) {
            return response()->json([
                'message' => 'Paciente archivado. Reactivalo para editar su informacion.',
                'type' => 'error',
            ], 423);
        }

        $validated = $request->validate([
            'relevantes.fechaNac' => ['nullable', 'date', 'before_or_equal:' . now()->subYears(2)->toDateString()],
        ], [
            'relevantes.fechaNac.before_or_equal' => 'El paciente debe tener al menos 2 años cumplidos.',
            'relevantes.fechaNac.date' => 'La fecha de nacimiento no es válida.',
        ]);

        if ($request->has('relationships') && $request->input('relationships') != ($patient->relationships ?? [])) {
            abort(422, 'Las relaciones deben actualizarse desde su módulo. Los representantes legales existentes no pueden editarse ni eliminarse.');
        }

        try {
            $patient->update($request->all());
            $response = [
                'rasson' => 'El usuario se a actualizado correctamente',
                'message' => 'Usuario actulizado ',
                'type' => 'success',
                'patient' => $patient->fresh(['connections', 'connections.user']),
            ];
        } catch (\Throwable $th) {
            Log::error('Error updating patient: ' . $th->getMessage(), [
                'patient_id' => $patient->id,
            ]);

            $response = [
                'rasson' => 'El usuario no se a actualizado correctamente',
                'message' => 'Usuario no actulizado',
                'type' => 'error'
            ];

            return response()->json($response, 500);
        }

        return response()->json($response, 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Patient $patient) {}

    private function patientArchivedForCurrentUser($patientId): bool
    {
        return PatientUser::where('patient', $patientId)
            ->where('user', auth()->id())
            ->whereNotNull('archived_at')
            ->exists();
    }

    private function saveInitialClinicalIntake(Request $request, Patient $patient): void
    {
        $motivoConsulta = $request->input('motivoConsulta');
        $clinicalIntake = $request->input('clinical_intake', []);

        if (!$motivoConsulta && empty(array_filter($clinicalIntake ?? []))) {
            return;
        }

        $historiaClinica = $patient->historiaClinica ?? [];
        if (!empty($clinicalIntake)) {
            $historiaClinica['clinical_intake'] = $clinicalIntake;
            $patient->historiaClinica = $historiaClinica;
            $patient->save();
        }

        if ($motivoConsulta) {
            Expediente::updateOrCreate(
                [
                    'patient_id' => $patient->id,
                    'user_id' => auth()->id(),
                ],
                [
                    'motivoConsulta' => $motivoConsulta,
                ]
            );
        }
    }

    private function saveConsentFromRequest(Request $request, Patient $patient, bool $forcePending = false): void
    {
        $signatureDataUrl = $request->input('consent_signature_data_url');
        $consent = $request->input('consentimiento', []);
        $fileUrl = $request->input('consent_file_url', data_get($consent, 'file_url'));
        $type = $request->input('consent_type', data_get($consent, 'type'));
        $content = trim((string) $request->input('consent_content', data_get($consent, 'content', data_get($patient->consentimiento, 'content', ''))));
        $professionalSignature = $request->input('professional_signature_data_url', data_get($consent, 'professional_signature_data_url'));
        $documentKind = $request->input('consent_document_kind', data_get($consent, 'document_kind', 'informed_consent'));
        $signedByName = $request->input('consent_signed_by_name', data_get($consent, 'signed_patient_name'));
        $signerRole = $request->input('consent_signer_role', data_get($consent, 'signer_role'));

        $request->validate([
            'consent_content' => ['nullable', 'string', 'max:30000'],
            'professional_signature_data_url' => ['nullable', 'string', 'max:2000000'],
            'consent_signature_data_url' => ['nullable', 'string', 'max:2000000'],
            'consent_document_kind' => ['nullable', 'in:informed_consent,minor_therapy_authorization'],
            'consent_signed_by_name' => ['nullable', 'string', 'max:255'],
            'consent_signer_role' => ['nullable', 'string', 'max:100'],
        ]);

        if (!$forcePending && !$signatureDataUrl && !$fileUrl && !$type && empty($consent)) {
            return;
        }

        $nextConsent = [
            'status' => 'pending',
            'type' => $type ?: 'pending',
            'source' => 'mindmeet_consent_v1',
            'document_kind' => $documentKind,
            'signed_patient_name' => $signedByName,
            'signer_role' => $signerRole,
            'updated_at' => now()->toIso8601String(),
        ];

        if ($content !== '') {
            $nextConsent['content'] = $content;
        }

        if ($professionalSignature) {
            $nextConsent = array_merge($nextConsent, [
                'professional_signature_data_url' => $professionalSignature,
                'professional_signed_by' => auth()->id(),
                'professional_signed_name' => data_get(auth()->user(), 'contacto.publicName') ?: auth()->user()?->name,
                'professional_signed_at' => now()->toIso8601String(),
            ]);
        }

        if ($fileUrl) {
            $nextConsent = array_merge($nextConsent, [
                'status' => 'uploaded',
                'type' => 'uploaded',
                'file_url' => $fileUrl,
                'uploaded_at' => now()->toIso8601String(),
            ]);
        }

        if ($signatureDataUrl) {
            $nextConsent = array_merge($nextConsent, [
                'status' => 'signed',
                'type' => 'digital',
                'signature_data_url' => $signatureDataUrl,
                'signed_at' => now()->toIso8601String(),
            ]);
        }

        if ($type === 'physical') {
            $nextConsent = array_merge($nextConsent, [
                'status' => 'physical',
                'type' => 'physical',
                'signed_at' => now()->toIso8601String(),
            ]);
        }

        $patient->consentimiento = array_merge($patient->consentimiento ?? [], $nextConsent);
        $patient->save();
    }

    private function publicConsentExpired(array $consent): bool
    {
        $expiresAt = data_get($consent, 'public_expires_at');
        return $expiresAt ? now()->greaterThan($expiresAt) : false;
    }

    private function configureCloudinary(): void
    {
        $cloudName = config('cloudinary.cloud_name');
        $apiKey = config('cloudinary.api_key');
        $apiSecret = config('cloudinary.api_secret');
        $cloudinaryUrl = config('cloudinary.url');

        if ($cloudName && $apiKey && $apiSecret) {
            Configuration::instance()->init([
                'cloud' => [
                    'cloud_name' => $cloudName,
                    'api_key' => $apiKey,
                    'api_secret' => $apiSecret,
                ],
            ]);
            return;
        }

        if ($cloudinaryUrl) {
            Configuration::instance()->init($cloudinaryUrl);
            return;
        }

        throw new \RuntimeException('Cloudinary no esta configurado');
    }
}
