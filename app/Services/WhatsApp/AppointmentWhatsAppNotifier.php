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

    public function appointmentRescheduled(Appointment $appointment, string $source = 'appointments.rescheduled'): bool
    {
        return $this->dispatchAppointmentTemplate($appointment, 'appointment_rescheduled', $source);
    }

    public function sessionStartCode(Appointment $appointment, string $source = 'appointments.start-code'): bool
    {
        return $this->dispatchAppointmentTemplate($appointment, 'session_start_code', $source);
    }

    protected function dispatchAppointmentTemplate(Appointment $appointment, string $templateKey, string $source): bool
    {
        $appointment->loadMissing(['patient', 'user']);
        $patient = $appointment->patient()->first();
        $professional = $appointment->user()->first();
        $rule = $this->notificationRule($templateKey);
        $resolvedTemplateKey = $rule?->whatsapp_template_key;
        $recipientType = $rule?->recipient ?: 'patient';
        $recipient = $recipientType === 'professional' ? $professional : $patient;

        Log::channel('whatsapp')->info('WhatsApp appointment notification flow started', [
            'source' => $source,
            'event' => $templateKey,
            'resolved_template_key' => $resolvedTemplateKey,
            'appointment_id' => $appointment->id,
            'patient_id' => $appointment->patient,
            'user_id' => $appointment->user,
            'has_patient' => (bool) $patient,
            'has_patient_phone' => filled($patient?->phone),
            'recipient' => $recipientType,
            'has_recipient_phone' => filled($recipient?->phone),
            'rule_active' => $rule?->is_active,
            'rule_channels' => $rule?->channels,
        ]);

        if (! $rule || ! $rule->sendsTo('whatsapp') || ! filled($resolvedTemplateKey)) {
            Log::channel('whatsapp')->info('WhatsApp appointment notification skipped by rule', [
                'source' => $source,
                'event' => $templateKey,
                'appointment_id' => $appointment->id,
                'rule_id' => $rule?->id,
                'channels' => $rule?->channels,
                'has_template_assignment' => filled($resolvedTemplateKey),
            ]);

            return false;
        }

        if (! $recipient) {
            Log::channel('whatsapp')->warning('WhatsApp appointment notification skipped: recipient not found', [
                'source' => $source,
                'event' => $templateKey,
                'appointment_id' => $appointment->id,
            ]);

            return false;
        }

        if (! filled($recipient->phone)) {
            Log::channel('whatsapp')->warning('WhatsApp appointment notification skipped: missing recipient phone', [
                'source' => $source,
                'event' => $templateKey,
                'appointment_id' => $appointment->id,
                'recipient' => $recipientType,
                'recipient_id' => $recipient->id,
            ]);

            return false;
        }

        $templateConfig = $this->templateConfig($resolvedTemplateKey);
        if (! $templateConfig || ! filled($templateConfig->template_name)) {
            Log::channel('whatsapp')->info('WhatsApp appointment notification skipped: template not configured', [
                'event' => $templateKey,
                'template_key' => $resolvedTemplateKey,
                'appointment_id' => $appointment->id,
            ]);

            return false;
        }

        $template = $templateConfig->template_name;
        $buttons = $templateConfig?->buttons ?: $this->defaultButtons($appointment, $templateKey);
        $bodyParameterKeys = $templateConfig->body_parameters ?: [];

        SendWhatsAppMessageJob::dispatch([
            'message_type' => 'template',
            'phone' => $recipient->phone,
            'template' => $template,
            'language' => $templateConfig->language ?: 'es_MX',
            'components' => $this->whatsApp->appointmentTemplateComponents(
                $appointment,
                $buttons,
                $bodyParameterKeys,
                $recipientType
            ),
            'context' => [
                'appointment_id' => $appointment->id,
                'patient_id' => $patient?->id,
                'user_id' => $appointment->user,
                'event' => $templateKey,
                'template_key' => $resolvedTemplateKey,
                'recipient' => $recipientType,
                'source' => $source,
            ],
        ]);

        Log::channel('whatsapp')->info('WhatsApp appointment notification queued', [
            'source' => $source,
            'event' => $templateKey,
            'appointment_id' => $appointment->id,
            'patient_id' => $patient?->id,
            'recipient' => $recipientType,
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

    protected function defaultButtons(Appointment $appointment, string $templateKey): array
    {
        if ($templateKey === 'appointment_reminder') {
            return [
                ['sub_type' => 'quick_reply', 'parameter_type' => 'payload', 'payload' => "appointment:{$appointment->id}:confirm"],
                ['sub_type' => 'quick_reply', 'parameter_type' => 'payload', 'payload' => "appointment:{$appointment->id}:postpone"],
            ];
        }

        return $this->defaultAppointmentUrlButton($appointment);
    }
}
