<?php

use App\Http\Middleware\AssignRequestContext;
use App\Support\SafeExceptionContext;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withCommands()
    ->withBroadcasting(
        __DIR__.'/../routes/channels.php',
        [
            // Matches front/src/realtime/echo.js authEndpoint (`${apiBaseUrl}/broadcasting/auth`)
            // and its Bearer-token auth header — this app uses Sanctum PATs, not session cookies
            // (config/cors.php has supports_credentials=false), so the auth route must live under
            // /api and be guarded by auth:sanctum rather than Laravel's default 'web' group.
            'prefix' => 'api',
            'middleware' => ['auth:sanctum'],
        ],
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->prependToGroup('api', [
            AssignRequestContext::class,
        ]);

        $middleware->alias([
            'admin' => \App\Http\Middleware\EnsureUserIsAdmin::class,
            'permission' => \App\Http\Middleware\EnsureUserHasPermission::class,
            'api.metrics' => \App\Http\Middleware\TrackApiPerformance::class,
            'ingestion.token' => \App\Http\Middleware\EnsureIngestionToken::class,
            // SEC-AUTH-002: not registered by default in Laravel 12's minimal bootstrap/app.php
            // skeleton (Sanctum ships the middleware classes but relies on the app to alias them,
            // same pattern as the other aliases above). Confirmed missing before adding.
            'abilities' => \Laravel\Sanctum\Http\Middleware\CheckAbilities::class,
            'ability' => \Laravel\Sanctum\Http\Middleware\CheckForAnyAbility::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->respond(function (Response $response): Response {
            $request = request();
            $requestId = $request?->attributes->get('request_id');

            if ($request?->is('api/*') && is_string($requestId)) {
                $response->headers->set('X-Request-Id', $requestId);
            }

            return $response;
        });

        $exceptions->report(function (Throwable $e) {
            $request = request();
            Log::error('Application exception', [
                'request_id' => $request?->attributes->get('request_id'),
                ...SafeExceptionContext::from($e),
            ]);
        })->stop();
    })->create();
