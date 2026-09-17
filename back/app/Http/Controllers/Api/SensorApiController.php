<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\SensorResource;
use App\Models\Device;
use App\Models\Sensor;
use App\Services\Ingestion\SensorReadingService;
use App\Services\Ingestion\SensorReadingProjectionService;
use App\Services\Monitoring\PublicGraphSeriesService;
use App\Services\Monitoring\RuleToGraphZones;
use App\Services\SensorMappingService;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Throwable;

class SensorApiController extends Controller
{
    private const MAX_GRAPH_WINDOW_SECONDS = 24 * 60 * 60;

    /** SEC-EXPORT-001: hard bounds on reading exports. */
    private const EXPORT_MAX_WINDOW_DAYS = 31;
    private const EXPORT_MAX_ROWS = 50000;

    public function __construct(
        private SensorReadingService $readingService,
        private SensorReadingProjectionService $readingProjection,
        private SensorMappingService $mappingService,
    ) {
    }

    public function store(Request $request, Sensor $sensor)
    {
        $startTime = microtime(true);

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

        // Hash-only per-device auth via Device::authenticate(). The global key is
        // available only when an operator explicitly enables the temporary fallback.
        $providedApiKey = (string) $validated['api_key'];
        $matchesDeviceKey = $device->authenticate($providedApiKey);
        $matchesGlobalApiKey = $this->matchesLegacyGlobalApiKey($providedApiKey);

        if (! $matchesDeviceKey && ! $matchesGlobalApiKey) {
            // SEC-LOG-001: no key material or fingerprints (length / presence) in logs.
            Log::warning('Sensor ingestion rejected: invalid API key', $context);

            return response()->json(['error' => 'Unauthorized'], 401);
        }

        try {
            // Crear nueva lectura
            $reading = $this->readingService->createReading(
            $sensor,
            $numericValue,
            $validated['reading_time'] ?? null,
        );

            Log::info('Sensor reading stored successfully', $context + [
                'reading_id' => $reading->id,
                'value' => $reading->value,
                'reading_time' => optional($reading->reading_time)->toDateTimeString(),
                'duration_ms' => round((microtime(true) - $startTime) * 1000, 2),
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
                'duration_ms' => round((microtime(true) - $startTime) * 1000, 2),
            ]);

            return response()->json([
                'error' => 'Database error',
                'message' => 'No fue posible guardar la lectura en base de datos.',
            ], 500);
        } catch (Throwable $e) {
            Log::error('Unexpected error while saving sensor reading', $context + [
                'exception' => $e->getMessage(),
                'duration_ms' => round((microtime(true) - $startTime) * 1000, 2),
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

    /**
     * Authenticated bounded graph series — the private-sensor counterpart of
     * PublicGraphController::series. Reuses the SAME PublicGraphSeriesService (indexed DB range,
     * server-computed stats, JSON_PRESERVE_ZERO_FRACTION) but WITHOUT PublicGraphVisibility: the
     * auth:sanctum middleware is the authorization boundary, so an authenticated user can pull the
     * history of a restricted (public_monitoring_enabled=false) sensor — the endpoint the frontend
     * previously (incorrectly) tried to satisfy via /public/graph/*, which 404s for restricted
     * sensors. Same window contract (from<to, YYYY-MM-DDTHH:mm:ssZ) as the public route.
     */
    public function series(Request $request, Sensor $sensor, PublicGraphSeriesService $service)
    {
        $this->authorize('view', $sensor);

        $startTime = microtime(true);

        $validated = $request->validate([
            'from' => ['required', 'string', 'regex:/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/'],
            'to' => ['required', 'string', 'regex:/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/'],
        ]);

        $from = CarbonImmutable::createFromFormat('Y-m-d\TH:i:s\Z', $validated['from'], 'UTC') ?: null;
        $to = CarbonImmutable::createFromFormat('Y-m-d\TH:i:s\Z', $validated['to'], 'UTC') ?: null;

        if (! $from || ! $to || ! $from->lessThan($to)
            || $from->diffInSeconds($to) > self::MAX_GRAPH_WINDOW_SECONDS) {
            throw ValidationException::withMessages([
                'range' => 'The graph window must be between 1 second and 24 hours using YYYY-MM-DDTHH:mm:ssZ.',
            ]);
        }

        $result = $service->series($sensor, $from, $to);

        Log::info('Authenticated sensor series request', [
            'sensor_id' => $sensor->id,
            'request_id' => $request->header('X-Request-Id', uniqid()),
            'duration_ms' => round((microtime(true) - $startTime) * 1000, 2),
        ]);

        return response()->json($result, 200, [], JSON_PRESERVE_ZERO_FRACTION);
    }

    public function readings(Request $request, Sensor $sensor)
    {
        $this->authorize('view', $sensor);

        $startTime = microtime(true);

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
                'duration_ms' => round((microtime(true) - $startTime) * 1000, 2),
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
                'duration_ms' => round((microtime(true) - $startTime) * 1000, 2),
            ]);

            return response()->json([
                'error' => 'Database error',
                'message' => 'No fue posible consultar lecturas en base de datos.',
            ], 500);
        } catch (Throwable $e) {
            Log::error('Error fetching sensor readings', [
                'sensor_id' => $sensor->id,
                'exception' => $e->getMessage(),
                'duration_ms' => round((microtime(true) - $startTime) * 1000, 2),
            ]);

            return response()->json([
                'error' => 'Error retrieving readings',
                'message' => 'Se produjo un error inesperado consultando lecturas.',
            ], 500);
        }
    }

    public function exportReadings(Request $request, Sensor $sensor)
    {
        $this->authorize('view', $sensor);

        // SEC-EXPORT-001: exports are bounded — mandatory date range, max 31-day window,
        // hard 50k row cap — so a single export can't pull unbounded history. Validation
        // runs outside the try so its 422 isn't swallowed into a generic 500.
        $filters = $request->validate([
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);

        $from = Carbon::createFromFormat('Y-m-d', $filters['from'])->startOfDay();
        $to = Carbon::createFromFormat('Y-m-d', $filters['to'])->endOfDay();

        if ($from->diffInDays($to) > self::EXPORT_MAX_WINDOW_DAYS) {
            throw ValidationException::withMessages([
                'to' => 'El rango de exportación no puede exceder '.self::EXPORT_MAX_WINDOW_DAYS.' días.',
            ]);
        }

        $startTime = microtime(true);

        try {
            $sensor->load(['sensorType', 'device.lab']);

            $baseQuery = $this->filteredReadingsQuery($sensor, $filters)
                ->where('reading_time', '<=', now());

            if ((clone $baseQuery)->count() > self::EXPORT_MAX_ROWS) {
                throw ValidationException::withMessages([
                    'range' => 'La exportación excede el máximo de '.self::EXPORT_MAX_ROWS.' registros. Reduce el rango.',
                ]);
            }

            $readings = $baseQuery
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
                'exception_class' => $e::class,
                'duration_ms' => round((microtime(true) - $startTime) * 1000, 2),
            ]);

            return response()->json([
                'error' => 'Database error',
                'message' => 'No fue posible exportar lecturas.',
            ], 500);
        } catch (ValidationException $e) {
            throw $e; // 422 row-cap rejection, not a 500.
        } catch (Throwable $e) {
            Log::error('Unexpected error exporting sensor readings', [
                'sensor_id' => $sensor->id,
                'exception_class' => $e::class,
                'duration_ms' => round((microtime(true) - $startTime) * 1000, 2),
            ]);

            return response()->json([
                'error' => 'Error exporting readings',
                'message' => 'Se produjo un error inesperado exportando lecturas.',
            ], 500);
        }
    }

