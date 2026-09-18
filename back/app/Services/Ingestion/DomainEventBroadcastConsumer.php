<?php

namespace App\Services\Ingestion;

use App\Events\AlertResolved;
use App\Events\DeviceStatusUpdated;
use App\Events\NewAlertTriggered;
use App\Events\NewSensorReading;
use App\Models\DomainEventOutbox;
use App\Models\Sensor;
use App\Models\SensorReading;
use App\Services\Ingestion\Concerns\UsesRawRedisCommands;
use App\Services\Monitoring\EventPipelineMetricsService;
use App\Services\Monitoring\PublicGraphVisibility;
use Illuminate\Redis\Connections\Connection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * PLAN.md Stage 4.1/4.3/4.4 — `browser-delivery-v1` consumer group over `iot.domain-events`.
 * ADR-4: direct stream consumer -> Pusher-compatible broadcaster (no Redis Pub/Sub hop).
 *
 * Existing code reused: this class's stream mechanics (ensureGroup/reclaim/read/XACK/XPENDING/DLQ,
 * the `raw()` client-agnostic helper via `UsesRawRedisCommands`) are a direct structural mirror of
 * `App\Services\Ingestion\RawStreamConsumer` (Stage 3) — same at-least-once + lease-recovery +
 * poison-message contract, applied to a different stream/business row. It never duplicates that
 * mechanics; only "what a message means" differs (broadcast a domain fact instead of normalizing a
 * reading).
 * Existing owner retired/delegated: this IS the new owner of "turn a durable domain fact into a
 * browser broadcast" — before this stage, `NewAlertTriggered`/`DeviceStatusUpdated` were either
 * broadcast synchronously in-request (alert triggered) or never dispatched at all (device status).
 * Compatibility window: n/a — alert.resolved/device.status.changed had no broadcast path before.
 *
 * Dedup: unlike `RawStreamConsumer` (which reuses `raw_sensor_events.status` as its ledger because
 * only one consumer group exists), this table's `status` column is already the *relay's* own
 * pending/publishing/published state, so this consumer uses the separate `delivered_at` column on
 * the same `DomainEventOutbox` row as its idempotency marker. See the outbox migration's ponytail
 * note for the upgrade path once a second consumer group is approved (Stage 8).
 */
class DomainEventBroadcastConsumer
{
    use UsesRawRedisCommands;

    public const MAX_ATTEMPTS = 5;

    public function __construct(
        private Connection $connection,
        private string $stream,
        private string $group,
        private string $deadLetterStream,
        private PublicGraphVisibility $publicVisibility = new PublicGraphVisibility(),
    ) {
    }

    public function ensureGroup(): void
    {
        try {
            $this->raw($this->connection, ['XGROUP', 'CREATE', $this->stream, $this->group, '0', 'MKSTREAM']);
        } catch (Throwable $e) {
            if (! str_contains($e->getMessage(), 'BUSYGROUP')) {
                throw $e;
            }
        }
    }

    /**
     * @return array{acked:int,dlq:int,pending:int}
     */
    public function runOnce(string $consumerName, int $batch, int $blockMs, int $claimIdleMs = 30000): array
    {
        Log::info('DomainEventBroadcastConsumer:runOnce entry', [
            'consumer' => $consumerName,
            'batch' => $batch,
            'block_ms' => $blockMs,
        ]);

        $startTime = microtime(true);
        $this->ensureGroup();

        $stats = ['acked' => 0, 'dlq' => 0, 'pending' => 0];

        foreach ($this->reclaimPending($consumerName, $batch, $claimIdleMs) as [$id, $fields]) {
            $stats[$this->handleMessage($id, $fields)]++;
        }

        foreach ($this->readBatch($consumerName, $batch, $blockMs) as [$id, $fields]) {
            $stats[$this->handleMessage($id, $fields)]++;
        }

        $durationMs = round((microtime(true) - $startTime) * 1000, 2);
        Log::info('DomainEventBroadcastConsumer:runOnce completed', array_merge($stats, ['duration_ms' => $durationMs]));

        if ($durationMs > 100) {
            Log::warning('DomainEventBroadcastConsumer:runOnce slow execution', ['duration_ms' => $durationMs]);
        }

        return $stats;
    }

