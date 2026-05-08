<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class ViewServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Intencionalmente vacío:
        // el conteo de alertas no resueltas se hidrata de forma asíncrona vía AppAlerts.
    }
}
