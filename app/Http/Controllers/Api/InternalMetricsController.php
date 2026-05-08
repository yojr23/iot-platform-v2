<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Monitoring\ApiMetricsService;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;

class InternalMetricsController extends Controller
{
    public function __construct(private ApiMetricsService $metrics)
    {
    }

    public function apiPerformance(): JsonResponse
    {
        if (! app()->environment('local')) {
            throw new HttpResponseException(response()->json([
                'message' => 'Forbidden',
            ], 403));
        }

        return response()->json([
            'generated_at' => now()->toIso8601String(),
            'api_performance' => $this->metrics->snapshot(),
        ]);
    }
}
