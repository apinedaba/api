<?php

namespace App\Providers;

use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Notifications\Events\NotificationSending;
use Illuminate\Notifications\Events\NotificationFailed;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Event;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event to listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        Registered::class => [
            SendEmailVerificationNotification::class,
        ],
        \App\Events\AppointmentCreated::class => [
            \App\Listeners\SendAppointmentCreatedPush::class,
        ],
        NotificationSent::class => [
            \App\Listeners\BroadcastAndPushDatabaseNotification::class,
            \App\Listeners\AuditNotificationDelivery::class,
        ],
        NotificationSending::class => [
            \App\Listeners\RespectNotificationPreferences::class,
        ],
        NotificationFailed::class => [
            \App\Listeners\AuditNotificationFailure::class,
        ],
    ];

    /**
     * Register any events for your application.
     */
    public function boot(): void
    {
        //
    }

    /**
     * Determine if events and listeners should be automatically discovered.
     */
    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}
