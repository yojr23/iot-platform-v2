<?php

namespace App\Services\Ingestion;

use App\Models\RawSensorEvent;
use App\Services\Ingestion\Concerns\UsesRawRedisCommands;
use Illuminate\Redis\Connections\Connection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * PLAN.md Stage 3.3 / docs/implementation/adr-g1.md — Redis Streams consumer group `raw-process-v1`
 * over `iot.raw-events`.
 *
 * Existing code reused: `RawSensorEvent` model/table as the idempotency ledger. This stage
 * deliberately does NOT add a separate `(consumer_name, event_id)` ledger table — the business
 * row's own `status` column ("received" -> "processed"/"failed") already carries exactly that
 * fact for the one consumer group that exists in Stage 3, and is written inside the same DB
 * transaction as the readings it produces. `RawReadingNormalizer` owns turning the receipt into
 * readings; `AlertService` (via the existing `SensorReadingObserver`) stays the sole alert-rule
 * evaluation owner — this class never touches alert logic.
 *
 * Uses a portable raw-command helper (`raw()`) for every stream command so it works with both
 * phpredis (Docker/production default) and predis (local/tests); reply shapes for
 * XREADGROUP/XAUTOCLAIM are handled explicitly since they nest differently.
 *
 * ponytail: bounded concurrency = single-process sequential processing of one batch at a time
 * (COUNT + BLOCK already bound work-in-flight and wait time — that is the backpressure). Ceiling:
 * no in-process worker pool. Scale out by running multiple `raw:consume` processes under a
 * supervisor, each with a distinct consumer name — the consumer-group protocol already makes that
 * safe, no code change needed.
 */
class RawStreamConsumer
{
    use UsesRawRedisCommands;

    public const MAX_ATTEMPTS = 5;

    public function __construct(
        private Connection $connection,
        private RawReadingNormalizer $normalizer,
        private string $stream,
        private string $group,
        private string $deadLetterStream,
    ) {
    }

    public function ensureGroup(): void
    {
        try {
            // Start at 0 (not $): the outbox relay can XADD a receipt before this consumer group
            // has ever been created (first boot, or a fresh group after a stream reset). Creating
            // the group at $ would silently skip that pre-existing backlog; 0 processes it. Already
            // acked messages are not redelivered because the group's last-delivered-id advances past
            // them, so 0 is safe for an existing group too (BUSYGROUP is ignored below).
            $this->raw($this->connection, ['XGROUP', 'CREATE', $this->stream, $this->group, '0', 'MKSTREAM']);
        } catch (Throwable $e) {
            if (! str_contains($e->getMessage(), 'BUSYGROUP')) {
                throw $e;
            }
        }
    }

