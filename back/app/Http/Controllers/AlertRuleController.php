<?php

namespace App\Http\Controllers;

use App\Models\AlertRule;
use App\Models\Device;
use App\Models\Sensor;
use App\Models\SensorType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class AlertRuleController extends Controller
{
    public function __construct()
    {
        $this->middleware('admin');
    }

    public function create()
    {
        $startTime = microtime(true);

        $sensorTypes = SensorType::all();
        $alertRules = AlertRule::with(['sensorType', 'device', 'sensor'])->get();
        $devices = Device::all();
        $sensors = Sensor::with(['device', 'sensorType'])->get();

        $durationMs = round((microtime(true) - $startTime) * 1000, 2);

        Log::info('AlertRule create form request', [
            'ip' => request()->ip(),
            'method' => request()->method(),
            'path' => request()->path(),
            'request_id' => request()->header('X-Request-Id', uniqid()),
            'user_id' => auth()->id(),
            'duration_ms' => $durationMs,
        ]);
        
        return view('alerts.rules.create', compact('sensorTypes', 'alertRules', 'devices', 'sensors'));
    }

    public function store(Request $request)
    {
        $startTime = microtime(true);

        try {
            $validated = $request->validate([
                'sensor_type_id' => 'required|exists:sensor_types,id',
                'device_id' => 'nullable|exists:devices,id',
                'sensor_id' => 'nullable|exists:sensors,id',
                'min_value' => 'nullable|numeric',
                'max_value' => 'nullable|numeric',
                'severity' => 'required|in:info,warning,danger',
                'message' => 'required|string|max:255',
                'name' => 'nullable|string|max:255',
            ]);

            $sensor = null;

            if (!empty($validated['sensor_id'])) {
                $sensor = Sensor::with('device')->find($validated['sensor_id']);

                if (!$sensor) {
                    throw ValidationException::withMessages([
                        'sensor_id' => 'El sensor seleccionado no existe.',
                    ]);
                }

                if (!empty($validated['device_id']) && $sensor->device_id !== (int) $validated['device_id']) {
                    throw ValidationException::withMessages([
                        'sensor_id' => 'El sensor seleccionado no pertenece al dispositivo especificado.',
                    ]);
                }

                if ($sensor->sensor_type_id !== (int) $validated['sensor_type_id']) {
                    throw ValidationException::withMessages([
                        'sensor_type_id' => 'El tipo de sensor no coincide con el sensor seleccionado.',
                    ]);
                }

                $validated['device_id'] = $sensor->device_id;
                $validated['sensor_type_id'] = $sensor->sensor_type_id;
            }

            if (is_null($validated['min_value']) && is_null($validated['max_value'])) {
                throw ValidationException::withMessages([
                    'min_value' => 'Debes definir un valor mínimo o máximo para la regla.',
                ]);
            }

            if (!is_null($validated['min_value']) && !is_null($validated['max_value']) && $validated['max_value'] <= $validated['min_value']) {
                throw ValidationException::withMessages([
                    'max_value' => 'El valor máximo debe ser mayor al mínimo cuando ambos se definen.',
                ]);
            }

            AlertRule::create($validated);

            $durationMs = round((microtime(true) - $startTime) * 1000, 2);

            Log::info('AlertRule store success', [
                'ip' => $request->ip(),
                'method' => $request->method(),
                'path' => $request->path(),
                'request_id' => $request->header('X-Request-Id', uniqid()),
                'user_id' => auth()->id(),
                'payload_keys' => array_keys($validated),
                'success' => true,
                'duration_ms' => $durationMs,
            ]);

            return redirect()->route('alert-rules.create')->with('success', 'Regla de alerta creada correctamente');
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            $durationMs = round((microtime(true) - $startTime) * 1000, 2);

            Log::error('AlertRule store error', [
                'ip' => $request->ip(),
                'method' => $request->method(),
                'path' => $request->path(),
                'request_id' => $request->header('X-Request-Id', uniqid()),
                'user_id' => auth()->id(),
                'exception' => $e->getMessage(),
                'duration_ms' => $durationMs,
            ]);

            return redirect()->back()->with('error', 'Error al crear la regla de alerta')->withInput();
        }
    }

    public function destroy(AlertRule $alertRule)
    {
        $startTime = microtime(true);

        try {
            $alertRule->delete();

            $durationMs = round((microtime(true) - $startTime) * 1000, 2);

            Log::info('AlertRule destroy success', [
                'ip' => request()->ip(),
                'method' => request()->method(),
                'path' => request()->path(),
                'request_id' => request()->header('X-Request-Id', uniqid()),
                'user_id' => auth()->id(),
                'alert_rule_id' => $alertRule->id,
                'success' => true,
                'duration_ms' => $durationMs,
            ]);

            return redirect()->back()->with('success', 'Regla de alerta eliminada correctamente');
        } catch (\Exception $e) {
            $durationMs = round((microtime(true) - $startTime) * 1000, 2);

            Log::error('AlertRule destroy error', [
                'ip' => request()->ip(),
                'method' => request()->method(),
                'path' => request()->path(),
                'request_id' => request()->header('X-Request-Id', uniqid()),
                'user_id' => auth()->id(),
                'alert_rule_id' => $alertRule->id,
                'exception' => $e->getMessage(),
                'duration_ms' => $durationMs,
            ]);

            return redirect()->back()->with('error', 'Error al eliminar la regla de alerta');
        }
    }

    public function index(Request $request)
    {
        $startTime = microtime(true);

        $deviceId = $request->query('device_id');
        $devices = Device::all();

        $alertRules = AlertRule::with(['sensorType', 'device', 'sensor'])
            ->when($deviceId, function ($query) use ($deviceId) {
                $query->where('device_id', $deviceId);
            })
            ->get();

        $durationMs = round((microtime(true) - $startTime) * 1000, 2);

        Log::info('AlertRule index request', [
            'ip' => $request->ip(),
            'method' => $request->method(),
            'path' => $request->path(),
            'request_id' => $request->header('X-Request-Id', uniqid()),
            'user_id' => auth()->id(),
            'count' => $alertRules->count(),
            'device_id' => $deviceId,
            'duration_ms' => $durationMs,
        ]);

        return view('alerts.rules.index', compact('alertRules', 'devices'));
    }
}
