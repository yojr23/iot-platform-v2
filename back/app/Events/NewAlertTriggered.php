<?php

namespace App\Events;

use App\Events\Concerns\HasEventEnvelope;
use App\Events\Contracts\VersionedDomainEvent;
use App\Models\Alert;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NewAlertTriggered implements ShouldBroadcastNow, VersionedDomainEvent
{
    use Dispatchable, InteractsWithSockets, SerializesModels, HasEventEnvelope;

    public $alert;

    public function __construct(Alert $alert, ?string $correlationId = null, ?string $causationId = null)
    {
        $this->alert = $alert;
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
        $reading = $this->alert->sensorReading;
        $sensor = $reading?->sensor;
        $sensorType = $sensor?->sensorType;
        $device = $sensor?->device;
        $lab = $device?->lab;
        $rule = $this->alert->alertRule;

        $legacy = [
            'id' => $this->alert->id,
            'message' => $rule?->message ?? 'Alerta generada',
            'severity' => $rule?->severity ?? 'warning',
            'value' => $reading?->value,
            'sensor_name' => $sensor?->name ?? 'Sensor desconocido',
            'sensor_type' => $sensorType?->name ?? '',
            'unit' => $sensorType?->unit ?? '',
            'device_name' => $device?->name ?? 'Dispositivo desconocido',
            'lab_name' => $lab?->name ?? 'Lab no definido',
            'timestamp' => $this->alert->created_at,
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
        return $this->alert->id;
    }
}
