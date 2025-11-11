<?php

namespace App\Http\Controllers\API;

use App\Models\Sensor;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Events\NewSensorReading;

class SensorApiController extends Controller
{

    public function store(Request $request, Sensor $sensor)
    {
        // Validación de los datos
        $validated = $request->validate([
            'value' => 'required|numeric',
            'reading_time' => 'nullable|date_format:Y-m-d H:i:s',
            'api_key' => 'required|string' // Para autenticación
        ]);

        // Verificar API key (configurada en tu .env)
        if ($validated['api_key'] !== config('app.api_key')) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        try {
            // Crear nueva lectura
            $reading = $sensor->readings()->create([
                'value' => $validated['value'],
                'reading_time' => $validated['reading_time'] ?? now()
            ]);

            // Evaluar reglas de alerta inmediatamente
            $reading->checkForAlert();

            // Disparar evento para actualización en tiempo real
            event(new NewSensorReading($reading));

            return response()->json([
                'message' => 'Reading saved successfully',
                'reading' => $reading
            ], 201);

        } catch (\Exception $e) {
            Log::error("Error saving sensor reading: " . $e->getMessage());
            return response()->json([
                'error' => 'Error processing reading',
                'details' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Alias requerido por la ruta POST /api/sensors/{sensor}/readings.
     * Laravel estaba intentando invocar storeReading (inexistente) y la petición fallaba,
     * evitando que se creen lecturas y, por lo tanto, alertas.
     */
    public function storeReading(Request $request, Sensor $sensor)
    {
        return $this->store($request, $sensor);
    }
    public function readings(Sensor $sensor)
    {
        try {
            // Get readings with pagination
            $readings = $sensor->readings()
                ->orderBy('reading_time', 'desc')
                ->paginate(15); // Adjust pagination as needed

            return response()->json([
                'sensor' => $sensor,
                'readings' => $readings
            ]);

        } catch (\Exception $e) {
            Log::error("Error fetching sensor readings: " . $e->getMessage());
            return response()->json([
                'error' => 'Error retrieving readings',
                'details' => $e->getMessage()
            ], 500);
        }
    }

    public function latestReadings(Request $request, Sensor $sensor)
    {
        try {
            $limit = (int) $request->query('limit', 10);
            $limit = max(1, min($limit, 100));

            $readings = $sensor->readings()
                ->orderBy('reading_time', 'desc')
                ->limit($limit)
                ->get()
                ->map(fn ($reading) => [
                    'id' => $reading->id,
                    'value' => (float) $reading->value,
                    'reading_time' => $reading->reading_time?->toIso8601String(),
                    'created_at' => $reading->created_at?->toIso8601String(),
                ])
                ->values();

            return response()->json($readings);
        } catch (\Exception $e) {
            Log::error("Error fetching latest sensor readings: " . $e->getMessage());
            return response()->json([
                'error' => 'Error retrieving latest readings',
                'details' => $e->getMessage()
            ], 500);
        }
    }
    public function allReadings()
    {
        try {
            // Cargar todos los sensores con sus lecturas más recientes (limitar a 100 por sensor)
            $sensors = Sensor::with(['sensorType', 'device.classroom', 'readings' => function ($query) {
                $query->orderBy('reading_time', 'desc')->limit(100);
            }])->get();

            return response()->json([
                'sensors' => $sensors->map(function ($sensor) {
                    return [
                        'id' => $sensor->id,
                        'name' => $sensor->name,
                        'unit' => $sensor->sensorType->unit,
                        'color' => $this->getColorForSensor($sensor->id),
                        'readings' => $sensor->readings->map(function ($reading) {
                            return [
                                'value' => floatval($reading->value),
                                'time' => $reading->reading_time->format('Y-m-d H:i:s')
                            ];
                        })->reverse() // Para orden cronológico
                    ];
                })
            ]);

        } catch (\Exception $e) {
            Log::error("Error fetching all sensor readings: " . $e->getMessage());
            return response()->json([
                'error' => 'Error retrieving sensor data',
                'details' => $e->getMessage()
            ], 500);
        }
    }

    private function getColorForSensor($id)
    {
        $colors = ['#2196F3', '#4CAF50', '#FF9800', '#9C27B0', '#f44336', '#00BCD4', '#8BC34A'];
        return $colors[$id % count($colors)];
    }
    public function index()
    {
        try {
            // Cargar sensores con sus relaciones
            $sensors = Sensor::with(['sensorType', 'device.classroom'])->get();

            if ($sensors->isEmpty()) {
                Log::warning('No se encontraron sensores en la base de datos.');
            }

            return response()->json(
                $sensors->map(function ($sensor) {
                    return [
                        'id' => $sensor->id,
                        'name' => $sensor->name,
                        'device_id' => $sensor->device_id ?? null,
                        'unit' => $sensor->sensorType->unit ?? '',
                        'sensor_type' => [
                            'name' => $sensor->sensorType->name ?? '',
                            'unit' => $sensor->sensorType->unit ?? '',
                            'min_range' => $sensor->sensorType->min_range ?? null,
                            'max_range' => $sensor->sensorType->max_range ?? null,
                        ]
                    ];
                })->values()
            );
        } catch (\Exception $e) {
            Log::error("Error fetching sensors: " . $e->getMessage());
            return response()->json([
                'error' => 'Error retrieving sensors',
                'details' => $e->getMessage()
            ], 500);
        }
    }
}
