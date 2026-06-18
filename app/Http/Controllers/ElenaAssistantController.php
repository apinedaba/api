<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\Patient;
use App\Models\PatientUser;
use App\Models\User;
use App\Notifications\CreateAppoinmentMail;
use App\Notifications\ProfessionalAppointmentCreatedNotification;
use App\Services\AppointmentService;
use App\Services\ElenaAssistantService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ElenaAssistantController extends Controller
{
    private const TIMEZONE = 'America/Mexico_City';

    public function __construct(
        private ElenaAssistantService $assistant,
        private AppointmentService $appointments
    ) {
    }

    public function message(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'message' => ['required', 'string', 'max:800'],
            'selected_patient_id' => ['nullable', 'integer'],
            'pending_intent' => ['nullable', 'array'],
        ]);

        $user = $request->user();
        $timezone = self::TIMEZONE;
        $now = Carbon::now($timezone);
        $intent = $this->assistant->interpret($validated['message'], [
            'now' => $now->toIso8601String(),
            'timezone' => $timezone,
            'previous_intent' => $validated['pending_intent'] ?? null,
        ]);
        $intent = $this->mergePendingIntent($validated['pending_intent'] ?? null, $intent);

        $selectedPatientId = $validated['selected_patient_id'] ?? null;
        if ($selectedPatientId) {
            $intent['selected_patient_id'] = (int) $selectedPatientId;
        }
        $request->attributes->set('assistant_intent', $intent);

        return match ($intent['intent']) {
            'search_patient' => $this->searchPatient($user, $intent),
            'next_session' => $this->nextSession($user, $intent),
            'schedule_session' => $this->scheduleProposal($user, $intent, $timezone),
            'help' => response()->json($this->helpResponse()),
            default => response()->json([
                'type' => 'message',
                'message' => 'Puedo ayudarte a buscar pacientes, consultar la siguiente sesion o preparar una nueva sesion. Prueba: "Adel, agenda una sesion para Adrian Pineda el martes a las 5pm".',
                'intent' => $intent,
            ]),
        };
    }

    public function confirm(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string'],
        ]);

        try {
            $payload = json_decode(Crypt::decryptString($validated['token']), true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            return response()->json([
                'message' => 'No pude confirmar esta accion. Vuelve a pedirle a Adel que prepare la sesion.',
            ], 422);
        }

        if (($payload['action'] ?? null) !== 'create_appointment' || (int) ($payload['user_id'] ?? 0) !== (int) $request->user()->id) {
            return response()->json(['message' => 'Accion no valida para tu cuenta.'], 403);
        }

        $start = Carbon::parse($payload['start']);
        $end = Carbon::parse($payload['end']);

        if ($start->isPast()) {
            return response()->json(['message' => 'No puedo crear una sesion en una fecha pasada.'], 422);
        }

        $patient = Patient::findOrFail((int) $payload['patient_id']);
        $relation = PatientUser::where('user', $request->user()->id)
            ->where('patient', $patient->id)
            ->firstOrFail();

        if ($relation->archived_at) {
            return response()->json(['message' => 'Paciente archivado. Reactivalo antes de agendar.'], 423);
        }

        if ($this->hasConflict($request->user()->id, $start, $end)) {
            return response()->json([
                'message' => 'Ya tienes una sesion en ese horario. Adel no creo la sesion.',
                'type' => 'conflict',
            ], 409);
        }

        $relation = $this->appointments->ensureRelationshipAndRoom($request->user()->id, $patient->id, $relation->clinic_id);

        $isRecurrent = (bool) ($payload['is_recurrent'] ?? false);
        $frequency = $payload['recurrence_frequency'] ?? null;
        $until = $payload['recurrence_until'] ?? null;
        $interval = max(1, (int) ($payload['recurrence_interval'] ?? 1));
        $recurrenceId = $isRecurrent ? (string) Str::uuid() : null;
        try {
            $occurrences = $isRecurrent
                ? $this->buildRecurringOccurrences($start, $end, $frequency, $until, $interval)
                : [[
                    'start' => $start,
                    'end' => $end,
                    'position' => 1,
                ]];
        } catch (\Throwable) {
            return response()->json([
                'message' => 'No pude crear la recurrencia con esos datos. Vuelve a pedirle a Adel que prepare la sesion.',
            ], 422);
        }

        foreach ($occurrences as $occurrence) {
            if ($this->hasConflict($request->user()->id, $occurrence['start'], $occurrence['end'])) {
                return response()->json([
                    'message' => 'Una de las sesiones recurrentes choca con otra sesion existente. Adel no creo la serie.',
                    'type' => 'conflict',
                ], 409);
            }
        }

        $created = [];

        foreach ($occurrences as $occurrence) {
            $created[] = Appointment::create([
                'organization_id' => $request->attributes->get('active_organization')?->id ?: $patient->organization_id,
                'user' => $request->user()->id,
                'patient' => $patient->id,
                'clinic_id' => $relation->clinic_id,
                'title' => $payload['title'] ?: "Sesion con {$patient->name}",
                'start' => $occurrence['start'],
                'end' => $occurrence['end'],
                'comments' => 'Creada por Adel Assistant',
                'payment_status' => $payload['payment_status'] ?? 'pending',
                'video_call_room' => $relation->video_call_room,
                'recurrence_id' => $recurrenceId,
                'recurrence_frequency' => $isRecurrent ? $frequency : null,
                'recurrence_interval' => $isRecurrent ? $interval : null,
                'recurrence_until' => $isRecurrent ? Carbon::parse($until)->toDateString() : null,
                'recurrence_position' => $occurrence['position'],
                'extendedProps' => [
                    'tipoSesion' => $payload['session_type'],
                    'formato' => $payload['format'] ?? 'online',
                    'precio' => $payload['price'] ?? null,
                    'is_recurrent' => $isRecurrent,
                ],
                'notification_meta' => [
                    'source' => 'adel_assistant',
                ],
            ]);
        }

        $appointment = $created[0];

        $this->notifyAppointmentCreated($appointment->fresh(['patient', 'user']));

        return response()->json([
            'type' => 'appointment_created',
            'message' => $isRecurrent
                ? "Listo, agende {$this->formatCount(count($created), 'sesion', 'sesiones')} recurrentes con {$patient->name} desde {$this->humanDate($start)}."
                : "Listo, agende la sesion con {$patient->name} para {$this->humanDate($start)}.",
            'appointment' => $appointment->fresh(['patient']),
            'appointments' => collect($created)->map(fn (Appointment $item) => $item->fresh(['patient']))->values(),
        ]);
    }

    private function searchPatient(User $user, array $intent): JsonResponse
    {
        $patients = $this->matchingPatients($user->id, $intent['patient_name']);

        if ($patients->isEmpty()) {
            return response()->json([
                'type' => 'message',
                'message' => $intent['patient_name']
                    ? "No encontre pacientes que coincidan con {$intent['patient_name']}."
                    : 'Dime el nombre del paciente que quieres buscar.',
            ]);
        }

        return response()->json([
            'type' => 'patients',
            'message' => $patients->count() === 1 ? 'Encontre este paciente.' : 'Encontre estos pacientes.',
            'patients' => $patients,
            'intent' => $intent,
        ]);
    }

    private function nextSession(User $user, array $intent): JsonResponse
    {
        $patient = $this->resolveSinglePatient(
            $user->id,
            $intent['patient_name'],
            $intent['selected_patient_id'] ?? null,
            $intent
        );

        if ($patient instanceof JsonResponse) {
            return $patient;
        }

        $appointment = Appointment::where('user', $user->id)
            ->where('patient', $patient->id)
            ->where('start', '>=', now()->subMinutes(5))
            ->whereNotIn('statusUser', ['Cancel'])
            ->whereNotIn('statusPatient', ['Cancel'])
            ->orderBy('start')
            ->first();

        if (! $appointment) {
            return response()->json([
                'type' => 'message',
                'message' => "No encontre una siguiente sesion programada con {$patient->name}.",
                'patient' => $this->serializePatient($patient),
            ]);
        }

        return response()->json([
            'type' => 'next_session',
            'message' => "La siguiente sesion con {$patient->name} es {$this->humanDate($appointment->start)}.",
            'patient' => $this->serializePatient($patient),
            'appointment' => $this->serializeAppointment($appointment),
        ]);
    }

    private function scheduleProposal(User $user, array $intent, string $timezone): JsonResponse
    {
        $patient = $this->resolveSinglePatient(
            $user->id,
            $intent['patient_name'],
            $intent['selected_patient_id'] ?? null,
            $intent
        );

        if ($patient instanceof JsonResponse) {
            return $patient;
        }

        if (! $intent['datetime_iso']) {
            return response()->json([
                'type' => 'message',
                'message' => "Tengo a {$patient->name}. Dime fecha y hora para preparar la sesion.",
            ]);
        }

        try {
            $start = Carbon::parse($intent['datetime_iso'], $timezone);
        } catch (\Throwable) {
            return response()->json([
                'type' => 'message',
                'message' => 'No pude entender la fecha y hora. Prueba con algo como: martes 23 de junio a las 5pm.',
            ]);
        }

        if ($start->isPast()) {
            return response()->json([
                'type' => 'message',
                'message' => 'Esa fecha ya paso. Dame una fecha futura para agendar.',
            ]);
        }

        $duration = $intent['duration_minutes'] ?: 50;
        $end = $start->copy()->addMinutes($duration);

        if ($this->hasConflict($user->id, $start, $end)) {
            return response()->json([
                'type' => 'conflict',
                'message' => "Ya tienes una sesion en ese horario. Puedo preparar otra hora si me la indicas.",
            ], 409);
        }

        $intent['selected_patient_id'] = $patient->id;
        $intent['patient_name'] = $patient->name;

        $missingFields = $this->missingScheduleFields($intent);
        if (! empty($missingFields)) {
            return $this->scheduleMissingFieldsResponse($patient, $intent, $start, $missingFields);
        }

        $isRecurrent = (bool) $intent['is_recurrent'];
        $frequency = $isRecurrent ? $intent['recurrence_frequency'] : null;
        $until = $isRecurrent ? $intent['recurrence_until'] : null;
        $interval = $isRecurrent ? max(1, (int) ($intent['recurrence_interval'] ?: 1)) : null;

        $payload = [
            'action' => 'create_appointment',
            'user_id' => $user->id,
            'patient_id' => $patient->id,
            'title' => "Sesion con {$patient->name}",
            'start' => $start->toIso8601String(),
            'end' => $end->toIso8601String(),
            'session_type' => $intent['session_type'],
            'format' => $intent['format'],
            'is_recurrent' => $isRecurrent,
            'recurrence_frequency' => $frequency,
            'recurrence_until' => $until,
            'recurrence_interval' => $interval,
            'payment_status' => $intent['payment_status'],
            'price' => $intent['price'],
        ];

        return response()->json([
            'type' => 'confirmation_required',
            'message' => "Puedo agendar una sesion con {$patient->name} para {$this->humanDate($start)}. Confirma para crearla.",
            'confirmation' => [
                'label' => 'Confirmar sesion',
                'token' => Crypt::encryptString(json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)),
                'summary' => [
                    'patient' => $this->serializePatient($patient),
                    'start' => $start->toIso8601String(),
                    'end' => $end->toIso8601String(),
                    'human_start' => $this->humanDate($start),
                    'duration_minutes' => $duration,
                    'format' => $payload['format'],
                    'session_type' => $payload['session_type'],
                    'payment_status' => $payload['payment_status'],
                    'price' => $payload['price'],
                    'is_recurrent' => $payload['is_recurrent'],
                    'recurrence_frequency' => $payload['recurrence_frequency'],
                    'recurrence_until' => $payload['recurrence_until'],
                    'recurrence_interval' => $payload['recurrence_interval'],
                ],
            ],
        ]);
    }

    private function mergePendingIntent(?array $previous, array $current): array
    {
        if (! $previous) {
            return $current;
        }

        $merged = $previous;
        foreach ($current as $key => $value) {
            if ($key === 'intent' && in_array($value, ['unknown', 'help'], true)) {
                continue;
            }

            if ($value === null || $value === '') {
                continue;
            }

            $merged[$key] = $value;
        }

        return $merged;
    }

    private function missingScheduleFields(array $intent): array
    {
        $missing = [];

        if (empty($intent['session_type'])) {
            $missing[] = 'session_type';
        }
        if (empty($intent['format'])) {
            $missing[] = 'format';
        }
        if (! array_key_exists('is_recurrent', $intent) || $intent['is_recurrent'] === null) {
            $missing[] = 'is_recurrent';
        }
        if (($intent['is_recurrent'] ?? false) === true && empty($intent['recurrence_frequency'])) {
            $missing[] = 'recurrence_frequency';
        }
        if (($intent['is_recurrent'] ?? false) === true && empty($intent['recurrence_until'])) {
            $missing[] = 'recurrence_until';
        }
        if (empty($intent['payment_status'])) {
            $missing[] = 'payment_status';
        }
        if (! array_key_exists('price', $intent) || $intent['price'] === null || $intent['price'] === '') {
            $missing[] = 'price';
        }

        return $missing;
    }

    private function scheduleMissingFieldsResponse(Patient $patient, array $intent, Carbon $start, array $missingFields): JsonResponse
    {
        $labels = collect($missingFields)
            ->map(fn (string $field) => $this->scheduleFieldLabel($field))
            ->implode(', ');

        return response()->json([
            'type' => 'missing_fields',
            'message' => "Tengo a {$patient->name} para {$this->humanDate($start)}. Para dejar completa la sesion dime: {$labels}. Puedes responder en una frase, por ejemplo: individual, online, no recurrente, pago pendiente, \$700.",
            'missing_fields' => $missingFields,
            'pending_intent' => $intent,
            'intent' => $intent,
        ]);
    }

    private function scheduleFieldLabel(string $field): string
    {
        return match ($field) {
            'session_type' => 'tipo de sesion',
            'format' => 'formato',
            'is_recurrent' => 'si es recurrente',
            'recurrence_frequency' => 'frecuencia de recurrencia',
            'recurrence_until' => 'hasta cuando se repite',
            'payment_status' => 'si ya pago o queda pendiente',
            'price' => 'costo',
            default => $field,
        };
    }

    private function resolveSinglePatient(int $userId, ?string $name, ?int $selectedPatientId = null, ?array $intent = null)
    {
        if (! empty($name) && is_numeric($name)) {
            $name = null;
        }

        if ($selectedPatientId) {
            $patient = Patient::query()
                ->where('id', $selectedPatientId)
                ->whereHas('connections', fn ($connection) => $connection->where('user', $userId))
                ->first();

            if (! $patient) {
                return response()->json([
                    'type' => 'message',
                    'message' => 'No pude usar ese paciente para tu cuenta.',
                ], 404);
            }

            return $patient;
        }

        if (! $name) {
            return response()->json([
                'type' => 'message',
                'message' => 'Dime el nombre del paciente.',
            ]);
        }

        $patients = $this->matchingPatients($userId, $name);

        if ($patients->isEmpty()) {
            return response()->json([
                'type' => 'message',
                'message' => "No encontre pacientes que coincidan con {$name}.",
            ]);
        }

        if ($patients->count() > 1) {
            return response()->json([
                'type' => 'patients',
                'message' => 'Encontre varios pacientes. Especifica un poco mas el nombre.',
                'patients' => $patients,
                'intent' => $intent,
            ]);
        }

        return Patient::find($patients->first()['id']);
    }

    private function matchingPatients(int $userId, ?string $name)
    {
        $query = Patient::query()
            ->select(['patients.id', 'patients.name', 'patients.email', 'patients.contacto', 'patients.relevantes'])
            ->whereHas('connections', fn ($connection) => $connection->where('user', $userId));

        if ($name) {
            $terms = collect(preg_split('/\s+/', trim($name)))
                ->filter()
                ->take(5)
                ->values();

            foreach ($terms as $term) {
                $query->where('name', 'like', '%' . str_replace(['%', '_'], ['\%', '\_'], $term) . '%');
            }
        }

        return $query
            ->orderBy('name')
            ->limit(6)
            ->get()
            ->map(fn (Patient $patient) => $this->serializePatient($patient))
            ->values();
    }

    private function hasConflict(int $userId, Carbon $start, Carbon $end): bool
    {
        return Appointment::where('user', $userId)
            ->whereNotIn('statusUser', ['Cancel'])
            ->whereNotIn('statusPatient', ['Cancel'])
            ->where('start', '<', $end)
            ->where('end', '>', $start)
            ->exists();
    }

    private function serializePatient(Patient $patient): array
    {
        return [
            'id' => $patient->id,
            'name' => $patient->name,
            'email' => $patient->email,
            'phone' => data_get($patient->contacto, 'telefono') ?: $patient->phone,
        ];
    }

    private function serializeAppointment(Appointment $appointment): array
    {
        return [
            'id' => $appointment->id,
            'title' => $appointment->title,
            'start' => optional($appointment->start)->toIso8601String(),
            'end' => optional($appointment->end)->toIso8601String(),
            'state' => $appointment->state,
        ];
    }

    private function humanDate(Carbon $date): string
    {
        return $date
            ->locale('es')
            ->timezone(self::TIMEZONE)
            ->translatedFormat('l d \d\e F \a \l\a\s H:i');
    }

    private function notifyAppointmentCreated(Appointment $appointment): void
    {
        try {
            $patient = Patient::find($appointment->patient);
            $professional = User::find($appointment->user);
            if (! $patient) {
                return;
            }

            $start = Carbon::parse($appointment->start);
            $end = Carbon::parse($appointment->end);
            $interval = $start->diff($end);
            $fecha = $start->format('d/m/Y');
            $hora = $start->format('H:i') . ' - ' . $end->format('H:i');

            $patient->notify(new CreateAppoinmentMail($appointment, $patient, $hora, $fecha, $interval));

            if ($professional) {
                $professional->notify(new ProfessionalAppointmentCreatedNotification($appointment));
            }
        } catch (\Throwable $exception) {
            Log::warning('Adel appointment notification failed: ' . $exception->getMessage());
        }
    }

    private function buildRecurringOccurrences(Carbon $start, Carbon $end, ?string $frequency, ?string $until, int $interval): array
    {
        if (! in_array($frequency, ['DAILY', 'WEEKLY', 'MONTHLY'], true) || ! $until) {
            throw new \RuntimeException('Recurrencia incompleta.');
        }

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

    private function formatCount(int $count, string $singular, string $plural): string
    {
        return $count === 1 ? "1 {$singular}" : "{$count} {$plural}";
    }

    private function helpResponse(): array
    {
        return [
            'type' => 'message',
            'message' => 'Puedo buscar pacientes, decirte la siguiente sesion y preparar sesiones nuevas. Ejemplos: "busca a Adrian Pineda", "cuando es la siguiente sesion con Adrian Pineda", "agenda una sesion para Adrian Pineda el martes a las 5pm".',
        ];
    }
}
