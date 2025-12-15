<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\AppointmentCart;
use App\Models\Patient;
use App\Models\User;
use App\Models\PatientUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Notifications\StateAppoinmentMail;
use App\Notifications\CreateAppoinmentMail;
use Response;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use App\Services\AppointmentService;
use App\Services\GoogleCalendarService;
use App\Services\InvalidGoogleTokenException;
use App\Jobs\SyncAppointmentToGoogleCalendar;
use Illuminate\Support\Facades\Crypt;


class AppointmentController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    protected $service;
    protected $googleCalendarService;
    public function __construct(AppointmentService $service, GoogleCalendarService $googleCalendarService)
    {
        $this->service = $service;
        $this->googleCalendarService = $googleCalendarService;
    }
    public function index(Request $request)
    {
        $user = Auth::user();
        $Appointments = Appointment::with('payments')->where("user", $user->id)->get();
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
            $appoinments = Appointment::where('user', $user->id)
                ->where('patient', $patientId)
                ->orderBy('id', 'desc')
                ->get();
        }

        if (in_array("patient", $middlewares)) {
            $appoinments = Appointment::where("patient", $user->id)->with(['user', 'payments'])->get();
        }




        return response()->json($appoinments, 200);
    }

    public function getAvailableSlots(Request $request, $id)
    {
        $now = Carbon::now();
        $start = Carbon::parse($request->start)->startOfDay();
        $end = Carbon::parse($request->end)->endOfDay();

        $user = User::findOrFail($id);
        $workingHours = $user->horarios;

        $appointments = Appointment::where('user', $id)
            ->whereBetween('start', [$start, $end])
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
                $blockStart = Carbon::parse("$fecha {$block['start']}");
                $blockEnd = Carbon::parse("$fecha {$block['end']}");

                $slotStart = $blockStart->gt($now)
                    ? $blockStart->copy()
                    : $now->copy();

                while ($slotStart->lt($blockEnd)) {
                    $slotEnd = $slotStart->copy()->addMinutes(50);

                    if ($slotEnd->gt($blockEnd))
                        break;

                    $empalme = $appointments->contains(function ($a) use ($slotStart, $slotEnd) {
                        return $slotStart->lt($a->end) && $slotEnd->gt($a->start);
                    });

                    if (!$empalme) {
                        $slots[] = [
                            'date' => $fecha,
                            'hour' => $slotStart->format('H:i'),
                        ];
                        $slotsFoundToday = true;
                    }

                    $slotStart->addMinutes(60); // siguiente slot real
                }
            }

            if ($slotsFoundToday) {
                $uniqueDays[] = $fecha;
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
            $request['user'] = $authUser->id;
        } elseif (in_array('patient', $middlewares)) {
            $request['patient'] = $authUser->id;
        } else {
            return response()->json([
                'rasson' => 'Middleware inválido',
                'message' => "No se pudo crear la cita",
                'type' => "error"
            ], 403);
        }
        $relation = $this->service->ensureRelationshipAndRoom($request['user'], $request['patient']);
        $request->video_call_room = $relation->video_call_room;
        $appointment = Appointment::create($request->except(['costo', 'formato', 'tipoSesion']));
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


        $cart = AppointmentCart::create([
            'appointment_id' => $appointment->id,
            'tipoSesion' => $request->tipoSesion,
            'formato' => $request->formato ?? 'online',
            'precio' => $request->costo ?? 0,
            'status' => 'pending',
            'patient_id' => $appointment->patient,
            'user_id' => $appointment->user,
            'duracion' => "0"

        ]);

        if ($cart) {
            $appointment->cart_id = $cart->id;
            $appointment->save();
        }
        $user = User::find($appointment->user);

        // Lógica para despachar el job
        if ($request->boolean('syncWithGoogle')) {
            if ($user->googleAccount && $user->googleAccount->refresh_token) {
                SyncAppointmentToGoogleCalendar::dispatch($appointment, $user, 'create');
            } else {
                // --- INICIO DE LA MODIFICACIÓN ---

                // 1. Preparamos los datos que necesitamos recordar.
                $statePayload = [
                    'user_id' => $user->id,
                    'appointment_id' => $appointment->id,
                ];

                // 2. Encriptamos los datos en una cadena segura.
                $encryptedState = Crypt::encrypt(json_encode($statePayload));

                // 3. Obtenemos la URL de autenticación y le adjuntamos nuestro estado encriptado.
                $authUrl = $this->googleCalendarService->getAuthUrl($encryptedState);

                // Ya no usamos la sesión.
                // session(['pending_google_sync_appointment_id' => $appointment->id]);

                return response()->json([
                    'action' => 'redirect_to_google_auth',
                    'url' => $authUrl
                ], 202);

                // --- FIN DE LA MODIFICACIÓN ---
            }
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
        $appointment = Appointment::where('id', $appointment->id)->with(['patient', 'payments', 'cart'])->first();
        return response()->json(['appointment' => $appointment], 200);
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

        $originalData = Appointment::with('user')->find($appointment->id);
        $updatedData = $request->except(['patient', 'cart', 'payments']);
        $fieldsToUpdate = [];
        $user = User::find($originalData->user);
        $arrayOriginal = $originalData->toArray();

        foreach ($updatedData as $key => $value) {
            if (array_key_exists($key, $arrayOriginal) && $arrayOriginal[$key] != $value) {
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
            if ($originalData->google_event_id) { // Esta línea parece ser para depuración
                $originalData->load('user');
                if ($user && $user->googleAccount) {
                    SyncAppointmentToGoogleCalendar::dispatch($originalData, $user, 'update');
                }
            }
            $appointment->update($fieldsToUpdate);
            $send = $this->sendNotificacionStatusEmail($originalData);

            return response()->json([
                'rasson' => 'La cita cambio sus caracteristicas con exito',
                'message' => "Cita modificada",
                'type' => "success"
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'rasson' => 'No se logro cambiar la cita con exito' . $th->getMessage(),
                'message' => "Cita no modificada",
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
        //codigo para cancelar cita
        $professional = $appointment->user;

        /*
        if ($appointment->google_event_id && $professional && $professional->googleAccount) {
            // --- LÍNEA CLAVE ---
            // Despachamos el job para eliminar el evento.
            SyncAppointmentToGoogleCalendar::dispatch($appointment, $professional, 'delete');
        }

        $appointment->delete();

        return response()->json([
            'message' => "Cita cancelada correctamente",
            'type' => "success"
        ], 200);
    }*/
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
