<?php

namespace Tests\Feature;

use App\Console\Kernel;
use Illuminate\Cache\DatabaseStore;
use Illuminate\Console\Scheduling\Schedule;
use ReflectionMethod;
use Tests\TestCase;

class SchedulerCacheTest extends TestCase
{
    public function test_overlapping_guards_use_the_database_instead_of_file_cache(): void
    {
        $schedule = app(Schedule::class);
        $registerSchedule = new ReflectionMethod(app(Kernel::class), 'schedule');
        $registerSchedule->setAccessible(true);
        $registerSchedule->invoke(app(Kernel::class), $schedule);

        $dailyWhatsApp = collect($schedule->events())->first(
            fn ($event) => str_contains($event->command, 'appointments:send-daily-whatsapp')
        );

        $this->assertNotNull($dailyWhatsApp);
        $this->assertTrue($dailyWhatsApp->withoutOverlapping);
        $this->assertSame('*/5 * * * *', $dailyWhatsApp->expression);
        $this->assertSame(30, $dailyWhatsApp->expiresAt);
        $this->assertSame('scheduler', $dailyWhatsApp->mutex->store);
        $this->assertInstanceOf(DatabaseStore::class, app('cache')->store('scheduler')->getStore());
    }
}
