<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class HealthController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $startTime = microtime(true);

        $durationMs = round((microtime(true) - $startTime) * 1000, 2);

        Log::channel('health')->info('Health check request', [
            'ip' => $request->ip(),
            'method' => $request->method(),
            'path' => $request->path(),
            'request_id' => $request->attributes->get('request_id'),
            'duration_ms' => $durationMs,
        ]);

        return response()->json([
            'status' => 'ok',
            'app' => 'iot-platform-v2',
            'timestamp' => now()->toIso8601String(),
        ]);
    }
}