    /**
     * One bounded iteration: reclaim idle-pending messages first (lease recovery), then read new
     * messages. Never blocks longer than $blockMs and never processes more than $batch+$batch
     * messages, so it is safe to call in a tight loop.
     *
     * @return array{acked:int,dlq:int,pending:int}
     */
    public function runOnce(string $consumerName, int $batch, int $blockMs, int $claimIdleMs = 30000): array
    {
        Log::info('RawStreamConsumer:runOnce entry', [
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
        Log::info('RawStreamConsumer:runOnce completed', array_merge($stats, ['duration_ms' => $durationMs]));

        if ($durationMs > 100) {
            Log::warning('RawStreamConsumer:runOnce slow execution', ['duration_ms' => $durationMs]);
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

        // XAUTOCLAIM reply: [cursor, [[id, [k,v,...]], ...], [deleted-ids]]
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

        // XREADGROUP reply: [[stream, [[id, [k,v,...]], ...]]]
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
                // XAUTOCLAIM can return a null id placeholder for deleted-but-still-pending
                // entries on some Redis versions; nothing to process.
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
        $eventId = $fields['event_id'] ?? null;

        if (! is_string($eventId) || $eventId === '' || ! ctype_digit($eventId)) {
            $this->deadLetter($id, $fields, 'malformed_message: missing or invalid event_id', null, $this->deliveryCount($id));
            $this->ack($id);

            return 'dlq';
        }

        $event = RawSensorEvent::query()->find((int) $eventId);

        if (! $event) {
            // References a business row that does not exist — no amount of retrying fixes this.
            $this->deadLetter($id, $fields, 'unknown_raw_sensor_event', null, $this->deliveryCount($id));
            $this->ack($id);

            return 'dlq';
        }

        if ($event->status === 'processed') {
            // Fast-path idempotent skip (no lock needed for a terminal state).
            $this->ack($id);

            return 'acked';
        }

        try {
            // Both outcomes (this delivery processed it, or a concurrent one already did) mean the
            // receipt is durably handled — ack either way.
            DB::transaction(function () use ($eventId): string {
                // Concurrent-safe idempotency: two physical stream entries for the same receipt
                // (at-least-once relay) can be claimed by two consumers at once. Re-read the row
                // FOR UPDATE inside the transaction so only one wins the "received -> processed"
                // transition; the loser sees 'processed' and skips. The unique
                // (source, source_event_id) constraint backstops duplicate receipts; this lock
                // backstops duplicate *processing* of one receipt.
                $locked = RawSensorEvent::query()->lockForUpdate()->find((int) $eventId);

                if (! $locked || $locked->status === 'processed') {
                    return 'skip';
                }

                $result = $this->normalizer->normalize($locked);

                // created==0 with a non-empty sensors payload = total normalization failure
                // (every value invalid / no sensor mapped). Do not mask it as success — throw so
                // it retries and eventually DLQs. An empty payload legitimately creates nothing.
                $sensorsPayload = (array) data_get($locked->payload, 'sensors', []);
                if ($result['created'] === 0 && $sensorsPayload !== []) {
                    throw new RuntimeException(
                        'normalization produced no readings; skipped keys: '.implode(',', $result['skipped'])
                    );
                }

                $locked->forceFill([
                    'status' => 'processed',
                    'processed_at' => now(),
                    'error' => null,
                ])->save();

                return 'processed';
            });

            $this->ack($id);

            return 'acked';
        } catch (Throwable $e) {
            $attempts = $this->deliveryCount($id);

            Log::warning('RawStreamConsumer: message processing failed', [
                'stream_id' => $id,
                'raw_sensor_event_id' => $event->id,
                'attempts' => $attempts,
                'exception' => $e->getMessage(),
            ]);

            if ($attempts >= self::MAX_ATTEMPTS) {
                $event->forceFill(['status' => 'failed', 'error' => $e->getMessage()])->save();
                $this->deadLetter($id, $fields, 'max_attempts_exceeded: '.$e->getMessage(), $event->source_event_id, $attempts);
                $this->ack($id);

                return 'dlq';
            }

            // Leave unacked: still pending in the PEL, picked up again by reclaimPending() once
            // the claim-idle threshold elapses. That idle window IS the bounded backoff — no
            // separate backoff timer/table needed.
            return 'pending';
        }
    }

    private function ack(string $id): void
    {
        $this->raw($this->connection, ['XACK', $this->stream, $this->group, $id]);
    }

    /**
     * @param  array<string,string|null>  $fields
     */
    private function deadLetter(string $id, array $fields, string $reason, ?string $sourceEventId, int $attempts): void
    {
        $this->raw($this->connection, [
            'XADD', $this->deadLetterStream, '*',
            'orig_id', $id,
            'reason', $reason,
            'event_id', (string) ($fields['event_id'] ?? ''),
            'source_event_id', (string) ($sourceEventId ?? ''),
            'attempts', (string) $attempts,
        ]);
    }

    private function deliveryCount(string $id): int
    {
        try {
            $reply = $this->raw($this->connection, ['XPENDING', $this->stream, $this->group, $id, $id, 1]);
        } catch (Throwable $e) {
            Log::warning('RawStreamConsumer:deliveryCount XPENDING failed', [
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
