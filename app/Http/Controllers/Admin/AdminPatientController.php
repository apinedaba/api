<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Patient;
use App\Models\PatientUser;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Notifications\PsychologistAssignedByAdmin;
use App\Notifications\PatientAssignedPsychologistByAdmin;

class AdminPatientController extends Controller
{
    /**
     * Obtener todos los pacientes con sus psicólogos asignados
     */
    public function index()
    {
        try {
            $patients = Patient::with([
                'connections' => function ($query) {
                    $query->with('user:id,name,email,image');
                },
                'medications'
            ])->get();

            return response()->json([
                'success' => true,
                'data' => $patients
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error fetching patients: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener los pacientes'
            ], 500);
        }
    }

    /**
     * Crear un nuevo paciente
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:patients,email',
            'contacto.telefono' => 'required|regex:/^[0-9]{10}$/',
            'password' => 'nullable|string|min:6',
            'address' => 'nullable|array',
            'psychologist_id' => 'nullable|exists:users,id',
            'activo' => 'boolean'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();

        try {
            $data = $request->all();
            $telefono = data_get($data, 'contacto.telefono');

            // Crear contraseña: usar la proporcionada o el teléfono
            $data['password'] = Hash::make($request->input('password', $telefono));
            $data['activo'] = $request->input('activo', true);
            $data['status'] = 'Registrado';

            $patient = Patient::create($data);

            // Si se proporcionó un psicólogo, crear la relación
            if ($request->has('psychologist_id')) {
                PatientUser::create([
                    'user' => $request->psychologist_id,
                    'patient' => $patient->id,
                    'activo' => true,
                    'status' => 'Asignado por Administrador'
                ]);

                // Enviar notificaciones
                try {
                    $psychologist = User::findOrFail($request->psychologist_id);
                    $admin = auth()->guard('web')->user();

                    // Notificar al psicólogo
                    $psychologist->notify(new PsychologistAssignedByAdmin($patient, $admin, true));

                    // Notificar al paciente
                    $patient->notify(new PatientAssignedPsychologistByAdmin($psychologist, $admin, true));

                    Log::info("Notificaciones enviadas al crear paciente: Psicólogo ID {$request->psychologist_id} asignado a Paciente ID {$patient->id}");
                } catch (\Exception $e) {
                    Log::error('Error al enviar notificaciones al crear paciente: ' . $e->getMessage());
                    // No fallar la creación si las notificaciones fallan
                }
            }

            DB::commit();

            $patient->load('connections.user');

            return response()->json([
                'success' => true,
                'message' => 'Paciente creado exitosamente',
                'data' => $patient
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error creating patient: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Error al crear el paciente: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener un paciente específico con toda su información
     */
    public function show($id)
    {
        try {
            $patient = Patient::with([
                'connections' => function ($query) {
                    $query->with('user:id,name,email,image,especialidad')
                        ->orderBy('activo', 'desc');
                },
                'medications'
            ])->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $patient
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error fetching patient: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Paciente no encontrado'
            ], 404);
        }
    }

    /**
     * Actualizar un paciente
     */
    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'email' => 'sometimes|required|email|unique:patients,email,' . $id,
            'contacto.telefono' => 'sometimes|required|regex:/^[0-9]{10}$/',
            'address' => 'nullable|array',
            'activo' => 'boolean'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $patient = Patient::findOrFail($id);
            $patient->update($request->all());
            $patient->load('connections.user');

            return response()->json([
                'success' => true,
                'message' => 'Paciente actualizado exitosamente',
                'data' => $patient
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error updating patient: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar el paciente'
            ], 500);
        }
    }

    /**
     * Eliminar (desactivar) un paciente
     */
    public function destroy($id)
    {
        try {
            $patient = Patient::findOrFail($id);
            $patient->update(['activo' => false]);

            return response()->json([
                'success' => true,
                'message' => 'Paciente desactivado exitosamente'
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error deactivating patient: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Error al desactivar el paciente'
            ], 500);
        }
    }

    /**
     * Asignar un psicólogo a un paciente
     */
    public function assignPsychologist(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'psychologist_id' => 'required|exists:users,id',
            'set_as_active' => 'boolean'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first()
            ], 422);
        }

        DB::beginTransaction();

        try {
            $patient = Patient::findOrFail($id);
            $psychologistId = $request->psychologist_id;
            $setAsActive = $request->input('set_as_active', false);

            // Verificar si la relación ya existe
            $existingRelation = PatientUser::where('patient', $patient->id)
                ->where('user', $psychologistId)
                ->first();

            if ($existingRelation) {
                // Si se solicita activar, desactivar otros y activar este
                if ($setAsActive) {
                    PatientUser::where('patient', $patient->id)
                        ->update(['activo' => false]);

                    $existingRelation->update(['activo' => true]);
                }

                DB::commit();

                return response()->json([
                    'success' => true,
                    'message' => 'La relación ya existe',
                    'data' => $existingRelation->load('user')
                ], 200);
            }

            // Si se solicita activar, desactivar las otras relaciones
            if ($setAsActive) {
                PatientUser::where('patient', $patient->id)
                    ->update(['activo' => false]);
            }

            // Crear nueva relación
            $relation = PatientUser::create([
                'user' => $psychologistId,
                'patient' => $patient->id,
                'activo' => $setAsActive,
                'status' => 'Asignado por Administrador'
            ]);

            // Obtener el psicólogo y el administrador actual
            $psychologist = User::findOrFail($psychologistId);
            $admin = auth()->guard('web')->user();

            // Enviar notificaciones
            try {
                // Notificar al psicólogo
                $psychologist->notify(new PsychologistAssignedByAdmin($patient, $admin, $setAsActive));

                // Notificar al paciente
                $patient->notify(new PatientAssignedPsychologistByAdmin($psychologist, $admin, $setAsActive));

                Log::info("Notificaciones enviadas: Psicólogo ID {$psychologistId} asignado a Paciente ID {$patient->id}");
            } catch (\Exception $e) {
                Log::error('Error al enviar notificaciones de asignación: ' . $e->getMessage());
                // No fallar la asignación si las notificaciones fallan
            }

            DB::commit();

            $relation->load('user');

            return response()->json([
                'success' => true,
                'message' => 'Psicólogo asignado exitosamente',
                'data' => $relation
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error assigning psychologist: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Error al asignar el psicólogo'
            ], 500);
        }
    }

    /**
     * Remover un psicólogo de un paciente
     */
    public function removePsychologist($patientId, $psychologistId)
    {
        try {
            $relation = PatientUser::where('patient', $patientId)
                ->where('user', $psychologistId)
                ->firstOrFail();

            $relation->delete();

            return response()->json([
                'success' => true,
                'message' => 'Psicólogo removido exitosamente'
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error removing psychologist: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Error al remover el psicólogo'
            ], 404);
        }
    }

    /**
     * Activar un psicólogo específico para un paciente
     */
    public function setActivePsychologist($patientId, $psychologistId)
    {
        DB::beginTransaction();

        try {
            // Desactivar todos los psicólogos del paciente
            PatientUser::where('patient', $patientId)
                ->update(['activo' => false]);

            // Activar el psicólogo especificado
            $relation = PatientUser::where('patient', $patientId)
                ->where('user', $psychologistId)
                ->firstOrFail();

            $relation->update(['activo' => true]);

            // Obtener modelos para notificaciones
            $patient = Patient::findOrFail($patientId);
            $psychologist = User::findOrFail($psychologistId);
            $admin = auth()->guard('web')->user();

            // Enviar notificaciones
            try {
                // Notificar al psicólogo que ahora es el principal
                $psychologist->notify(new PsychologistAssignedByAdmin($patient, $admin, true));

                // Notificar al paciente del cambio
                $patient->notify(new PatientAssignedPsychologistByAdmin($psychologist, $admin, true));

                Log::info("Notificaciones enviadas: Psicólogo ID {$psychologistId} activado como principal para Paciente ID {$patientId}");
            } catch (\Exception $e) {
                Log::error('Error al enviar notificaciones de activación: ' . $e->getMessage());
                // No fallar la operación si las notificaciones fallan
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Psicólogo principal actualizado',
                'data' => $relation->load('user')
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error setting active psychologist: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Error al establecer el psicólogo principal'
            ], 500);
        }
    }

    /**
     * Obtener lista de psicólogos disponibles
     */
    public function getAvailablePsychologists()
    {
        try {
            $psychologists = User::select('id', 'name', 'email', 'image', 'identity_verification_status', 'activo')
                ->orderBy('name', 'asc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $psychologists
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error fetching psychologists: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Error al obtener los psicólogos'
            ], 500);
        }
    }
}
