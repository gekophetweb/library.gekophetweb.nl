<?php

namespace App\Providers;

use App\Models\User;
use App\Services\Auth\SocialiteSsoDriverFactory;
use App\Services\Auth\SsoDriverFactory;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(SsoDriverFactory::class, SocialiteSsoDriverFactory::class);
    }

    public function boot(): void
    {
        Gate::define('admin', fn (User $user) => $user->is_admin);
    }
}
