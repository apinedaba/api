<?php

namespace App\Providers;

use App\Notifications\Channels\WhatsAppChannel;
use Carbon\Carbon;
use Cloudinary\Configuration\Configuration;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\ServiceProvider;
use Stripe\Stripe;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Notification::extend('whatsapp', fn ($app) => $app->make(WhatsAppChannel::class));

        Carbon::setLocale('es');
        date_default_timezone_set(config('app.timezone'));
        Stripe::setApiKey(config('services.stripe.secret_key'));
        Configuration::instance([
            'cloud' => [
                'cloud_name' => config('cloudinary.cloud_name'),
                'api_key' => config('cloudinary.api_key'),
                'api_secret' => config('cloudinary.api_secret'),
            ],
            'url' => [
                'secure' => true,
            ],
        ]);
    }
}
