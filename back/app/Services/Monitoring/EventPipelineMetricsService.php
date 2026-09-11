<?php

namespace App\Services\Monitoring;

use App\Models\DomainEventOutbox;
use App\Models\RawEventOutbox;
use App\Services\Ingestion\Concerns\UsesRawRedisCommands;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Throwable;

/**
 * PLAN.md Stage 7 observability — event pipeline health for the raw/domain Redis Streams
 * pipeline plus their transactional outboxes.
 *
 * Existing code reused: `App\Services\Monitoring\ApiMetricsService`'s Cache-counter-with-TTL
 * pattern (`Cache::add()` + `Cache::increment()`) for the low-cardinality counters below, and
 * `App\Services\Ingestion\Concerns\UsesRawRedisCommands` (already shared by `RawStreamConsumer`
 * and `DomainEventBroadcastConsumer`) for client-agnostic (phpredis/predis) raw stream commands —
 * no second Redis reply-shape parser was written. Stream/group names come from the same
 * `config('app.*')` keys `RawSensorEventPublisher`/`ConsumeRawEvents`/`ConsumeDomainEvents`
 * already read; none are hardcoded here.
 * Existing owner retired/delegated: n/a — this is the first pipeline-health surface; it reads
 * state, it does not own any transition.
 * Compatibility window: none.
 *
 * Never full-table-scans for latency: outbox latency percentiles are computed over a bounded
 * `latest()->limit()` sample, not every row ever published.
 */
class EventPipelineMetricsService
{
    use UsesRawRedisCommands;

    private const COUNTER_TTL_SECONDS = 604800; // 7 days, matches ApiMetricsService's rolling-window intent scaled for low-frequency counters

    private const LATENCY_SAMPLE_SIZE = 200;

    /**
     * Step 7.2 — the fixed, low-cardinality counter set. Never keyed by sensor_id/device_id.
     */
    public const COUNTERS = [
        'cdc_publish_success',
        'cdc_publish_failure',
        'cdc_dlq',
        'domain_broadcast_success',
        'domain_broadcast_failure',
        'raw_processed',
        'raw_failed',
    ];

    /**
     * Record one of the fixed COUNTERS. Static + Cache-backed (no DI needed at call sites),
     * matching how `Log::info(...)` is already sprinkled through the ingestion classes.
     */
    public static function increment(string $counter, int $step = 1): void
    {
        if (! in_array($counter, self::COUNTERS, true)) {
            // Guard against typo'd/unknown counter names turning into unbounded cache key sprawl.
            Log::warning('EventPipelineMetricsService:increment unknown counter', ['counter' => $counter]);

            return;
        }

        $key = self::counterKey($counter);
        Cache::add($key, 0, now()->addSeconds(self::COUNTER_TTL_SECONDS));
        Cache::increment($key, $step);
    }

