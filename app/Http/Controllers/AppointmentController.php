<?php

namespace App\Http\Controllers;

use App\Events\AppointmentCreated;
use App\Events\NewNotification;
use App\Jobs\SyncAppointmentToGoogleCalendar;
use App\Models\Appointment;
use App\Models\AppointmentCart;
use App\Models\ConsultaContacto;
use App\Models\OrganizationMembership;
use App\Models\Patient;
use App\Models\PatientUser;
use App\Models\User;
use App\Notifications\CreateAppoinmentMail;
use App\Notifications\ProfessionalAppointmentCreatedNotification;
use App\Notifications\ProfessionalAppointmentStatusNotification;
use App\Notifications\RecurringAppointmentSeriesNotification;
use App\Notifications\StateAppoinmentMail;
use App\Services\AppointmentDeletionService;
use App\Services\AppointmentService;
use App\Services\GoogleCalendarService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

class AppointmentController extends Controller
{
    protected AppointmentService $service;
    protected GoogleCalendarService $googleCalendarService;
    protected AppointmentDeletionService $appointmentDeletionService;

    public function __construct(
        AppointmentService $service,
        GoogleCalendarService $googleCalendarService,
        AppointmentDeletionService $appointmentDeletionService
    )
    {
        $this->service = $service;
        $this->googleCalendarService = $googleCalendarService;
        $this->appointmentDeletionService = $appointmentDeletionService;
    }

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $appointments = Appointment::with(['payments', 'cart'])
            ->where('user', $user->id)
            ->orderBy('start')
            ->get();