    /**
     * @return list<array{0:string,1:array<string,string>}>
     */
    public function reclaimPending(string $consumerName, int $count, int $idleMs): array
    {
        $reply = $this->raw($this->connection, [
            'XAUTOCLAIM', $this->stream, $this->group, $consumerName,
            (string) $idleMs, '0-0', 'COUNT', (string) $count,
        ]);

        $entries = is_array($reply) ? ($reply[1] ?? []) : [];

        return $this->normalizeEntries($entries);
    }

    /**
     * @return list<array{0:string,1:array<string,string>}>
     */
    public function readBatch(string $consumerName, int $count, int $blockMs): array
    {
        $reply = $this->raw($this->connection, [
            'XREADGROUP', 'GROUP', $this->group, $consumerName,
            'COUNT', (string) $count, 'BLOCK', (string) $blockMs,
            'STREAMS', $this->stream, '>',
        ]);

        if (! is_array($reply) || $reply === []) {
            return [];
        }

        $entries = $reply[0][1] ?? [];

        return $this->normalizeEntries($entries);
    }

    /**
     * @param  array{0:string,1:array<string,string>}  $entries
     * @return list<array{0:string,1:array<string,string>}>
     */
    private function normalizeEntries(array $entries): array
    {
        return self::normalizeStreamEntries($entries);
    }

    /**
     * @param  array<string,string|null>  $fields
     * @return 'acked'|'dlq'|'pending'
     */
    private function handleMessage(string $id, array $fields): string
    {
        $outboxId = $fields['event_id'] ?? null;

        if (! is_string($outboxId) || $outboxId === '' || ! ctype_digit($outboxId)) {
            $this->deadLetter($id, $fields, 'malformed_message: missing or invalid event_id', $this->deliveryCount($id));
            $this->ack($id);
            EventPipelineMetricsService::increment('domain_broadcast_failure');

            return 'dlq';
        }

        $outbox = DomainEventOutbox::query()->find((int) $outboxId);

        if (! $outbox) {
            $this->deadLetter($id, $fields, 'unknown_domain_event_outbox', $this->deliveryCount($id));
            $this->ack($id);
            EventPipelineMetricsService::increment('domain_broadcast_failure');

            return 'dlq';
        }

        if ($outbox->delivered_at !== null) {
            // Idempotent fast-path skip — already broadcast by a previous delivery of this message.
            $this->ack($id);

            return 'acked';
        }

        try {
            // Step 1: broadcast first (read-only, no DB lock held).
            // If this throws, the message stays unacked for retry — no data loss.
            $this->broadcastFact($outbox);
            EventPipelineMetricsService::increment('domain_broadcast_success');

            // Step 2: claim delivery atomically (mark delivered_at under lock) then XACK.
            // The XACK must follow the broadcast so that a broadcast failure leaves the
            // message pending for reprocessing (at-least-once delivery).
            $outcome = DB::transaction(function () use ($outbox): string {
                $locked = DomainEventOutbox::query()->lockForUpdate()->find($outbox->id);

                if (! $locked || $locked->delivered_at !== null) {
                    return 'skip';
                }

                $locked->forceFill(['delivered_at' => now()])->save();

                return 'delivered';
            });

            $this->ack($id);

            return 'acked';
        } catch (Throwable $e) {
            $attempts = $this->deliveryCount($id);

            Log::warning('DomainEventBroadcastConsumer: message processing failed', [
                'stream_id' => $id,
                'outbox_id' => $outbox->id,
                'event_type' => $outbox->event_type,
                'attempts' => $attempts,
                'exception' => $e->getMessage(),
            ]);

            if ($attempts >= self::MAX_ATTEMPTS) {
                $this->deadLetter($id, $fields, 'max_attempts_exceeded: '.$e->getMessage(), $attempts);
                $this->ack($id);
                EventPipelineMetricsService::increment('domain_broadcast_failure');

                return 'dlq';
            }

            // Leave unacked: reclaimed again once claim-idle elapses (bounded backoff), same as
            // RawStreamConsumer. On reclaim, delivered_at is null so broadcast will be retried.
            return 'pending';
        }
    }

