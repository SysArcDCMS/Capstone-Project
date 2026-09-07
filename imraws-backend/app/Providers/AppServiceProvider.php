<?php

namespace App\Providers;

use App\Services\NlpService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Bind the FastAPI NLP wrapper with config from .env.
        $this->app->singleton(NlpService::class, function ($app) {
            return new NlpService(
                baseUrl:        (string) env('NLP_SERVICE_URL', 'http://127.0.0.1:8000'),
                timeoutSeconds: (int)    env('NLP_SERVICE_TIMEOUT', 30),
            );
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
