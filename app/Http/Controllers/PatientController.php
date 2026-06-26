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
        $patients = Patient::with('connections')->with('connections.user')->with('expediente')->get();
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
        $patient = PatientIdentity::findByEmailOrPhone($email, $telefono);
        $isNewPatient = $patient === null;
        $initialPassword = null;

        $validationRules = [
            'email' => ['nullable', 'email'],
            'contacto.telefono' => ['nullable', 'string', 'max:20'],
            'organization_id' => ['nullable', 'exists:organizations,id'],
        ];

        if (!$email && !$telefono) {
            return response()->json([
                'rasson' => 'Debes ingresar al menos un correo o un telefono para identificar al paciente.',
                'message' => 'Error al agregar paciente',
                'type' => 'error'
            ], 400);
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

        $validateUser = Validator::make($data, $validationRules);

        if ($validateUser->fails()) {
            return response()->json([
                'rasson' => $validateUser->errors()->first(),
                'message' => 'Error al agregar paciente',
                'type' => 'error'
            ], 400);
        }

        if ($isNewPatient) {
            $passwordSeed = $request->input('password') ?: $telefono ?: $email;
            $initialPassword = $passwordSeed;
            $historiaClinica = array_merge($request->input('historiaClinica', []) ?? [], [
                'clinical_intake' => $request->input('clinical_intake', data_get($data, 'historiaClinica.clinical_intake', [])),
            ]);
            $data = array_merge($data, $attributes, [
                'organization_id' => $request->input('organization_id') ?: $request->attributes->get('active_organization')?->id,
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

            if ($dirty) {
                $patient->save();
            }
        }

        $this->saveInitialClinicalIntake($request, $patient);
        $this->saveConsentFromRequest($request, $patient);

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
                    'data' => ['patient_id' => $patient->id]  // Puedes devolver el ID del paciente si lo necesitas
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
                    'data' => $enlace
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
            'relationships.*.es_contacto_emergencia' => 'required|boolean',
        ]);

        $patient->relationships = $validated['relationships'];
        $patient->save();

        return response()->json(
            [
                'rasson' => 'Actualizacion de relaciones exitosa',
                'message' => 'Modificacion exitosa',
                'type' => 'success',
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

        $token = Str::random(72);
        $consent = array_merge($patient->consentimiento ?? [], [
            'status' => data_get($patient->consentimiento, 'status', 'pending'),
            'type' => data_get($patient->consentimiento, 'type', 'pending'),
            'public_token' => $token,
            'public_generated_by' => auth()->id(),
            'public_generated_at' => now()->toIso8601String(),
            'public_expires_at' => now()->addDays(30)->toIso8601String(),
            'source' => 'mindmeet_consent_v1',
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
            ],
        ]);
    }

    public function signPublicConsent(Request $request, string $token): JsonResponse
    {
        $request->validate([
            'consent_signature_data_url' => ['required', 'string'],
            'patient_name' => ['nullable', 'string', 'max:255'],
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

        $patient->consentimiento = array_merge($consent, [
            'status' => 'signed',
            'type' => 'digital',
            'signature_data_url' => $request->input('consent_signature_data_url'),
            'signed_patient_name' => $request->input('patient_name'),
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

        if (!$forcePending && !$signatureDataUrl && !$fileUrl && !$type && empty($consent)) {
            return;
        }

        $nextConsent = [
            'status' => 'pending',
            'type' => $type ?: 'pending',
            'source' => 'mindmeet_consent_v1',
            'updated_at' => now()->toIso8601String(),
        ];

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