    public function latestReadings(Request $request, Sensor $sensor)
    {
        $this->authorize('view', $sensor);

        $startTime = microtime(true);

        try {
            $originalLimit = $request->query('limit', 10);
            $filteredLimit = filter_var($originalLimit, FILTER_VALIDATE_INT);
            if ($filteredLimit === false || $filteredLimit < 1) {
                $filteredLimit = 10;
                Log::warning('latestReadings received invalid limit; using default', [
                    'sensor_id' => $sensor->id,
                    'requested_limit' => $originalLimit,
                ]);
            }
            $limit = max(1, min($filteredLimit, 100));

            $source = 'redis';
            $readings = $this->readingProjection->latest($sensor->id, $limit);
            $databaseReadingIds = $sensor->readings()
                ->where('reading_time', '<=', now())
                ->orderBy('reading_time', 'desc')
                ->orderBy('id', 'desc')
                ->limit($limit)
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();
            $redisReadingIds = $readings?->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();

            if ($readings === null
                || $readings->isEmpty()
                || $redisReadingIds !== $databaseReadingIds) {
                $source = 'database';
                $readings = $sensor->readings()
                    ->where('reading_time', '<=', now())
                    ->orderBy('reading_time', 'desc')
                    ->orderBy('id', 'desc')
                    ->limit($limit)
                    ->get()
                      ->map(fn ($reading) => $this->readingProjection->format($reading))
                      ->values();

                $this->readingProjection->warm($sensor->id, $readings);
            }

            Log::info('Latest sensor readings fetched', [
                'sensor_id' => $sensor->id,
                'requested_limit' => $originalLimit,
                'effective_limit' => $limit,
                'returned_count' => $readings->count(),
                'source' => $source,
                'duration_ms' => round((microtime(true) - $startTime) * 1000, 2),
            ]);

            return response()->json($readings);
        } catch (QueryException $e) {
            Log::error('Database error fetching latest sensor readings', [
                'sensor_id' => $sensor->id,
                'sql_state' => $e->errorInfo[0] ?? null,
                'db_error_code' => $e->errorInfo[1] ?? null,
                'exception' => $e->getMessage(),
                'duration_ms' => round((microtime(true) - $startTime) * 1000, 2),
            ]);

            return response()->json([
                'error' => 'Database error',
                'message' => 'No fue posible consultar últimas lecturas.',
            ], 500);
        } catch (Throwable $e) {
            Log::error('Error fetching latest sensor readings', [
                'sensor_id' => $sensor->id,
                'exception' => $e->getMessage(),
                'duration_ms' => round((microtime(true) - $startTime) * 1000, 2),
            ]);

            return response()->json([
                'error' => 'Error retrieving latest readings',
                'message' => 'Se produjo un error inesperado consultando últimas lecturas.',
            ], 500);
        }
    }

