<?php
// app/Services/DeviceService.php
namespace App\Services;

use App\Models\Device;
use Illuminate\Support\Facades\Log;

class DeviceService
{
    public function createDevice(array $data)
    {
        try {
            $device = Device::create($data);

            // Registrar el estado inicial

            $device->statusLogs()->create([
                'status' => $data['status'] ?? true,
                'changed_at' => now(),
            ]);
            return $device;
        } catch (\Exception $e) {
            Log::error('DeviceService Error: ' . $e->getMessage());
            return null;
        }
    }
}