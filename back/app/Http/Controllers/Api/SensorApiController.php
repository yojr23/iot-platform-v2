<?php

namespace App\Http\Controllers\API;

use App\Events\NewSensorReading;
use App\Http\Controllers\Controller;
use App\Http\Resources\SensorResource;
use App\Models\Device;
use App\Models\Sensor;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Throwable;

class SensorApiController extends Controller
{
    private const REDIS_SENSOR_READINGS_CACHE_LIMIT = 120;

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

            $this->cacheLatestReadingInRedis($sensor->id, $reading);

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

    public function readings(Request $request, Sensor $sensor)
    {
        try {
            $filters = $this->validatedReadingFilters($request);
            $readings = $this->filteredReadingsQuery($sensor, $filters)
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

    public function exportReadings(Request $request, Sensor $sensor)
    {
        try {
            $filters = $this->validatedReadingFilters($request);
            $sensor->load(['sensorType', 'device.lab']);

            $readings = $this->filteredReadingsQuery($sensor, $filters)
                ->where('reading_time', '<=', now())
                ->orderBy('reading_time', 'desc')
                ->get()
                ->map(fn ($reading): array => [
                    'id' => $reading->id,
                    'value' => (float) $reading->value,
                    'reading_time' => $reading->reading_time?->toIso8601String(),
                    'created_at' => $reading->created_at?->toIso8601String(),
                ])
                ->values();

            $fileName = 'sensor_'.$sensor->id.'_readings_'.now()->format('Y-m-d_His').'.json';

            return response()->json([
                'sensor' => [
                    'id' => $sensor->id,
                    'name' => $sensor->name,
                    'type' => $sensor->sensorType?->name,
                    'unit' => $sensor->sensorType?->unit,
                    'device' => $sensor->device?->name,
                    'lab' => $sensor->device?->lab?->name,
                ],
                'filters' => $filters,
                'readings' => $readings,
            ])->header('Content-Disposition', 'attachment; filename='.$fileName);
        } catch (QueryException $e) {
            Log::error('Database error exporting sensor readings', [
                'sensor_id' => $sensor->id,
                'sql_state' => $e->errorInfo[0] ?? null,
                'db_error_code' => $e->errorInfo[1] ?? null,
                'exception' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Database error',
                'message' => 'No fue posible exportar lecturas.',
            ], 500);
        } catch (Throwable $e) {
            Log::error('Unexpected error exporting sensor readings', [
                'sensor_id' => $sensor->id,
                'exception' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Error exporting readings',
                'message' => 'Se produjo un error inesperado exportando lecturas.',
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

            $source = 'redis';
            $readings = $this->getLatestReadingsFromRedis($sensor->id, $limit);

            if ($readings === null || $readings->isEmpty()) {
                $source = 'database';
                $readings = $sensor->readings()
                    ->where('reading_time', '<=', now())
                    ->orderBy('reading_time', 'desc')
                    ->orderBy('id', 'desc')
                    ->limit($limit)
                    ->get()
                    ->map(fn ($reading) => $this->formatReadingForResponse($reading))
                    ->values();

                if ($readings->isNotEmpty()) {
                    $this->warmLatestReadingsInRedis($sensor->id, $readings);
                }
            }

            Log::info('Latest sensor readings fetched', [
                'sensor_id' => $sensor->id,
                'requested_limit' => $originalLimit,
                'effective_limit' => $limit,
                'returned_count' => $readings->count(),
                'source' => $source,
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

    private function getSensorReadingsRedisKey(int $sensorId): string
    {
        return "sensor:latest_readings:{$sensorId}";
    }

    private function formatReadingForResponse($reading): array
    {
        return [
            'id' => $reading->id,
            'value' => (float) $reading->value,
            'reading_time' => $reading->reading_time?->toIso8601String(),
            'created_at' => $reading->created_at?->toIso8601String(),
        ];
    }

    private function cacheLatestReadingInRedis(int $sensorId, $reading): void
    {
        try {
            $key = $this->getSensorReadingsRedisKey($sensorId);
            $payload = json_encode($this->formatReadingForResponse($reading), JSON_UNESCAPED_UNICODE);

            if ($payload === false) {
                return;
            }

            Redis::pipeline(function ($pipe) use ($key, $payload): void {
                $pipe->lpush($key, $payload);
                $pipe->ltrim($key, 0, self::REDIS_SENSOR_READINGS_CACHE_LIMIT - 1);
            });
        } catch (Throwable $e) {
            Log::warning('Redis cache update skipped for latest readings', [
                'sensor_id' => $sensorId,
                'exception' => $e->getMessage(),
            ]);
        }
    }

    private function warmLatestReadingsInRedis(int $sensorId, $readings): void
    {
        try {
            $serialized = $readings
                ->filter(fn ($item) => is_array($item))
                ->map(fn ($item) => json_encode($item, JSON_UNESCAPED_UNICODE))
                ->filter(fn ($item) => $item !== false)
                ->values()
                ->all();

            if ($serialized === []) {
                return;
            }

            $key = $this->getSensorReadingsRedisKey($sensorId);
            Redis::pipeline(function ($pipe) use ($key, $serialized): void {
                $pipe->del($key);

                foreach (array_reverse($serialized) as $payload) {
                    $pipe->lpush($key, $payload);
                }

                $pipe->ltrim($key, 0, self::REDIS_SENSOR_READINGS_CACHE_LIMIT - 1);
            });
        } catch (Throwable $e) {
            Log::warning('Redis cache warm-up skipped for latest readings', [
                'sensor_id' => $sensorId,
                'exception' => $e->getMessage(),
            ]);
        }
    }

    private function getLatestReadingsFromRedis(int $sensorId, int $limit)
    {
        try {
            $key = $this->getSensorReadingsRedisKey($sensorId);
            $rawEntries = Redis::lrange($key, 0, $limit - 1);

            if (! is_array($rawEntries) || $rawEntries === []) {
                return collect();
            }

            return collect($rawEntries)
                ->map(function ($entry) {
                    if (! is_string($entry)) {
                        return null;
                    }

                    $decoded = json_decode($entry, true);
                    if (! is_array($decoded)) {
                        return null;
                    }

                    return [
                        'id' => isset($decoded['id']) ? (int) $decoded['id'] : null,
                        'value' => isset($decoded['value']) ? (float) $decoded['value'] : null,
                        'reading_time' => $decoded['reading_time'] ?? null,
                        'created_at' => $decoded['created_at'] ?? null,
                    ];
                })
                ->filter(fn ($entry) => is_array($entry) && $entry['id'] !== null && $entry['value'] !== null)
                ->values();
        } catch (Throwable $e) {
            Log::warning('Redis lookup failed for latest sensor readings', [
                'sensor_id' => $sensorId,
                'exception' => $e->getMessage(),
            ]);

            return null;
        }
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

            return response()->json(SensorResource::collection($sensors)->resolve($request));
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

    public function createSensor(Request $request)
    {
        $validated = $this->validatedSensorPayload($request);
        $device = Device::findOrFail($validated['device_id']);

        if (! $device->status || ! $device->is_active) {
            return response()->json([
                'message' => 'El dispositivo seleccionado no está disponible.',
                'errors' => [
                    'device_id' => ['El dispositivo seleccionado no está disponible.'],
                ],
            ], 422);
        }

        try {
            $sensor = Sensor::create($validated + ['status' => true]);
            $sensor->load(['sensorType', 'device.lab']);

            return (new SensorResource($sensor))
                ->additional(['message' => 'Sensor creado correctamente.'])
                ->response()
                ->setStatusCode(201);
        } catch (QueryException $e) {
            Log::error('Database error creating sensor', [
                'sql_state' => $e->errorInfo[0] ?? null,
                'db_error_code' => $e->errorInfo[1] ?? null,
                'exception' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Database error',
                'message' => 'No fue posible crear el sensor.',
            ], 500);
        } catch (Throwable $e) {
            Log::error('Unexpected error creating sensor', [
                'exception' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Error creating sensor',
                'message' => 'Se produjo un error inesperado creando el sensor.',
            ], 500);
        }
    }

    public function updateSensor(Request $request, Sensor $sensor)
    {
        $validated = $this->validatedSensorPayload($request);

        try {
            $sensor->update($validated);
            $sensor->refresh()->load(['sensorType', 'device.lab']);

            return (new SensorResource($sensor))
                ->additional(['message' => 'Sensor actualizado correctamente.']);
        } catch (QueryException $e) {
            Log::error('Database error updating sensor', [
                'sensor_id' => $sensor->id,
                'sql_state' => $e->errorInfo[0] ?? null,
                'db_error_code' => $e->errorInfo[1] ?? null,
                'exception' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Database error',
                'message' => 'No fue posible actualizar el sensor.',
            ], 500);
        } catch (Throwable $e) {
            Log::error('Unexpected error updating sensor', [
                'sensor_id' => $sensor->id,
                'exception' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Error updating sensor',
                'message' => 'Se produjo un error inesperado actualizando el sensor.',
            ], 500);
        }
    }

    public function destroySensor(Sensor $sensor)
    {
        if ($sensor->readings()->exists() || $sensor->alertRules()->exists()) {
            return response()->json([
                'message' => 'No se puede eliminar el sensor porque tiene lecturas o reglas asociadas.',
            ], 409);
        }

        try {
            $sensor->delete();

            return response()->json(['message' => 'Sensor eliminado correctamente.']);
        } catch (QueryException $e) {
            Log::error('Database error deleting sensor', [
                'sensor_id' => $sensor->id,
                'sql_state' => $e->errorInfo[0] ?? null,
                'db_error_code' => $e->errorInfo[1] ?? null,
                'exception' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Database error',
                'message' => 'No fue posible eliminar el sensor.',
            ], 500);
        } catch (Throwable $e) {
            Log::error('Unexpected error deleting sensor', [
                'sensor_id' => $sensor->id,
                'exception' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Error deleting sensor',
                'message' => 'Se produjo un error inesperado eliminando el sensor.',
            ], 500);
        }
    }

    public function show(Request $request, Sensor $sensor)
    {
        try {
            $sensor->load([
                'sensorType',
                'device.lab',
                'readings' => function ($query) {
                    $query->where('reading_time', '<=', now())
                        ->orderByDesc('reading_time')
                        ->limit(10);
                },
            ]);

            return response()->json((new SensorResource($sensor))->resolve($request));
        } catch (QueryException $e) {
            Log::error('Database error fetching sensor detail', [
                'sensor_id' => $sensor->id,
                'sql_state' => $e->errorInfo[0] ?? null,
                'db_error_code' => $e->errorInfo[1] ?? null,
                'exception' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Database error',
                'message' => 'No fue posible consultar el sensor.',
            ], 500);
        } catch (Throwable $e) {
            Log::error('Error fetching sensor detail', [
                'sensor_id' => $sensor->id,
                'exception' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Error retrieving sensor',
                'message' => 'Se produjo un error inesperado consultando el sensor.',
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

    /**
     * @return array<string,mixed>
     */
    private function validatedSensorPayload(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'device_id' => ['required', 'exists:devices,id'],
            'sensor_type_id' => ['required', 'exists:sensor_types,id'],
            'status' => ['sometimes', 'boolean'],
        ]);
    }

    /**
     * @return array{from?: string, to?: string}
     */
    private function validatedReadingFilters(Request $request): array
    {
        return $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);
    }

    private function filteredReadingsQuery(Sensor $sensor, array $filters)
    {
        return $sensor->readings()
            ->when(isset($filters['from']), function ($query) use ($filters) {
                $query->where('reading_time', '>=', Carbon::createFromFormat('Y-m-d', $filters['from'])->startOfDay());
            })
            ->when(isset($filters['to']), function ($query) use ($filters) {
                $query->where('reading_time', '<=', Carbon::createFromFormat('Y-m-d', $filters['to'])->endOfDay());
            });
    }
}
