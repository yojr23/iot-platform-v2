<?php

namespace App\Events;

use App\Events\Concerns\HasEventEnvelope;
use App\Events\Contracts\VersionedDomainEvent;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

class NewAlertTriggered implements ShouldBroadcastNow, VersionedDomainEvent
{
    use Dispatchable, InteractsWithSockets, HasEventEnvelope;

    /**
     * Immutable alert.triggered fact, captured in DomainEventOutbox when the alert is created.
     */
    public function __construct(
        public readonly int $alertId,
        public readonly string $message,
        public readonly string $severity,
        public readonly ?float $value,
        public readonly string $sensorName,
        public readonly string $sensorType,
        public readonly string $unit,
        public readonly string $deviceName,
        public readonly string $labName,
        public readonly ?string $timestamp,
        ?string $correlationId = null,
        ?string $causationId = null,
    ) {
        $this->correlationId = $correlationId;
        $this->causationId = $causationId;
    }

    public function broadcastOn()
    {
        // PLAN.md Stage 7 / audit.md §12a: alerts are an authorized capability, not public/guest
        // data. Channel name kept (`alerts`, wire name `private-alerts`) for continuity with the
        // pre-existing event/channel identity; only the transport moved from a public `Channel` to
        // an authorization-enforced `PrivateChannel`, gated by routes/channels.php. Payload/event
        // name unchanged.
        return new PrivateChannel('alerts');
    }

    public function broadcastWith()
    {
        $legacy = [
            'id' => $this->alertId,
            'message' => $this->message,
            'severity' => $this->severity,
            'value' => $this->value,
            'sensor_name' => $this->sensorName,
            'sensor_type' => $this->sensorType,
            'unit' => $this->unit,
            'device_name' => $this->deviceName,
            'lab_name' => $this->labName,
            'timestamp' => $this->timestamp,
        ];

        // Additive envelope metadata (Stage 2.2) — existing keys unchanged.
        return array_merge($legacy, $this->envelopeMetadata());
    }

    public function eventType(): string
    {
        return 'alert.triggered';
    }

    public function aggregateType(): string
    {
        return 'alert';
    }

    public function aggregateId(): int|string
    {
        return $this->alertId;
    }
}
