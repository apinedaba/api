<?php
namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\AiDiagnosisService;

class AiDiagnosisServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->singleton(AiDiagnosisService::class, function ($app) {
            return new AiDiagnosisService();
        });
    }

    public function boot()
    {
        //
    }
}
