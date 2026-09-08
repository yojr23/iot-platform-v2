<?php

namespace App\Events\Concerns;

use Illuminate\Support\Str;

/**
 * Builds the canonical versioned event envelope defined in docs/implementation/adr-g1.md and
 * PLAN.md Stage 2.2: event_id, event_type, event_version, occurred_at, producer,
 * aggregate_type/id/version, correlation_id, causation_id, payload.
 *
 * Existing code reused: none (first shared envelope owner — Events/Contracts and Events/Concerns
 * did not exist before Stage 2). Every event using this trait must implement
 * App\Events\Contracts\VersionedDomainEvent.
 *
 * Compatibility window: `envelopeMetadata()` is merged additively alongside each event's existing
 * flat `broadcastWith()` payload so current Echo/front consumers keep working unchanged (audit.md
 * open Q#3 — public channels stay public). `envelope()` returns the full nested-payload shape for
 * future durable outbox/consumer use (Stage 3/4) without duplicating data on the wire today.
 */
trait HasEventEnvelope
{
    protected ?string $envelopeEventId = null;

    protected ?string $envelopeOccurredAt = null;

    public ?string $correlationId = null;

    public ?string $causationId = null;

    /**
     * Full canonical envelope, payload nested under `payload` (Stage 3/4 outbox shape).
     */
    public function envelope(array $payload): array
    {
        return array_merge($this->envelopeMetadata(), ['payload' => $payload]);
    }

    /**
     * Envelope fields only (no nested payload) — merged additively into today's flat
     * broadcastWith() wire payload so old and new consumers coexist during rollout.
     */
    public function envelopeMetadata(): array
    {
        return [
            'event_id' => $this->envelopeEventId(),
            'event_type' => $this->eventType(),
            'event_version' => $this->eventVersion(),
            'occurred_at' => $this->envelopeOccurredAt(),
            'producer' => config('app.name', 'iot-platform-backend'),
            'aggregate_type' => $this->aggregateType(),
            'aggregate_id' => $this->aggregateId(),
            'aggregate_version' => $this->aggregateVersion(),
            'correlation_id' => $this->correlationId ??= (string) Str::uuid(),
            'causation_id' => $this->causationId,
        ];
    }

    public function envelopeEventId(): string
    {
        return $this->envelopeEventId ??= (string) Str::uuid();
    }

    public function envelopeOccurredAt(): string
    {
        return $this->envelopeOccurredAt ??= now()->toIso8601String();
    }

    public function eventVersion(): int
    {
        return 1;
    }

    // ponytail: no optimistic-concurrency/version column exists on the underlying aggregates yet,
    // so a static 1 stands in for "first known version of this fact". Revisit once Stage 3/4's
    // outbox assigns a real per-aggregate monotonic version.
    public function aggregateVersion(): int
    {
        return 1;
    }
}
