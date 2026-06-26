<?php

namespace App\Listeners;

use App\Events\DeviceCommunicationReceived;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class UpdateDeviceLastCommunication
{
    /**
     * Handle the event.
     *
     * @param DeviceCommunicationReceived $event
     * @return void
     */
    public function handle(DeviceCommunicationReceived $event)
    {
        $event->device->update(['last_communication' => now()]);
    }
}
