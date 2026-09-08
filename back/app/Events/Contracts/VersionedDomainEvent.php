<?php

namespace App\Events\Contracts;

/**
 * Canonical versioned event envelope contract (PLAN.md Stage 2.2, docs/implementation/adr-g1.md).
 *
 * Existing code reused: none — no envelope/versioning existed on any event before Stage 2.
 * Implementations supply identity; App\Events\Concerns\HasEventEnvelope builds the envelope.
 */
interface VersionedDomainEvent
{
    /**
     * Canonical dot-scoped event name, e.g. "sensor.reading.created".
     * Independent of the Laravel broadcast/class name, which stays unchanged for
     * backward compatibility during the Stage 2 rollout.
     */
    public function eventType(): string;

    public function eventVersion(): int;

    public function aggregateType(): string;

    public function aggregateId(): int|string;

    public function aggregateVersion(): int;
}
