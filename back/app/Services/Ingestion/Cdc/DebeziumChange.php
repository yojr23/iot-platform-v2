<?php

namespace App\Services\Ingestion\Cdc;

use RuntimeException;

/**
 * Gate 10 (PLAN.md Stage 10 / docs/implementation/adr-g1.md) — parses one Debezium Server change
 * event delivered onto a Redis CDC stream (`iot-cdc.<db>.<table>`).
 *
 * Debezium is configured with `debezium.sink.redis.message.format=extended`, so each XADD entry
 * carries two fields: `key` (the row PK envelope) and `value` (the change envelope). We only need
 * the `value` envelope's `op` and `after` image to decide whether/what to republish.
 *
 * This class does no Redis I/O and no DB I/O — it is a pure value object so it stays trivially
 * testable (Task 5.1 test matrix: create/snapshot/update ops, malformed JSON, missing after).
 */
final readonly class DebeziumChange
{
    /**
     * @param  array<string,mixed>  $after
     */
    public function __construct(
        public string $operation,
        public array $after,
    ) {
    }

    /**
     * @param  array<string,mixed>  $fields  the flattened Redis stream entry fields
     */
    public static function fromRedisFields(array $fields): self
    {
        if (! isset($fields['value']) || ! is_string($fields['value'])) {
            throw new RuntimeException('Debezium CDC message is missing value');
        }

        $envelope = json_decode($fields['value'], true, 512, JSON_THROW_ON_ERROR);

        if (! is_array($envelope)) {
            throw new RuntimeException('Debezium CDC message value is not an object');
        }

        $operation = (string) ($envelope['op'] ?? '');
        $after = $envelope['after'] ?? null;

        if (! is_array($after)) {
            throw new RuntimeException('Debezium CDC message is missing after image');
        }

        return new self(operation: $operation, after: $after);
    }

    /** Insert (`c`) or snapshot read (`r`) — the only ops that carry a fresh row to publish. */
    public function isInsertOrSnapshot(): bool
    {
        return $this->operation === 'c' || $this->operation === 'r';
    }
}
