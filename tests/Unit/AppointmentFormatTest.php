<?php

namespace Tests\Unit;

use App\Models\Appointment;
use App\Models\AppointmentCart;
use Tests\TestCase;

class AppointmentFormatTest extends TestCase
{
    public function test_it_identifies_in_person_format_from_extended_properties(): void
    {
        $appointment = new Appointment([
            'extendedProps' => ['formato' => 'Presencial'],
        ]);
        $appointment->setRelation('cart', null);

        $this->assertSame('presencial', $appointment->sessionFormat());
        $this->assertTrue($appointment->isInPerson());
    }

    public function test_it_keeps_online_sessions_eligible_for_meet(): void
    {
        $appointment = new Appointment([
            'extendedProps' => ['formato' => 'online'],
        ]);
        $appointment->setRelation('cart', null);

        $this->assertFalse($appointment->isInPerson());
    }

    public function test_cart_format_has_priority_over_legacy_properties(): void
    {
        $appointment = new Appointment([
            'extendedProps' => ['formato' => 'online'],
        ]);
        $appointment->setRelation('cart', new AppointmentCart([
            'formato' => 'presencial',
        ]));

        $this->assertTrue($appointment->isInPerson());
    }

    public function test_professional_completion_ignores_patient_status(): void
    {
        $appointment = new Appointment([
            'statusUser' => 'Completed',
            'statusPatient' => 'Pending Approve',
        ]);

        $this->assertTrue($appointment->isProfessionallyCompleted());
    }
}
