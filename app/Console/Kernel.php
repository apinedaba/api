<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // $schedule->command('inspire')->hourly();
        $schedule->command('app:expire-trials')->daily();
        $schedule->command('queue:work --stop-when-empty')
            ->everySecond()
            ->withoutOverlapping();
        $schedule->command('appointments:send-reminders')
            ->everyFiveMinutes()
            ->timezone('America/Mexico_City');
        $schedule->command('sessions:daily-summary')->dailyAt('08:00')->timezone('America/Mexico_City');
        $schedule->command('sellers:generate-commission-cut')
            ->monthlyOn(25, '02:30')
            ->timezone('America/Mexico_City');
        //$schedule->command('mindmeet:notify-psychologists')->dailyAt('10:00')->timezone('America/Mexico_City');
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__ . '/Commands');

        require base_path('routes/console.php');
    }
}
