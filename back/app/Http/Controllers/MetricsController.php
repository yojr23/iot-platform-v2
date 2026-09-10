<?php

namespace App\Http\Controllers;

use App\Services\Monitoring\ApiMetricsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class MetricsController extends Controller
{
    public function __construct(private ApiMetricsService $metrics)
    {
    }

    public function index(): View
    {
        $startTime = microtime(true);

        $snapshot = $this->metrics->snapshot();

        $durationMs = round((microtime(true) - $startTime) * 1000, 2);

        Log::info('Metrics index request', [
            'ip' => request()->ip(),
            'method' => request()->method(),
            'path' => request()->path(),
            'request_id' => request()->header('X-Request-Id', uniqid()),
            'user_id' => auth()->id(),
            'duration_ms' => $durationMs,
        ]);

        return view('metrics.index', compact('snapshot'));
    }

    public function data(): JsonResponse
    {
        $startTime = microtime(true);

        $snapshot = $this->metrics->snapshot();

        $durationMs = round((microtime(true) - $startTime) * 1000, 2);

        Log::info('Metrics data request', [
            'ip' => request()->ip(),
            'method' => request()->method(),
            'path' => request()->path(),
            'request_id' => request()->header('X-Request-Id', uniqid()),
            'user_id' => auth()->id(),
            'duration_ms' => $durationMs,
        ]);

        return response()->json([
            'generated_at' => now()->toIso8601String(),
            'snapshot' => $snapshot,
        ]);
    }
}

