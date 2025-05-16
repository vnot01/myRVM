<?php
namespace App\Providers;

use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Contracts\Auth\Access\Gate;
use App\Policies\UserPolicy; // Import UserPolicy Anda
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate as BaseGate;
use Illuminate\Support\Facades\Auth;
use App\Models\User;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The policy mappings for the application.
     *
     * @var array
     */
    protected $policies = [
        // Contoh:
        // User::class => UserPolicy::class,
    ];

    /**
     * Register any application authentication / authorization services.
     *
     * @param  \Illuminate\Contracts\Auth\Access\Gate  $gate
     * @return void
     */
    public function boot(Gate $gate)
    {
        $this->registerPolicies();
        // ...
    }
}