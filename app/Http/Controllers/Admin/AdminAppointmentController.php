<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Patient;
use App\Models\User;
use App\Models\Availabiliti;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class AdminAppointmentController extends Controller
{
    /**
     * Obtener todas las citas de un paciente
     */
    public function index($patientId)
    {
        try {
            $appointments = Appointment::with(['user:id,name,email,image', 'patient:id,name,email'])
                ->where('patient', $patientId)
                ->orderBy('start', 'desc')
                ->get()
                ->map(function ($appointment) {
                    $extendedProps = is_array($appointment->extendedProps) ? $appointment->extendedProps : [];
                    return [
                        'id' => $appointment->id,
                        'user_id' => $appointment->user,
                        'patient_id' => $appointment->patient,
                        'fecha_inicio' => $appointment->start,
                        'fecha_fin' => $appointment->end,
                        'motivo' => $appointment->title,
                        'observaciones' => $appointment->comments,
                        'tipo' => $extendedProps['tipo'] ?? 'virtual',
                        'state' => $appointment->state,
                        'user' => $appointment->user()->first(['id', 'name', 'email', 'image']),
                        'patient' => $appointment->patient()->first(['id', 'name', 'email']),
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => $appointments
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error fetching appointments: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Error al obtener las citas'
            ], 500);
        }
    }

    /**
     * Crear una nueva cita
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'patient_id' => 'required|exists:patients,id',
            'user_id' => 'required|exists:users,id',
            'fecha_inicio' => 'required|date',
            'fecha_fin' => 'required|date|after:fecha_inicio',
            'tipo' => 'nullable|in:presencial,virtual',
            'state' => 'nullable|in:Creado,Confirmado,Completado,Cancelado,No asistió,programada,confirmada,completada,cancelada,no_asistio',
            'motivo' => 'nullable|string|max:255',
            'observaciones' => 'nullable|string',
            'link' => 'nullable|url'
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
            // Verificar disponibilidad del psicólogo
            $conflict = Appointment::where('user', $request->user_id)
                ->where(function ($query) use ($request) {
                    $query->whereBetween('start', [$request->fecha_inicio, $request->fecha_fin])
                        ->orWhereBetween('end', [$request->fecha_inicio, $request->fecha_fin])
                        ->orWhere(function ($q) use ($request) {
                            $q->where('start', '<=', $request->fecha_inicio)
                                ->where('end', '>=', $request->fecha_fin);
                        });
                })
                ->whereNotIn('state', ['cancelada', 'Cancelado'])
                ->exists();

            if ($conflict) {
                return response()->json([
                    'success' => false,
                    'message' => 'El psicólogo ya tiene una cita en ese horario'
                ], 422);
            }

            // Crear la cita
            $appointment = Appointment::create([
                'user' => $request->user_id,
                'patient' => $request->patient_id,
                'title' => $request->motivo ?? 'Cita programada',
                'start' => $request->fecha_inicio,
                'end' => $request->fecha_fin,
                'comments' => $request->observaciones,
                'state' => $request->state ?? 'Creado',
                'link' => $request->link,
                'extendedProps' => [
                    'tipo' => $request->tipo ?? 'virtual'
                ],
                'statusUser' => 'pendiente',
                'statusPatient' => 'pendiente',
            ]);

            DB::commit();

            $appointment->load('user', 'patient');

            return response()->json([
                'success' => true,
                'message' => 'Cita creada exitosamente',
                'data' => $appointment
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error creating appointment: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Error al crear la cita: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener una cita específica
     */
    public function show($id)
    {
        try {
            $appointment = Appointment::with(['user', 'patient'])
                ->findOrFail($id);

            $extendedProps = is_array($appointment->extendedProps) ? $appointment->extendedProps : [];
            $data = [
                'id' => $appointment->id,
                'user_id' => $appointment->user,
                'patient_id' => $appointment->patient,
                'fecha_inicio' => $appointment->start,
                'fecha_fin' => $appointment->end,
                'motivo' => $appointment->title,
                'observaciones' => $appointment->comments,
                'tipo' => $extendedProps['tipo'] ?? 'virtual',
                'state' => $appointment->state,
                'link' => $appointment->link,
                'user' => $appointment->user()->first(['id', 'name', 'email', 'image']),
                'patient' => $appointment->patient()->first(['id', 'name', 'email']),
            ];

            return response()->json([
                'success' => true,
                'data' => $data
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error fetching appointment: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Cita no encontrada'
            ], 404);
        }
    }

    /**
     * Actualizar una cita
     */
    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'sometimes|required|exists:users,id',
            'fecha_inicio' => 'sometimes|required|date',
            'fecha_fin' => 'sometimes|required|date|after:fecha_inicio',
            'tipo' => 'nullable|in:presencial,virtual',
            'observaciones' => 'nullable|string',
            'motivo' => 'nullable|string|max:255',
            'state' => 'nullable|in:Creado,Confirmado,Completado,Cancelado,No asistió,programada,confirmada,completada,cancelada,no_asistio'
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
            $appointment = Appointment::findOrFail($id);

            // Si se cambian las fechas, verificar disponibilidad
            if ($request->has('fecha_inicio') || $request->has('fecha_fin')) {
                $start = $request->fecha_inicio ?? $appointment->start;
                $end = $request->fecha_fin ?? $appointment->end;
                $userId = $request->user_id ?? $appointment->user;

                $conflict = Appointment::where('user', $userId)
                    ->where('id', '!=', $id)
                    ->where(function ($query) use ($start, $end) {
                        $query->whereBetween('start', [$start, $end])
                            ->orWhereBetween('end', [$start, $end])
                            ->orWhere(function ($q) use ($start, $end) {
                                $q->where('start', '<=', $start)
                                    ->where('end', '>=', $end);
                            });
                    })
                    ->whereNotIn('state', ['cancelada', 'Cancelado'])
                    ->exists();

                if ($conflict) {
                    return response()->json([
                        'success' => false,
                        'message' => 'El psicólogo ya tiene una cita en ese horario'
                    ], 422);
                }
            }

            // Mapear campos del request al modelo
            $updateData = [];
            if ($request->has('user_id')) {
                $updateData['user'] = $request->user_id;
            }
            if ($request->has('fecha_inicio')) {
                $updateData['start'] = $request->fecha_inicio;
            }
            if ($request->has('fecha_fin')) {
                $updateData['end'] = $request->fecha_fin;
            }
            if ($request->has('motivo')) {
                $updateData['title'] = $request->motivo;
            }
            if ($request->has('observaciones')) {
                $updateData['comments'] = $request->observaciones;
            }
            if ($request->has('link')) {
                $updateData['link'] = $request->link;
            }
            if ($request->has('state')) {
                $updateData['state'] = $request->state;
            }

            // Manejar extendedProps
            if ($request->has('tipo')) {
                $extendedProps = $appointment->extendedProps ?? [];
                $extendedProps['tipo'] = $request->tipo;
                $updateData['extendedProps'] = $extendedProps;
            }

            $appointment->update($updateData);

            DB::commit();

            $appointment->load('user', 'patient');

            return response()->json([
                'success' => true,
                'message' => 'Cita actualizada exitosamente',
                'data' => $appointment
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error updating appointment: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar la cita'
            ], 500);
        }
    }

    /**
     * Cancelar una cita
     */
    public function destroy($id)
    {
        try {
            $appointment = Appointment::findOrFail($id);
            $appointment->update([
                'state' => 'Cancelado',
                'statusUser' => 'Cancelado',
                'statusPatient' => 'Cancelado'
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Cita cancelada exitosamente'
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error canceling appointment: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Error al cancelar la cita'
            ], 500);
        }
    }

    /**
     * Obtener disponibilidad del psicólogo
     */
    public function getAvailability(Request $request, $psychologistId)
    {
        try {
            $date = $request->input('date', now()->format('Y-m-d'));
            $dayName = Carbon::parse($date)->locale('es')->isoFormat('dddd'); // lunes, martes, etc.

            // Obtener horarios configurados del psicólogo
            $availability = Availabiliti::where('user_id', $psychologistId)
                ->where('day', $dayName)
                ->get();

            // Obtener citas existentes para ese día
            $appointments = Appointment::where('user', $psychologistId)
                ->whereDate('start', $date)
                ->whereNotIn('state', ['cancelada', 'Cancelado'])
                ->get(['start', 'end']);

            $hasAvailability = $availability->isNotEmpty();

            return response()->json([
                'success' => true,
                'available' => $hasAvailability,
                'data' => [
                    'availability' => $availability,
                    'appointments' => $appointments,
                    'date' => $date
                ]
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error fetching availability: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Error al obtener disponibilidad'
            ], 500);
        }
    }

    /**
     * Obtener estadísticas de citas
     */
    public function getStats($patientId)
    {
        try {
            $stats = [
                'total' => Appointment::where('patient', $patientId)->count(),
                'pendientes' => Appointment::where('patient', $patientId)
                    ->whereIn('state', ['Creado', 'programada', 'pendiente'])
                    ->count(),
                'completadas' => Appointment::where('patient', $patientId)
                    ->whereIn('state', ['Completado', 'completada'])
                    ->count(),
                'canceladas' => Appointment::where('patient', $patientId)
                    ->whereIn('state', ['Cancelado', 'cancelada'])
                    ->count(),
                'proxima' => Appointment::where('patient', $patientId)
                    ->where('start', '>', now())
                    ->whereNotIn('state', ['Cancelado', 'cancelada'])
                    ->orderBy('start', 'asc')
                    ->with('user')
                    ->first()
            ];

            return response()->json([
                'success' => true,
                'data' => $stats
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error fetching appointment stats: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Error al obtener estadísticas'
            ], 500);
        }
    }
}