    /**
     * Reconstructs the aggregate and dispatches the matching versioned broadcast event. This is
     * the ADR-4 "direct" fan-out step: no Redis Pub/Sub, `event()` hands straight to the configured
     * broadcaster (Pusher-compatible) because both events implement `ShouldBroadcastNow`.
     *
     * ponytail: a `match` on `event_type` is the whole registry. Add a case here (not a new
     * consumer/dispatcher class) when a third domain fact needs browser delivery.
     */
    private function broadcastFact(DomainEventOutbox $outbox): void
    {
        match ($outbox->event_type) {
            'alert.triggered' => $this->broadcastAlertTriggered($outbox),
            'alert.resolved' => $this->broadcastAlertResolved($outbox),
            'device.status.changed' => $this->broadcastDeviceStatusChanged($outbox),
            'sensor.reading.created' => $this->broadcastSensorReadingCreated($outbox),
            default => throw new \RuntimeException("unknown domain event_type [{$outbox->event_type}]"),
        };
    }

    private function broadcastAlertTriggered(DomainEventOutbox $outbox): void
    {
        $payload = $outbox->payload;
        $value = data_get($payload, 'value');
        $timestamp = data_get($payload, 'timestamp');

        $event = new NewAlertTriggered(
            alertId: (int) data_get($payload, 'alert_id'),
            message: (string) data_get($payload, 'message', 'Alerta generada'),
            severity: (string) data_get($payload, 'severity', 'warning'),
            value: $value !== null ? (float) $value : null,
            sensorName: (string) data_get($payload, 'sensor_name', 'Sensor desconocido'),
            sensorType: (string) data_get($payload, 'sensor_type', ''),
            unit: (string) data_get($payload, 'unit', ''),
            deviceName: (string) data_get($payload, 'device_name', 'Dispositivo desconocido'),
            labName: (string) data_get($payload, 'lab_name', 'Lab no definido'),
            timestamp: $timestamp !== null ? (string) $timestamp : null,
        );

        event($this->seedFromOutbox($event, $outbox));
    }

    private function broadcastAlertResolved(DomainEventOutbox $outbox): void
    {
        $payload = $outbox->payload;
        $resolvedAt = data_get($payload, 'resolved_at');

        $event = new AlertResolved(
            alertId: (int) data_get($payload, 'alert_id'),
            resolved: (bool) data_get($payload, 'resolved', true),
            resolvedAt: $resolvedAt !== null ? (string) $resolvedAt : null,
        );

        event($this->seedFromOutbox($event, $outbox));
    }

    private function broadcastDeviceStatusChanged(DomainEventOutbox $outbox): void
    {
        // Gate 8: the fact is fully self-contained in the outbox payload (captured at write time
        // by DeviceService::changeStatus()) — no `Device::find()` re-read here. Re-reading the
        // current row would let a later transition's status leak into an earlier fact's broadcast.
        $payload = $outbox->payload;

        $event = new DeviceStatusUpdated(
            deviceId: (int) data_get($payload, 'device_id'),
            status: (bool) data_get($payload, 'status'),
            isActive: (bool) data_get($payload, 'is_active'),
            changedAt: (string) data_get($payload, 'changed_at'),
            eventSequence: $outbox->id,
        );

        event($this->seedFromOutbox($event, $outbox));
    }

    private function broadcastSensorReadingCreated(DomainEventOutbox $outbox): void
    {
        $payload = $outbox->payload;
        $readingId = data_get($payload, 'reading_id');

        // P1 (durable self-contained event): prefer the live row when present (freshest relations),
        // but if it has since been deleted, reconstruct the reading + its sensor/type/device/lab
        // entirely from the immutable outbox payload so the fact is STILL delivered. A vanished
        // SensorReading must not silently drop a durable event.
        $reading = SensorReading::query()
            ->with(['sensor.sensorType', 'sensor.device.lab'])
            ->find($readingId);

        $publicAtOccurrence = data_get($payload, 'public_at_occurrence');

        if (! $reading) {
            // Legacy payloads (written before this field existed) can't be reconstructed and have no
            // recorded audience — fall back to the old behavior of skipping, since we can neither
            // rebuild the metadata nor honor event-time visibility.
            if (! array_key_exists('sensor_name', $payload)) {
                Log::warning('DomainEventBroadcastConsumer: sensor.reading.created target gone and payload is pre-enrichment', [
                    'outbox_id' => $outbox->id,
                    'reading_id' => $readingId,
                ]);

                return;
            }

            $reading = $this->hydrateReadingFromPayload($payload);
        }

        // Event-time audience: use the decision captured when the reading occurred
        // (`public_at_occurrence`). Only for legacy payloads that predate the field do we fall back
        // to the current-time `PublicGraphVisibility::isPublic()` decision.
        $includePublic = $publicAtOccurrence === null
            ? $this->publicVisibility->isPublic($reading->sensor)
            : (bool) $publicAtOccurrence;

        $event = new NewSensorReading(
            $reading,
            includePublicChannel: $includePublic,
            includePrivateChannel: true,
        );

        event($this->seedFromOutbox($event, $outbox));
    }

