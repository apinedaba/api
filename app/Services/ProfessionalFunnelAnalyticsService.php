<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\ConsultaContacto;
use App\Models\Payment;
use App\Models\ProfessionalAnalyticsEvent;
use Illuminate\Support\Facades\Schema;

class ProfessionalFunnelAnalyticsService
{
    public function appointmentBooked(Appointment $appointment): void
    {
        $lead = $this->leadFor($appointment);
        $isRepeat = Appointment::query()
            ->where('user', $appointment->user)
            ->where('patient', $appointment->patient)
            ->whereKeyNot($appointment->id)
            ->where('start', '<', $appointment->start)
            ->where(function ($query) {
                $query->whereNotNull('completed_at')
                    ->orWhereIn('lifecycle_status', ['completed', 'completada'])
                    ->orWhereIn('statusUser', ['completed', 'completada', 'finalizada']);
            })
            ->exists();

        $this->record(
            $isRepeat ? 'repeat_appointment_booked' : 'appointment_booked',
            "appointment:{$appointment->id}:booked",
            $appointment,
            $lead,
            null,
            ['is_repeat' => $isRepeat]
        );
    }

    public function appointmentCompleted(Appointment $appointment): void
    {
        $this->record(
            'session_completed',
            "appointment:{$appointment->id}:completed",
            $appointment,
            $this->leadFor($appointment)
        );
    }

    public function appointmentPaid(Appointment $appointment): void
    {
        $this->record(
            'appointment_paid',
            "appointment:{$appointment->id}:paid",
            $appointment,
            $this->leadFor($appointment)
        );
    }

    public function paymentCompleted(Payment $payment): void
    {
        if (! $payment->appointment_id || ! $payment->appointment) {
            return;
        }

        $this->record(
            'payment_completed',
            "payment:{$payment->id}:completed",
            $payment->appointment,
            $this->leadFor($payment->appointment),
            $payment,
            [
                'amount' => $payment->amount,
                'currency' => $payment->currency,
                'payment_method' => $payment->payment_method,
            ]
        );
    }

    private function record(
        string $eventType,
        string $eventKey,
        Appointment $appointment,
        ?ConsultaContacto $lead = null,
        ?Payment $payment = null,
        array $metadata = []
    ): void {
        if (! $appointment->user || ! Schema::hasColumn('professional_analytics_events', 'event_key')) {
            return;
        }

        ProfessionalAnalyticsEvent::query()->firstOrCreate(
            ['event_key' => $eventKey],
            [
                'user_id' => $appointment->user,
                'consulta_contacto_id' => $lead?->id,
                'appointment_id' => $appointment->id,
                'payment_id' => $payment?->id,
                'patient_id' => $appointment->patient,
                'event_type' => $eventType,
                'source' => $lead?->lead_source ?: $lead?->utm_source,
                'medium' => $lead?->lead_medium ?: $lead?->utm_medium,
                'campaign' => $lead?->lead_campaign ?: $lead?->utm_campaign,
                'landing_page' => $lead?->landing_page,
                'referrer' => $lead?->referrer,
                'metadata' => array_filter(array_merge([
                    'lead_type' => $lead?->lead_type,
                    'appointment_start' => $appointment->start?->toIso8601String(),
                ], $metadata), fn ($value) => $value !== null),
            ]
        );
    }

    private function leadFor(Appointment $appointment): ?ConsultaContacto
    {
        return ConsultaContacto::query()
            ->where('user_id', $appointment->user)
            ->where('created_at', '<=', $appointment->created_at ?: now())
            ->where(function ($query) use ($appointment) {
                $query->where('appointment_id', $appointment->id);
                if ($appointment->patient) {
                    $query->orWhere('patient_id', $appointment->patient);
                }
            })
            ->orderByRaw('CASE WHEN appointment_id = ? THEN 0 ELSE 1 END', [$appointment->id])
            ->latest('converted_at')
            ->latest('created_at')
            ->first();
    }
}
