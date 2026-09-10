<?php

namespace App\Http\Controllers;

use App\Models\Sensor;
use App\Models\Device;
use App\Models\SensorType;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class SensorController extends Controller
{
    public function __construct()
    {
        $this->middleware('admin')->only([
            'create',
            'store',
            'edit',
            'update',
            'destroy',
        ]);
    }

    public function create()
    {
        // Obtener dispositivos activos con sus laboratorios
        $devices = Device::with('lab')
            ->where('status', true) // Solo dispositivos activos
            ->orderBy('name')
            ->get();
        
        $sensorTypes = SensorType::orderBy('name')->get();

        // Debug: Verificar qué dispositivos se están obteniendo
        Log::debug('Dispositivos disponibles para sensor:', $devices->toArray());

        return view('sensors.create', compact('devices', 'sensorTypes'));
    }

    public function store(Request $request)
    {
        $startTime = microtime(true);

        // Validación mejorada
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'device_id' => [
                'required',
                'exists:devices,id',
                function ($attribute, $value, $fail) {
                    $device = Device::find($value);
                    if (!$device || !$device->status) {
                        $fail('El dispositivo seleccionado no está disponible.');
                    }
                }
            ],
            'sensor_type_id' => 'required|exists:sensor_types,id',
            'status' => 'sometimes|boolean'
        ], [
            'device_id.exists' => 'El dispositivo seleccionado no existe.',
            'sensor_type_id.exists' => 'El tipo de sensor seleccionado no existe.'
        ]);

        try {
            // Crear el sensor con el dispositivo relacionado
            $sensor = new Sensor();
            $sensor->name = $validated['name'];
            $sensor->status = $validated['status'] ?? true;
            
            // Asignar relaciones
            $sensor->device()->associate($validated['device_id']);
            $sensor->sensorType()->associate($validated['sensor_type_id']);
            
            $sensor->save();

            $durationMs = round((microtime(true) - $startTime) * 1000, 2);

            Log::info('Sensor store success', [
                'ip' => $request->ip(),
                'method' => $request->method(),
                'path' => $request->path(),
                'request_id' => $request->header('X-Request-Id', uniqid()),
                'user_id' => auth()->id(),
                'sensor_id' => $sensor->id,
                'payload_keys' => array_keys($validated),
                'success' => true,
                'duration_ms' => $durationMs,
            ]);
            
            return redirect()->route('sensors.index')
                ->with('success', 'Sensor creado exitosamente!');
                
        } catch (Throwable $e) {
            $durationMs = round((microtime(true) - $startTime) * 1000, 2);

            Log::error('Sensor store error', [
                'ip' => $request->ip(),
                'method' => $request->method(),
                'path' => $request->path(),
                'request_id' => $request->header('X-Request-Id', uniqid()),
                'user_id' => auth()->id(),
                'exception' => $e->getMessage(),
                'duration_ms' => $durationMs,
            ]);

            return back()->withInput()
                ->with('error', 'Error al crear el sensor: ' . $e->getMessage());
        }
    }

    public function edit(Sensor $sensor)
    {
        $devices = Device::with('lab')->get();
        $sensorTypes = SensorType::all();
        return view('sensors.edit', compact('sensor', 'devices', 'sensorTypes'));
    }

    public function update(Request $request, Sensor $sensor)
    {
        $startTime = microtime(true);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'min_range' => 'required|numeric',
            'max_range' => 'required|numeric',
        ]);

        try {
            $sensor->name = $validated['name'];
            $sensor->sensorType->min_range = $validated['min_range'];
            $sensor->sensorType->max_range = $validated['max_range'];
            $sensor->sensorType->save();
            $sensor->save();

            $durationMs = round((microtime(true) - $startTime) * 1000, 2);

            Log::info('Sensor update success', [
                'ip' => $request->ip(),
                'method' => $request->method(),
                'path' => $request->path(),
                'request_id' => $request->header('X-Request-Id', uniqid()),
                'user_id' => auth()->id(),
                'sensor_id' => $sensor->id,
                'payload_keys' => array_keys($validated),
                'success' => true,
                'duration_ms' => $durationMs,
            ]);

            return redirect()->route('sensors.index')
                ->with('success', 'Sensor actualizado exitosamente');
        } catch (Throwable $e) {
            $durationMs = round((microtime(true) - $startTime) * 1000, 2);

            Log::error('Sensor update error', [
                'ip' => $request->ip(),
                'method' => $request->method(),
                'path' => $request->path(),
                'request_id' => $request->header('X-Request-Id', uniqid()),
                'user_id' => auth()->id(),
                'sensor_id' => $sensor->id,
                'exception' => $e->getMessage(),
                'duration_ms' => $durationMs,
            ]);

            return back()->withInput()
                ->with('error', 'Error al actualizar el sensor. Por favor intente nuevamente.');
        }
    }

    public function destroy(Sensor $sensor)
    {
        $startTime = microtime(true);

        try {
            $sensor->delete();

            $durationMs = round((microtime(true) - $startTime) * 1000, 2);

            Log::info('Sensor destroy success', [
                'ip' => request()->ip(),
                'method' => request()->method(),
                'path' => request()->path(),
                'request_id' => request()->header('X-Request-Id', uniqid()),
                'user_id' => auth()->id(),
                'sensor_id' => $sensor->id,
                'success' => true,
                'duration_ms' => $durationMs,
            ]);

            return redirect()->route('sensors.index')
                ->with('success', 'Sensor eliminado exitosamente');
        } catch (Throwable $e) {
            $durationMs = round((microtime(true) - $startTime) * 1000, 2);

            Log::error('Sensor destroy error', [
                'ip' => request()->ip(),
                'method' => request()->method(),
                'path' => request()->path(),
                'request_id' => request()->header('X-Request-Id', uniqid()),
                'user_id' => auth()->id(),
                'sensor_id' => $sensor->id,
                'exception' => $e->getMessage(),
                'duration_ms' => $durationMs,
            ]);

            return back()->with('error', 'Error al eliminar el sensor. Por favor intente nuevamente.');
        }
    }

    public function getLatestReadings(Request $request)
    {
        $validated = $request->validate([
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $limit = (int) ($validated['limit'] ?? 1);
        $sensors = Sensor::with(['readings' => function ($query) use ($limit) {
            $query->orderBy('reading_time', 'desc')->limit($limit);
        }])->get();

        return response()->json(['sensors' => $sensors]);
    }

    public function downloadReadings(Sensor $sensor)
{
    $startTime = microtime(true);

    try {
        // Obtener todas las lecturas del sensor
        $readings = $sensor->readings()
            ->orderBy('reading_time', 'desc')
            ->get()
            ->map(function ($reading) {
                return [
                    'value' => $reading->value,
                    'reading_time' => $reading->reading_time,
                    'created_at' => $reading->created_at
                ];
            });

        // Preparar los datos para el JSON
        $data = [
            'sensor' => [
                'name' => $sensor->name,
                'type' => $sensor->sensorType->name,
                'unit' => $sensor->sensorType->unit,
                'device' => $sensor->device->name,
                'lab' => $sensor->device->lab->name
            ],
            'readings' => $readings
        ];

        // Generar el nombre del archivo
        $fileName = 'sensor_' . $sensor->id . '_readings_' . date('Y-m-d_His') . '.json';

        $durationMs = round((microtime(true) - $startTime) * 1000, 2);

        Log::info('Sensor downloadReadings success', [
            'ip' => request()->ip(),
            'method' => request()->method(),
            'path' => request()->path(),
            'request_id' => request()->header('X-Request-Id', uniqid()),
            'user_id' => auth()->id(),
            'sensor_id' => $sensor->id,
            'readings_count' => $readings->count(),
            'success' => true,
            'duration_ms' => $durationMs,
        ]);

        // Retornar la respuesta como descarga
        return response()->json($data)
            ->header('Content-Disposition', 'attachment; filename=' . $fileName)
            ->header('Content-Type', 'application/json');

    } catch (Throwable $e) {
        $durationMs = round((microtime(true) - $startTime) * 1000, 2);

        Log::error('Sensor downloadReadings error', [
            'ip' => request()->ip(),
            'method' => request()->method(),
            'path' => request()->path(),
            'request_id' => request()->header('X-Request-Id', uniqid()),
            'user_id' => auth()->id(),
            'sensor_id' => $sensor->id,
            'exception' => $e->getMessage(),
            'duration_ms' => $durationMs,
        ]);

        return back()->with('error', 'Error al descargar los datos del sensor.');
    }
}

    public function getSensorReadings(Sensor $sensor)
    {
        $readings = $sensor->readings()
            ->orderBy('reading_time', 'desc')
            ->paginate(10);
            
        return view('sensors.readings', compact('sensor', 'readings'));
    }

public function getReadingsByDateRange(Request $request, Sensor $sensor)
{
    $startTime = microtime(true);

    try {
        $validated = $request->validate([
            'startDate' => ['required', 'date_format:Y-m-d'],
            'endDate' => ['required', 'date_format:Y-m-d', 'after_or_equal:startDate'],
        ]);

        $startDate = Carbon::createFromFormat('Y-m-d', $validated['startDate'])->startOfDay();
        $endDate = Carbon::createFromFormat('Y-m-d', $validated['endDate'])->endOfDay();

        $query = $sensor->readings()
            ->whereBetween('reading_time', [$startDate, $endDate])
            ->orderBy('reading_time', 'desc');

        $readings = $query->get();

        $durationMs = round((microtime(true) - $startTime) * 1000, 2);

        Log::info('Sensor getReadingsByDateRange success', [
            'ip' => $request->ip(),
            'method' => $request->method(),
            'path' => $request->path(),
            'request_id' => $request->header('X-Request-Id', uniqid()),
            'user_id' => auth()->id(),
            'sensor_id' => $sensor->id,
            'readings_count' => $readings->count(),
            'success' => true,
            'duration_ms' => $durationMs,
        ]);

        return response()->json([
            'success' => true,
            'readings' => $readings,
            'sensor' => [
                'unit' => $sensor->sensorType->unit
            ]
        ]);

    } catch (Throwable $e) {
        $durationMs = round((microtime(true) - $startTime) * 1000, 2);

        Log::error('Sensor getReadingsByDateRange error', [
            'ip' => $request->ip(),
            'method' => $request->method(),
            'path' => $request->path(),
            'request_id' => $request->header('X-Request-Id', uniqid()),
            'user_id' => auth()->id(),
            'sensor_id' => $sensor->id,
            'exception' => $e->getMessage(),
            'duration_ms' => $durationMs,
        ]);

        return response()->json([
            'success' => false,
            'message' => 'Error al obtener las lecturas'
        ], 500);
    }
}

public function getByDevice(Device $device)
{
    $startTime = microtime(true);

    try {
        $sensors = $device->sensors()->get();

        $durationMs = round((microtime(true) - $startTime) * 1000, 2);

        Log::info('Sensor getByDevice success', [
            'ip' => request()->ip(),
            'method' => request()->method(),
            'path' => request()->path(),
            'request_id' => request()->header('X-Request-Id', uniqid()),
            'user_id' => auth()->id(),
            'device_id' => $device->id,
            'count' => $sensors->count(),
            'success' => true,
            'duration_ms' => $durationMs,
        ]);

        return response()->json($sensors);
    } catch (Throwable $e) {
        $durationMs = round((microtime(true) - $startTime) * 1000, 2);

        Log::error('Sensor getByDevice error', [
            'ip' => request()->ip(),
            'method' => request()->method(),
            'path' => request()->path(),
            'request_id' => request()->header('X-Request-Id', uniqid()),
            'user_id' => auth()->id(),
            'device_id' => $device->id,
            'exception' => $e->getMessage(),
            'duration_ms' => $durationMs,
        ]);

        return response()->json(['error' => 'No fue posible obtener sensores del dispositivo.'], 500);
    }
}
}
