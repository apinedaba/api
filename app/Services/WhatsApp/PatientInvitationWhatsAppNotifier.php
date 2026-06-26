<?php

namespace App\Services\WhatsApp;

use App\Jobs\SendWhatsAppMessageJob;
use App\Models\Patient;
use App\Models\User;
use App\Models\WhatsAppNotificationRule;
use App\Models\WhatsAppTemplate;
use Illuminate\Support\Facades\Log;

class PatientInvitationWhatsAppNotifier
{
    public function __construct(protected WhatsAppService $whatsApp)
    {
    }

    public function send(User $professional, Patient $patient, ?string $initialPassword = null, string $source = 'patient.store'): bool
    {
        $rule = $this->notificationRule();

        Log::channel('whatsapp')->info('WhatsApp patient invitation flow started', [
            'source' => $source,
            'patient_id' => $patient->id,
            'user_id' => $professional->id,
            'has_patient_phone' => filled($patient->phone),
            'has_patient_whatsapp' => filled(data_get($patient->contacto, 'whatsapp')),
            'rule_active' => $rule?->is_active,
            'rule_channels' => $rule?->channels,
        ]);

        if ($rule && ! $rule->sendsTo('whatsapp')) {
            return false;
        }

        $phone = data_get($patient->contacto, 'whatsapp') ?: $patient->phone;
        if (! filled($phone)) {
            Log::channel('whatsapp')->warning('WhatsApp patient invitation skipped: missing phone', [
                'source' => $source,
                'patient_id' => $patient->id,
            ]);

            return false;
        }

        $templateKey = $rule?->whatsapp_template_key ?: 'patient_invitation';
        $template = $this->whatsApp->templateName($templateKey);
        $templateConfig = $this->templateConfig($templateKey);
        $url = rtrim(config('app.perfil_paciente_url') ?: 'https://paciente.mindmeet.com.mx', '/').'/iniciar-sesion';

        SendWhatsAppMessageJob::dispatch([
            'message_type' => 'template',
            'phone' => $phone,
            'template' => $template,
            'language' => $templateConfig?->language ?: 'es_MX',
            'parameters' => $this->templateParameters($templateConfig, $patient, $professional, $url, $initialPassword),
            'context' => [
                'patient_id' => $patient->id,
                'user_id' => $professional->id,
                'event' => 'patient_invitation',
                'template_key' => $templateKey,
                'source' => $source,
            ],
        ]);

        Log::channel('whatsapp')->info('WhatsApp patient invitation queued', [
            'source' => $source,
            'patient_id' => $patient->id,
            'user_id' => $professional->id,
            'template' => $template,
        ]);

        return true;
    }

    protected function notificationRule(): ?WhatsAppNotificationRule
    {
        try {
            return WhatsAppNotificationRule::query()
                ->where('event_key', 'patient_invitation')
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

    protected function templateParameters(
        ?WhatsAppTemplate $templateConfig,
        Patient $patient,
        User $professional,
        string $url,
        ?string $initialPassword
    ): array {
        $values = [
            'patient_name' => $patient->name,
            'url' => $url,
            'initial_password' => $initialPassword ?: 'tu contrasena actual',
            'professional_name' => $professional->name,
        ];

        $configured = $templateConfig?->body_parameters;
        if (is_array($configured) && $configured !== []) {
            return collect($configured)
                ->map(fn ($key) => $values[(string) $key] ?? '')
                ->values()
                ->all();
        }

        return array_values($values);
    }
}
