<?php

namespace App\Providers;

use App\Models\User;
use App\Services\Netbox\NetboxClient;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(NetboxClient::class, fn () => NetboxClient::make());
    }

    public function boot(): void
    {
        Gate::define('viewApiDocs', fn (?User $user) => $user !== null);
    }
}
