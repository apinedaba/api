<?php

namespace App\Http\Controllers;

use App\Models\Patient;
use App\Models\PatientUser;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use App\Http\Controllers\PatientUserController;
use Illuminate\Support\Facades\Auth;
use App\Notifications\PatientAssignedEmailNotification;
use Illuminate\Support\Facades\Log;

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

    /**
     * Store a newly created resource in storage.
     */

    private $registerValidationRules = [
        'name' => 'required',
        'email' => 'required|email|unique:patients,email',
        'contacto.telefono' => 'required|regex:/^[0-9]{10}$/', // Assuming a 10-digit phone number
        'password' => 'required'
    ];

    public function store(Request $request)
    {
        $data = $request->all();
        $email = $request->input('email');
        $telefono = data_get($data, 'contacto.telefono' || $data['contacto']['telefono']);
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
                'message' => "Error al agregar paciente",
                'type' => "error"
            ], 400);
        }

        if ($isNewPatient) {
            if (!$telefono) {
                return response()->json([
                    'rasson' => "El telefono es requerido",
                    'message' => "Error al agregar paciente",
                    'type' => "error"
                ], 400);
            }

            $data["password"] = Hash::make($request->input('password', $telefono));

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
                    'rasson' => "El paciente ya se encuentra enlazado a su cuenta.",
                    'message' => "Paciente ya agregado",
                    'type' => "info",
                    "data" => ['patient_id' => $patient->id] // Puedes devolver el ID del paciente si lo necesitas
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
                ? "El paciente se creó y se le envió una invitación con éxito. Espera a que acepte la invitación para poder agendarle citas."
                : "El paciente existente fue enlazado con éxito a tu cuenta. Se le envió una notificación. Espera a que la acepte para poder agendarle citas.";

            return response()->json(
                [
                    'rasson' => $successMessage,
                    'message' => "Paciente agregado",
                    'type' => "success",
                    "data" => $enlace
                ],
                200
            );
        }

        return response()->json([
            'rasson' => "Error desconocido al intentar finalizar el proceso de enlace del paciente.",
            'message' => "Error al agregar paciente",
            'type' => "error"
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
                'rasson' => "Actualizacion de relaciones exitosa",
                'message' => "Modificacion exitosa",
                'type' => "success",
            ],
            200
        );
    }
    public function sendNotificacionEmailByUser($user, $patient, $enlace)
    {
        if ($enlace) {
            try {
                //code...
                $patient->notify(new PatientAssignedEmailNotification($user, $patient, $enlace));
                return true;
            } catch (\Throwable $th) {
                Log::error($th->getMessage());
                //throw $th;
            }
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Patient $patient)
    {

        return response()->json($patient, 200);
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
    public function update(Request $request, Patient $patient)
    {
        try {
            $patient->update($request->all());
            $response = [
                'rasson' => 'El usuario se a actualizado correctamente',
                'message' => "Usuario actulizado ",
                'type' => "success"
            ];
        } catch (\Throwable $th) {
            $response = [
                'rasson' => 'El usuario no se a actualizado correctamente',
                'message' => "Usuario no actulizado",
                'type' => "error"
            ];
        }

        return response()->json($response, 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Patient $patient) {}
}
