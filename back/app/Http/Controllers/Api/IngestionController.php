<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreRawIngestionEventRequest;
use App\Jobs\RelayRawOutboxJob;
use App\Models\RawEventOutbox;
use App\Models\RawSensorEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * PLAN.md Stage 3.1 / docs/implementation/adr-g1.md ADR-1 (transactional outbox).
 *
 * Existing code reused: `RawSensorEvent` (business row, unchanged shape).
 * Existing owner retired/delegated: this controller no longer calls
 * `RawSensorEventPublisher::publish()` synchronously (G0D row B6) — that dual-write gap (201
 * returned even when XADD failed) is closed by never depending on a synchronous publish here at
 * all. Publishing is now owned solely by `App\Services\Ingestion\RawOutboxRelay`, invoked via the
 * `RelayRawOutboxJob` wake-up hint and the `ingestion:relay-outbox` discovery loop.
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

        Log::info('Ingestion store request received', $context + [
            'payload_keys' => array_keys($validated['payload'] ?? []),
        ]);

        $event = DB::transaction(function () use ($validated, $nodeId): RawSensorEvent {
            $event = RawSensorEvent::create([
                'topic' => $validated['topic'] ?? null,
                'source' => $validated['source'] ?? 'ingestion_service',
                'source_event_id' => $validated['source_event_id'] ?? null,
                'node_id' => is_string($nodeId) && $nodeId !== '' ? $nodeId : null,
                'payload' => $validated['payload'],
                'received_at' => $validated['received_at'] ?? null,
                'status' => 'received',
            ]);

            RawEventOutbox::create([
                'raw_sensor_event_id' => $event->id,
                'status' => 'pending',
            ]);

            return $event;
        });

        // Low-latency wake-up hint only (ADR-1). If this dispatch is lost — process crash, queue
        // outage — the row is not stranded: `ingestion:relay-outbox`'s durable discovery loop
        // claims lease-expired/pending rows independently of this hint ever firing.
        RelayRawOutboxJob::dispatch()->afterCommit();

        $durationMs = round((microtime(true) - $startTime) * 1000, 2);

        Log::info('Ingestion store success', $context + [
            'event_id' => $event->id,
            'success' => true,
            'duration_ms' => $durationMs,
        ]);

        return response()->json([
            'message' => 'Raw sensor event stored successfully',
            'event_id' => $event->id,
            // Honest receipt-vs-published status (PLAN.md Stage 3.1): this has only ever meant
            // "the receipt was durably persisted", never "published to Redis".
            'status' => $event->status,
        ], 201);
    }
}
