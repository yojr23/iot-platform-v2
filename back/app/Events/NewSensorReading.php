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
        $legacy = [
            'reading_id' => $this->reading->id,
            'sensor_id' => $this->reading->sensor_id,
            'value' => $this->reading->value,
            'reading_time' => $this->reading->reading_time?->toIso8601String(),
            'sensor_name' => $this->reading->sensor->name,
            'sensor_type' => $this->reading->sensor->sensorType->name,
            'unit' => $this->reading->sensor->sensorType->unit,
            'device_name' => $this->reading->sensor->device->name,
            'lab_name' => $this->reading->sensor->device->lab->name,
        ];

        // Additive envelope metadata (Stage 2.2) — existing keys unchanged, old consumers
        // tolerate/ignore the new ones (PLAN.md Stage 2 done-when: "additive fields tolerated").
        return array_merge($legacy, $this->envelopeMetadata());
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

    public function handle()
    {
        $this->reading->checkForAlert();
    }
}
