<?php

namespace App\Http\Controllers;

use App\Models\Alert;
use App\Services\Alerts\AlertLifecycleService;
use Illuminate\Support\Facades\Log;

class AlertController extends Controller
{
    public function __construct(private AlertLifecycleService $alertLifecycleService)
    {
    }

    public function index()
    {
        $startTime = microtime(true);

        $activeAlerts = Alert::withContext()
            ->active()
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        $alertHistory = Alert::withContext()
            ->resolved()
            ->orderByDesc('resolved_at')
            ->paginate(20);

        $durationMs = round((microtime(true) - $startTime) * 1000, 2);

        Log::info('Alert index request', [
            'ip' => request()->ip(),
            'method' => request()->method(),
            'path' => request()->path(),
            'request_id' => request()->header('X-Request-Id', uniqid()),
            'user_id' => auth()->id(),
            'active_count' => $activeAlerts->total(),
            'history_count' => $alertHistory->total(),
            'duration_ms' => $durationMs,
        ]);

        return view('alerts.index', compact('activeAlerts', 'alertHistory'));
    }

    public function resolve(Alert $alert)
    {
        $startTime = microtime(true);

        $this->alertLifecycleService->resolve($alert);

        $durationMs = round((microtime(true) - $startTime) * 1000, 2);

        Log::info('Alert resolve request', [
            'ip' => request()->ip(),
            'method' => request()->method(),
            'path' => request()->path(),
            'request_id' => request()->header('X-Request-Id', uniqid()),
            'user_id' => auth()->id(),
            'alert_id' => $alert->id,
            'success' => true,
            'duration_ms' => $durationMs,
        ]);

        return back()->with('success', 'Alerta marcada como resuelta');
    }

    public function unresolved()
    {
        $startTime = microtime(true);

        $alerts = Alert::withContext()
            ->active()
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        $durationMs = round((microtime(true) - $startTime) * 1000, 2);

        Log::info('Alert unresolved request', [
            'ip' => request()->ip(),
            'method' => request()->method(),
            'path' => request()->path(),
            'request_id' => request()->header('X-Request-Id', uniqid()),
            'user_id' => auth()->id(),
            'count' => $alerts->total(),
            'duration_ms' => $durationMs,
        ]);
            
        return view('alerts.unresolved', compact('alerts'));
    }

    public function history()
    {
        $startTime = microtime(true);

        $alertHistory = Alert::withContext()
            ->resolved()
            ->orderBy('updated_at', 'desc')
            ->paginate(20);

        $durationMs = round((microtime(true) - $startTime) * 1000, 2);

        Log::info('Alert history request', [
            'ip' => request()->ip(),
            'method' => request()->method(),
            'path' => request()->path(),
            'request_id' => request()->header('X-Request-Id', uniqid()),
            'user_id' => auth()->id(),
            'count' => $alertHistory->total(),
            'duration_ms' => $durationMs,
        ]);

        return view('alerts.history', compact('alertHistory'));
    }

    public function markAllAsResolved()
    {
        $startTime = microtime(true);

        // PLAN.md Stage 4.2 (audit RC2): same transition owner as the API's resolveAll() — this
        // mass update() previously bypassed AlertObserver and emitted nothing.
        $this->alertLifecycleService->resolveAll();

        $durationMs = round((microtime(true) - $startTime) * 1000, 2);

        Log::info('Alert markAllAsResolved request', [
            'ip' => request()->ip(),
            'method' => request()->method(),
            'path' => request()->path(),
            'request_id' => request()->header('X-Request-Id', uniqid()),
            'user_id' => auth()->id(),
            'success' => true,
            'duration_ms' => $durationMs,
        ]);

        return back()->with('success', 'Todas las alertas han sido marcadas como revisadas');
    }
}
