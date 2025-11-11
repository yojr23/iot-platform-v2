<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use App\Models\SensorReading;

class NewSensorReading implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $reading;

    public function __construct(SensorReading $reading)
    {
        $this->reading = $reading;
    }

    public function broadcastOn()
    {
        return new Channel('sensor.'.$this->reading->sensor_id);
    }

    public function broadcastWith()
    {
        return [
            'sensor_id' => $this->reading->sensor_id,
            'value' => $this->reading->value,
            'reading_time' => $this->reading->reading_time,
            'sensor_name' => $this->reading->sensor->name,
            'sensor_type' => $this->reading->sensor->sensorType->name,
            'unit' => $this->reading->sensor->sensorType->unit,
            'device_name' => $this->reading->sensor->device->name,
            'classroom_name' => $this->reading->sensor->device->classroom->name,
        ];
    }

    public function handle()
    {
        $this->reading->checkForAlert();
    }
}
