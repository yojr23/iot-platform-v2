<?php

namespace App\Services\Monitoring;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Throwable;

class ApiMetricsService
{
    private const TTL_SECONDS = 7200;
    private const WINDOW_MINUTES = 10;

    public function record(Request $request, SymfonyResponse $response, float $durationMs): void
    {
        try {
            $bucket = now()->format('YmdHi');
            $route = $request->route();
            $endpoint = $route ? $route->uri() : trim($request->path(), '/');
            $endpoint = $endpoint !== '' ? $endpoint : '/';
            $endpointKey = substr(hash('sha1', $endpoint), 0, 16);
            $status = method_exists($response, 'getStatusCode') ? $response->getStatusCode() : Response::HTTP_OK;
            $isError = $status >= 500 ? 1 : 0;
            $durationInt = max(0, (int) round($durationMs));

            $this->incrementWithTtl("api_metrics:minute:{$bucket}:requests", 1);
            $this->incrementWithTtl("api_metrics:minute:{$bucket}:errors", $isError);
            $this->incrementWithTtl("api_metrics:minute:{$bucket}:duration_total_ms", $durationInt);

            $this->incrementWithTtl("api_metrics:endpoint:{$endpointKey}:requests", 1);
            $this->incrementWithTtl("api_metrics:endpoint:{$endpointKey}:errors", $isError);
            $this->incrementWithTtl("api_metrics:endpoint:{$endpointKey}:duration_total_ms", $durationInt);
            Cache::put("api_metrics:endpoint:{$endpointKey}:name", $endpoint, now()->addSeconds(self::TTL_SECONDS));

            $maxKey = "api_metrics:endpoint:{$endpointKey}:duration_max_ms";
            $currentMax = (int) (Cache::get($maxKey, 0));
            if ($durationInt > $currentMax) {
                Cache::put($maxKey, $durationInt, now()->addSeconds(self::TTL_SECONDS));
            }

            Log::info('ApiMetricsService:record completed', [
                'endpoint' => $endpoint,
                'status' => $status,
                'duration_ms' => $durationMs,
                'is_error' => $isError,
            ]);
        } catch (Throwable $e) {
            Log::error('ApiMetricsService:record failed', [
                'endpoint' => $request->path(),
                'exception' => $e->getMessage(),
                'exception_class' => $e::class,
            ]);
        }
    }

    public function snapshot(): array
    {
        Log::info('ApiMetricsService:snapshot entry');

        $startTime = microtime(true);
        $now = now();
        $minutes = [];
        $requestsTotal = 0;
        $errorsTotal = 0;
        $durationTotal = 0;

        for ($i = self::WINDOW_MINUTES - 1; $i >= 0; $i--) {
            $bucket = $now->copy()->subMinutes($i)->format('YmdHi');
            $req = (int) Cache::get("api_metrics:minute:{$bucket}:requests", 0);
            $err = (int) Cache::get("api_metrics:minute:{$bucket}:errors", 0);
            $dur = (int) Cache::get("api_metrics:minute:{$bucket}:duration_total_ms", 0);
            $avg = $req > 0 ? round($dur / $req, 2) : 0.0;

            $minutes[] = [
                'bucket' => $bucket,
                'requests' => $req,
                'errors' => $err,
                'avg_latency_ms' => $avg,
            ];

            $requestsTotal += $req;
            $errorsTotal += $err;
            $durationTotal += $dur;
        }

        $overallAvg = $requestsTotal > 0 ? round($durationTotal / $requestsTotal, 2) : 0.0;

        $durationMs = round((microtime(true) - $startTime) * 1000, 2);
        Log::info('ApiMetricsService:snapshot completed', [
            'requests_total' => $requestsTotal,
            'errors_total' => $errorsTotal,
            'duration_ms' => $durationMs,
        ]);

        if ($durationMs > 100) {
            Log::warning('ApiMetricsService:snapshot slow execution', ['duration_ms' => $durationMs]);
        }

        return [
            'window_minutes' => self::WINDOW_MINUTES,
            'requests_total' => $requestsTotal,
            'errors_total' => $errorsTotal,
            'error_rate_percent' => $requestsTotal > 0 ? round(($errorsTotal / $requestsTotal) * 100, 2) : 0.0,
            'avg_latency_ms' => $overallAvg,
            'throughput_rpm_avg' => round($requestsTotal / self::WINDOW_MINUTES, 2),
            'series' => $minutes,
        ];
    }

    private function incrementWithTtl(string $key, int $step): void
    {
        Cache::add($key, 0, now()->addSeconds(self::TTL_SECONDS));
        Cache::increment($key, $step);
    }
}

