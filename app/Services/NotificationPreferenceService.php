<?php

namespace App\Services;

use App\Models\NotificationPreference;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

class NotificationPreferenceService
{
    public const CATALOG = [
        'appointment_created' => ['label' => 'Sesión creada', 'channels' => ['database', 'push', 'mail', 'whatsapp'], 'critical' => false],
        'appointment_reminder' => ['label' => 'Recordatorios de sesión', 'channels' => ['database', 'push', 'mail', 'whatsapp'], 'critical' => false],
        'appointment_status' => ['label' => 'Cambios y cancelaciones', 'channels' => ['database', 'push', 'mail', 'whatsapp'], 'critical' => false],
        'session_start_code' => ['label' => 'Código de inicio de sesión', 'channels' => ['database', 'push', 'mail', 'whatsapp'], 'critical' => true],
        'patient_invitation' => ['label' => 'Invitación de paciente', 'channels' => ['database', 'push', 'mail', 'whatsapp'], 'critical' => true],
        'patient_consent_signed' => ['label' => 'Consentimiento firmado', 'channels' => ['database', 'push'], 'critical' => false],
        'patient_document' => ['label' => 'Documentos clínicos', 'channels' => ['database', 'push', 'mail'], 'critical' => false],
        'session_payment' => ['label' => 'Pagos de sesiones', 'channels' => ['database', 'push', 'mail'], 'critical' => false],
        'subscription_billing' => ['label' => 'Cobros de membresía', 'channels' => ['database', 'push', 'mail', 'whatsapp'], 'critical' => true],
        'community' => ['label' => 'Mentes en Red', 'channels' => ['database', 'push'], 'critical' => false],
        'minder' => ['label' => 'Minder', 'channels' => ['database', 'push', 'mail'], 'critical' => false],
    ];

    public function eventKey(Notification $notification): string
    {
        if (method_exists($notification, 'eventKey')) return (string) $notification->eventKey();
        $name = class_basename($notification);
        return match (true) {
            str_contains($name, 'AppointmentReminder') => 'appointment_reminder',
            str_contains($name, 'AppointmentCreated'), str_contains($name, 'CreateAppoinment'), str_contains($name, 'RecurringAppointment') => 'appointment_created',
            str_contains($name, 'AppointmentStatus'), str_contains($name, 'StateAppoinment'), str_contains($name, 'AppointmentCancelled'), str_contains($name, 'AppointmentRescheduled') => 'appointment_status',
            str_contains($name, 'SessionStartCode') => 'session_start_code',
            str_contains($name, 'PatientInvitation'), str_contains($name, 'PatientAssigned'), str_contains($name, 'NuevoPacienteBienvenida') => 'patient_invitation',
            str_contains($name, 'Consent') => 'patient_consent_signed',
            str_contains($name, 'Document') => 'patient_document',
            str_contains($name, 'Payment') => 'session_payment',
            str_contains($name, 'Subscription'), str_contains($name, 'Membership') => 'subscription_billing',
            str_contains($name, 'Minder') => 'minder',
            str_contains($name, 'Pregunta'), str_contains($name, 'Respuesta'), str_contains($name, 'RedContent') => 'community',
            default => Str::snake(Str::replaceLast('Notification', '', $name)),
        };
    }

    public function enabled(object $notifiable, Notification $notification, string $channel): bool
    {
        $eventKey = $this->eventKey($notification);
        $definition = self::CATALOG[$eventKey] ?? null;
        if ($definition && !in_array($channel, $definition['channels'], true)) return false;
        if (($definition['critical'] ?? false) && in_array($channel, ['database', 'mail'], true)) return true;
        $preference = NotificationPreference::query()->whereMorphedTo('notifiable', $notifiable)->where('event_key', $eventKey)->first();
        if (!$preference) return true;
        return in_array($channel === 'database' ? 'database' : $channel, $preference->channels ?? [], true);
    }
}
