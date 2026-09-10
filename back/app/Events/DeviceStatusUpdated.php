<?php

namespace App\Events;

use App\Events\Concerns\HasEventEnvelope;
use App\Events\Contracts\VersionedDomainEvent;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * PLAN.md Stage 4.2/4.4, hardened at Gate 8: `device.status.changed` is an immutable,
 * sequence-ordered domain fact, not a live read of the mutable `Device` row.
 *
 * Existing code reused: envelope trait, `ShouldBroadcastNow` contract, dispatch-only-from-consumer
 * shape — all unchanged from the Stage 4 version of this class (see git history for the prior
 * `Device $device`-constructed version). What changed at Gate 8: the constructor now takes the
 * scalar fields already captured in the outbox payload by `DeviceService::changeStatus()` at write
 * time (`deviceId`, `status`, `isActive`, `changedAt`, `eventSequence`), instead of a live `Device`
 * model reloaded by `DomainEventBroadcastConsumer`. Audit finding this closes: two rapid status
 * transitions could previously both resolve to the *current* (latest) status when the consumer
 * re-read `Device::find()`, so the first fact's broadcast silently carried the second fact's value.
 * `eventSequence` is the outbox row id (`DomainEventOutbox::id`), a strictly increasing per-fact
 * sequence number for the same reason `sensor.reading.created` doesn't need one — device status has
 * no reading-id equivalent, so the outbox id is the ordering key.
 * Existing owner retired/delegated: `DomainEventBroadcastConsumer::broadcastDeviceStatusChanged()`
 * no longer does `Device::find()` to construct this event — see that class for the matching change.
 * Compatibility window: none. Dispatched only from `DomainEventBroadcastConsumer`, never from a
 * controller/service directly.
 *
 * Channel: moved from a public `Channel('device-status')` to `PrivateChannel('device-status')`
 * (Gate 8) — device status, like alerts (Stage 7 / audit.md §12a), is not public/guest data. Any
 * authenticated user may subscribe (see `routes/channels.php`), matching the `alerts` channel's
 * authorization shape (no per-device ACL model exists in this app).
 */
class DeviceStatusUpdated implements ShouldBroadcastNow, VersionedDomainEvent
{
    use Dispatchable, InteractsWithSockets, SerializesModels, HasEventEnvelope;

    public function __construct(
        public readonly int $deviceId,
        public readonly bool $status,
        public readonly bool $isActive,
        public readonly string $changedAt,
        public readonly int $eventSequence,
        ?string $correlationId = null,
        ?string $causationId = null,
    ) {
        $this->correlationId = $correlationId;
        $this->causationId = $causationId;
    }

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('device-status');
    }

    public function broadcastWith()
    {
        $legacy = [
            'device_id' => $this->deviceId,
            'status' => $this->status,
            'is_active' => $this->isActive,
            'changed_at' => $this->changedAt,
            'event_sequence' => $this->eventSequence,
        ];

        // Additive envelope metadata (Stage 2.2) — existing keys unchanged.
        return array_merge($legacy, $this->envelopeMetadata());
    }

    public function eventType(): string
    {
        return 'device.status.changed';
    }

    public function aggregateType(): string
    {
        return 'device';
    }

    public function aggregateId(): int|string
    {
        return $this->deviceId;
    }
}
