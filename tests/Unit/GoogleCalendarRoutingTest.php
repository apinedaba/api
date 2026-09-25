<?php

namespace Tests\Unit;

use App\Models\Appointment;
use App\Models\GoogleAccount;
use App\Models\User;
use App\Services\GoogleCalendarService;
use Carbon\Carbon;
use Tests\TestCase;

class GoogleCalendarRoutingTest extends TestCase
{
    public function test_it_uses_the_first_matching_rule_in_the_professional_timezone(): void
    {
        $user = new User(['timezone' => 'America/Hermosillo']);
        $user->setRelation('googleAccount', new GoogleAccount([
            'default_calendar_id' => 'default-calendar',
            'calendar_sync_rules' => [
                ['start_time' => '09:00', 'end_time' => '13:00', 'calendar_id' => 'morning-calendar', 'enabled' => true],
                ['start_time' => '13:00', 'end_time' => '18:00', 'calendar_id' => 'afternoon-calendar', 'enabled' => true],
            ],
        ]));
        $appointment = new Appointment([
            'start' => Carbon::parse('2030-08-28 11:00', 'America/Mexico_City'),
        ]);

        $this->assertSame('morning-calendar', app(GoogleCalendarService::class)->resolveCalendarId($appointment, $user));
    }

    public function test_manual_calendar_selection_overrides_routing_rules(): void
    {
        $user = new User(['timezone' => 'America/Hermosillo']);
        $user->setRelation('googleAccount', new GoogleAccount([
            'default_calendar_id' => 'default-calendar',
            'calendar_sync_rules' => [
                ['start_time' => '00:00', 'end_time' => '23:59', 'calendar_id' => 'rule-calendar', 'enabled' => true],
            ],
        ]));
        $appointment = new Appointment([
            'start' => Carbon::parse('2030-08-28 11:00', 'America/Mexico_City'),
            'google_calendar_id' => 'manually-selected-calendar',
        ]);

        $this->assertSame('manually-selected-calendar', app(GoogleCalendarService::class)->resolveCalendarId($appointment, $user));
    }
}
