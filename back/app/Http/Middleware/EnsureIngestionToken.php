<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class EnsureIngestionToken
{
    public function handle(Request $request, Closure $next)
    {
        $startTime = microtime(true);
        $providedToken = (string) $request->header('X-Ingestion-Token', '');
        $maskedToken = $providedToken !== '' ? substr($providedToken, 0, 4) . '****' : '(empty)';

        Log::info('EnsureIngestionToken: validating', [
            'token_prefix' => $maskedToken,
            'path' => $request->path(),
        ]);

        $expectedToken = (string) config('app.ingestion_service_token', '');

        if ($expectedToken === '' || $providedToken === '' || ! hash_equals($expectedToken, $providedToken)) {
            $durationMs = round((microtime(true) - $startTime) * 1000, 2);
            Log::warning('EnsureIngestionToken: rejected', [
                'token_prefix' => $maskedToken,
                'duration_ms' => $durationMs,
            ]);

            return response()->json([
                'error' => 'Unauthorized',
                'message' => 'Invalid ingestion token.',
            ], 401);
        }

        $durationMs = round((microtime(true) - $startTime) * 1000, 2);
        Log::info('EnsureIngestionToken: accepted', [
            'token_prefix' => $maskedToken,
            'duration_ms' => $durationMs,
        ]);

        return $next($request);
    }
}
