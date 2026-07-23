<?php

namespace Tests\Unit;

use App\Console\Commands\SendDailyAppointmentsWhatsApp;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

class SendDailyAppointmentsWhatsAppTest extends TestCase
{
    public function test_it_formats_whole_hour_appointments_without_minutes(): void
    {
        $this->assertSame(
            'Adrian Pineda - 9am',
            SendDailyAppointmentsWhatsApp::formatAppointmentLine(
                'Adrian Pineda',
                CarbonImmutable::parse('2026-07-22 09:00:00')
            )
        );

        $this->assertSame(
            'Axel Boyzo - 12pm',
            SendDailyAppointmentsWhatsApp::formatAppointmentLine(
                'Axel Boyzo',
                CarbonImmutable::parse('2026-07-22 12:00:00')
            )
        );
    }

    public function test_it_keeps_minutes_when_the_appointment_is_not_on_the_hour(): void
    {
        $this->assertSame(
            'Jesus Hernandez - 11:30am',
            SendDailyAppointmentsWhatsApp::formatAppointmentLine(
                'Jesus Hernandez',
                CarbonImmutable::parse('2026-07-22 11:30:00')
            )
        );
    }

    public function test_it_removes_characters_forbidden_by_meta_template_parameters(): void
    {
        $this->assertSame(
            'Jesus Hernandez - 11am',
            SendDailyAppointmentsWhatsApp::formatAppointmentLine(
                "Jesus\n    Hernandez",
                CarbonImmutable::parse('2026-07-22 11:00:00')
            )
        );
    }
}
