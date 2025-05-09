<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\URL;

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
        //
        if (env('APP_ENV') !== 'local' || env('NGROK_URL')) { // Atau kondisi lain jika Anda hanya ingin ini saat ngrok aktif
            URL::forceScheme('https');
        }
        
    }
}
