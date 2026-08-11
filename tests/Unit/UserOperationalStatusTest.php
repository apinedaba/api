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
}
