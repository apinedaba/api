<?php

namespace App\Http\Controllers;

use App\Events\AppointmentCreated;
use App\Http\Requests\StoreAppointmentRequestRequest;
use App\Http\Requests\UpdateAppointmentRequestRequest;
use App\Jobs\SyncAppointmentToGoogleCalendar;
use App\Models\Appointment;
use App\Models\AppointmentRequest;
use App\Models\PatientUser;
use App\Models\User;
use App\Notifications\CreateAppoinmentMail;
use App\Notifications\ProfessionalAppointmentCreatedNotification;
use App\Services\AppointmentService;
use App\Services\WhatsApp\AppointmentWhatsAppNotifier;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AppointmentRequestController extends Controller
{
    public function __construct(private AppointmentService $appointmentService)
    {
    }

    /**
     * POST /appointment-requests
     *
     * Crea una nueva solicitud de cita.
     * Usada desde el dashboard del paciente/usuario.
     */
    public function store(StoreAppointmentRequestRequest $request): JsonResponse
    {
        $patient = $request->user();
        $data = $request->validated();

        $hasActiveRelation = PatientUser::where('patient', $patient->id)
            ->where('user', $data['psychologist_id'])
            ->where('activo', true)
            ->whereNull('archived_at')
            ->exists();

        if (! $hasActiveRelation) {
            return response()->json([
                'message' => 'Solo puedes solicitar citas con un psicólogo vinculado a tu cuenta.',
                'type' => 'error',
            ], 403);
        }

        $alreadyPending = AppointmentRequest::where('patient_id', $patient->id)
            ->where('psychologist_id', $data['psychologist_id'])
            ->where('date', $data['date'])
            ->where('time', $data['time'])
            ->pending()
            ->exists();

        if ($alreadyPending) {
            return response()->json([
                'message' => 'Ya tienes una solicitud pendiente para este horario.',
                'type' => 'error',
            ], 422);
        }

        $appointmentRequest = AppointmentRequest::create([
            ...$data,
            'patient_id' => $patient->id,
        ]);

        return response()->json(
            $appointmentRequest->load(['patient', 'psychologist']),
            201
        );
    }

    /**
     * GET /appointment-requests
     *
     * Devuelve las solicitudes creadas por el paciente autenticado.
     */
    public function indexByPatient(): JsonResponse
    {
        $patient = request()->user();

        $requests = AppointmentRequest::with('psychologist')
            ->where('patient_id', $patient->id)
            ->orderByRaw("FIELD(status, 'pending', 'approved', 'rejected')")
            ->orderBy('date')
            ->orderBy('time')
            ->get();

        return response()->json($requests, 200);
    }

    /**
     * GET /user/appointment-requests
     *
     * Devuelve solicitudes pendientes para el psicólogo autenticado.
     */
    public function indexForAuthenticatedPsychologist(): JsonResponse
    {
        return $this->indexByPsychologist((int) auth()->id());
    }

    /**
     * PATCH /appointment-requests/{id}
     *
     * Actualiza el estado de una solicitud (approved / rejected).
     * Solo el psicólogo dueño de la solicitud puede modificarla.
     */
    public function update(UpdateAppointmentRequestRequest $request, int $id): JsonResponse
    {
        $appointmentRequest = AppointmentRequest::with(['patient', 'psychologist'])->findOrFail($id);

        // Verificar que la solicitud pertenece al psicólogo autenticado.
        if ($appointmentRequest->psychologist_id !== auth()->id()) {
            return response()->json(['message' => 'No autorizado.'], 403);
        }

        if ($appointmentRequest->status !== 'pending') {
            return response()->json([
                'message' => 'Esta solicitud ya fue atendida.',
                'type' => 'error',
            ], 422);
        }

        $data = $request->validated();

        if ($data['status'] === 'rejected') {
            $appointmentRequest->update([
                'status' => 'rejected',
                'notes' => $data['notes'] ?? null,
            ]);

            return response()->json(
                $appointmentRequest->fresh()->load(['patient', 'psychologist']),
                200
            );
        }

        $start = Carbon::createFromFormat(
            'Y-m-d H:i',
            "{$appointmentRequest->date->format('Y-m-d')} ".substr($appointmentRequest->time, 0, 5),
            config('app.timezone')
        );
        $end = $start->copy()->addMinutes(50);

        if ($start->lessThanOrEqualTo(now())) {
            return response()->json([
                'message' => 'No puedes aprobar una solicitud con horario vencido.',
                'type' => 'error',
            ], 422);
        }

        $hasConflict = Appointment::where('user', $appointmentRequest->psychologist_id)
            ->whereNotIn('statusUser', ['Cancel'])
            ->whereNotIn('statusPatient', ['Cancel'])
            ->where(function ($query) use ($start, $end) {
                $query->where('start', '<', $end)
                    ->where('end', '>', $start);
            })
            ->exists();

        if ($hasConflict) {
            return response()->json([
                'message' => 'Ya tienes una cita registrada en ese horario.',
                'type' => 'error',
            ], 422);
        }

        $appointment = DB::transaction(function () use ($appointmentRequest, $data, $start, $end) {
            $relation = $this->appointmentService->ensureRelationshipAndRoom(
                $appointmentRequest->psychologist_id,
                $appointmentRequest->patient_id
            );

            if ($relation?->archived_at) {
                abort(423, 'Paciente archivado. Reactivalo para agendar nuevas sesiones.');
            }

            $appointment = Appointment::create([
                'user' => $appointmentRequest->psychologist_id,
                'patient' => $appointmentRequest->patient_id,
                'clinic_id' => $relation?->clinic_id,
                'title' => 'Sesión con '.$appointmentRequest->patient?->name,
                'start' => $start,
                'end' => $end,
                'comments' => $data['notes'] ?? 'Solicitud creada desde el portal del paciente.',
                'payment_status' => 'pending',
                'video_call_room' => $relation?->video_call_room,
                'synced_with_google' => (bool) ($data['syncWithGoogle'] ?? false),
                'extendedProps' => [
                    'tipoSesion' => 'individual',
                    'formato' => 'online',
                    'source' => 'patient_request',
                    'appointment_request_id' => $appointmentRequest->id,
                ],
                'notification_meta' => [],
            ]);

            $appointmentRequest->update([
                'status' => 'approved',
                'notes' => $data['notes'] ?? null,
            ]);

            return $appointment->fresh(['patient', 'user']);
        });

        $this->notifyAppointmentApproved($appointment, (bool) ($data['syncWithGoogle'] ?? false));

        return response()->json(
            [
                'request' => $appointmentRequest->fresh()->load(['patient', 'psychologist']),
                'appointment' => $appointment,
                'message' => 'Solicitud aprobada y cita creada correctamente.',
                'type' => 'success',
            ],
            200
        );
    }

    /**
     * GET /psychologists/{id}/appointment-requests
     *
     * Devuelve todas las solicitudes pendientes de un psicólogo específico,
     * ordenadas de más reciente a más antigua.
     */
    public function indexByPsychologist(int $id): JsonResponse
    {
        // Verificamos que el psicólogo exista antes de consultar.
        $psychologist = User::findOrFail($id);

        $requests = AppointmentRequest::with('patient')
            ->where('psychologist_id', $psychologist->id)
            ->pending()
            ->orderBy('date')
            ->orderBy('time')
            ->get();

        return response()->json($requests, 200);
    }

    private function notifyAppointmentApproved(Appointment $appointment, bool $syncWithGoogle): void
    {
        try {
            $start = Carbon::parse($appointment->start);
            $end = Carbon::parse($appointment->end);
            $interval = $start->diff($end);
            $fecha = $start->format('d/m/Y');
            $hora = $start->format('H:i').' - '.$end->format('H:i');
            $patient = $appointment->patient()->first();
            $professional = $appointment->user()->first();

            if ($patient) {
                $patient->notify(new CreateAppoinmentMail($appointment, $patient, $hora, $fecha, $interval));
            }

            if ($professional) {
                $professional->notify(new ProfessionalAppointmentCreatedNotification($appointment));
                app(AppointmentWhatsAppNotifier::class)->appointmentCreated($appointment, 'user.appointment-requests.approved');

                if ($syncWithGoogle && $professional->googleAccount?->refresh_token) {
                    SyncAppointmentToGoogleCalendar::dispatch($appointment, $professional, 'create', true);
                }
            }

            event(new AppointmentCreated($appointment->id, $appointment->getAttribute('user'), $appointment->getAttribute('patient')));
        } catch (\Throwable $throwable) {
            Log::error('Error al notificar aprobación de solicitud de cita: '.$throwable->getMessage());
        }
    }
}
