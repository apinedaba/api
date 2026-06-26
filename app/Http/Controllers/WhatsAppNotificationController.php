<?php

namespace App\Http\Controllers;

use App\Jobs\SendWhatsAppMessageJob;
use App\Models\Appointment;
use App\Services\WhatsApp\WhatsAppService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class WhatsAppNotificationController extends Controller
{
    public function appointmentCreated(
        Request $request,
        Appointment $appointment,
        WhatsAppService $whatsApp
    ): JsonResponse {
        $this->authorizeAppointment($request, $appointment);

        return $this->dispatchAppointmentTemplate(
            $request,
            $appointment,
            $whatsApp,
            'appointment_created',
            'cita creada'
        );
    }

    public function appointmentReminder(
        Request $request,
        Appointment $appointment,
        WhatsAppService $whatsApp
    ): JsonResponse {
        $this->authorizeAppointment($request, $appointment);

        return $this->dispatchAppointmentTemplate(
            $request,
            $appointment,
            $whatsApp,
            'appointment_reminder',
            'recordatorio de cita'
        );
    }

    public function appointmentCancelled(
        Request $request,
        Appointment $appointment,
        WhatsAppService $whatsApp
    ): JsonResponse {
        $this->authorizeAppointment($request, $appointment);

        return $this->dispatchAppointmentTemplate(
            $request,
            $appointment,
            $whatsApp,
            'appointment_cancelled',
            'cita cancelada'
        );
    }

    public function template(Request $request): JsonResponse
    {
        $data = $request->validate([
            'phone' => ['required', 'string'],
            'template' => ['required', 'string'],
            'language' => ['nullable', 'string'],
            'parameters' => ['nullable', 'array'],
            'components' => ['nullable', 'array'],
            'context' => ['nullable', 'array'],
        ]);

        SendWhatsAppMessageJob::dispatch([
            'message_type' => 'template',
            'phone' => $data['phone'],
            'template' => $data['template'],
            'language' => $data['language'] ?? 'es_MX',
            'parameters' => $data['parameters'] ?? [],
            'components' => $data['components'] ?? [],
            'context' => $data['context'] ?? [],
        ]);

        return $this->queuedResponse('Template WhatsApp encolado.');
    }

    public function interactiveButtons(Request $request): JsonResponse
    {
        $data = $request->validate([
            'phone' => ['required', 'string'],
            'header' => ['nullable', 'string', 'max:60'],
            'body' => ['required', 'string', 'max:1024'],
            'footer' => ['nullable', 'string', 'max:60'],
            'buttons' => ['required', 'array', 'min:1', 'max:3'],
            'buttons.*.id' => ['required', 'string', 'max:256'],
            'buttons.*.title' => ['required', 'string', 'max:20'],
            'context' => ['nullable', 'array'],
        ]);

        SendWhatsAppMessageJob::dispatch([
            'message_type' => 'interactive_buttons',
            'phone' => $data['phone'],
            'header' => $data['header'] ?? null,
            'body' => $data['body'],
            'footer' => $data['footer'] ?? null,
            'buttons' => $data['buttons'],
            'context' => $data['context'] ?? [],
        ]);

        return $this->queuedResponse('Mensaje interactivo WhatsApp encolado.');
    }

    protected function dispatchAppointmentTemplate(
        Request $request,
        Appointment $appointment,
        WhatsAppService $whatsApp,
        string $templateKey,
        string $event
    ): JsonResponse {
        $data = $request->validate([
            'phone' => ['nullable', 'string'],
            'template' => ['nullable', 'string'],
            'language' => ['nullable', 'string'],
            'buttons' => ['nullable', 'array', 'max:3'],
            'buttons.*.id' => ['nullable', 'string', 'max:256'],
            'buttons.*.payload' => ['nullable', 'string', 'max:256'],
            'buttons.*.text' => ['nullable', 'string', 'max:256'],
            'buttons.*.sub_type' => ['nullable', Rule::in(['quick_reply', 'url'])],
            'buttons.*.parameter_type' => ['nullable', Rule::in(['payload', 'text'])],
        ]);

        $appointment->loadMissing(['patient', 'user']);
        $patient = $appointment->patient()->first();

        abort_unless($patient, 404, 'La cita no tiene paciente asociado.');

        $phone = $data['phone'] ?? $patient->phone;

        abort_unless(filled($phone), 422, 'El paciente no tiene telefono registrado.');

        SendWhatsAppMessageJob::dispatch([
            'message_type' => 'template',
            'phone' => $phone,
            'template' => $data['template'] ?? $whatsApp->templateName($templateKey),
            'language' => $data['language'] ?? 'es_MX',
            'components' => $whatsApp->appointmentTemplateComponents(
                $appointment,
                $data['buttons'] ?? $this->defaultAppointmentUrlButton($appointment)
            ),
            'context' => [
                'appointment_id' => $appointment->id,
                'patient_id' => $patient->id,
                'user_id' => $appointment->user,
                'event' => $templateKey,
            ],
        ]);

        return $this->queuedResponse('Notificacion WhatsApp de cita encolada.');
    }

    protected function authorizeAppointment(Request $request, Appointment $appointment): void
    {
        $user = $request->user();

        abort_unless($user && (int) $appointment->user === (int) $user->id, 403);
    }

    protected function defaultAppointmentUrlButton(Appointment $appointment): array
    {
        return [
            [
                'sub_type' => 'url',
                'parameter_type' => 'text',
                'text' => $appointment->public_uuid ?: (string) $appointment->id,
            ],
        ];
    }

    protected function queuedResponse(string $message): JsonResponse
    {
        return response()->json([
            'message' => $message,
            'status' => 'queued',
        ], 202);
    }
}