    /**
     * Phase E: hard global ceiling on total rows hydrated by allReadings(), independent of how many
     * sensors exist. Prevents a single legal request from producing sensor_count x per_sensor_limit
     * in-memory Eloquent objects. Per-sensor limit is derived from this budget at request time.
     */
    public const ALL_READINGS_GLOBAL_ROW_BUDGET = 5000;

    public function allReadings(Request $request)
    {
        $startTime = microtime(true);

        // Phase E: validate inputs -> controlled 422, not a generic 500 from Carbon::parse throwing.
        $validated = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:'.self::ALL_READINGS_GLOBAL_ROW_BUDGET],
        ]);

        try {
            // P1: bound the query window. Without from/to, default to last 24h.
            // Hard max is 7 days to prevent accidental full-table scans.
            $maxWindow = now()->subDays(7);
            $from = isset($validated['from'])
                ? Carbon::parse($validated['from'])->max($maxWindow)
                : now()->subDay();
            $to = isset($validated['to'])
                ? Carbon::parse($validated['to'])
                : now();
            $requestedLimit = min((int) ($validated['limit'] ?? 1000), self::ALL_READINGS_GLOBAL_ROW_BUDGET);

            // Phase E global budget: total work is capped at ALL_READINGS_GLOBAL_ROW_BUDGET rows across
            // ALL sensors, not per-sensor. Split the budget evenly so more sensors -> smaller per-sensor
            // slice, never sensor_count x limit. At least 1 row/sensor so every sensor still reports.
            $sensorCount = max(1, Sensor::query()->count());
            $perSensorLimit = max(1, min($requestedLimit, (int) floor(self::ALL_READINGS_GLOBAL_ROW_BUDGET / $sensorCount)));

            $sensors = Sensor::with(['sensorType', 'device.lab', 'readings' => function ($query) use ($from, $to, $perSensorLimit) {
                $query->where('reading_time', '>=', $from)
                    ->where('reading_time', '<=', $to)
                    ->orderBy('reading_time', 'desc')
                    ->limit($perSensorLimit);
            }])->get();

            Log::info('All sensor readings requested', [
                'sensor_count' => $sensors->count(),
                'duration_ms' => round((microtime(true) - $startTime) * 1000, 2),
            ]);

            return response()->json([
                'from' => $from->toIso8601String(),
                'to' => $to->toIso8601String(),
                'sensors' => $sensors->map(function ($sensor) {
                    return [
                        'id' => $sensor->id,
                        'name' => $sensor->name,
                        'unit' => $sensor->sensorType?->unit ?? '',
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
                'duration_ms' => round((microtime(true) - $startTime) * 1000, 2),
            ]);

            return response()->json([
                'error' => 'Database error',
                'message' => 'No fue posible consultar datos de sensores.',
            ], 500);
        } catch (Throwable $e) {
            Log::error('Error fetching all sensor readings', [
                'exception' => $e->getMessage(),
                'duration_ms' => round((microtime(true) - $startTime) * 1000, 2),
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
        $startTime = microtime(true);

        // SEC-IOT-002: header-only credential. A query-string key leaks into access logs,
        // proxies and browser history; a body key is likewise disallowed here.
        // Hash-only per-device auth: try the header credential first. The global
        // fallback is off by default and requires an explicit operator opt-in.
        $providedApiKey = (string) $request->header('X-Device-Key', '');
        if ($providedApiKey === '') {
            Log::warning('IoT sensor listing rejected: missing API key', [
                'ip' => $request->ip(),
                'request_id' => $request->header('X-Request-Id'),
            ]);

            return response()->json(['error' => 'Unauthorized'], 401);
        }

        // Try per-device auth: hash the provided key and look up directly in DB.
        $providedHash = hash('sha256', $providedApiKey);
        $deviceMatch = Device::where('is_active', true)
            ->where('status', true)
            ->where('api_key_hash', $providedHash)
            ->first();

        $matchesGlobalKey = $this->matchesLegacyGlobalApiKey($providedApiKey);

        if (! $deviceMatch && ! $matchesGlobalKey) {
            // SEC-LOG-001: no key material or fingerprints (length / presence) in logs.
            Log::warning('IoT sensor listing rejected: invalid API key', [
                'ip' => $request->ip(),
                'request_id' => $request->header('X-Request-Id'),
            ]);

            return response()->json(['error' => 'Unauthorized'], 401);
        }

        // A device credential may provision only its own sensors. The explicitly
        // enabled global compatibility credential retains the legacy full inventory.
        $sensorQuery = Sensor::with(['sensorType', 'device.lab', 'latestReading']);
        if ($deviceMatch) {
            $sensorQuery->where('device_id', $deviceMatch->id);
        }

        $sensors = $sensorQuery->get();

        return response()->json(SensorResource::collection($sensors)->resolve($request));
    }

    public function index(Request $request)
    {
        $startTime = microtime(true);

        try {
            // SEC-INV-001: paginate with a hard max page size so the inventory can't be
            // pulled as one unbounded result set. latestReading eager-loaded so
            // SensorResource's `latest_reading` is populated on the global list too — single
            // extra query via the hasOneOfMany relation, no N+1.
            $perPage = min(max((int) $request->integer('per_page', 50), 1), 100);
            $sensors = Sensor::with(['sensorType', 'device.lab', 'latestReading'])
                ->paginate($perPage);

            Log::info('Sensors listed successfully', [
                'count' => $sensors->count(),
                'total' => $sensors->total(),
                'path' => $request->path(),
                'duration_ms' => round((microtime(true) - $startTime) * 1000, 2),
            ]);

            return SensorResource::collection($sensors)->response();
        } catch (QueryException $e) {
            Log::error('Database error fetching sensors', [
                'sql_state' => $e->errorInfo[0] ?? null,
                'db_error_code' => $e->errorInfo[1] ?? null,
                'exception' => $e->getMessage(),
                'duration_ms' => round((microtime(true) - $startTime) * 1000, 2),
            ]);

            return response()->json([
                'error' => 'Database error',
                'message' => 'No fue posible consultar sensores.',
            ], 500);
        } catch (Throwable $e) {
            Log::error('Error fetching sensors', [
                'exception' => $e->getMessage(),
                'duration_ms' => round((microtime(true) - $startTime) * 1000, 2),
            ]);

            return response()->json([
                'error' => 'Error retrieving sensors',
                'message' => 'Se produjo un error inesperado consultando sensores.',
            ], 500);
        }
    }

    public function createSensor(Request $request)
    {
        $startTime = microtime(true);

        $validated = $this->validatedSensorPayload($request);
        $device = Device::find($validated['device_id']);

        if (! $device) {
            return response()->json([
                'message' => 'El dispositivo seleccionado no existe.',
                'errors' => [
                    'device_id' => ['El dispositivo seleccionado no existe.'],
                ],
            ], 422);
        }

        if (! $device->status || ! $device->is_active) {
            return response()->json([
                'message' => 'El dispositivo seleccionado no está disponible.',
                'errors' => [
                    'device_id' => ['El dispositivo seleccionado no está disponible.'],
                ],
            ], 422);
        }

        try {
            // SEC-TX-003: sensor row + its initial canonical mapping are one provisioning unit. Without
            // this outer transaction, a mapSensor() failure leaves a sensor the normalizer cannot resolve
            // (exists but has no canonical mapping). mapSensor's own inner transaction nests as a savepoint.
            $sensor = DB::transaction(function () use ($validated, $device) {
                $sensor = Sensor::create($validated + ['status' => true]);

                // P0: auto-create the canonical mapping so the new normalizer can resolve
                // this sensor immediately after creation (no separate operator step needed).
                $this->mappingService->mapSensor(
                    $device,
                    $sensor,
                    'ingestion_service',
                    $sensor->name,
                );

                return $sensor;
            });
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
                'duration_ms' => round((microtime(true) - $startTime) * 1000, 2),
            ]);

            return response()->json([
                'error' => 'Database error',
                'message' => 'No fue posible crear el sensor.',
            ], 500);
        } catch (Throwable $e) {
            Log::error('Unexpected error creating sensor', [
                'exception' => $e->getMessage(),
                'duration_ms' => round((microtime(true) - $startTime) * 1000, 2),
            ]);

            return response()->json([
                'error' => 'Error creating sensor',
                'message' => 'Se produjo un error inesperado creando el sensor.',
            ], 500);
        }
    }

    public function updateSensor(Request $request, Sensor $sensor)
    {
        $startTime = microtime(true);

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
                'duration_ms' => round((microtime(true) - $startTime) * 1000, 2),
            ]);

            return response()->json([
                'error' => 'Database error',
                'message' => 'No fue posible actualizar el sensor.',
            ], 500);
        } catch (Throwable $e) {
            Log::error('Unexpected error updating sensor', [
                'sensor_id' => $sensor->id,
                'exception' => $e->getMessage(),
                'duration_ms' => round((microtime(true) - $startTime) * 1000, 2),
            ]);

            return response()->json([
                'error' => 'Error updating sensor',
                'message' => 'Se produjo un error inesperado actualizando el sensor.',
            ], 500);
        }
    }

    public function destroySensor(Sensor $sensor)
    {
        $startTime = microtime(true);

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
                'duration_ms' => round((microtime(true) - $startTime) * 1000, 2),
            ]);

            return response()->json([
                'error' => 'Database error',
                'message' => 'No fue posible eliminar el sensor.',
            ], 500);
        } catch (Throwable $e) {
            Log::error('Unexpected error deleting sensor', [
                'sensor_id' => $sensor->id,
                'exception' => $e->getMessage(),
                'duration_ms' => round((microtime(true) - $startTime) * 1000, 2),
            ]);

            return response()->json([
                'error' => 'Error deleting sensor',
                'message' => 'Se produjo un error inesperado eliminando el sensor.',
            ], 500);
        }
    }

    /**
     * docs/implementation/graph-semantic-zones-plan.md (GRAPH-001/008) — authenticated projection
     * of the same `RuleToGraphZones` normalizer the public bootstrap uses. Fuller than the public
     * projection (includes rule boundary values + rule ids for the Sensor Inspector), but still no
     * notification policy — severity is the only semantic source exposed.
     */
    public function graphZones(Sensor $sensor, RuleToGraphZones $zones)
    {
        $this->authorize('view', $sensor);

        // JSON_PRESERVE_ZERO_FRACTION: an integer-valued threshold like 30.0 must not silently
        // decode as the int 30 on the client (same reasoning as PublicGraphController::series()).
        return response()->json($zones->zonesFor($sensor), 200, [], JSON_PRESERVE_ZERO_FRACTION);
    }

    public function show(Request $request, Sensor $sensor)
    {
        $this->authorize('view', $sensor);

        $startTime = microtime(true);

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
                'duration_ms' => round((microtime(true) - $startTime) * 1000, 2),
            ]);

            return response()->json([
                'error' => 'Database error',
                'message' => 'No fue posible consultar el sensor.',
            ], 500);
        } catch (Throwable $e) {
            Log::error('Error fetching sensor detail', [
                'sensor_id' => $sensor->id,
                'exception' => $e->getMessage(),
                'duration_ms' => round((microtime(true) - $startTime) * 1000, 2),
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

    private function matchesLegacyGlobalApiKey(string $providedApiKey): bool
    {
        // Strictly require the boolean config value. A non-empty string must never
        // silently enable this temporary, isolation-bypassing compatibility path.
        if (config('app.iot_legacy_global_key_fallback_enabled', false) !== true) {
            return false;
        }

        $configuredApiKey = (string) config('app.api_key');

        return $configuredApiKey !== '' && hash_equals($configuredApiKey, $providedApiKey);
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
            // Stage 6 boundary: admins opt a sensor into the public graph through this existing
            // CRUD owner (never raw SQL). Fail-closed — the column defaults FALSE; absence keeps it so.
            'public_monitoring_enabled' => ['sometimes', 'boolean'],
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
