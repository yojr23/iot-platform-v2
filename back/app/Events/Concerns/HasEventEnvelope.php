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

    /**
     * PENDING CI EXECUTION — root-cause fix for Fix 3 (persistent event-envelope identity on
     * retry). Existing code reused: the trait's own `??=`-memoized envelope properties — this only
     * adds a way to seed them from outside instead of introducing a second identity mechanism.
     * Existing owner retired/delegated: none; `envelopeEventId()`/`envelopeOccurredAt()` remain the
     * sole readers, they just get a chance to be pre-filled from a durable, stable source before
     * their own lazy `Str::uuid()`/`now()` fallback would otherwise fire.
     *
     * Root cause this fixes: every call site that dispatches one of these events (in practice only
     * `DomainEventBroadcastConsumer::broadcastFact()`) constructs a BRAND NEW event object on each
     * delivery attempt. Because `envelopeEventId`/`envelopeOccurredAt` were only ever lazily
     * generated on the object itself (`??=`), a redelivered message (XAUTOCLAIM reclaim after a
     * crash between broadcast success and `delivered_at`/XACK, or any other at-least-once retry)
     * built a fresh object and therefore emitted a DIFFERENT event_id and a DIFFERENT occurred_at
     * for what is logically the same fact — breaking downstream dedup by event_id. The already-
     * durable identity for these events is the `DomainEventOutbox` row (`id`, `created_at`), set
     * once at first persistence and never mutated by retries (see `CdcOutboxStreamConsumer`,
     * `RawSensorEventPublisher`, `DomainEventPublisher`, all of which already read `$outbox->id`/
     * `$outbox->created_at` directly and were already correct). This method lets the broadcast
     * consumer seed the SAME identity into the broadcast event, closing the one place it was not
     * yet reused. Guarded with `??=` so it never overrides an explicitly-set value.
     *
     * Task 7(a) extension (correlation_id): `correlation_id` had the exact same per-instance-mint
     * bug as `event_id` — `envelopeMetadata()` calls `$this->correlationId ??= Str::uuid()`, and
     * since `broadcastFact()` builds a fresh event object per delivery attempt, a retried broadcast
     * previously got a DIFFERENT correlation_id too. There is no dedicated `correlation_id` column
     * on `domain_event_outboxes` (no evidence yet of cross-event causal chains needing a distinct
     * value — see ponytail note below), so this reuses the SAME stable per-outbox-row identity
     * already passed for `$eventId` (the outbox row's own `id`) as the default correlation seed,
     * rather than inventing a second column/mechanism for a value nothing yet needs to differ.
     * `??=` still guards an explicitly-set correlationId (e.g. a future causal chain) from being
     * overridden.
     *
     * ponytail: correlation_id == event_id (the outbox row id) is a v1 simplification — it is
     * stable across retries (this fix's actual requirement) but does not yet link separate events
     * from the same causal chain (e.g. a reading that triggers an alert). Add a real
     * `correlation_id` column, threaded from `DomainEventRecorder::record()` through to this seed
     * call, only when a concrete cross-event correlation need shows up.
     */
    public function seedEnvelope(string $eventId, string $occurredAt, ?string $correlationId = null): static
    {
        $this->envelopeEventId ??= $eventId;
        $this->envelopeOccurredAt ??= $occurredAt;
        $this->correlationId ??= $correlationId ?? $eventId;

        return $this;
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
