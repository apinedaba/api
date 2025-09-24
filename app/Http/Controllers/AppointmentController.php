<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\Patient;
use App\Models\User;
use App\Models\PatientUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Notifications\StateAppoinmentMail;
use App\Notifications\CreateAppoinmentMail;
use Response;
use Carbon\Carbon;
use \Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use App\Services\AppointmentService;

class AppointmentController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    protected $service;

    public function __construct(AppointmentService $service)
    {
        $this->service = $service;
    }
    public function index(Request $request)
    {
        $user = Auth::user();
        $Appointments = Appointment::where("user", $user->id)-with('payments')->get();
        return response()->json($Appointments, 200);
    }
    /**
     * Display a listing of the resource.
     */
    public function getAppoinmentsByPatient(Request $request)
    {
        $route = Route::getCurrentRoute();
        $middlewares = $route->gatherMiddleware();
        $user = Auth::user();
        $requestAll = $request->all();

        if (in_array("user", $middlewares)) {
            $patientId = $requestAll['patient'];
            $enlaceId = $requestAll['id'];
            $filter = [
                'id' => $enlaceId,
                'patient' => $patientId,
                'user' => $user->id
            ];
            $enlace = PatientUser::where($filter)->first();

            if (!isset($enlace['id'])) {
                return response()->json([
                    'rasson' => 'No se pudo obtener informacion de la  consulta',
                    'message' => "No coincide la informacion",
                    'type' => "error",
                    'middleware' => $middlewares
                ], 400);
            }
            $appoinments = Appointment::where('user', $user->id)->where('patient', $patientId)->get();
        }

        if (in_array("patient", $middlewares)) {
            $appoinments = Appointment::where("patient", $user->id)->with(['user', 'payments'])->get();
        }




        return response()->json($appoinments, 200);
    }

    public function getAvailableSlots(Request $request, $id)
    {
        $now = Carbon::now();
        $start = $now->copy()->startOfDay();
        $end = $start->copy()->addDays(30); // rango máximo

        $user = User::findOrFail($id);
        $workingHours = $user->horarios;

        $appointments = Appointment::where('user', $id)
            ->whereBetween('start', [$now, $end])
            ->get();

        $slots = [];
        $uniqueDays = [];

        $current = $start->copy();

        while ($current->lte($end) && count($uniqueDays) < 5) {
            $weekday = strtolower($current->format('l'));
            $fecha = $current->format('Y-m-d');

            if (empty($workingHours[$weekday])) {
                $current->addDay();
                continue;
            }

            $slotsFoundToday = false;

            foreach ($workingHours[$weekday] as $block) {
                $blockStart = Carbon::parse($fecha . ' ' . $block['start']);
                $blockEnd = Carbon::parse($fecha . ' ' . $block['end']);

                if ($blockStart->gte($blockEnd)) {
                    continue; // Bloque inválido
                }

                $slotStart = $blockStart->greaterThan($now) ? $blockStart->copy() : max($blockStart, $now->copy());

                while ($slotStart->lt($blockEnd)) {
                    $slotEnd = $slotStart->copy()->addHour();

                    if ($slotEnd->gt($blockEnd))
                        break;

                    if ($slotStart->isPast()) {
                        $slotStart->addHour();
                        continue;
                    }

                    $empalme = $appointments->contains(function ($a) use ($slotStart, $slotEnd) {
                        $aStart = Carbon::parse($a->start);
                        $aEnd = Carbon::parse($a->end);
                        return $slotStart->lt($aEnd) && $slotEnd->gt($aStart);
                    });

                    if (!$empalme) {
                        $slots[] = [
                            'date' => $fecha,
                            'hour' => $slotStart->format('H:i'),
                        ];
                        $slotsFoundToday = true;
                    }

                    $slotStart->addHour();
                }
            }

            if ($slotsFoundToday) {
                $uniqueDays[] = $fecha; // Agrega este día a los días únicos con slots
            }

            $current->addDay();
        }

        return response()->json($slots);
    }





    public function store(Request $request)
    {
        $route = Route::getCurrentRoute();
        $middlewares = $route->gatherMiddleware();
        $authUser = Auth::user();

        // Validar campos mínimos requeridos
        $validated = $request->validate([
            'start' => 'required|date',
            'end' => 'required',
            'title' => 'required|string|max:255',
            'user' => 'required_if:middleware,patient',
            'patient' => 'required_if:middleware,user',
            'costo' => 'nullable|numeric', 
            'tipo' => 'nullable|string',
        ]);

        // Forzar asignación correcta
        if (in_array('user', $middlewares)) {
            $validated['user'] = $authUser->id;
        } elseif (in_array('patient', $middlewares)) {
            $validated['patient'] = $authUser->id;
        } else {
            return response()->json([
                'rasson' => 'Middleware inválido',
                'message' => "No se pudo crear la cita",
                'type' => "error"
            ], 403);
        }

        // 🔥 1️⃣ Usar el servicio para validar o crear relación con video_call_room
        $relation = $this->service->ensureRelationshipAndRoom($validated['user'], $validated['patient']);


        $validated['video_call_room'] = $relation->video_call_room;

        // 🔥 2️⃣ Crear la cita con el room correcto
        $appointment = Appointment::create($validated);

        if (!$appointment) {
            return response()->json([
                'rasson' => 'No se logró crear la cita',
                'message' => "Cita no creada",
                'type' => "error"
            ], 400);
        }

        // Notificación
        $send = $this->sendNotificacionCreateAppoimentEmail($appointment);
        event(new \App\Events\AppointmentCreated(
            appointmentId: $appointment->id,
            psychologistId: $appointment->user,
            patientId: $appointment->patient
        ));
        if (!$send) {
            return response()->json([
                'rasson' => 'No se logró enviar la notificación vía email',
                'message' => "Cita creada sin notificación",
                'type' => "warning",
                'appointment' => $appointment
            ], 200);
        }

        return response()->json([
            'rasson' => 'Se creó la cita correctamente',
            'message' => "Cita creada",
            'type' => "success",
            'appointment' => $appointment
        ], 200);
    }

    /**
     * Display the specified resource.
     */
    public function show(Appointment $appointment)
    {
        $appointment = Appointment::where('id', $appointment->id)->first();
        $patient = Patient::where('id', $appointment->patient)->first();
        return response()->json(['appointment' => $appointment, 'patient' => $patient], 200);
    }
    public function showABP($id)
    {

        $patient = Auth::user();
        $appointment = Appointment::where('id', $id)->where('patient', $patient->id)->with(['cart', 'user'])->first();
        return response()->json($appointment, 200);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Appointment $appointment)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Appointment $appointment)
    {

        $originalData = $appointment->toArray();
        $updatedData = $request->all();
        $fieldsToUpdate = [];

        foreach ($updatedData as $key => $value) {
            if (array_key_exists($key, $originalData) && $originalData[$key] != $value) {
                if ($key === 'created_at' || $key === 'updated_at') {
                    continue;
                }
                $fieldsToUpdate[$key] = $value;
            }
        }
        if (empty($fieldsToUpdate)) {
            return response()->json([
                'rasson' => 'No se detectaron cambios en la cita',
                'message' => "Sin modificaciones",
                'type' => "info"
            ], 200);
        }

        try {

            $appointment->update($fieldsToUpdate);
            $send = $this->sendNotificacionStatusEmail($appointment);

            return response()->json([
                'rasson' => 'La cita cambio sus caracteristicas con exito',
                'message' => "Cita modificada",
                'type' => "success"
            ], 200);

        } catch (\Throwable $th) {
            return response()->json([
                'rasson' => 'No se logro cambiar la cita con exito',
                'message' => "Cita modificada",
                'type' => "error"
            ], 400);
            //throw $th;
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Appointment $appointment)
    {
        //
    }

    public function sendNotificacionStatusEmail($appointment)
    {
        try {
            // Obtener el paciente
            $patient = Patient::where('id', $appointment->patient)->first();

            // Obtener estado desde el status del usuario
            $estado = $appointment->statusUser;

            // Convertir start y end a objetos Carbon
            $start = Carbon::parse($appointment->start);
            $end = Carbon::parse($appointment->end);

            // Extraer la fecha en formato legible
            $fecha = $start->format('d/m/Y');

            // Extraer la hora en formato legible
            $hora = $start->format('H:i') . ' - ' . $end->format('H:i');

            // Enviar notificación
            $patient->notify(new StateAppoinmentMail($patient, $estado, $fecha, $hora));

            return true;
        } catch (\Throwable $th) {
            Log::error($th->getMessage());
        }
    }
    public function sendNotificacionCreateAppoimentEmail($appointment)
    {

        // Convertir start y end a objetos Carbon
        $start = Carbon::parse($appointment->start);
        $end = Carbon::parse($appointment->end);
        // Obtener el intervalo
        $interval = $start->diff($end);
        // Extraer la fecha en formato legible
        $fecha = $start->format('d/m/Y');

        // Extraer la hora en formato legible
        $hora = $start->format('H:i') . ' - ' . $end->format('H:i');

        try {
            //code...
            $patient = Patient::where('id', $appointment->patient)->first();
            $patient->notify(new CreateAppoinmentMail($appointment, $patient, $hora, $fecha, $interval));
            return true;
        } catch (\Throwable $th) {
            Log::error($th->getMessage());
            //throw $th;
        }
    }
}
