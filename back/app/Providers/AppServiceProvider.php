<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

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
        \Illuminate\Pagination\Paginator::defaultView('pagination::custom');
        \Illuminate\Pagination\Paginator::defaultSimpleView('pagination::custom');
        // Política de rate limit para API:
        // - Lectura: más permisivo
        // - Escritura: más estricto por IP/usuario + sensor objetivo
        RateLimiter::for('api-read', function (Request $request) {
            if ($this->app->environment('local')) {
                return Limit::none();
            }

            $identifier = $request->user()?->id ? 'user:'.$request->user()->id : 'ip:'.$request->ip();

            return Limit::perMinute(120)->by($identifier);
        });

        RateLimiter::for('api-write', function (Request $request) {
            if ($this->app->environment('local')) {
                return Limit::none();
            }

            $identifier = $request->user()?->id ? 'user:'.$request->user()->id : 'ip:'.$request->ip();
            $sensor = $request->route('sensor');
            $sensorId = is_object($sensor) && method_exists($sensor, 'getKey')
                ? $sensor->getKey()
                : (string) $sensor;
            $apiKeyFingerprint = substr(hash('sha256', (string) ($request->header('X-Device-Key') ?? $request->input('api_key', ''))), 0, 16);

            return Limit::perMinute(60)->by($identifier.'|sensor:'.$sensorId.'|key:'.$apiKeyFingerprint);
        });

        // Login de API más estricto para reducir credential stuffing.
        RateLimiter::for('auth-login', function (Request $request) {
            if ($this->app->environment('local')) {
                return Limit::none();
            }

            $email = strtolower((string) $request->input('email'));

            return Limit::perMinute(5)->by('email:'.$email.'|ip:'.$request->ip());
        });
    }
}
