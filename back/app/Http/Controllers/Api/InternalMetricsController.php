<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Monitoring\ApiMetricsService;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class InternalMetricsController extends Controller
{
    public function __construct(private ApiMetricsService $metrics)
    {
    }

    public function apiPerformance(): JsonResponse
    {
        $startTime = microtime(true);

        if (! app()->environment('local')) {
            $durationMs = round((microtime(true) - $startTime) * 1000, 2);

            Log::warning('InternalMetrics apiPerformance blocked: not local', [
                'ip' => request()->ip(),
                'method' => request()->method(),
                'path' => request()->path(),
                'request_id' => request()->header('X-Request-Id', uniqid()),
                'duration_ms' => $durationMs,
            ]);

            throw new HttpResponseException(response()->json([
                'message' => 'Forbidden',
            ], 403));
        }

        $result = [
            'generated_at' => now()->toIso8601String(),
            'api_performance' => $this->metrics->snapshot(),
        ];

        $durationMs = round((microtime(true) - $startTime) * 1000, 2);

        Log::info('InternalMetrics apiPerformance request', [
            'ip' => request()->ip(),
            'method' => request()->method(),
            'path' => request()->path(),
            'request_id' => request()->header('X-Request-Id', uniqid()),
            'user_id' => auth()->id(),
            'duration_ms' => $durationMs,
        ]);

        return response()->json($result);
    }
}
