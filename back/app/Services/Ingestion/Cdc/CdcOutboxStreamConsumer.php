<?php

namespace App\Services\Ingestion\Cdc;

use App\Models\DomainEventOutbox;
use App\Models\RawEventOutbox;
use App\Services\Ingestion\Concerns\UsesRawRedisCommands;
use App\Services\Ingestion\DomainEventPublisher;
use App\Services\Ingestion\RawSensorEventPublisher;
use App\Services\Monitoring\EventPipelineMetricsService;
use Illuminate\Redis\Connections\Connection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Gate 10 (PLAN.md Stage 10 / docs/implementation/adr-g1.md ADR-G1) — the SINGLE final publication
 * owner between committed outbox rows and the two existing application streams.
 *
 * Replaces the two periodic `SELECT pending` outbox relays (`RawOutboxRelay`, `DomainOutboxRelay`).
 * Instead of polling the DB, it consumes MySQL binlog changes that Debezium Server has already
 * captured and written onto dedicated Redis CDC streams:
 *   - iot-cdc.<db>.raw_event_outboxes    -> RawSensorEventPublisher::publish(RawSensorEvent)
 *   - iot-cdc.<db>.domain_event_outboxes -> DomainEventPublisher::publish(DomainEventOutbox)
 *
 * Both existing publishers are reused unchanged; the `iot.raw-events` / `iot.domain-events`
 * envelopes are untouched, so all downstream consumers keep working.
 *
 * Work model (per iteration, no sleep, no DB discovery query):
 *   XAUTOCLAIM raw CDC stream (lease recovery)
 *   XAUTOCLAIM domain CDC stream (lease recovery)
 *   XREADGROUP BLOCK both CDC streams (one blocking read)
 *   for each entry: publish -> mark outbox published -> XACK
 *
 * Idempotency: a crash after the application XADD but before the outbox status commit can redeliver
 * the CDC entry, producing a physical duplicate on `iot.raw-events` / `iot.domain-events`. That is
 * accepted at-least-once behaviour — the raw consumer dedupes by `raw_sensor_events.status` and the
 * domain broadcast consumer dedupes by `delivered_at`, exactly as under the old relays.
 */
class CdcOutboxStreamConsumer
{
    use UsesRawRedisCommands;

    public const MAX_ATTEMPTS = 5;

    private const KIND_RAW = 'raw';
    private const KIND_DOMAIN = 'domain';

    public function __construct(
        private Connection $connection,
        private RawSensorEventPublisher $rawPublisher,
        private DomainEventPublisher $domainPublisher,
        private string $rawCdcStream,
        private string $domainCdcStream,
        private string $group,
        private string $deadLetterStream,
    ) {
    }

    public function ensureGroups(): void
    {
        foreach ([$this->rawCdcStream, $this->domainCdcStream] as $stream) {
            try {
                // Start at 0 + MKSTREAM: Debezium may not have created the CDC stream yet on first
                // boot, and a pre-existing backlog captured before this group existed must still be
                // processed. Already-acked entries are not redelivered (group cursor advances past
                // them), so 0 is safe for an existing group too. BUSYGROUP is ignored.
                $this->raw($this->connection, ['XGROUP', 'CREATE', $stream, $this->group, '0', 'MKSTREAM']);
            } catch (Throwable $e) {
                if (! str_contains($e->getMessage(), 'BUSYGROUP')) {
                    throw $e;
                }
            }
        }
    }

    /**
     * One bounded iteration: reclaim idle-pending on both CDC streams (lease recovery), then one
     * blocking read across both. Safe to call in a tight loop; BLOCK is the backpressure.
     *
     * @return array{acked:int,dlq:int,pending:int}
     */
    public function runOnce(string $consumerName, int $batch, int $blockMs, int $claimIdleMs = 30000): array
    {
        Log::info('CdcOutboxStreamConsumer:runOnce entry', [
            'consumer' => $consumerName,
            'batch' => $batch,
            'block_ms' => $blockMs,
        ]);

        $startTime = microtime(true);
        $this->ensureGroups();

        $stats = ['acked' => 0, 'dlq' => 0, 'pending' => 0];

        foreach ([self::KIND_RAW => $this->rawCdcStream, self::KIND_DOMAIN => $this->domainCdcStream] as $kind => $stream) {
            foreach ($this->reclaimPending($stream, $consumerName, $batch, $claimIdleMs) as [$id, $fields]) {
                $stats[$this->handleMessage($kind, $stream, $id, $fields)]++;
            }
        }

        foreach ($this->readBatch($consumerName, $batch, $blockMs) as [$kind, $stream, $id, $fields]) {
            $stats[$this->handleMessage($kind, $stream, $id, $fields)]++;
        }

        $durationMs = round((microtime(true) - $startTime) * 1000, 2);
        Log::info('CdcOutboxStreamConsumer:runOnce completed', array_merge($stats, ['duration_ms' => $durationMs]));

        return $stats;
    }

