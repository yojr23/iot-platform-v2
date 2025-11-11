<?php

namespace App\Events;


use App\Models\Device;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DeviceStatusUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $device;

    public function __construct(Device $device)
    {
        $this->device = $device;
    }

    public function broadcastOn()
    {
        return new Channel('device-status');
    }

    public function broadcastWith()
    {
        return [
            'device_id' => $this->device->id,
            'status' => $this->device->status,
            'name' => $this->device->name,
            'classroom_name' => $this->device->classroom->name,
        ];
    }
}