    public function snapshot(): array
    {
        Log::info('EventPipelineMetricsService:snapshot entry');
        $startTime = microtime(true);

        $rawStream = (string) config('app.ingestion_raw_events_stream', 'iot.raw-events');
        $domainStream = (string) config('app.domain_events_stream', 'iot.domain-events');
        $dlqStream = (string) config('app.ingestion_dead_letter_stream', 'iot.dead-letter-events');
        $rawGroup = (string) config('app.ingestion_raw_consumer_group', 'raw-process-v1');
        $domainGroup = (string) config('app.domain_events_consumer_group', 'browser-delivery-v1');

        // Gate 10 (PLAN.md Stage 10): the CDC consumer group draining the two Debezium outbox
        // streams is the current final-publication owner (retired the periodic outbox relays this
        // service originally instrumented). Reused here, not reinvented — same config keys
        // `ConsumeCdcOutboxes`/`CdcOutboxStreamConsumer` already read.
        $cdcRawStream = (string) config('app.cdc_raw_outbox_stream', '');
        $cdcDomainStream = (string) config('app.cdc_domain_outbox_stream', '');
        $cdcGroup = (string) config('app.cdc_outbox_consumer_group', 'outbox-publish-v1');

        $dlqLen = $this->xlen($dlqStream);
        $rawHealth = $this->streamHealth($rawStream, $rawGroup);
        $domainHealth = $this->streamHealth($domainStream, $domainGroup);
        $cdcHealth = $this->mergedStreamHealth([$cdcRawStream, $cdcDomainStream], $cdcGroup);

        $result = [
            'streams' => [
                'raw_events' => array_merge($rawHealth, ['dlq_length' => $dlqLen]),
                'domain_events' => array_merge($domainHealth, ['dlq_length' => $dlqLen]),
                'dead_letter' => ['xlen' => $dlqLen],
            ],
            'consumers' => [
                'raw_process' => [
                    'consumer_group' => $rawGroup,
                    'pending_count' => $rawHealth['pending_count'],
                    'lag' => $rawHealth['lag'],
                    'oldest_pending_age_ms' => $rawHealth['oldest_pending_age_ms'],
                    'raw_processed' => $this->counter('raw_processed'),
                    'raw_failed' => $this->counter('raw_failed'),
                ],
                'browser_delivery' => [
                    'consumer_group' => $domainGroup,
                    'pending_count' => $domainHealth['pending_count'],
                    'lag' => $domainHealth['lag'],
                    'oldest_pending_age_ms' => $domainHealth['oldest_pending_age_ms'],
                    'domain_broadcast_success' => $this->counter('domain_broadcast_success'),
                    'domain_broadcast_failure' => $this->counter('domain_broadcast_failure'),
                ],
                'outbox_cdc' => [
                    'consumer_group' => $cdcGroup,
                    'pending_count' => $cdcHealth['pending_count'],
                    'lag' => $cdcHealth['lag'],
                    'oldest_pending_age_ms' => $cdcHealth['oldest_pending_age_ms'],
                    'cdc_publish_success' => $this->counter('cdc_publish_success'),
                    'cdc_publish_failure' => $this->counter('cdc_publish_failure'),
                    'cdc_dlq' => $this->counter('cdc_dlq'),
                ],
            ],
            'outbox' => [
                'raw' => $this->outboxSnapshot(RawEventOutbox::class, null),
                'domain' => $this->outboxSnapshot(DomainEventOutbox::class, 'delivered_at'),
            ],
        ];

        $durationMs = round((microtime(true) - $startTime) * 1000, 2);
        Log::info('EventPipelineMetricsService:snapshot completed', ['duration_ms' => $durationMs]);

        if ($durationMs > 100) {
            Log::warning('EventPipelineMetricsService:snapshot slow execution', ['duration_ms' => $durationMs]);
        }

        return $result;
    }

    private static function counterKey(string $counter): string
    {
        return "event_pipeline_metrics:counter:{$counter}";
    }

    private function counter(string $counter): int
    {
        return (int) Cache::get(self::counterKey($counter), 0);
    }

    /**
     * @return array{xlen:int,consumer_group:string,pending_count:int,lag:int,oldest_pending_age_ms:int}
     */
    private function streamHealth(string $stream, string $group): array
    {
        $xlen = $this->xlen($stream);
        [$pending, $lag] = $this->groupPendingAndLag($stream, $group);
        $oldestAge = $this->oldestPendingAgeMs($stream, $group);

        return [
            'xlen' => $xlen,
            'consumer_group' => $group,
            'pending_count' => $pending,
            'lag' => $lag,
            'oldest_pending_age_ms' => $oldestAge,
        ];
    }

    /**
     * outbox-publish-v1 is one consumer group shared across two CDC streams (raw + domain outbox
     * captures) — sum pending/lag across both, oldest age is whichever stream is furthest behind.
     *
     * @param  list<string>  $streams
     * @return array{pending_count:int,lag:int,oldest_pending_age_ms:int}
     */
    private function mergedStreamHealth(array $streams, string $group): array
    {
        $pending = 0;
        $lag = 0;
        $oldestAge = 0;

        foreach (array_filter($streams) as $stream) {
            [$streamPending, $streamLag] = $this->groupPendingAndLag($stream, $group);
            $pending += $streamPending;
            $lag += $streamLag;
            $oldestAge = max($oldestAge, $this->oldestPendingAgeMs($stream, $group));
        }

        return ['pending_count' => $pending, 'lag' => $lag, 'oldest_pending_age_ms' => $oldestAge];
    }

    private function xlen(string $stream): int
    {
        try {
            $reply = $this->raw($this->connection(), ['XLEN', $stream]);

            return (int) $reply;
        } catch (Throwable $e) {
            Log::warning('EventPipelineMetricsService: XLEN failed', ['stream' => $stream, 'exception' => $e->getMessage()]);

            return 0;
        }
    }

