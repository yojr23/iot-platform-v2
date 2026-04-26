<?php

namespace App\Http\Controllers\API;

use App\Events\NewSensorReading;
use App\Http\Controllers\Controller;
use App\Models\Sensor;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Throwable;

class SensorApiController extends Controller
{
    public function store(Request $request, Sensor $sensor)
    {
        $context = $this->buildContext($request, $sensor);
        $payload = $request->all();
        $this->extractProvidedApiKey($request, $payload);

        Log::info('Sensor ingestion request received', $context + [
            'payload_keys' => array_keys($payload),
        ]);

        $unexpectedFields = $this->detectUnexpectedFields($payload, ['value', 'reading_time', 'api_key']);
        if ($unexpectedFields !== []) {
            Log::warning('Sensor ingestion payload has unexpected fields', $context + [
                'unexpected_fields' => $unexpectedFields,
                'payload' => $this->safePayload($payload),
            ]);
        }

        $device = $sensor->device;

        // Verificar si el dispositivo está activo
        if (! $device || ! $device->is_active || ! $device->status) {
            Log::warning('Sensor ingestion rejected: device inactive or missing', $context + [
                'device_exists' => (bool) $device,
                'device_status' => $device?->status,
                'device_is_active' => $device?->is_active,
            ]);

            return response()->json([
                'error' => 'Device Inactive',
                'message' => 'El dispositivo está desactivado y no puede recibir datos',
            ], 403);
        }

        $validator = Validator::make($payload, [
            'value' => 'required|numeric',
            'reading_time' => 'nullable|date_format:Y-m-d H:i:s',
            'api_key' => 'required|string|min:8|max:255',
        ]);

        if ($validator->fails()) {
            Log::warning('Sensor ingestion validation failed', $context + [
                'errors' => $validator->errors()->toArray(),
                'payload' => $this->safePayload($payload),
            ]);

            throw new ValidationException($validator);
        }

        $validated = $validator->validated();
        $numericValue = (float) $validated['value'];

        if (! is_finite($numericValue)) {
            Log::warning('Sensor ingestion rejected: unexpected numeric value', $context + [
                'value' => $validated['value'],
            ]);

            return response()->json([
                'error' => 'Invalid value',
                'message' => 'El valor enviado no es un número finito válido.',
            ], 422);
        }

        // Verificar API key:
        // 1) Clave por dispositivo (preferida)
        // 2) Clave global de compatibilidad (legacy)
        $configuredApiKey = (string) config('app.api_key');
        $deviceApiKey = (string) ($device?->api_key ?? '');
        $providedApiKey = (string) $validated['api_key'];
        $matchesDeviceApiKey = $deviceApiKey !== '' && hash_equals($deviceApiKey, $providedApiKey);
        $matchesGlobalApiKey = $configuredApiKey !== '' && hash_equals($configuredApiKey, $providedApiKey);

        if (! $matchesDeviceApiKey && ! $matchesGlobalApiKey) {
            Log::warning('Sensor ingestion rejected: invalid API key', $context + [
                'provided_api_key_length' => strlen($providedApiKey),
                'configured_api_key_present' => $configuredApiKey !== '',
                'device_api_key_present' => $deviceApiKey !== '',
            ]);

            return response()->json(['error' => 'Unauthorized'], 401);
        }

        try {
            // Crear nueva lectura
            $reading = $sensor->readings()->create([
                'value' => $numericValue,
                'reading_time' => $validated['reading_time'] ?? now(),
            ]);

            // Disparar evento para actualización en tiempo real
            event(new NewSensorReading($reading));

            Log::info('Sensor reading stored successfully', $context + [
                'reading_id' => $reading->id,
                'value' => $reading->value,
                'reading_time' => optional($reading->reading_time)->toDateTimeString(),
            ]);

            return response()->json([
                'message' => 'Reading saved successfully',
                'reading' => $reading,
            ], 201);
        } catch (QueryException $e) {
            Log::error('Database error while saving sensor reading', $context + [
                'sql_state' => $e->errorInfo[0] ?? null,
                'db_error_code' => $e->errorInfo[1] ?? null,
                'exception' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Database error',
                'message' => 'No fue posible guardar la lectura en base de datos.',
            ], 500);
        } catch (Throwable $e) {
            Log::error('Unexpected error while saving sensor reading', $context + [
                'exception' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Error processing reading',
                'message' => 'Se produjo un error inesperado al procesar la lectura.',
            ], 500);
        }
    }

