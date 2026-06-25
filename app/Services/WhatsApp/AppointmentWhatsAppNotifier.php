<?php

namespace App\Services\WhatsApp;

use App\Jobs\SendWhatsAppMessageJob;
use App\Models\Appointment;
use App\Models\WhatsAppNotificationRule;
use App\Models\WhatsAppTemplate;
use Illuminate\Support\Facades\Log;

class AppointmentWhatsAppNotifier
{
    public function __construct(protected WhatsAppService $whatsApp)
    {
    }

    public function appointmentCreated(Appointment $appointment, string $source = 'appointments.create'): bool
    {
        return $this->dispatchAppointmentTemplate($appointment, 'appointment_created', $source);
    }

    public function appointmentReminder(Appointment $appointment, string $source = 'appointments.reminder'): bool
    {
        return $this->dispatchAppointmentTemplate($appointment, 'appointment_reminder', $source);
    }

    public function appointmentCancelled(Appointment $appointment, string $source = 'appointments.cancelled'): bool
    {
        return $this->dispatchAppointmentTemplate($appointment, 'appointment_cancelled', $source);
    }

    protected function dispatchAppointmentTemplate(Appointment $appointment, string $templateKey, string $source): bool
    {
        $appointment->loadMissing(['patient', 'user']);
        $patient = $appointment->patient()->first();
        $rule = $this->notificationRule($templateKey);
        $resolvedTemplateKey = $rule?->whatsapp_template_key ?: $templateKey;

        Log::channel('whatsapp')->info('WhatsApp appointment notification flow started', [
            'source' => $source,
            'event' => $templateKey,
            'resolved_template_key' => $resolvedTemplateKey,
            'appointment_id' => $appointment->id,
            'patient_id' => $appointment->patient,
            'user_id' => $appointment->user,
            'has_patient' => (bool) $patient,
            'has_patient_phone' => filled($patient?->phone),
            'rule_active' => $rule?->is_active,
            'rule_channels' => $rule?->channels,
        ]);

        if ($rule && ! $rule->sendsTo('whatsapp')) {
            Log::channel('whatsapp')->info('WhatsApp appointment notification skipped by rule', [
                'source' => $source,
                'event' => $templateKey,
                'appointment_id' => $appointment->id,
                'rule_id' => $rule->id,
                'channels' => $rule->channels,
            ]);

            return false;
        }

        if (! $patient) {
            Log::channel('whatsapp')->warning('WhatsApp appointment notification skipped: patient not found', [
                'source' => $source,
                'event' => $templateKey,
                'appointment_id' => $appointment->id,
            ]);

            return false;
        }

        if (! filled($patient->phone)) {
            Log::channel('whatsapp')->warning('WhatsApp appointment notification skipped: missing patient phone', [
                'source' => $source,
                'event' => $templateKey,
                'appointment_id' => $appointment->id,
                'patient_id' => $patient->id,
            ]);

            return false;
        }

        $template = $this->whatsApp->templateName($resolvedTemplateKey);
        $templateConfig = $this->templateConfig($resolvedTemplateKey);
        $buttons = $templateConfig?->buttons ?: $this->defaultAppointmentButtons($appointment);

        SendWhatsAppMessageJob::dispatch([
            'message_type' => 'template',
            'phone' => $patient->phone,
            'template' => $template,
            'language' => $templateConfig?->language ?: 'es_MX',
            'components' => $this->whatsApp->appointmentTemplateComponents(
                $appointment,
                $buttons
            ),
            'context' => [
                'appointment_id' => $appointment->id,
                'patient_id' => $patient->id,
                'user_id' => $appointment->user,
                'event' => $templateKey,
                'template_key' => $resolvedTemplateKey,
                'source' => $source,
            ],
        ]);

        Log::channel('whatsapp')->info('WhatsApp appointment notification queued', [
            'source' => $source,
            'event' => $templateKey,
            'appointment_id' => $appointment->id,
            'patient_id' => $patient->id,
            'template' => $template,
        ]);

        return true;
    }

    protected function notificationRule(string $templateKey): ?WhatsAppNotificationRule
    {
        try {
            return WhatsAppNotificationRule::query()
                ->where('event_key', $templateKey)
                ->first();
        } catch (\Throwable) {
            return null;
        }
    }

    protected function templateConfig(string $templateKey): ?WhatsAppTemplate
    {
        try {
            return WhatsAppTemplate::query()
                ->active()
                ->where('key', $templateKey)
                ->first();
        } catch (\Throwable) {
            return null;
        }
    }

    protected function defaultAppointmentButtons(Appointment $appointment): array
    {
        return [
            [
                'id' => "appointment_{$appointment->id}_confirm",
                'payload' => "appointment:{$appointment->id}:confirm",
            ],
            [
                'id' => "appointment_{$appointment->id}_postpone",
                'payload' => "appointment:{$appointment->id}:postpone",
            ],
            [
                'id' => "appointment_{$appointment->id}_cancel",
                'payload' => "appointment:{$appointment->id}:cancel",
            ],
        ];
    }
}
