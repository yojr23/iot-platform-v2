<?php

namespace App\Services\Ingestion;

use App\Events\AlertResolved;
use App\Events\DeviceStatusUpdated;
use App\Events\NewAlertTriggered;
use App\Events\NewSensorReading;
use App\Models\Alert;
use App\Models\DomainEventOutbox;
use App\Models\SensorReading;
use App\Services\Ingestion\Concerns\UsesRawRedisCommands;
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
        $this->ensureGroup();

        $stats = ['acked' => 0, 'dlq' => 0, 'pending' => 0];

        foreach ($this->reclaimPending($consumerName, $batch, $claimIdleMs) as [$id, $fields]) {
            $stats[$this->handleMessage($id, $fields)]++;
        }

        foreach ($this->readBatch($consumerName, $batch, $blockMs) as [$id, $fields]) {
            $stats[$this->handleMessage($id, $fields)]++;
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
        $out = [];

        foreach ($entries as $entry) {
            [$id, $flat] = $entry;

            if ($id === null) {
                continue;
            }

            $fields = [];
            $flat = is_array($flat) ? $flat : [];

            for ($i = 0; $i < count($flat); $i += 2) {
                $fields[$flat[$i]] = $flat[$i + 1] ?? null;
            }

            $out[] = [$id, $fields];
        }

        return $out;
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

            return 'dlq';
        }

        $outbox = DomainEventOutbox::query()->find((int) $outboxId);

        if (! $outbox) {
            $this->deadLetter($id, $fields, 'unknown_domain_event_outbox', $this->deliveryCount($id));
            $this->ack($id);

            return 'dlq';
        }

        if ($outbox->delivered_at !== null) {
            // Idempotent fast-path skip — already broadcast by a previous delivery of this message.
            $this->ack($id);

            return 'acked';
        }

        try {
            $outcome = DB::transaction(function () use ($outbox): string {
                // Same concurrent-safe idempotency pattern as RawStreamConsumer: re-read FOR UPDATE
                // so only one of two concurrent redeliveries wins the "undelivered -> delivered"
                // transition.
                $locked = DomainEventOutbox::query()->lockForUpdate()->find($outbox->id);

                if (! $locked || $locked->delivered_at !== null) {
                    return 'skip';
                }

                $this->broadcastFact($locked);

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

                return 'dlq';
            }

            // Leave unacked: reclaimed again once claim-idle elapses (bounded backoff), same as
            // RawStreamConsumer.
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
        $alertId = data_get($outbox->payload, 'alert_id');
        $alert = Alert::query()->with(['sensorReading.sensor.sensorType', 'sensorReading.sensor.device.lab', 'alertRule'])
            ->find($alertId);

        if (! $alert) {
            Log::info('DomainEventBroadcastConsumer: alert.triggered target no longer exists', [
                'outbox_id' => $outbox->id,
                'alert_id' => $alertId,
            ]);

            return;
        }

        event(new NewAlertTriggered($alert));
    }

    private function broadcastAlertResolved(DomainEventOutbox $outbox): void
    {
        $alertId = data_get($outbox->payload, 'alert_id');
        $alert = Alert::query()->with(['sensorReading.sensor.sensorType', 'sensorReading.sensor.device.lab', 'alertRule'])
            ->find($alertId);

        if (! $alert) {
            // Alert was deleted after resolution — nothing meaningful left to broadcast, but the
            // outbox row is still marked delivered (this is a legitimate terminal outcome, not a
            // retryable failure).
            Log::info('DomainEventBroadcastConsumer: alert.resolved target no longer exists', [
                'outbox_id' => $outbox->id,
                'alert_id' => $alertId,
            ]);

            return;
        }

        event(new AlertResolved($alert));
    }

    private function broadcastDeviceStatusChanged(DomainEventOutbox $outbox): void
    {
        // Gate 8: the fact is fully self-contained in the outbox payload (captured at write time
        // by DeviceService::changeStatus()) — no `Device::find()` re-read here. Re-reading the
        // current row would let a later transition's status leak into an earlier fact's broadcast.
        $payload = $outbox->payload;

        event(new DeviceStatusUpdated(
            deviceId: (int) data_get($payload, 'device_id'),
            status: (bool) data_get($payload, 'status'),
            isActive: (bool) data_get($payload, 'is_active'),
            changedAt: (string) data_get($payload, 'changed_at'),
            eventSequence: $outbox->id,
        ));
    }

    private function broadcastSensorReadingCreated(DomainEventOutbox $outbox): void
    {
        $readingId = data_get($outbox->payload, 'reading_id');
        $reading = SensorReading::query()
            ->with(['sensor.sensorType', 'sensor.device.lab'])
            ->find($readingId);

        if (! $reading) {
            Log::info('DomainEventBroadcastConsumer: sensor.reading.created target no longer exists', [
                'outbox_id' => $outbox->id,
                'reading_id' => $readingId,
            ]);

            return;
        }

        // Stage 6.0: this is the real dispatch site, so the audience decision lives here (not
        // inside the event, which stays fail-closed and query-free). The public flag is now the
        // explicit, fail-closed `PublicGraphVisibility::isPublic()` decision instead of a literal
        // `true` — a restricted sensor's queued fact still gets acked/delivered (terminal success),
        // it just never reaches the public channel. The private channel is unaffected: authorized
        // viewers of a restricted sensor keep realtime via the private `sensor.{id}` channel added
        // in preflight.
        event(new NewSensorReading(
            $reading,
            includePublicChannel: $this->publicVisibility->isPublic($reading->sensor),
            includePrivateChannel: true,
        ));
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
        $this->raw($this->connection, [
            'XADD', $this->deadLetterStream, '*',
            'orig_id', $id,
            'reason', $reason,
            'event_id', (string) ($fields['event_id'] ?? ''),
            'event_type', (string) ($fields['event_type'] ?? ''),
            'attempts', (string) $attempts,
        ]);
    }

    private function deliveryCount(string $id): int
    {
        try {
            $reply = $this->raw($this->connection, ['XPENDING', $this->stream, $this->group, $id, $id, 1]);
        } catch (Throwable) {
            return 1;
        }

        if (! is_array($reply) || ! isset($reply[0]) || ! is_array($reply[0])) {
            return 1;
        }

        return (int) ($reply[0][3] ?? 1);
    }
}
