<?php
namespace App\Http\Controllers;

use App\Models\Patient;
use App\Models\PatientMedication;
use Illuminate\Http\Request;

class PatientMedicationController extends Controller
{
    public function __construct()
    {
        // Asegura que venga el token y el usuario esté autenticado
        $this->middleware(['auth:sanctum', 'handle_invalid_token', 'user']);
    }

    // Listado de medicamentos (solo de pacientes propios)
    public function index($patientId)
    {
        $patient = auth()->user()
            ->patientUsers()
            ->findOrFail($patientId);
        // Verifica que el paciente pertenezca al usuario autenticado

        return $patient
            ->medications()
            ->orderBy('start_date', 'desc')
            ->get();
    }

    // Nuevo medicamento
    public function store(Request $request, $patientId)
    {
        $patient = auth()->user()
            ->patientUsers()
            ->findOrFail($patientId);

        $data = $request->validate([
            'medication_name' => 'required|string|max:255',
            'dosage' => 'nullable|string|max:100',
            'frequency' => 'nullable|string|max:100',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'notes' => 'nullable|string',
        ]);
        // return response()->json($patient->patient, 200);
        $data['patient_id'] = $patient->patient; // Asigna el ID del paciente
        $data['user_id'] = auth()->id(); // Asigna el ID del usuario autenticado
        $med = $patient->medications()->create($data);

        return response()->json($med, 201);
    }

    // Actualizar
    public function update(Request $request, $patientId, $id)
    {
        $patient = auth()->user()
            ->patientUsers()
            ->findOrFail($patientId);

        $med = $patient->medications()->findOrFail($id);

        $data = $request->validate([
            'medication_name' => 'sometimes|required|string|max:255',
            'dosage' => 'nullable|string|max:100',
            'frequency' => 'nullable|string|max:100',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'notes' => 'nullable|string',
        ]);
        $data['patient_id'] = $patient->patient->id; // Asigna el ID del paciente
        $data['user_id'] = auth()->id(); // Asigna el ID del usuario autenticado
        $med->update($data);
        return response()->json($med);
    }

    // Eliminar
    public function destroy($patientId, $id)
    {
        $patient = auth()->user()
                         ->patientUsers()
                         ->findOrFail($patientId);;

        $patient->medications()
            ->findOrFail($id)
            ->delete();

        return response()->json([], 204);
    }
}