    /**
     * @return array{0:int,1:int} [pending_count, lag]
     */
    private function groupPendingAndLag(string $stream, string $group): array
    {
        try {
            $reply = $this->raw($this->connection(), ['XINFO', 'GROUPS', $stream]);
        } catch (Throwable $e) {
            Log::warning('EventPipelineMetricsService: XINFO GROUPS failed', ['stream' => $stream, 'exception' => $e->getMessage()]);

            return [0, 0];
        }

        foreach ((array) $reply as $groupEntry) {
            $info = $this->toAssoc($groupEntry);

            if (($info['name'] ?? null) === $group) {
                $pending = (int) ($info['pending'] ?? 0);

                // ponytail: Redis 7+ reports a real "lag" field on XINFO GROUPS; on older Redis it
                // is absent and pending count is the closest available proxy. Upgrade path: none
                // needed once the deployed Redis version is confirmed >= 7 everywhere.
                $lag = isset($info['lag']) && $info['lag'] !== null ? (int) $info['lag'] : $pending;

                return [$pending, $lag];
            }
        }

        return [0, 0];
    }

    private function oldestPendingAgeMs(string $stream, string $group): int
    {
        try {
            $reply = $this->raw($this->connection(), ['XPENDING', $stream, $group, '-', '+', '1']);
        } catch (Throwable $e) {
            Log::warning('EventPipelineMetricsService: XPENDING failed', ['stream' => $stream, 'group' => $group, 'exception' => $e->getMessage()]);

            return 0;
        }

        if (! is_array($reply) || ! isset($reply[0]) || ! is_array($reply[0])) {
            return 0;
        }

        // Extended XPENDING entry: [id, consumer, idle-time-ms, delivery-count]. Range "- +" is
        // ascending by ID, so the first (and only, COUNT 1) entry is the oldest pending message.
        return (int) ($reply[0][2] ?? 0);
    }

    /**
     * @param  class-string<RawEventOutbox|DomainEventOutbox>  $modelClass
     */
    private function outboxSnapshot(string $modelClass, ?string $deliveredColumn): array
    {
        $result = [
            'pending' => $this->outboxQuery($modelClass)->where('status', 'pending')->count(),
            'publishing' => $this->outboxQuery($modelClass)->where('status', 'publishing')->count(),
            'published' => $this->outboxQuery($modelClass)->where('status', 'published')->count(),
        ];

        $columns = $deliveredColumn ? ['created_at', 'published_at', $deliveredColumn] : ['created_at', 'published_at'];

        $recent = $this->outboxQuery($modelClass)
            ->where('status', 'published')
            ->whereNotNull('published_at')
            ->latest('id')
            ->limit(self::LATENCY_SAMPLE_SIZE)
            ->get($columns);

        $publishLatencies = $recent
            ->filter(fn ($row) => $row->created_at !== null && $row->published_at !== null)
            ->map(fn ($row) => $row->created_at->diffInMilliseconds($row->published_at))
            ->sort()
            ->values();

        $result['publish_latency_p50_ms'] = $this->percentile($publishLatencies, 50);
        $result['publish_latency_p95_ms'] = $this->percentile($publishLatencies, 95);

        if ($deliveredColumn) {
            $deliveryLatencies = $recent
                ->filter(fn ($row) => $row->published_at !== null && $row->{$deliveredColumn} !== null)
                ->map(fn ($row) => $row->published_at->diffInMilliseconds($row->{$deliveredColumn}))
                ->sort()
                ->values();

            $result['delivery_latency_p50_ms'] = $this->percentile($deliveryLatencies, 50);
            $result['delivery_latency_p95_ms'] = $this->percentile($deliveryLatencies, 95);
        }

        return $result;
    }

    /**
     * @param  class-string<RawEventOutbox|DomainEventOutbox>  $modelClass
     */
    private function outboxQuery(string $modelClass): Builder
    {
        return $modelClass::query();
    }

    private function percentile(Collection $sortedMs, int $p): ?float
    {
        if ($sortedMs->isEmpty()) {
            return null;
        }

        $index = (int) ceil(($p / 100) * $sortedMs->count()) - 1;
        $index = max(0, min($sortedMs->count() - 1, $index));

        return (float) $sortedMs->get($index);
    }

    private function connection(): \Illuminate\Redis\Connections\Connection
    {
        return Redis::connection('default');
    }

    /**
     * Normalizes a raw stream-command reply entry (flat [key, value, key, value, ...] on phpredis
     * rawCommand / predis executeRaw) into an associative array. Some clients may already return
     * an assoc array for XINFO entries — pass those through unchanged.
     */
    private function toAssoc(mixed $entry): array
    {
        if (! is_array($entry)) {
            return [];
        }

        if (array_is_list($entry)) {
            $out = [];
            for ($i = 0; $i < count($entry); $i += 2) {
                $out[$entry[$i]] = $entry[$i + 1] ?? null;
            }

            return $out;
        }

        return $entry;
    }
}
