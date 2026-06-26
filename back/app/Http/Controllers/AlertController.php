<?php

namespace App\Http\Controllers;

use App\Models\Alert;
class AlertController extends Controller
{
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
        $alert->update([
            'resolved' => true,
            'resolved_at' => now()
        ]);
        
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
        Alert::active()->update([
            'resolved' => true,
            'resolved_at' => now()
        ]);
        
        return back()->with('success', 'Todas las alertas han sido marcadas como revisadas');
    }
}
