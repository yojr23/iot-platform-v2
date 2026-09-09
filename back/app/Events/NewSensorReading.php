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
     * Pre-Stage-6 preflight (delivery-audience flags): both default to the exact pre-existing
     * behavior of this event — public-only, single `Channel`, so `broadcastOn()` keeps returning
     * a lone Channel object (not an array) for any caller that doesn't opt in, preserving
     * `tests/Unit/EventEnvelopeTest.php`'s `->name` access on the un-flagged construction path.
     * `DomainEventBroadcastConsumer` (the only real dispatch site) explicitly passes both flags
     * true today; Stage 6 will instead compute `includePublicChannel` from
     * `PublicGraphVisibility::isPublic()` at that same call site — no change needed here.
     */
    private bool $includePublicChannel;
    private bool $includePrivateChannel;

    public function __construct(
        SensorReading $reading,
        ?string $correlationId = null,
        ?string $causationId = null,
        bool $includePublicChannel = true,
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
