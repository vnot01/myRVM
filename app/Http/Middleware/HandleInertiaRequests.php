<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;
use Tighten\Ziggy\Ziggy;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return array_merge(parent::share($request), [
            'auth' => [
                'user' => $request->user() ? [ // Hanya kirim data user yang relevan & aman
                    'id' => $request->user()->id,
                    'name' => $request->user()->name,
                    'email' => $request->user()->email,
                    'role' => $request->user()->role, // Pastikan role dikirim
                    // Jangan kirim password hash atau data sensitif lainnya
                ] : null,
            ],
            'ziggy' => fn () => [ // Konfigurasi Ziggy
                ...(new Ziggy)->toArray(),
                'location' => $request->url(),
            ],
            // --- INI BAGIAN PENTING UNTUK FLASH MESSAGES ---
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error'   => fn () => $request->session()->get('error'),
                'warning' => fn () => $request->session()->get('warning'),
                'info'    => fn () => $request->session()->get('info'),
            ],
            // --- AKHIR BAGIAN FLASH MESSAGES ---
        ]);
        // return [
        //     ...parent::share($request),
        //     'auth' => [
        //         'user' => $request->user(),
        //     ],
        //     'ziggy' => fn () => [
        //         ...(new Ziggy)->toArray(),
        //         'location' => $request->url(),
        //     ],
        // ];
    }
}