        return response()->json($appointments, 200);
    }

    public function getAppoinmentsByPatient($patient = null): JsonResponse
    {
        $middlewares = Route::getCurrentRoute()->gatherMiddleware();
        $user = request()->user();

        if (in_array('user', $middlewares, true)) {
            $appointments = Appointment::where('user', $user->id)
                ->where('patient', $patient)
                ->orderByDesc('id')
                ->get();
        } else {
            $appointments = Appointment::where('patient', $user->id)
                ->with(['user', 'payments'])
                ->orderBy('start')
                ->get();
        }

        return response()->json($appointments, 200);
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
            ->whereNotIn('statusUser', ['Cancel'])
            ->whereNotIn('statusPatient', ['Cancel'])
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
                $slotStart = $blockStart->gt($now) ? $blockStart->copy() : $now->copy();

                while ($slotStart->lt($blockEnd)) {
                    $slotEnd = $slotStart->copy()->addMinutes(50);

                    if ($slotEnd->gt($blockEnd)) {
                        break;
                    }

                    $empalme = $appointments->contains(function ($appointment) use ($slotStart, $slotEnd) {
                        return $slotStart->lt($appointment->end) && $slotEnd->gt($appointment->start);
                    });

                    if (!$empalme) {
                        $slots[] = [
                            'date' => $fecha,
                            'hour' => $slotStart->format('H:i'),
                        ];
                        $slotsFoundToday = true;
                    }

                    $slotStart->addMinutes(60);
                }
            }

            if ($slotsFoundToday) {
                $uniqueDays[] = $fecha;
            }

            $current->addDay();
        }

        return response()->json($slots);
    }

    public function store(Request $request): JsonResponse
    {
        Log::info('Creando cita con datos', $request->all());

        $middlewares = Route::getCurrentRoute()->gatherMiddleware();
        $authUser = $request->user();

        $request->validate([
            'start' => 'required|date',
            'end' => 'required|date|after:start',
            'title' => 'required|string|max:255',
            'user' => 'nullable|exists:users,id',
            'patient' => 'nullable|exists:patients,id',
            'costo' => 'nullable|numeric',
            'tipoSesion' => 'nullable|string|max:255',
            'formato' => 'nullable|string|max:255',
            'comments' => 'nullable|string',
            'objective' => 'nullable|string',
            'session_description' => 'nullable|string',
            'pre_session_note' => 'nullable|string',
            'interventions' => 'nullable|string',
            'action_plan' => 'nullable|string',
            'observations' => 'nullable|string',
            'payment_status' => 'nullable|in:pending,paid',
            'is_recurrent' => 'nullable|boolean',
            'frequency' => 'nullable|string|in:DAILY,WEEKLY,MONTHLY',
            'until' => 'nullable|date|after_or_equal:start',
            'interval' => 'nullable|integer|min:1',
            'syncWithGoogle' => 'nullable|boolean',
            'clinic_id' => 'nullable|exists:clinics,id',
            'organization_id' => 'nullable|exists:organizations,id',
        ]);

        if (in_array('user', $middlewares, true)) {
            $requestedProfessionalId = (int) $request->input('user', $authUser->id);
            $activeOrganization = $request->attributes->get('active_organization');
            $membership = $request->attributes->get('organization_membership');
            $canAssignOrganizationProfessional = $this->canAssignOrganizationProfessional(
                $activeOrganization?->id,
                $membership,
                $requestedProfessionalId
            );

            $request->merge([
                'user' => $canAssignOrganizationProfessional
                    ? $requestedProfessionalId
                    : $authUser->id,
            ]);
        } elseif (in_array('patient', $middlewares, true)) {
            $request->merge(['patient' => $authUser->id]);
        } else {
            return response()->json([
                'rasson' => 'Middleware invalido',
                'message' => 'No se pudo crear la cita',
                'type' => 'error',
            ], 403);
        }

        $clinicId = $this->resolveClinicContext(
            $request->input('clinic_id'),
            (int) $request->input('user'),
            (int) $request->input('patient')
        );
        $organizationId = $request->input('organization_id')
            ?: $request->attributes->get('active_organization')?->id;

        if ($request->filled('lead') && in_array('user', $middlewares, true)) {
            $request->merge([
                'patient' => $this->resolveLeadToPatient((int) $request->input('lead'), (int) $request->input('user'), $clinicId, $organizationId),
            ]);
        }

        if (!$request->filled('user') || !$request->filled('patient')) {
            return response()->json([
                'rasson' => 'La cita requiere un profesional y un paciente validos.',
                'message' => 'Datos incompletos',
                'type' => 'error',
            ], 422);
        }

        $relation = $this->service->ensureRelationshipAndRoom($request->input('user'), $request->input('patient'), $clinicId);
        if ($relation?->archived_at) {
            return response()->json([
                'rasson' => 'Paciente archivado. Reactivalo para agendar nuevas sesiones.',
                'message' => 'Paciente archivado',
                'type' => 'error',
            ], 423);
        }

        $start = Carbon::parse($request->input('start'));
        $end = Carbon::parse($request->input('end'));
        $duration = max($start->diffInMinutes($end), 1);
        $isRecurrent = $request->boolean('is_recurrent');
        $frequency = strtoupper((string) $request->input('frequency', data_get($request->input('recurrence', []), 'frequency', '')));
        $until = $request->input('until', data_get($request->input('recurrence', []), 'until'));
        $interval = (int) $request->input('interval', data_get($request->input('recurrence', []), 'interval', 1));
        $syncWithGoogle = $request->boolean('syncWithGoogle');

        if ($isRecurrent && (!$frequency || !$until)) {
            return response()->json([
                'rasson' => 'Las citas recurrentes requieren frecuencia y fecha limite.',
                'message' => 'Recurrencia incompleta',
                'type' => 'error',
            ], 422);
        }

        $appointments = [];
        $recurrenceId = $isRecurrent ? (string) Str::uuid() : null;
        $occurrences = $isRecurrent
            ? $this->buildRecurringOccurrences($start, $end, $frequency, $until, $interval)
            : [[
                'start' => $start,
                'end' => $end,
                'position' => 1,
            ]];

        foreach ($occurrences as $occurrence) {
            $appointment = Appointment::create([
                'organization_id' => $organizationId,
                'user' => $request->input('user'),
                'patient' => $request->input('patient'),
                'clinic_id' => $clinicId,
                'title' => $request->input('title'),
                'start' => $occurrence['start'],
                'end' => $occurrence['end'],
                'comments' => $request->input('comments'),
                'objective' => $request->input('objective'),
                'session_description' => $request->input('session_description'),
                'pre_session_note' => $request->input('pre_session_note'),
                'interventions' => $request->input('interventions'),
                'action_plan' => $request->input('action_plan'),
                'observations' => $request->input('observations'),
                'payment_status' => $request->input('payment_status', 'pending'),
                'video_call_room' => $relation->video_call_room,
                'recurrence_id' => $recurrenceId,
                'recurrence_frequency' => $isRecurrent ? $frequency : null,
                'recurrence_interval' => $isRecurrent ? $interval : null,
                'recurrence_until' => $isRecurrent ? Carbon::parse($until)->toDateString() : null,
                'recurrence_position' => $occurrence['position'],
                'synced_with_google' => $syncWithGoogle,
                'extendedProps' => [
                    'tipoSesion' => $request->input('tipoSesion'),
                    'formato' => $request->input('formato'),
                    'telefono' => $request->input('telefono'),
                ],
                'notification_meta' => [],
            ]);

            $cart = $this->createAppointmentCart($appointment, $request, $duration);
            if ($cart) {
                $appointment->cart_id = $cart->id;
                $appointment->save();
            }

            $appointments[] = $appointment->fresh(['patient', 'user', 'cart']);

            if (!$isRecurrent) {
                $this->sendNotificacionCreateAppoimentEmail($appointment);
            }

            if (!$isRecurrent) {
                event(new AppointmentCreated($appointment->id, $appointment->user, $appointment->patient));
            }
        }

        if ($isRecurrent && count($appointments) > 0) {
            $this->sendRecurringSeriesNotification($appointments, $frequency, $until);
        }

        if ($syncWithGoogle && count($appointments) > 0) {
            $googleResponse = $this->handleGoogleSyncRequest($appointments);
            if ($googleResponse) {
                return $googleResponse;
            }
        }

        return response()->json([
            'rasson' => 'Se creo la(s) cita(s) correctamente',
            'message' => 'Cita(s) creada(s)',
            'type' => 'success',
            'appointments' => $appointments,
        ], 200);
    }

    private function canAssignOrganizationProfessional(
        ?int $organizationId,
        ?OrganizationMembership $membership,
        int $professionalId
    ): bool {
        if (!$organizationId || !$membership || !$professionalId) {
            return false;
        }

        $permissions = $membership->permissions ?: [];
        $canManageSchedule = in_array($membership->role, [
            OrganizationMembership::ROLE_OWNER,
            OrganizationMembership::ROLE_ADMIN,
            OrganizationMembership::ROLE_RECEPTIONIST,
        ], true)
            || in_array('*', $permissions, true)
            || in_array('appointments.create', $permissions, true)
            || in_array('appointments.manage', $permissions, true)
            || in_array('schedule.manage', $permissions, true)
            || (is_array($permissions) && !empty($permissions['appointments.create']))
            || (is_array($permissions) && !empty($permissions['appointments.manage']))
            || (is_array($permissions) && !empty($permissions['schedule.manage']));

        if (!$canManageSchedule) {
            return false;
        }

        return OrganizationMembership::query()
            ->where('organization_id', $organizationId)
            ->where('user_id', $professionalId)
            ->where('role', OrganizationMembership::ROLE_PSYCHOLOGIST)
            ->where('status', OrganizationMembership::STATUS_ACTIVE)
            ->exists();
    }

    public function show(Appointment $appointment): JsonResponse
    {
        $appointment = Appointment::where('id', $appointment->id)
            ->with(['patient', 'payments', 'cart', 'user'])
            ->first();

        return response()->json(['appointment' => $appointment], 200);
    }

    public function showABP($id): JsonResponse
    {
        $patient = request()->user();
        $appointment = Appointment::where('id', $id)
            ->where('patient', $patient->id)
            ->with(['cart', 'user'])
            ->first();

        return response()->json($appointment, 200);
    }

    public function update(Request $request, Appointment $appointment): JsonResponse
    {
        $originalData = Appointment::with(['user', 'cart', 'patient'])->findOrFail($appointment->id);
        $updatedData = $request->only([
            'title',
            'start',
            'end',
            'statusUser',
            'statusPatient',
            'state',
            'comments',
            'objective',
            'session_description',
            'pre_session_note',
            'interventions',
            'action_plan',
            'observations',
            'payment_status',
            'link',
            'video_call_room',
        ]);
        $fieldsToUpdate = [];
        $arrayOriginal = $originalData->toArray();

        if ($this->isCancellationRequest($request)) {
            return $this->destroy($request, $appointment);
        }

        foreach ($updatedData as $key => $value) {
            if (!array_key_exists($key, $arrayOriginal) || in_array($key, ['created_at', 'updated_at'], true)) {
                continue;
            }

            if ((string) $arrayOriginal[$key] !== (string) $value) {
                $fieldsToUpdate[$key] = $value;
            }
        }

        if ($request->exists('comments')) {
            $incomingComments = (string) $request->input('comments', '');
            $originalComments = (string) ($originalData->comments ?? '');

            if ($incomingComments !== $originalComments) {
                $fieldsToUpdate['comments'] = $incomingComments;
            }
        }

        if (empty($fieldsToUpdate) && !$request->hasAny(['costo', 'tipoSesion', 'formato'])) {
            return response()->json([
                'rasson' => 'No se detectaron cambios en la cita',
                'message' => 'Sin modificaciones',
                'type' => 'info',
            ], 200);
        }

        try {
            if (!empty($fieldsToUpdate)) {
                $appointment->update($fieldsToUpdate);
            }

            if ($appointment->cart) {
                $cartPayload = [];
                if ($request->filled('costo')) {
                    $cartPayload['precio'] = $request->input('costo');
                }
                if ($request->filled('tipoSesion')) {
                    $cartPayload['tipoSesion'] = $request->input('tipoSesion');
                }
                if ($request->filled('formato')) {
                    $cartPayload['formato'] = $request->input('formato');
                }
                if ($request->filled('payment_status')) {
                    $cartPayload['estado'] = $request->input('payment_status') === 'paid' ? 'pagado' : 'pendiente';
                }
                if (!empty($cartPayload)) {
                    $appointment->cart->update($cartPayload);
                }
            }

            $appointment->refresh();
            $user = User::find($appointment->user);

            if ($appointment->google_event_id && $user && $user->googleAccount) {
                SyncAppointmentToGoogleCalendar::dispatch($appointment, $user, 'update');
            }

            if ($request->hasAny(['statusUser', 'statusPatient', 'state'])) {
                $this->sendNotificacionStatusEmail($appointment, $originalData);
            }

            return response()->json([
                'rasson' => 'La cita cambio sus caracteristicas con exito',
                'message' => 'Cita modificada',
                'type' => 'success',
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'rasson' => 'No se logro cambiar la cita con exito. ' . $th->getMessage(),
                'message' => 'Cita no modificada',
                'type' => 'error',
            ], 400);
        }
    }

    public function destroy(Request $request, Appointment $appointment): JsonResponse
    {
        $scope = $request->input('scope', 'single');
        $targets = $this->resolveCancellationTargets($appointment, $scope);

        if ($targets->isEmpty()) {
            return response()->json([
                'message' => 'No se encontraron sesiones para eliminar.',
                'type' => 'info',
                'count' => 0,
            ], 200);
        }

        $firstTarget = $targets->first()->loadMissing(['patient', 'user']);
        $originalFirstTarget = clone $firstTarget;
        $firstTarget->statusUser = 'Cancel';
        $firstTarget->statusPatient = 'Cancel';
        $firstTarget->state = 'Cancelada';

        $this->sendNotificacionStatusEmail($firstTarget, $originalFirstTarget);

        $deletedCount = $this->appointmentDeletionService->deleteMany($targets);

        return response()->json([
            'message' => $scope === 'future'
                ? 'La sesion seleccionada y sus recurrencias futuras fueron eliminadas.'
                : 'La sesion fue eliminada correctamente.',
            'type' => 'success',
            'count' => $deletedCount,
        ], 200);
    }

    private function buildRecurringOccurrences(Carbon $start, Carbon $end, string $frequency, string $until, int $interval): array
    {
        $occurrences = [];
        $currentStart = $start->copy();
        $duration = max($start->diffInMinutes($end), 1);
        $untilDate = Carbon::parse($until)->endOfDay();
        $position = 1;

        while ($currentStart->lte($untilDate)) {
            $occurrences[] = [
                'start' => $currentStart->copy(),
                'end' => $currentStart->copy()->addMinutes($duration),
                'position' => $position,
            ];

            $currentStart = match ($frequency) {
                'DAILY' => $currentStart->copy()->addDays($interval),
                'MONTHLY' => $currentStart->copy()->addMonthsNoOverflow($interval),
                default => $currentStart->copy()->addWeeks($interval),
            };

            $position++;
        }

        if (empty($occurrences)) {
            throw new \RuntimeException('La recurrencia no genero ocurrencias validas.');
        }

        return $occurrences;
    }

    private function createAppointmentCart(Appointment $appointment, Request $request, int $duration): AppointmentCart
    {
        return AppointmentCart::create([
            'appointment_id' => $appointment->id,
            'tipoSesion' => $request->input('tipoSesion'),
            'formato' => $request->input('formato', 'online'),
            'precio' => $request->input('costo', 0),
            'estado' => $request->input('payment_status') === 'paid' ? 'pagado' : 'pendiente',
            'source' => 'panel',
            'patient_id' => $appointment->patient,
            'user_id' => $appointment->user,
            'duracion' => (string) $duration,
        ]);
    }

    private function handleGoogleSyncRequest(array $appointments): ?JsonResponse
    {
        $owner = User::find($appointments[0]->user);

        if (!$owner) {
            return null;
        }

        if ($owner->googleAccount && $owner->googleAccount->refresh_token) {
            $notifyEachAppointment = count($appointments) <= 1;

            foreach ($appointments as $appointment) {
                SyncAppointmentToGoogleCalendar::dispatch($appointment, $owner, 'create', $notifyEachAppointment);
            }

            if (!$notifyEachAppointment) {
                event(new NewNotification(
                    "user.{$owner->id}",
                    "Se estan sincronizando " . count($appointments) . " sesiones recurrentes con Google Meet. Te notificamos solo una vez para evitar duplicados."
                ));
            }

            return null;
        }

        $statePayload = [
            'user_id' => $owner->id,
            'appointment_ids' => collect($appointments)->pluck('id')->all(),
        ];

        $encryptedState = Crypt::encrypt(json_encode($statePayload));
        $authUrl = $this->googleCalendarService->getAuthUrl($encryptedState);

        return response()->json([
            'action' => 'redirect_to_google_auth',
            'url' => $authUrl,
        ], 202);
    }

    private function resolveCancellationTargets(Appointment $appointment, string $scope)
    {
        if (!$appointment->recurrence_id || $scope === 'single') {
            return collect([$appointment->load('cart')]);
        }

        $query = Appointment::with('cart')
            ->where('recurrence_id', $appointment->recurrence_id)
            ->orderBy('start');

        if ($scope === 'future') {
            $query->where('start', '>=', $appointment->start);
        }

        return $query->get();
    }

    private function isCancellationRequest(Request $request): bool
    {
        $status = strtolower((string) (
            $request->input('statusUser')
            ?: $request->input('statusPatient')
            ?: $request->input('state')
            ?: ''
        ));

        return in_array($status, ['cancel', 'cancelado', 'cancelada'], true);
    }

    private function resolveLeadToPatient(int $leadId, int $userId, ?int $clinicId = null, ?int $organizationId = null): int
    {
        $consulta = ConsultaContacto::findOrFail($leadId);

        $patient = Patient::firstOrCreate(
            ['email' => $consulta->email],
            [
                'name' => $consulta->nombre,
                'organization_id' => $organizationId,
                'contacto' => ['telefono' => $consulta->telefono],
                'phone' => preg_replace('/\D+/', '', (string) $consulta->telefono) ?: null,
                'password' => Hash::make($consulta->telefono ?? Str::random(12)),
            ]
        );

        if (!$patient->organization_id && $organizationId) {
            $patient->organization_id = $organizationId;
            $patient->save();
        }

        $relacionExiste = PatientUser::where('user', $userId)
            ->where('patient', $patient->id)
            ->exists();

        if (!$relacionExiste) {
            PatientUser::create([
                'user' => $userId,
                'patient' => $patient->id,
                'clinic_id' => $clinicId ?: $this->resolveClinicContext(null, $userId, $patient->id),
                'activo' => true,
                'status' => 'Vinculado',
                'video_call_room' => 'mindmeet-room-' . Str::uuid(),
            ]);
            Log::info("Relacion creada: psicologo #{$userId} -> paciente #{$patient->id}");
        }

        return $patient->id;
    }

    private function resolveClinicContext($requestedClinicId, int $userId, ?int $patientId = null): ?int
    {
        if ($requestedClinicId) {
            return (int) $requestedClinicId;
        }

        if ($patientId) {
            $existingRelationClinicId = PatientUser::query()
                ->where('user', $userId)
                ->where('patient', $patientId)
                ->value('clinic_id');

            if ($existingRelationClinicId) {
                return (int) $existingRelationClinicId;
            }
        }

        return User::with('primaryClinicMembership')
            ->find($userId)
            ?->primaryClinicMembership
            ?->clinic_id;
    }

    public function sendNotificacionStatusEmail($appointment, ?Appointment $originalAppointment = null): bool
    {
        try {
            $patient = Patient::find($appointment->patient);
            $professional = User::find($appointment->user);
            if (!$patient && !$professional) {
                return false;
            }

            $start = Carbon::parse($appointment->start);
            $end = Carbon::parse($appointment->end);
            $fecha = $start->format('d/m/Y');
            $hora = $start->format('H:i') . ' - ' . $end->format('H:i');
            $patientStatus = $this->resolveAppointmentStatusValue(
                $appointment->statusUser,
                $appointment->state
            );
            $professionalStatus = $this->resolveAppointmentStatusValue(
                $appointment->statusPatient,
                $appointment->state
            );
            $patientStatusChanged = $originalAppointment
                ? $this->didAppointmentStatusChange($originalAppointment, $appointment, ['statusUser', 'state'])
                : filled($patientStatus);
            $professionalStatusChanged = $originalAppointment
                ? $this->didAppointmentStatusChange($originalAppointment, $appointment, ['statusPatient', 'state'])
                : filled($professionalStatus);

            if ($patient && $patientStatusChanged && filled($patientStatus)) {
                $patient->notify(new StateAppoinmentMail($patient, $patientStatus, $fecha, $hora));
            }

            if ($professional && $professionalStatusChanged && filled($professionalStatus)) {
                $professional->notify(new ProfessionalAppointmentStatusNotification(
                    $appointment->loadMissing(['patient', 'user']),
                    $professionalStatus
                ));
            }

            return true;
        } catch (\Throwable $th) {
            Log::error($th->getMessage());
            return false;
        }
    }

    public function sendNotificacionCreateAppoimentEmail($appointment): bool
    {
        $start = Carbon::parse($appointment->start);
        $end = Carbon::parse($appointment->end);
        $interval = $start->diff($end);
        $fecha = $start->format('d/m/Y');
        $hora = $start->format('H:i') . ' - ' . $end->format('H:i');

        try {
            $patient = Patient::find($appointment->patient);
            $professional = User::find($appointment->user);
            if (!$patient) {
                return false;
            }

            $patient->notify(new CreateAppoinmentMail($appointment, $patient, $hora, $fecha, $interval));
            if ($professional) {
                $professional->notify(new ProfessionalAppointmentCreatedNotification(
                    $appointment->loadMissing(['patient', 'user'])
                ));
            }
            return true;
        } catch (\Throwable $th) {
            Log::error($th->getMessage());
            return false;
        }
    }

    private function resolveAppointmentStatusValue(?string $primaryStatus, ?string $fallbackState = null): ?string
    {
        $status = $primaryStatus ?: $fallbackState;

        return filled($status) ? $status : null;
    }

    private function didAppointmentStatusChange(Appointment $originalAppointment, Appointment $currentAppointment, array $fields): bool
    {
        foreach ($fields as $field) {
            if ((string) data_get($originalAppointment, $field) !== (string) data_get($currentAppointment, $field)) {
                return true;
            }
        }

        return false;
    }

    public function sendRecurringSeriesNotification(array $appointments, string $frequency, string $until): bool
    {
        try {
            $firstAppointment = collect($appointments)->sortBy('start')->first();
            if (!$firstAppointment) {
                return false;
            }

            $patient = Patient::find($firstAppointment->patient);
            $professional = User::find($firstAppointment->user);
            if ($patient) {
                $patient->notify(new RecurringAppointmentSeriesNotification(
                    $firstAppointment->loadMissing(['patient', 'user']),
                    $frequency,
                    $until,
                    count($appointments)
                ));
            }

            if ($professional) {
                $professional->notify(new RecurringAppointmentSeriesNotification(
                    $firstAppointment->loadMissing(['patient', 'user']),
                    $frequency,
                    $until,
                    count($appointments)
                ));
            }

            return true;
        } catch (\Throwable $th) {
            Log::error($th->getMessage());
            return false;
        }
    }

    public function publicConfirm(Request $request): JsonResponse
    {
        $hash = $request->input('hash');
        $status = $request->input('status', 'Confirmed');

        if (!$hash) {
            return response()->json(['rasson' => 'Hash requerido', 'message' => 'Hash missing', 'type' => 'error'], 400);
        }

        try {
            $decoded = json_decode(base64_decode($hash), true);
            if (!is_array($decoded) || !isset($decoded['id'])) {
                return response()->json(['rasson' => 'Hash invalido', 'message' => 'Invalid hash payload', 'type' => 'error'], 400);
            }

            $appointment = Appointment::find($decoded['id']);
            if (!$appointment) {
                return response()->json(['rasson' => 'Cita no encontrada', 'message' => 'Appointment not found', 'type' => 'error'], 404);
            }

            $appointment->statusPatient = $status;
            $appointment->save();
            $this->sendNotificacionStatusEmail($appointment);

            return response()->json(['rasson' => 'Cita confirmada', 'message' => 'Appointment confirmed', 'type' => 'success'], 200);
        } catch (\Throwable $th) {
            Log::error('publicConfirm error: ' . $th->getMessage());
            return response()->json(['rasson' => 'Error interno', 'message' => 'Internal error', 'type' => 'error'], 500);
        }
    }

    public function publicShow($hash): JsonResponse
    {
        try {
            $decoded = json_decode(base64_decode($hash), true);
            if (!is_array($decoded) || !isset($decoded['id'])) {
                return response()->json(['rasson' => 'Hash invalido', 'message' => 'Invalid hash payload', 'type' => 'error'], 400);
            }

            $appointment = Appointment::where('id', $decoded['id'])->with('user')->first();
            if (!$appointment) {
                return response()->json(['rasson' => 'Cita no encontrada', 'message' => 'Appointment not found', 'type' => 'error'], 404);
            }

            $start = Carbon::parse($appointment->start);
            $end = Carbon::parse($appointment->end);
            $data = [
                'professional' => $appointment->user?->name,
                'fecha' => $start->format('d/m/Y'),
                'hora' => $start->format('H:i') . ' - ' . $end->format('H:i'),
                'duration' => $start->diff($end)->format('%h horas %i minutos'),
                'statusPatient' => $appointment->statusPatient,
            ];

            return response()->json($data, 200);
        } catch (\Throwable $th) {
            Log::error('publicShow error: ' . $th->getMessage());
            return response()->json(['rasson' => 'Error interno', 'message' => 'Internal error', 'type' => 'error'], 500);
        }
    }
}
