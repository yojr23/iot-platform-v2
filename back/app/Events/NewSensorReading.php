<?php

namespace App\Events;

use App\Events\Concerns\HasEventEnvelope;
use App\Events\Contracts\VersionedDomainEvent;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use App\Models\SensorReading;

class NewSensorReading implements ShouldBroadcastNow, VersionedDomainEvent
{
    use Dispatchable, InteractsWithSockets, SerializesModels, HasEventEnvelope;

    public $reading;

    /**
     * Gate 6 fail-closed default (PublicGraphVisibility default FALSE): both audience flags
     * default to false, so a bare `new NewSensorReading($reading)` broadcasts on NO channel
     * (`broadcastOn()` returns `[]`). Every production dispatcher must opt in explicitly.
     * `DomainEventBroadcastConsumer` (the only real dispatch site) already passes both flags
     * true explicitly and is unaffected by this default change.
     */
    private bool $includePublicChannel;
    private bool $includePrivateChannel;

    public function __construct(
        SensorReading $reading,
        ?string $correlationId = null,
        ?string $causationId = null,
        bool $includePublicChannel = false,
        bool $includePrivateChannel = false,
    ) {
        $this->reading = $reading;
        $this->correlationId = $correlationId;
        $this->causationId = $causationId;
        $this->includePublicChannel = $includePublicChannel;
        $this->includePrivateChannel = $includePrivateChannel;
    }

    public function broadcastOn()
    {
        $channels = [];

        if ($this->includePublicChannel) {
            // Backward-compat channel name kept (PLAN.md 2.2/2.3) — public guest dashboard projection.
            $channels[] = new Channel('sensor.'.$this->reading->sensor_id);
        }

        if ($this->includePrivateChannel) {
            // Pre-Stage-6 preflight — authenticated delivery path for restricted sensors, authorized
            // in routes/channels.php. Same channel "identity" (sensor.{id}), private transport.
            $channels[] = new PrivateChannel('sensor.'.$this->reading->sensor_id);
        }

        return count($channels) === 1 ? $channels[0] : $channels;
    }

    public function broadcastWith()
    {
        // Gate 6 (Task 6.7): minimal public wire payload — identity + value + time only. The
        // display metadata (sensor_name/sensor_type/unit/device_name/lab_name) that used to ride
        // on every reading is removed; guests get that once from the public graph bootstrap, so
        // leaking it per-reading on the public channel was both redundant and an info-disclosure
        // surface for restricted sensors.
        $payload = [
            'reading_id' => $this->reading->id,
            'sensor_id' => $this->reading->sensor_id,
            'value' => (float) $this->reading->value,
            'reading_time' => $this->reading->reading_time
                ?->clone()->setTimezone('UTC')->format('Y-m-d\TH:i:s\Z'),
        ];

        // Additive envelope metadata (Stage 2.2) — existing keys unchanged, old consumers
        // tolerate/ignore the new ones (PLAN.md Stage 2 done-when: "additive fields tolerated").
        return array_merge($payload, $this->envelopeMetadata());
    }

    public function eventType(): string
    {
        return 'sensor.reading.created';
    }

    public function aggregateType(): string
    {
        return 'sensor_reading';
    }

    public function aggregateId(): int|string
    {
        return $this->reading->id;
    }
}
