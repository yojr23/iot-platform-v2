<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreRawIngestionEventRequest;
use App\Models\RawEventOutbox;
use App\Models\RawSensorEvent;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * PLAN.md Stage 3.1 / docs/implementation/adr-g1.md ADR-1 (transactional outbox).
 *
 * Existing code reused: `RawSensorEvent` (business row, unchanged shape).
 * Existing owner retired/delegated: this controller no longer calls
 * `RawSensorEventPublisher::publish()` synchronously (G0D row B6) — that dual-write gap (201
 * returned even when XADD failed) is closed by never depending on a synchronous publish here at
 * all. Gate 10: publishing is now triggered by MySQL binlog CDC (Debezium) captured onto a Redis
 * CDC stream and drained by `cdc:consume-outboxes`. This handler only commits the business row +
 * outbox row in one transaction; it dispatches nothing. No wake-up job, no polling relay.
 * Compatibility window: none — the response body already only ever claimed `status: 'received'`,
 * so no client-visible contract changes; only the internal delivery mechanism changed.
 */
class IngestionController extends Controller
{
    public function store(StoreRawIngestionEventRequest $request): JsonResponse
    {
        $startTime = microtime(true);

        $context = [
            'ip' => $request->ip(),
            'method' => $request->method(),
            'path' => $request->path(),
            'request_id' => $request->header('X-Request-Id', uniqid()),
            'user_id' => auth()->id(),
        ];

        $validated = $request->validated();
        $nodeId = data_get($validated, 'payload.device.node_id');
        $source = $validated['source'] ?? 'ingestion_service';
        $sourceEventId = $validated['source_event_id'] ?? null;

        Log::info('Ingestion store request received', $context + [
            'payload_keys' => array_keys($validated['payload'] ?? []),
        ]);

        try {
            [$event, $duplicate] = DB::transaction(function () use ($validated, $nodeId, $source, $sourceEventId): array {
                $attributes = [
                    'topic' => $validated['topic'] ?? null,
                    'source' => $source,
                    'source_event_id' => $sourceEventId,
                    'node_id' => is_string($nodeId) && $nodeId !== '' ? $nodeId : null,
                    'payload' => $validated['payload'],
                    'received_at' => $validated['received_at'] ?? null,
                    'status' => 'received',
                ];

                if (is_string($sourceEventId) && $sourceEventId !== '') {
                    $event = RawSensorEvent::query()->firstOrCreate([
                        'source' => $source,
                        'source_event_id' => $sourceEventId,
                    ], $attributes);
                } else {
                    $event = RawSensorEvent::create($attributes);
                }

                if ($event->wasRecentlyCreated) {
                    RawEventOutbox::create([
                        'raw_sensor_event_id' => $event->id,
                        'status' => 'pending',
                    ]);
                }

                return [$event, ! $event->wasRecentlyCreated];
            });
        } catch (UniqueConstraintViolationException $e) {
            $event = $this->isRawSensorEventIdentityConstraintViolation($e)
                && is_string($sourceEventId) && $sourceEventId !== ''
                ? RawSensorEvent::query()->where([
                    'source' => $source,
                    'source_event_id' => $sourceEventId,
                ])->first()
                : null;

            if ($event !== null) {
                $duplicate = true;
            } else {
                Log::error('Ingestion store transaction error', $context + [
                    'exception' => $e->getMessage(),
                ]);

                return response()->json([
                    'error' => 'Transaction error',
                    'message' => 'No fue posible almacenar el evento de sensor.',
                ], 500);
            }
        } catch (Throwable $e) {
            Log::error('Ingestion store transaction error', $context + [
                'exception' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Transaction error',
                'message' => 'No fue posible almacenar el evento de sensor.',
            ], 500);
        }

        // Gate 10: no wake-up dispatch. The committed RawEventOutbox row is captured from the MySQL
        // binlog by Debezium and drained by `cdc:consume-outboxes`. Delivery no longer depends on
        // any in-request dispatch or periodic DB discovery loop.
        $durationMs = round((microtime(true) - $startTime) * 1000, 2);

        Log::info('Ingestion store success', $context + [
            'event_id' => $event->id,
            'success' => true,
            'duplicate' => $duplicate,
            'duration_ms' => $durationMs,
        ]);

        return response()->json([
            'message' => 'Raw sensor event stored successfully',
            'event_id' => $event->id,
            // Honest receipt-vs-published status (PLAN.md Stage 3.1): this has only ever meant
            // "the receipt was durably persisted", never "published to Redis".
            'status' => $event->status,
            'duplicate' => $duplicate,
        ], $duplicate ? 200 : 201);
    }

    private function isRawSensorEventIdentityConstraintViolation(UniqueConstraintViolationException $exception): bool
    {
        $message = $exception->getMessage();

        return str_contains($message, 'raw_sensor_events_source_source_event_id_unique')
            || str_contains($message, 'UNIQUE constraint failed: raw_sensor_events.source, raw_sensor_events.source_event_id');
    }
}