    /**
     * @return list<array{0:string,1:array<string,string>}>
     */
    public function reclaimPending(string $stream, string $consumerName, int $count, int $idleMs): array
    {
        $reply = $this->raw($this->connection, [
            'XAUTOCLAIM', $stream, $this->group, $consumerName,
            (string) $idleMs, '0-0', 'COUNT', (string) $count,
        ]);

        // XAUTOCLAIM reply: [cursor, [[id, [k,v,...]], ...], [deleted-ids]]
        $entries = is_array($reply) ? ($reply[1] ?? []) : [];

        return $this->normalizeEntries($entries);
    }

    /**
     * One blocking read across both CDC streams. Returns entries tagged with their stream kind so
     * the caller routes each to the correct publisher.
     *
     * @return list<array{0:string,1:string,2:string,3:array<string,string>}>  [kind, stream, id, fields]
     */
    public function readBatch(string $consumerName, int $count, int $blockMs): array
    {
        $reply = $this->raw($this->connection, [
            'XREADGROUP', 'GROUP', $this->group, $consumerName,
            'COUNT', (string) $count, 'BLOCK', (string) $blockMs,
            'STREAMS', $this->rawCdcStream, $this->domainCdcStream, '>', '>',
        ]);

        if (! is_array($reply) || $reply === []) {
            return [];
        }

        $out = [];

        // XREADGROUP reply: [[stream, [[id, [k,v,...]], ...]], [stream2, [...]]]
        foreach ($reply as $streamBlock) {
            if (! is_array($streamBlock) || count($streamBlock) < 2) {
                continue;
            }

            [$streamName, $entries] = $streamBlock;
            $kind = ((string) $streamName === $this->domainCdcStream) ? self::KIND_DOMAIN : self::KIND_RAW;

            foreach ($this->normalizeEntries(is_array($entries) ? $entries : []) as [$id, $fields]) {
                $out[] = [$kind, (string) $streamName, $id, $fields];
            }
        }

        return $out;
    }

