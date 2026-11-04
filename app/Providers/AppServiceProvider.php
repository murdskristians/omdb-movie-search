<?php

namespace App\Providers;

use App\Services\Omdb\OmdbClient;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(OmdbClient::class, fn () => new OmdbClient(
            config('services.omdb.url'),
            config('services.omdb.key'),
        ));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
