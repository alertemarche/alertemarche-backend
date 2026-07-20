<?php

namespace App\Providers;

use App\Services\OpenAIService;
use App\Services\PricingService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(OpenAIService::class);
        $this->app->singleton(PricingService::class);
    }

    public function boot(): void
    {
        //
    }
}
