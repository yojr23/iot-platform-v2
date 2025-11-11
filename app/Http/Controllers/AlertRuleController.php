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
        $sensorTypes = SensorType::all();
        $alertRules = AlertRule::with(['sensorType', 'device', 'sensor'])->get();
        $devices = Device::all();
        $sensors = Sensor::with('device')->get();
        
        return view('alerts.rules.create', compact('sensorTypes', 'alertRules', 'devices', 'sensors'));
    }

    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'sensor_type_id' => 'required|exists:sensor_types,id',
                'device_id' => 'required|exists:devices,id',
                'sensor_id' => 'required|exists:sensors,id',
                'min_value' => 'nullable|numeric',
                'max_value' => 'nullable|numeric',
                'severity' => 'required|in:info,warning,danger',
                'message' => 'required|string|max:255'
            ]);

            $sensor = Sensor::with('device')->find($validated['sensor_id']);

            if (!$sensor || $sensor->device_id !== (int) $validated['device_id']) {
                throw ValidationException::withMessages([
                    'sensor_id' => 'El sensor seleccionado no pertenece al dispositivo especificado.',
                ]);
            }

            if ($sensor->sensor_type_id !== (int) $validated['sensor_type_id']) {
                throw ValidationException::withMessages([
                    'sensor_type_id' => 'El tipo de sensor no coincide con el sensor seleccionado.',
                ]);
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

            $validated['sensor_type_id'] = $sensor->sensor_type_id;
            $validated['device_id'] = $sensor->device_id;
            $validated['sensor_id'] = $sensor->id;

            AlertRule::create($validated);

            return redirect()->route('alert-rules.create')->with('success', 'Regla de alerta creada correctamente');
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('Error al crear regla de alerta: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Error al crear la regla de alerta')->withInput();
        }
    }

    public function destroy(AlertRule $alertRule)
    {
        try {
            $alertRule->delete();
            return redirect()->back()->with('success', 'Regla de alerta eliminada correctamente');
        } catch (\Exception $e) {
            Log::error('Error al eliminar regla de alerta: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Error al eliminar la regla de alerta');
        }
    }

    public function index(Request $request)
    {
        $deviceId = $request->query('device_id');
        $devices = Device::all();

        $alertRules = AlertRule::with(['sensorType', 'device', 'sensor'])
            ->when($deviceId, function ($query) use ($deviceId) {
                $query->where('device_id', $deviceId);
            })
            ->get();

        return view('alerts.rules.index', compact('alertRules', 'devices'));
    }
}
