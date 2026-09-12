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

            // Bucket keys must never be selectable by attacker-controlled input
            // (e.g. a rotating API key fingerprint) — that lets an attacker get
            // a fresh bucket per request. Key only on the IP and the sensor
            // route param (resolved server-side from the URL, not the body).
            $ip = $request->ip();
            $sensor = $request->route('sensor');
            $sensorId = is_object($sensor) && method_exists($sensor, 'getKey')
                ? $sensor->getKey()
                : (string) $sensor;

            return [
                Limit::perMinute(60)->by('iot-write:ip:'.$ip),
                Limit::perMinute(30)->by('iot-write:ip:'.$ip.':sensor:'.$sensorId),
            ];
        });

        // Login de API más estricto para reducir credential stuffing.
        RateLimiter::for('auth-login', function (Request $request) {
            if ($this->app->environment('local')) {
                return Limit::none();
            }

            $email = strtolower(trim((string) $request->input('email')));
            $ip = $request->ip();

            return [
                // Global per-IP ceiling: rotating the email cannot bypass it.
                Limit::perMinute(5)->by('login:ip:'.$ip),
                Limit::perMinute(5)->by('login:email:'.$email.':ip:'.$ip),
            ];
        });
    }
}