    /**
     * Alias requerido por la ruta POST /api/sensors/{sensor}/readings.
     */
    public function storeReading(Request $request, Sensor $sensor)
    {
        return $this->store($request, $sensor);
    }

    public function readings(Sensor $sensor)
    {
        try {
            $readings = $sensor->readings()
                ->where('reading_time', '<=', now())
                ->orderBy('reading_time', 'desc')
                ->paginate(15);

            Log::info('Sensor readings fetched', [
                'sensor_id' => $sensor->id,
                'total' => $readings->total(),
                'current_page' => $readings->currentPage(),
            ]);

            return response()->json([
                'sensor' => $sensor,
                'readings' => $readings,
            ]);
        } catch (QueryException $e) {
            Log::error('Database error fetching sensor readings', [
                'sensor_id' => $sensor->id,
                'sql_state' => $e->errorInfo[0] ?? null,
                'db_error_code' => $e->errorInfo[1] ?? null,
                'exception' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Database error',
                'message' => 'No fue posible consultar lecturas en base de datos.',
            ], 500);
        } catch (Throwable $e) {
            Log::error('Error fetching sensor readings', [
                'sensor_id' => $sensor->id,
                'exception' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Error retrieving readings',
                'message' => 'Se produjo un error inesperado consultando lecturas.',
            ], 500);
        }
    }

    public function latestReadings(Request $request, Sensor $sensor)
    {
        try {
            $originalLimit = $request->query('limit', 10);
            $limit = (int) $originalLimit;
            $limit = max(1, min($limit, 100));

            if ((string) $originalLimit !== (string) $limit) {
                Log::warning('latestReadings received unexpected limit format/value; clamped to safe range', [
                    'sensor_id' => $sensor->id,
                    'requested_limit' => $originalLimit,
                    'effective_limit' => $limit,
                ]);
            }

            $readings = $sensor->readings()
                ->where('reading_time', '<=', now())
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

            Log::info('Latest sensor readings fetched', [
                'sensor_id' => $sensor->id,
                'requested_limit' => $originalLimit,
                'effective_limit' => $limit,
                'returned_count' => $readings->count(),
            ]);

            return response()->json($readings);
        } catch (QueryException $e) {
            Log::error('Database error fetching latest sensor readings', [
                'sensor_id' => $sensor->id,
                'sql_state' => $e->errorInfo[0] ?? null,
                'db_error_code' => $e->errorInfo[1] ?? null,
                'exception' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Database error',
                'message' => 'No fue posible consultar últimas lecturas.',
            ], 500);
        } catch (Throwable $e) {
            Log::error('Error fetching latest sensor readings', [
                'sensor_id' => $sensor->id,
                'exception' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Error retrieving latest readings',
                'message' => 'Se produjo un error inesperado consultando últimas lecturas.',
            ], 500);
        }
    }

    public function allReadings()
    {
        try {
            $sensors = Sensor::with(['sensorType', 'device.lab', 'readings' => function ($query) {
                $query->where('reading_time', '<=', now())
                    ->orderBy('reading_time', 'desc')
                    ->limit(100);
            }])->get();

            Log::info('All sensor readings requested', [
                'sensor_count' => $sensors->count(),
            ]);

            return response()->json([
                'sensors' => $sensors->map(function ($sensor) {
                    return [
                        'id' => $sensor->id,
                        'name' => $sensor->name,
                        'unit' => $sensor->sensorType->unit,
                        'color' => $this->getColorForSensor($sensor->id),
                        'readings' => $sensor->readings->map(function ($reading) {
                            return [
                                'value' => (float) $reading->value,
                                'time' => $reading->reading_time->format('Y-m-d H:i:s'),
                            ];
                        })->reverse(),
                    ];
                }),
            ]);
        } catch (QueryException $e) {
            Log::error('Database error fetching all sensor readings', [
                'sql_state' => $e->errorInfo[0] ?? null,
                'db_error_code' => $e->errorInfo[1] ?? null,
                'exception' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Database error',
                'message' => 'No fue posible consultar datos de sensores.',
            ], 500);
        } catch (Throwable $e) {
            Log::error('Error fetching all sensor readings', [
                'exception' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Error retrieving sensor data',
                'message' => 'Se produjo un error inesperado consultando datos de sensores.',
            ], 500);
        }
    }

