<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Monitoring\ApiMetricsService;

class MetricsController extends Controller
{
    public function __construct(private ApiMetricsService $metrics)
    {
    }

    public function index()
    {
        return response()->json([
            'generated_at' => now()->toIso8601String(),
            'snapshot' => $this->metrics->snapshot(),
        ]);
    }
}
