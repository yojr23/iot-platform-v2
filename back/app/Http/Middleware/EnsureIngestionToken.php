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
        $expectedToken = (string) config('app.ingestion_service_token', '');

        if ($expectedToken === '' || $providedToken === '' || ! hash_equals($expectedToken, $providedToken)) {
            $durationMs = round((microtime(true) - $startTime) * 1000, 2);
            Log::warning('EnsureIngestionToken: rejected', [
                'path' => $request->path(),
                'ip' => $request->ip(),
                'request_id' => $request->attributes->get('request_id'),
                'duration_ms' => $durationMs,
            ]);

            return response()->json([
                'error' => 'Unauthorized',
                'message' => 'Invalid ingestion token.',
            ], 401);
        }

        $durationMs = round((microtime(true) - $startTime) * 1000, 2);
        Log::info('EnsureIngestionToken: accepted', [
            'path' => $request->path(),
            'ip' => $request->ip(),
            'request_id' => $request->attributes->get('request_id'),
            'duration_ms' => $durationMs,
        ]);

        return $next($request);
    }
}