    /**
     * @param  array<int,mixed>  $entries
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
    private function handleMessage(string $kind, string $stream, string $id, array $fields): string
    {
        // Parse the CDC envelope. A malformed/incomplete envelope can never be fixed by retrying —
        // quarantine it and ack so it does not wedge the stream.
        try {
            $change = DebeziumChange::fromRedisFields($fields);
        } catch (Throwable $e) {
            $this->deadLetter($stream, $id, 'malformed_cdc_message: '.$e->getMessage(), $this->deliveryCount($stream, $id));
            $this->ack($stream, $id);

            return 'dlq';
        }

        // Only inserts (op c) and snapshot reads (op r) carry a fresh outbox row to publish.
        // Updates (e.g. our own "mark published" write) and deletes are ignored — ack and move on.
        if (! $change->isInsertOrSnapshot()) {
            $this->ack($stream, $id);

            return 'acked';
        }

        $outboxId = isset($change->after['id']) ? (int) $change->after['id'] : 0;

        if ($outboxId <= 0) {
            $this->deadLetter($stream, $id, 'cdc_after_missing_id', $this->deliveryCount($stream, $id));
            $this->ack($stream, $id);

            return 'dlq';
        }

        // Fast-path skip for a row already marked published in the CDC after-image: no publish, ack.
        // (The old crashed-relay `publishing` status is NOT skipped — it is republished, below.)
        $afterStatus = (string) ($change->after['status'] ?? '');
        if ($afterStatus === 'published') {
            $this->ack($stream, $id);

            return 'acked';
        }

        return $kind === self::KIND_DOMAIN
            ? $this->publishDomain($stream, $id, $outboxId)
            : $this->publishRaw($stream, $id, $outboxId);
    }

    private function publishRaw(string $stream, string $id, int $outboxId): string
    {
        $outbox = RawEventOutbox::query()->with('rawSensorEvent')->find($outboxId);

        if (! $outbox) {
            $this->deadLetter($stream, $id, 'unknown_raw_event_outbox:'.$outboxId, $this->deliveryCount($stream, $id));
            $this->ack($stream, $id);

            return 'dlq';
        }

        // Authoritative row won a "publishing"/published race elsewhere — skip, ack.
        if ($outbox->status === 'published') {
            $this->ack($stream, $id);

            return 'acked';
        }

        if (! $outbox->rawSensorEvent) {
            $outbox->forceFill(['status' => 'failed', 'last_error' => 'raw_sensor_event missing', 'locked_until' => null])->save();
            $this->deadLetter($stream, $id, 'raw_sensor_event_missing:'.$outboxId, $this->deliveryCount($stream, $id));
            $this->ack($stream, $id);
            EventPipelineMetricsService::increment('cdc_dlq');

            return 'dlq';
        }

        return $this->finish(
            $stream,
            $id,
            fn (): bool => $this->rawPublisher->publish($outbox->rawSensorEvent),
            function () use ($outbox): void {
                $outbox->forceFill([
                    'status' => 'published',
                    'published_at' => Carbon::now(),
                    'locked_until' => null,
                    'last_error' => null,
                ])->save();
            },
            fn () => $outbox->forceFill(['attempts' => (int) $outbox->attempts + 1, 'last_error' => 'cdc publish() returned false'])->save(),
        );
    }

    private function publishDomain(string $stream, string $id, int $outboxId): string
    {
        $outbox = DomainEventOutbox::query()->find($outboxId);

        if (! $outbox) {
            $this->deadLetter($stream, $id, 'unknown_domain_event_outbox:'.$outboxId, $this->deliveryCount($stream, $id));
            $this->ack($stream, $id);

            return 'dlq';
        }

        if ($outbox->status === 'published') {
            $this->ack($stream, $id);

            return 'acked';
        }

        return $this->finish(
            $stream,
            $id,
            fn (): bool => $this->domainPublisher->publish($outbox),
            function () use ($outbox): void {
                // delivered_at is owned by DomainEventBroadcastConsumer — do NOT set it here.
                $outbox->forceFill([
                    'status' => 'published',
                    'published_at' => Carbon::now(),
                    'locked_until' => null,
                    'last_error' => null,
                ])->save();
            },
            fn () => $outbox->forceFill(['attempts' => (int) $outbox->attempts + 1, 'last_error' => 'cdc publish() returned false'])->save(),
        );
    }

    /**
     * Shared publish->commit->ack flow with bounded-retry DLQ, so raw/domain never drift.
     *
     * @param  callable():bool  $publish
     * @param  callable():void  $markPublished
     * @param  callable():void  $recordFailure
     * @return 'acked'|'dlq'|'pending'
     */
    private function finish(string $stream, string $id, callable $publish, callable $markPublished, callable $recordFailure): string
    {
        try {
            $published = $publish();
        } catch (Throwable $e) {
            return $this->afterFailure($stream, $id, $e->getMessage(), $recordFailure);
        }

        if (! $published) {
            // Publish (XADD to the application stream) failed — typically Redis unavailable. Leave
            // unacked so XAUTOCLAIM redelivers after the idle window; that idle window is the
            // bounded backoff. Only DLQ once delivery count crosses MAX_ATTEMPTS.
            return $this->afterFailure($stream, $id, 'cdc publish() returned false', $recordFailure);
        }

        // Ordering matters: XADD already succeeded. Commit the outbox status, then ack. A crash
        // between these two is the accepted at-least-once duplicate window (see class docblock).
        $markPublished();
        $this->ack($stream, $id);
        EventPipelineMetricsService::increment('cdc_publish_success');

        return 'acked';
    }

    /**
     * @param  callable():void  $recordFailure
     * @return 'dlq'|'pending'
     */
    private function afterFailure(string $stream, string $id, string $reason, callable $recordFailure): string
    {
        try {
            $recordFailure();
        } catch (Throwable) {
            // best-effort attempt bookkeeping; the DB may be the thing that's down.
        }

        EventPipelineMetricsService::increment('cdc_publish_failure');

        $attempts = $this->deliveryCount($stream, $id);

        if ($attempts >= self::MAX_ATTEMPTS) {
            $this->deadLetter($stream, $id, 'max_attempts_exceeded: '.$reason, $attempts);
            $this->ack($stream, $id);
            EventPipelineMetricsService::increment('cdc_dlq');

            return 'dlq';
        }

        Log::warning('CdcOutboxStreamConsumer: publish failed, left pending for reclaim', [
            'stream' => $stream,
            'stream_id' => $id,
            'attempts' => $attempts,
            'reason' => $reason,
        ]);

        return 'pending';
    }

    private function ack(string $stream, string $id): void
    {
        $this->raw($this->connection, ['XACK', $stream, $this->group, $id]);
    }

    private function deadLetter(string $stream, string $id, string $reason, int $attempts): void
    {
        $this->rawXadd($this->connection, $this->deadLetterStream, (int) config('app.dlq_maxlen', 1000000), [
            'orig_stream' => $stream,
            'orig_id' => $id,
            'reason' => $reason,
            'attempts' => (string) $attempts,
        ]);
    }

    private function deliveryCount(string $stream, string $id): int
    {
        try {
            $reply = $this->raw($this->connection, ['XPENDING', $stream, $this->group, $id, $id, 1]);
        } catch (Throwable $e) {
            Log::warning('CdcOutboxStreamConsumer:deliveryCount XPENDING failed', [
                'stream' => $stream,
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
