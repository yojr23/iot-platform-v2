<?php

namespace App\Events;


use App\Events\Concerns\HasEventEnvelope;
use App\Events\Contracts\VersionedDomainEvent;
use App\Models\Device;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * PLAN.md Stage 4.2/4.4 (audit RC2: this event previously existed but was never dispatched —
 * `DeviceApiController::updateStatus` mutated the device with no `event(...)` call at all).
 *
 * Existing code reused: this class's shape (envelope trait, `device-status` channel, payload) is
 * unchanged. Only the broadcast contract moved from `ShouldBroadcast` (queued) to
 * `ShouldBroadcastNow` (direct) to match ADR-4 (`browser-delivery-v1` consumer -> Pusher-compatible
 * broadcaster directly, no extra queue hop) — the async boundary is now the domain-event stream +
 * `DomainEventBroadcastConsumer`, not a second Laravel queue dispatch on top of it.
 * Existing owner retired/delegated: n/a — nothing dispatched this event before.
 * Compatibility window: none. Dispatched only from `DomainEventBroadcastConsumer`, never from a
 * controller/service directly.
 */
class DeviceStatusUpdated implements ShouldBroadcastNow, VersionedDomainEvent
{
    use Dispatchable, InteractsWithSockets, SerializesModels, HasEventEnvelope;

    public $device;

    public function __construct(Device $device, ?string $correlationId = null, ?string $causationId = null)
    {
        $this->device = $device;
        $this->correlationId = $correlationId;
        $this->causationId = $causationId;
    }

    public function broadcastOn()
    {
        // Backward-compat channel name kept (PLAN.md 2.2/2.3) — public guest dashboard projection.
        return new Channel('device-status');
    }

    public function broadcastWith()
    {
        $legacy = [
            'device_id' => $this->device->id,
            'status' => $this->device->status,
            'name' => $this->device->name,
            'lab_name' => $this->device->lab->name,
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
        return $this->device->id;
    }
}
