<?php

namespace App\Listeners;

use App\Events\DeviceCommunicationReceived;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Throwable;

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
        try {
            $event->device->update(['last_communication' => now()]);
        } catch (Throwable $e) {
            Log::error('Failed to update device last communication', [
                'device_id' => $event->device->id ?? null,
                'exception' => $e->getMessage(),
            ]);
        }
    }
}
