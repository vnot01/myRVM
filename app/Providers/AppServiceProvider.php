<?php

namespace App\Providers;

use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
// use App\Models\User; // <-- Tambahkan ini
// use App\Policies\UserPolicy; // <-- Tambahkan ini


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
        // app(Ziggy::class)-> group(); // Memastikan Ziggy di-resolve dan default-nya diambil
        // Jika Anda ingin override port secara eksplisit saat APP_ENV=local
        // if ($this->app->environment('local')) {
        //     config(['ziggy.port' => env('SERVER_PORT', 8000)]); // Ambil dari SERVER_PORT jika ada, atau default 8000
        //      // Atau hardcode jika selalu 8000 saat dev dengan php artisan serve
        //      // config(['ziggy.port' => 8000]);
        // }
        Vite::prefetch(concurrency: 3);
    }
}
