<?php

namespace App\Events;

use App\Models\Alert;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NewAlertTriggered implements ShouldBroadcastNow
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
        $reading = $this->alert->sensorReading;
        $sensor = $reading?->sensor;
        $sensorType = $sensor?->sensorType;
        $device = $sensor?->device;
        $lab = $device?->lab;
        $rule = $this->alert->alertRule;

        return [
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
    }
}
