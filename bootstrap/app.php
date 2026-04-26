<?php

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withCommands()
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'admin' => \App\Http\Middleware\EnsureUserIsAdmin::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->report(function (ValidationException $e) {
            $request = request();
            if (! $request || ! $request->is('api/*')) {
                return;
            }

            Log::warning('API validation exception', [
                'path' => $request->path(),
                'method' => $request->method(),
                'ip' => $request->ip(),
                'errors' => $e->errors(),
            ]);
        });

        $exceptions->report(function (BadRequestHttpException $e) {
            $request = request();
            if (! $request || ! $request->is('api/*')) {
                return;
            }

            Log::warning('API bad request exception (possibly malformed payload)', [
                'path' => $request->path(),
                'method' => $request->method(),
                'ip' => $request->ip(),
                'exception' => $e->getMessage(),
            ]);
        });

        $exceptions->report(function (QueryException $e) {
            $request = request();
            Log::error('Database query exception', [
                'path' => $request?->path(),
                'method' => $request?->method(),
                'ip' => $request?->ip(),
                'sql_state' => $e->errorInfo[0] ?? null,
                'db_error_code' => $e->errorInfo[1] ?? null,
                'exception' => $e->getMessage(),
            ]);
        });

        $exceptions->report(function (\PDOException $e) {
            $request = request();
            Log::critical('Database connection exception', [
                'path' => $request?->path(),
                'method' => $request?->method(),
                'ip' => $request?->ip(),
                'exception' => $e->getMessage(),
            ]);
        });
    })->create();
