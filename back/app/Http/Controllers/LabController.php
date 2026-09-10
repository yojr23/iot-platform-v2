<?php

namespace App\Http\Controllers;

use App\Models\Lab;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class LabController extends Controller
{
    public function __construct()
    {
        $this->middleware('admin');
    }

    public function create()
    {
        $startTime = microtime(true);

        $labs = Lab::all();

        $durationMs = round((microtime(true) - $startTime) * 1000, 2);

        Log::info('Lab create view request', [
            'ip' => request()->ip(),
            'method' => request()->method(),
            'path' => request()->path(),
            'request_id' => request()->header('X-Request-Id', uniqid()),
            'user_id' => auth()->id(),
            'duration_ms' => $durationMs,
        ]);

        return view('labs.create', compact('labs'));
    }

    public function store(Request $request)
    {
        $startTime = microtime(true);

        $validatedData = $request->validate([
            'name' => 'required|string|max:255',
            'area' => 'required|string|max:255',
            'process_line' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        Lab::create($validatedData);

        $durationMs = round((microtime(true) - $startTime) * 1000, 2);

        Log::info('Lab store request', [
            'ip' => $request->ip(),
            'method' => $request->method(),
            'path' => $request->path(),
            'request_id' => $request->header('X-Request-Id', uniqid()),
            'user_id' => auth()->id(),
            'duration_ms' => $durationMs,
        ]);

        return redirect()->route('labs.create')->with('success', 'Laboratorio creado correctamente.');
    }

    public function edit(Lab $lab)
    {
        $startTime = microtime(true);

        $durationMs = round((microtime(true) - $startTime) * 1000, 2);

        Log::info('Lab edit view request', [
            'ip' => request()->ip(),
            'method' => request()->method(),
            'path' => request()->path(),
            'request_id' => request()->header('X-Request-Id', uniqid()),
            'user_id' => auth()->id(),
            'lab_id' => $lab->id,
            'duration_ms' => $durationMs,
        ]);

        return view('labs.edit', compact('lab'));
    }

    public function update(Request $request, Lab $lab)
    {
        $startTime = microtime(true);

        $validatedData = $request->validate([
            'name' => 'required|string|max:255',
            'area' => 'required|string|max:255',
            'process_line' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        $lab->update($validatedData);

        $durationMs = round((microtime(true) - $startTime) * 1000, 2);

        Log::info('Lab update request', [
            'ip' => $request->ip(),
            'method' => $request->method(),
            'path' => $request->path(),
            'request_id' => $request->header('X-Request-Id', uniqid()),
            'user_id' => auth()->id(),
            'lab_id' => $lab->id,
            'duration_ms' => $durationMs,
        ]);

        return redirect()->route('labs.create')->with('success', 'Laboratorio actualizado correctamente.');
    }

    public function destroy(Lab $lab)
    {
        $startTime = microtime(true);

        $lab->delete();

        $durationMs = round((microtime(true) - $startTime) * 1000, 2);

        Log::info('Lab destroy request', [
            'ip' => request()->ip(),
            'method' => request()->method(),
            'path' => request()->path(),
            'request_id' => request()->header('X-Request-Id', uniqid()),
            'user_id' => auth()->id(),
            'lab_id' => $lab->id,
            'duration_ms' => $durationMs,
        ]);

        return redirect()->route('labs.create')->with('success', 'Laboratorio eliminado correctamente.');
    }
}