    private function getColorForSensor($id)
    {
        $colors = ['#2196F3', '#4CAF50', '#FF9800', '#9C27B0', '#f44336', '#00BCD4', '#8BC34A'];

        return $colors[$id % count($colors)];
    }

    public function iotIndex(Request $request)
    {
        $configuredApiKey = (string) config('app.api_key');
        $providedApiKey = (string) ($request->header('X-Device-Key')
            ?? $request->query('api_key')
            ?? $request->input('api_key', ''));

        if ($configuredApiKey === '' || $providedApiKey === '' || ! hash_equals($configuredApiKey, $providedApiKey)) {
            Log::warning('IoT sensor listing rejected: invalid API key', [
                'ip' => $request->ip(),
                'provided_api_key_length' => strlen($providedApiKey),
                'configured_api_key_present' => $configuredApiKey !== '',
            ]);

            return response()->json(['error' => 'Unauthorized'], 401);
        }

        return $this->index($request);
    }

    public function index(Request $request)
    {
        try {
            $sensors = Sensor::with(['sensorType', 'device.lab'])->get();

            if ($sensors->isEmpty()) {
                Log::warning('No sensors found in database', [
                    'path' => $request->path(),
                ]);
            } else {
                Log::info('Sensors listed successfully', [
                    'count' => $sensors->count(),
                    'path' => $request->path(),
                ]);
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
                        ],
                    ];
                })->values()
            );
        } catch (QueryException $e) {
            Log::error('Database error fetching sensors', [
                'sql_state' => $e->errorInfo[0] ?? null,
                'db_error_code' => $e->errorInfo[1] ?? null,
                'exception' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Database error',
                'message' => 'No fue posible consultar sensores.',
            ], 500);
        } catch (Throwable $e) {
            Log::error('Error fetching sensors', [
                'exception' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Error retrieving sensors',
                'message' => 'Se produjo un error inesperado consultando sensores.',
            ], 500);
        }
    }

    /**
     * Prioriza X-Device-Key para evitar exponer credenciales en payloads.
     *
     * @param array<string,mixed> $payload
     */
    private function extractProvidedApiKey(Request $request, array &$payload): string
    {
        $headerApiKey = (string) $request->header('X-Device-Key', '');
        $bodyApiKey = (string) ($payload['api_key'] ?? '');
        $effectiveApiKey = $headerApiKey !== '' ? $headerApiKey : $bodyApiKey;

        $payload['api_key'] = $effectiveApiKey;

        return $effectiveApiKey;
    }

    private function buildContext(Request $request, Sensor $sensor): array
    {
        return [
            'sensor_id' => $sensor->id,
            'device_id' => $sensor->device_id,
            'ip' => $request->ip(),
            'path' => $request->path(),
            'method' => $request->method(),
            'user_agent' => $request->userAgent(),
            'request_id' => $request->header('X-Request-Id'),
        ];
    }

    private function safePayload(array $payload): array
    {
        if (array_key_exists('api_key', $payload)) {
            $payload['api_key'] = $this->maskApiKey((string) $payload['api_key']);
        }

        return $payload;
    }

    private function maskApiKey(string $apiKey): string
    {
        if ($apiKey === '') {
            return '';
        }

        if (strlen($apiKey) <= 6) {
            return str_repeat('*', strlen($apiKey));
        }

        return substr($apiKey, 0, 3).str_repeat('*', max(strlen($apiKey) - 6, 1)).substr($apiKey, -3);
    }

    /**
     * @param array<string,mixed> $payload
     * @param array<int,string> $allowedFields
     * @return array<int,string>
     */
    private function detectUnexpectedFields(array $payload, array $allowedFields): array
    {
        return array_values(array_diff(array_keys($payload), $allowedFields));
    }
}
