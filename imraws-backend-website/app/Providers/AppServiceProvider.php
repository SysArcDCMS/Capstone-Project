<?php

namespace App\Providers;

use App\Services\AiRoutingService;
use App\Services\NlpService;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // FastAPI NLP wrapper.
        $this->app->singleton(NlpService::class, function ($app) {
            return new NlpService(
                baseUrl:        (string) env('NLP_SERVICE_URL', 'http://127.0.0.1:8000'),
                timeoutSeconds: (int)    env('NLP_SERVICE_TIMEOUT', 30),
            );
        });

        // AI-Driven Routing engine — capstone DFD 3.0.
        $this->app->singleton(AiRoutingService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Site-styled pagination (no Tailwind/Bootstrap dependency).
        Paginator::defaultView('pagination.imraws');
    }
}