    /**
     * Rebuild an in-memory (never persisted) SensorReading and its sensor/type/device/lab relations
     * purely from the enriched outbox payload, so NewSensorReading::broadcastWith() can render the
     * event even though the original SensorReading row is gone. Nothing here touches the DB.
     *
     * @param  array<string,mixed>  $payload
     */
    private function hydrateReadingFromPayload(array $payload): SensorReading
    {
        $sensorType = new \App\Models\SensorType([
            'name' => data_get($payload, 'sensor_type'),
            'unit' => data_get($payload, 'unit'),
        ]);

        $lab = new \App\Models\Lab(['name' => data_get($payload, 'lab_name')]);
        $lab->id = data_get($payload, 'lab_id');

        $device = new \App\Models\Device(['name' => data_get($payload, 'device_name')]);
        $device->id = data_get($payload, 'device_id');
        $device->setRelation('lab', $lab);

        $sensor = new Sensor(['name' => data_get($payload, 'sensor_name')]);
        $sensor->id = data_get($payload, 'sensor_id');
        $sensor->setRelation('sensorType', $sensorType);
        $sensor->setRelation('device', $device);

        $reading = new SensorReading([
            'value' => data_get($payload, 'value'),
            'reading_time' => data_get($payload, 'reading_time'),
        ]);
        $reading->id = data_get($payload, 'reading_id');
        $reading->sensor_id = data_get($payload, 'sensor_id');
        $reading->setRelation('sensor', $sensor);

        return $reading;
    }

    /**
     * PENDING CI EXECUTION — Fix 3 (persistent event-envelope identity on retry). Every
     * broadcastXxx() method above constructs a brand-new event object per delivery attempt; without
     * this, a redelivered message (crash between broadcast success and delivered_at/XACK, then
     * XAUTOCLAIM reclaim) would emit a fresh `event_id`/`occurred_at`/`correlation_id` for the same
     * logical fact (App\Events\Concerns\HasEventEnvelope lazily generates all three with `??=` on
     * first read). The `DomainEventOutbox` row is the stable identity already used by the
     * raw/domain outbox publishers (`$outbox->id`, `$outbox->created_at`) — this is the single
     * place that seeds the SAME identity into the broadcast event, so a retried broadcast is
     * provably the same event_id, occurred_at AND correlation_id as the first attempt (task 7a).
     * `correlation_id` is passed the same `$outbox->id` as `event_id` — see
     * `HasEventEnvelope::seedEnvelope()`'s ponytail note for why, and the upgrade path if a real
     * cross-event correlation value is ever needed.
     */
    private function seedFromOutbox(object $event, DomainEventOutbox $outbox): object
    {
        return $event->seedEnvelope(
            (string) $outbox->id,
            ($outbox->created_at ?? now())->toIso8601String(),
            (string) $outbox->id,
        );
    }

    private function ack(string $id): void
    {
        $this->raw($this->connection, ['XACK', $this->stream, $this->group, $id]);
    }

    /**
     * @param  array<string,string|null>  $fields
     */
    private function deadLetter(string $id, array $fields, string $reason, int $attempts): void
    {
        $this->rawXadd($this->connection, $this->deadLetterStream, (int) config('app.dlq_maxlen', 1000000), [
            'orig_stream' => $this->stream,
            'orig_id' => $id,
            'reason' => $reason,
            'event_id' => (string) ($fields['event_id'] ?? ''),
            'event_type' => (string) ($fields['event_type'] ?? ''),
            'attempts' => (string) $attempts,
            'payload_json' => json_encode($fields, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
            'failed_at' => now()->toIso8601String(),
        ]);
    }

    private function deliveryCount(string $id): int
    {
        try {
            $reply = $this->raw($this->connection, ['XPENDING', $this->stream, $this->group, $id, $id, 1]);
        } catch (Throwable $e) {
            Log::warning('DomainEventBroadcastConsumer:deliveryCount XPENDING failed', [
                'stream_id' => $id,
                'exception' => $e->getMessage(),
            ]);
            return 1;
        }

        if (! is_array($reply) || ! isset($reply[0]) || ! is_array($reply[0])) {
            return 1;
        }

        return (int) ($reply[0][3] ?? 1);
    }
}
