<?php

namespace Tests\Unit;

use App\Models\Appointment;
use App\Models\Patient;
use App\Models\User;
use App\Notifications\AppointmentReminderNotification;
use App\Notifications\AppointmentRescheduledWhatsAppNotification;
use App\Notifications\MembershipPaymentFailedWhatsAppNotification;
use Carbon\CarbonImmutable;
use Tests\TestCase;

class WhatsAppEventNotificationsTest extends TestCase
{
    public function test_failed_membership_uses_professional_public_name(): void
    {
        $professional = $this->professional();
        $message = (new MembershipPaymentFailedWhatsAppNotification())->toWhatsApp($professional);

        $this->assertSame('pago_fallido', $message['template']);
        $this->assertSame('Psic. Adrian', $message['components'][0]['parameters'][0]['text']);
    }

    public function test_rescheduled_appointment_sends_patient_date_and_professional_name(): void
    {
        [$appointment, $patient] = $this->appointment();
        $message = (new AppointmentRescheduledWhatsAppNotification($appointment))->toWhatsApp($patient);

        $this->assertSame('cita_reprogramada', $message['template']);
        $this->assertSame(
            ['Paciente Uno', '22/07/2026', 'Psic. Adrian'],
            array_column($message['components'][0]['parameters'], 'text')
        );
    }

    public function test_reminder_changes_first_parameter_for_each_recipient(): void
    {
        [$appointment, $patient, $professional] = $this->appointment();
        $notification = new AppointmentReminderNotification($appointment, '30m');

        $patientMessage = $notification->toWhatsApp($patient);
        $professionalMessage = $notification->toWhatsApp($professional);

        $this->assertSame('cita_recordatorio', $patientMessage['template']);
        $this->assertSame(['Psic. Adrian', '9am'], array_column($patientMessage['components'][0]['parameters'], 'text'));
        $this->assertSame(['Paciente Uno', '9am'], array_column($professionalMessage['components'][0]['parameters'], 'text'));
    }

    private function appointment(): array
    {
        $professional = $this->professional();
        $patient = new Patient([
            'name' => 'Paciente Uno',
            'phone' => '5512345678',
        ]);
        $patient->id = 20;

        $appointment = new Appointment([
            'start' => CarbonImmutable::parse('2026-07-22 09:00:00', 'America/Mexico_City'),
        ]);
        $appointment->id = 30;
        $appointment->setRelation('patient', $patient);
        $appointment->setRelation('user', $professional);

        return [$appointment, $patient, $professional];
    }

    private function professional(): User
    {
        $professional = new User([
            'name' => 'Adrian Pineda',
            'contacto' => [
                'publicname' => 'Psic. Adrian',
                'whatsapp' => '5512345678',
            ],
        ]);
        $professional->id = 10;

        return $professional;
    }
}
