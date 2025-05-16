<?php

namespace App\Providers;

// use Illuminate\Support\Facades\Gate; // Uncomment jika Anda menggunakan Gates
use App\Models\User; // Import model User Anda
use App\Policies\UserPolicy; // Import UserPolicy Anda
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        User::class => UserPolicy::class, // Daftarkan UserPolicy Anda di sini
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        $this->registerPolicies();

        // Anda bisa mendefinisikan Gate di sini jika perlu
        // Gate::define('edit-settings', function (User $user) {
        //     return $user->isAdmin();
        // });
    }
}