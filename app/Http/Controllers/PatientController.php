<?php

namespace App\Http\Controllers;

use App\Http\Controllers\PatientUserController;
use App\Models\Patient;
use App\Models\PatientUser;
use App\Notifications\PatientAssignedEmailNotification;
use App\Notifications\SendEmail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\JsonResponse;
use Illuminate\Validation\Rule;
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
        $patient = Patient::where('email', $request->email)->first();
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
        $patients = Patient::with('connections')->with('connections.user')->get();
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
    private $registerValidationRules = [
        'name' => 'required',
        'email' => 'required|email|unique:patients,email',
        'contacto.telefono' => 'required|regex:/^[0-9]{10}$/',  // Assuming a 10-digit phone number
        'password' => 'required'
    ];

    public function store(Request $request)
    {
        $data = $request->all();
        $email = $request->input('email');
        $telefono = data_get($data, 'contacto.telefono');
        $patient = Patient::where('email', $email)->first();
        $isNewPatient = $patient === null;
        $validationRules = [
            'email' => ['required', 'email'],
            'contacto.telefono' => ['required', 'regex:/^[0-9]{10}$/'],
        ];

        if ($isNewPatient) {
            $validationRules['name'] = 'required|string|max:255';
            $validationRules['email'] = array_merge($validationRules['email'], ['unique:patients,email']);
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
            if (!$telefono) {
                return response()->json([
                    'rasson' => 'El telefono es requerido',
                    'message' => 'Error al agregar paciente',
                    'type' => 'error'
                ], 400);
            }

            $data['password'] = Hash::make($request->input('password', $telefono));

            $patient = new Patient();
            $patient->fill($data);
            $patient->save();
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
            $send = $this->sendNotificacionEmailByUser($user, $patient, $enlace);

            $successMessage = $isNewPatient
                ? 'El paciente se creó y se le envió una invitación con éxito. Espera a que acepte la invitación para poder agendarle citas.'
                : 'El paciente existente fue enlazado con éxito a tu cuenta. Se le envió una notificación. Espera a que la acepte para poder agendarle citas.';

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
        $patient = Patient::findOrFail($id);

        $validated = $request->validate([
            'relationships' => 'array',
            'relationships.*.nombre' => 'required|string',
            'relationships.*.parentesco' => 'required|string',
            'relationships.*.telefono' => 'required|string',
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

    public function sendNotificacionEmailByUser($user, $patient, $enlace)
    {
        if ($enlace) {
            try {
                // code...
                $patient->notify(new PatientAssignedEmailNotification($user, $patient, $enlace));
                return true;
            } catch (\Throwable $th) {
                Log::error($th->getMessage());
                // throw $th;
            }
        }
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
    public function updateFromUser(Request $request)
    {
        $user = $request->user();

        $patient = Patient::where('email', $user->email)->firstOrFail();

        $validated = $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|unique:users,email,' . $user->id,
            'telefono' => 'nullable|string|max:20',
        ]);

        $user->update([
            'name'  => $validated['name'],
            'email' => $validated['email'],
        ]);

        $contacto = $patient->contacto ?? [];
        $contacto['telefono'] = $validated['telefono'] ?? null;

        $patient->update([
            'contacto' => $contacto,
        ]);

        return response()->json([
            'ok' => true,
            'contacto' => $contacto,
        ]);
    }
    public function update(Request $request, Patient $patient)
    {
        try {
            $patient->update($request->all());
            $response = [
                'rasson' => 'El usuario se a actualizado correctamente',
                'message' => 'Usuario actulizado ',
                'type' => 'success'
            ];
        } catch (\Throwable $th) {
            $response = [
                'rasson' => 'El usuario no se a actualizado correctamente',
                'message' => 'Usuario no actulizado',
                'type' => 'error'
            ];
        }

        return response()->json($response, 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Patient $patient)
    {
    }
}
