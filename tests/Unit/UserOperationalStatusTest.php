<?php

namespace Tests\Unit;

use App\Models\User;
use PHPUnit\Framework\TestCase;

class UserOperationalStatusTest extends TestCase
{
    public function test_operational_setup_requires_profile_specialties_schedule_and_services(): void
    {
        $user = new User([
            'isProfileComplete' => true,
            'contacto' => ['telefono' => '5512345678'],
            'educacion' => ['especialidades' => ['ansiedad']],
            'horarios' => ['lunes' => [['start' => '09:00', 'end' => '10:00']]],
            'configurations' => ['sesiones' => [['tipoSesion' => 'Individual']]],
        ]);

        $this->assertTrue($user->hasOperationalSetup());

        $user->horarios = [];
        $this->assertFalse($user->hasOperationalSetup());

        $user->horarios = ['lunes' => [['start' => '09:00', 'end' => '10:00']]];
        $user->configurations = ['sesiones' => []];
        $this->assertFalse($user->hasOperationalSetup());
    }

    public function test_identity_approval_is_not_part_of_operational_setup(): void
    {
        $user = new User([
            'isProfileComplete' => true,
            'contacto' => ['telefono' => '5512345678'],
            'identity_verification_status' => 'pending',
            'educacion' => ['especialidades' => ['pareja']],
            'horarios' => ['martes' => [['start' => '10:00', 'end' => '11:00']]],
            'configurations' => ['sesiones' => [['tipoSesion' => 'Pareja']]],
        ]);

        $this->assertTrue($user->hasOperationalSetup());
    }

    public function test_operational_setup_requires_a_valid_phone(): void
    {
        $user = new User([
            'isProfileComplete' => true,
            'contacto' => ['telefono' => null],
            'educacion' => ['especialidades' => ['ansiedad']],
            'horarios' => ['lunes' => [['start' => '09:00', 'end' => '10:00']]],
            'configurations' => ['sesiones' => [['tipoSesion' => 'Individual']]],
        ]);

        $this->assertFalse($user->hasOperationalSetup());

        $user->contacto = ['telefono' => '+52 55 1234 5678'];
        $this->assertTrue($user->hasValidPhone());
        $this->assertTrue($user->hasOperationalSetup());
    }

    public function test_valid_whatsapp_can_be_copied_when_phone_is_missing(): void
    {
        $user = new User([
            'contacto' => [
                'telefono' => null,
                'whatsapp' => '+52 55 9876 5432',
            ],
        ]);

        $this->assertTrue($user->syncPhoneFromWhatsapp(false));
        $this->assertSame('5598765432', data_get($user->contacto, 'telefono'));
        $this->assertTrue($user->hasValidPhone());
    }

    public function test_whatsapp_does_not_replace_an_existing_valid_phone(): void
    {
        $user = new User([
            'contacto' => [
                'telefono' => '5512345678',
                'whatsapp' => '5598765432',
            ],
        ]);

        $this->assertFalse($user->syncPhoneFromWhatsapp(false));
        $this->assertSame('5512345678', data_get($user->contacto, 'telefono'));
    }

    public function test_invalid_whatsapp_is_not_copied_to_phone(): void
    {
        $user = new User([
            'contacto' => [
                'telefono' => null,
                'whatsapp' => '12345',
            ],
        ]);

        $this->assertFalse($user->syncPhoneFromWhatsapp(false));
        $this->assertNull(data_get($user->contacto, 'telefono'));
    }
}
