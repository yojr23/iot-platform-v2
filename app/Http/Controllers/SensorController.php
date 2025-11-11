<?php

namespace App\Http\Controllers;

use App\Models\Sensor;
use App\Models\Device;
use App\Models\SensorType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

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

    public function index()
    {
        $sensors = Sensor::with(['device.classroom', 'sensorType', 'readings'])
            ->orderBy('created_at', 'desc')
            ->paginate(10);
            
        return view('sensors.index', compact('sensors'));
    }

    public function create()
    {
        // Obtener dispositivos activos con sus aulas
        $devices = Device::with('classroom')
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
            
            return redirect()->route('sensors.index')
                ->with('success', 'Sensor creado exitosamente!');
                
        } catch (\Exception $e) {
            Log::error('Error al crear sensor: ' . $e->getMessage());
            return back()->withInput()
                ->with('error', 'Error al crear el sensor: ' . $e->getMessage());
        }
    }

    public function show(Sensor $sensor)
    {
        $readings = $sensor->readings()
            ->orderBy('reading_time', 'desc')
            ->paginate(10);
            
        return view('sensors.show', compact('sensor', 'readings'));
    }

    public function edit(Sensor $sensor)
    {
        $devices = Device::with('classroom')->get();
        $sensorTypes = SensorType::all();
        return view('sensors.edit', compact('sensor', 'devices', 'sensorTypes'));
    }

    public function update(Request $request, Sensor $sensor)
    {
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

            return redirect()->route('sensors.index')
                ->with('success', 'Sensor actualizado exitosamente');
        } catch (\Exception $e) {
            Log::error('Error al actualizar sensor: ' . $e->getMessage());
            return back()->withInput()
                ->with('error', 'Error al actualizar el sensor. Por favor intente nuevamente.');
        }
    }

    public function destroy(Sensor $sensor)
    {
        try {
            $sensor->delete();
            return redirect()->route('sensors.index')
                ->with('success', 'Sensor eliminado exitosamente');
        } catch (\Exception $e) {
            Log::error('Error al eliminar sensor: ' . $e->getMessage());
            return back()->with('error', 'Error al eliminar el sensor. Por favor intente nuevamente.');
        }
    }

    public function getLatestReadings(Request $request)
    {
        $limit = $request->query('limit', 1); // Obtener el límite de lecturas
        $sensors = Sensor::with(['readings' => function ($query) use ($limit) {
            $query->orderBy('reading_time', 'desc')->limit($limit);
        }])->get();

        return response()->json(['sensors' => $sensors]);
    }

    public function downloadReadings(Sensor $sensor)
{
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
                'classroom' => $sensor->device->classroom->name
            ],
            'readings' => $readings
        ];

        // Generar el nombre del archivo
        $fileName = 'sensor_' . $sensor->id . '_readings_' . date('Y-m-d_His') . '.json';

        // Retornar la respuesta como descarga
        return response()->json($data)
            ->header('Content-Disposition', 'attachment; filename=' . $fileName)
            ->header('Content-Type', 'application/json');

    } catch (\Exception $e) {
        Log::error('Error al descargar lecturas del sensor: ' . $e->getMessage());
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
    try {
        $startDate = $request->input('startDate');
        $endDate = $request->input('endDate');

        $query = $sensor->readings()
            ->whereBetween('reading_time', [
                $startDate . ' 00:00:00',
                $endDate . ' 23:59:59'
            ])
            ->orderBy('reading_time', 'desc');

        $readings = $query->get();

        return response()->json([
            'success' => true,
            'readings' => $readings,
            'sensor' => [
                'unit' => $sensor->sensorType->unit
            ]
        ]);

    } catch (\Exception $e) {
        Log::error('Error al filtrar lecturas: ' . $e->getMessage());
        return response()->json([
            'success' => false,
            'message' => 'Error al obtener las lecturas'
        ], 500);
    }
}

public function getByDevice($deviceId)
{
    try {
        $sensors = Sensor::where('device_id', $deviceId)->get();
        return response()->json($sensors);
    } catch (\Exception $e) {
        return response()->json(['error' => 'Error al obtener sensores: ' . $e->getMessage()], 500);
    }
}
}
