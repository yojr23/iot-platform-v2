<?php

namespace App\Http\Controllers;

use App\Models\Alert;
use Illuminate\Http\Request;

class AlertController extends Controller
{
    public function index()
    {
        $activeAlerts = Alert::with(['sensorReading.sensor.device.classroom', 'alertRule'])
            ->where('resolved', false)
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        $alertHistory = Alert::with(['sensorReading.sensor.device.classroom', 'alertRule'])
            ->where('resolved', true)
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
        $alerts = Alert::with(['sensorReading.sensor.device.classroom', 'alertRule'])
            ->where('resolved', false)
            ->orderBy('created_at', 'desc')
            ->paginate(20);
            
        return view('alerts.unresolved', compact('alerts'));
    }

    public function history()
    {
        $alertHistory = Alert::where('resolved', true)->orderBy('updated_at', 'desc')->paginate(20);
        return view('alerts.history', compact('alertHistory'));
    }

    public function markAllAsResolved()
    {
        Alert::where('resolved', false)->update([
            'resolved' => true,
            'resolved_at' => now()
        ]);
        
        return back()->with('success', 'Todas las alertas han sido marcadas como revisadas');
    }
}
