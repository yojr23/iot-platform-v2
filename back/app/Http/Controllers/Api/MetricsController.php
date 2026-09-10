<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Monitoring\ApiMetricsService;
use Illuminate\Support\Facades\Log;

class MetricsController extends Controller
{
    public function __construct(private ApiMetricsService $metrics)
    {
    }

    public function index()
    {
        $startTime = microtime(true);

        $result = [
            'generated_at' => now()->toIso8601String(),
            'snapshot' => $this->metrics->snapshot(),
        ];

        $durationMs = round((microtime(true) - $startTime) * 1000, 2);

        Log::info('Metrics index request', [
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
