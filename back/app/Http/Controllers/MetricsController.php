<?php

namespace App\Http\Controllers;

use App\Services\Monitoring\ApiMetricsService;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

class MetricsController extends Controller
{
    public function __construct(private ApiMetricsService $metrics)
    {
    }

    public function index(): View
    {
        $snapshot = $this->metrics->snapshot();

        return view('metrics.index', compact('snapshot'));
    }

    public function data(): JsonResponse
    {
        return response()->json([
            'generated_at' => now()->toIso8601String(),
            'snapshot' => $this->metrics->snapshot(),
        ]);
    }
}

