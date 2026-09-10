<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class HealthController extends Controller
{
    public function show(): JsonResponse
    {
        $startTime = microtime(true);

        $durationMs = round((microtime(true) - $startTime) * 1000, 2);

        Log::info('Health check request', [
            'ip' => request()->ip(),
            'method' => request()->method(),
            'path' => request()->path(),
            'request_id' => request()->header('X-Request-Id', uniqid()),
            'duration_ms' => $durationMs,
        ]);

        return response()->json([
            'status' => 'ok',
            'app' => 'iot-platform-v2',
            'timestamp' => now()->toIso8601String(),
        ]);
    }
}
