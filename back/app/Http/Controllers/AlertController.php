<?php

namespace App\Http\Controllers;

use App\Models\Alert;
use App\Services\Alerts\AlertLifecycleService;

class AlertController extends Controller
{
    public function __construct(private AlertLifecycleService $alertLifecycleService)
    {
    }

    public function index()
    {
        $activeAlerts = Alert::withContext()
            ->active()
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        $alertHistory = Alert::withContext()
            ->resolved()
            ->orderByDesc('resolved_at')
            ->paginate(20);

        return view('alerts.index', compact('activeAlerts', 'alertHistory'));
    }

    public function resolve(Alert $alert)
    {
        $this->alertLifecycleService->resolve($alert);

        return back()->with('success', 'Alerta marcada como resuelta');
    }

    public function unresolved()
    {
        $alerts = Alert::withContext()
            ->active()
            ->orderBy('created_at', 'desc')
            ->paginate(20);
            
        return view('alerts.unresolved', compact('alerts'));
    }

    public function history()
    {
        $alertHistory = Alert::withContext()
            ->resolved()
            ->orderBy('updated_at', 'desc')
            ->paginate(20);
        return view('alerts.history', compact('alertHistory'));
    }

    public function markAllAsResolved()
    {
        // PLAN.md Stage 4.2 (audit RC2): same transition owner as the API's resolveAll() — this
        // mass update() previously bypassed AlertObserver and emitted nothing.
        $this->alertLifecycleService->resolveAll();

        return back()->with('success', 'Todas las alertas han sido marcadas como revisadas');
    }
}
