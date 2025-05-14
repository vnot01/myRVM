<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->web(append: [
            \Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets::class,
        ]);

        //
        $middleware->alias([
            'auth.rvm' => \App\Http\Middleware\AuthenticateRvm::class,
            'role'     => \App\Http\Middleware\CheckRole::class,
        ]);
        // Tambahkan HandleInertiaRequests ke grup 'web'
        // Cara yang umum adalah menambahkannya ke $middleware->web(...)
        // atau jika Anda ingin lebih spesifik, bisa juga di akhir semua middleware global
        // atau sebagai middleware group sendiri jika perlu.
        // Untuk Inertia, biasanya di akhir grup 'web'.
        $middleware->appendToGroup('web', [ // Atau $middleware->web([...]) jika Anda mendefinisikan semua di sana
            \App\Http\Middleware\HandleInertiaRequests::class,
        ]);

        // Pastikan middleware lain yang penting untuk sesi dan CSRF juga ada di grup 'web'
        // seperti EncryptCookies, AddQueuedCookiesToResponse, StartSession, ShareErrorsFromSession, VerifyCsrfToken.
        // Laravel 12 biasanya sudah mengaturnya dengan baik secara default.
        // HandleInertiaRequests biasanya diletakkan setelah StartSession.

    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
