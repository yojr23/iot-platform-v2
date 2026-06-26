<?php

namespace App\Http\Middleware;

use App\Services\Monitoring\ApiMetricsService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TrackApiPerformance
{
    public function __construct(private ApiMetricsService $metrics)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $start = microtime(true);
        /** @var Response $response */
        $response = $next($request);
        $durationMs = (microtime(true) - $start) * 1000;

        $this->metrics->record($request, $response, $durationMs);

        return $response;
    }
}

