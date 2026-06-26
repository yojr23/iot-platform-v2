<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Alerts\AlertService;
use Illuminate\Http\JsonResponse;

class AlertFeedController extends Controller
{
    public function __construct(private AlertService $alertService)
    {
    }

    public function active(): JsonResponse
    {
        return response()->json([
            'count' => $this->alertService->getActiveAlertsCount(),
            'alerts' => $this->alertService->getActiveAlertsList(10),
        ]);
    }
}

