<?php

namespace App\Events;

use App\Models\Alert;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NewAlertTriggered implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $alert;

    public function __construct(Alert $alert)
    {
        $this->alert = $alert;
    }

    public function broadcastOn()
    {
        return new Channel('alerts');
    }

    public function broadcastWith()
    {
        return [
            'id' => $this->alert->id,
            'message' => $this->alert->alertRule->message,
            'severity' => $this->alert->alertRule->severity,
            'value' => $this->alert->sensorReading->value,
            'sensor_name' => $this->alert->sensorReading->sensor->name,
            'sensor_type' => $this->alert->sensorReading->sensor->sensorType->name,
            'unit' => $this->alert->sensorReading->sensor->sensorType->unit,
            'device_name' => $this->alert->sensorReading->sensor->device->name,
            'classroom_name' => $this->alert->sensorReading->sensor->device->classroom->name,
            'timestamp' => $this->alert->created_at,
        ];
    }
}
