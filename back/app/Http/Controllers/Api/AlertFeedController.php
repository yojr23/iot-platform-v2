<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Alerts\AlertService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class AlertFeedController extends Controller
{
    public function __construct(private AlertService $alertService)
    {
    }

    public function active(): JsonResponse
    {
        $startTime = microtime(true);

        $result = [
            'count' => $this->alertService->getActiveAlertsCount(),
            'alerts' => $this->alertService->getActiveAlertsList(10),
        ];

        $durationMs = round((microtime(true) - $startTime) * 1000, 2);

        Log::info('AlertFeed active request', [
            'ip' => request()->ip(),
            'method' => request()->method(),
            'path' => request()->path(),
            'request_id' => request()->header('X-Request-Id', uniqid()),
            'user_id' => auth()->id(),
            'active_count' => $result['count'],
            'duration_ms' => $durationMs,
        ]);

        return response()->json($result);
    }
}

