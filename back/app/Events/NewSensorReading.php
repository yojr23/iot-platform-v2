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
        // Task 7(b), supersedes Gate 6 Task 6.7's minimization for THIS field set: the payload is
        // self-contained again — identity + value + time + the denormalized sensor/device/unit
        // fields `front/src/realtime/useSensorRealtime.js`'s normalizeReading() reads
        // (sensor_name/sensor_type/unit/device_name/lab_name) — so a browser never needs a
        // follow-up read of the SensorReading row to render a live event.
        //
        // Why this doesn't reopen Gate 6's info-disclosure concern: the audience decision already
        // happened before this event is even constructed
        // (`DomainEventBroadcastConsumer::broadcastSensorReadingCreated()` asks
        // `PublicGraphVisibility::isPublic()` for `includePublicChannel`) — a restricted sensor
        // never reaches the public channel at all, so a fact that IS on the public channel is, by
        // construction, for a sensor already enumerated with this same metadata in the public graph
        // catalog/bootstrap. The private channel is unaffected: it was already gated to viewers
        // authorized to see this metadata via REST.
        //
        // Existing code reused: relies on the SAME eager-loaded relations
        // (`sensor.sensorType`, `sensor.device.lab`) the one production dispatch site
        // (`broadcastSensorReadingCreated()`) already loads before constructing this event — no new
        // query added on that path.
        $sensor = $this->reading->sensor;
        $sensorType = $sensor?->sensorType;
        $device = $sensor?->device;

        $payload = [
            'id' => $this->reading->id,
            'reading_id' => $this->reading->id,
            'sensor_id' => $this->reading->sensor_id,
            'value' => (float) $this->reading->value,
            'reading_time' => $this->reading->reading_time
                ?->clone()->setTimezone('UTC')->format('Y-m-d\TH:i:s\Z'),
            'sensor_name' => $sensor?->name,
            'sensor_type' => $sensorType?->name,
            'unit' => $sensorType?->unit,
            'device_name' => $device?->name,
            'lab_name' => $device?->lab?->name,
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
