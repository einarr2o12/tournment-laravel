<?php

declare(strict_types=1);

namespace App\Providers;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * Register any authentication / authorization services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any authentication / authorization services.
     */
    public function boot(): void
    {
        Gate::define('admin', static function (User $user): bool {
            return $user->role === UserRole::ADMIN;
        });

        Gate::define('referee', static function (User $user): bool {
            return $user->role === UserRole::REFEREE
                || $user->role === UserRole::ADMIN;
        });
    }
}
