<?php

namespace App\Http\Middleware;

use App\Services\Monitoring\ApiMetricsService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class TrackApiPerformance
{
    public function __construct(private ApiMetricsService $metrics)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $start = microtime(true);

        Log::debug('TrackApiPerformance: request started', [
            'method' => $request->method(),
            'path' => $request->path(),
        ]);

        /** @var Response $response */
        $response = $next($request);
        $durationMs = (microtime(true) - $start) * 1000;

        Log::debug('TrackApiPerformance: request completed', [
            'method' => $request->method(),
            'path' => $request->path(),
            'status' => $response->getStatusCode(),
            'duration_ms' => round($durationMs, 2),
        ]);

        $this->metrics->record($request, $response, $durationMs);

        return $response;
    }
}

