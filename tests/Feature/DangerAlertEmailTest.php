<?php

namespace Tests\Feature;

use App\Mail\DangerAlertMail;
use App\Models\AlertRule;
use App\Models\Device;
use App\Models\Sensor;
use App\Models\SensorReading;
use App\Models\SensorType;
use App\Models\SystemSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class DangerAlertEmailTest extends TestCase
{
    use RefreshDatabase;

    public function test_danger_alert_triggers_email_via_alert_observer(): void
    {
        Mail::fake();

        SystemSetting::set('mail_to', 'alerts@example.test');
        SystemSetting::set('mail_mailer', 'log');
        SystemSetting::set('mail_host', 'smtp.test');
        SystemSetting::set('mail_port', 2525);
        SystemSetting::set('mail_username', 'alerts@example.test');
        SystemSetting::set('mail_password', 'secret');
        SystemSetting::set('mail_encryption', 'tls');
        SystemSetting::set('mail_from_address', 'no-reply@example.test');
        SystemSetting::set('mail_from_name', 'SINOA Alerts');

        $sensorType = SensorType::factory()->create();
        $device = Device::factory()->create();
        $sensor = Sensor::factory()->create([
            'sensor_type_id' => $sensorType->id,
            'device_id' => $device->id,
        ]);

        AlertRule::create([
            'sensor_type_id' => $sensorType->id,
            'device_id' => $device->id,
            'sensor_id' => $sensor->id,
            'min_value' => null,
            'max_value' => 50,
            'severity' => 'danger',
            'message' => 'Valor peligroso detectado',
            'name' => 'Danger Rule',
        ]);

        $reading = SensorReading::factory()->create([
            'sensor_id' => $sensor->id,
            'value' => 80,
        ]);

        $reading->checkForAlert();

        Mail::assertSent(DangerAlertMail::class);
    }

    public function test_danger_alert_email_is_rate_limited_for_burst_events(): void
    {
        Mail::fake();

        SystemSetting::set('mail_to', 'alerts@example.test');
        SystemSetting::set('mail_mailer', 'log');
        SystemSetting::set('danger_email_rate_limit_seconds', 120, 'integer', 'alerts');

        $sensorType = SensorType::factory()->create();
        $device = Device::factory()->create();
        $sensor = Sensor::factory()->create([
            'sensor_type_id' => $sensorType->id,
            'device_id' => $device->id,
        ]);

        AlertRule::create([
            'sensor_type_id' => $sensorType->id,
            'device_id' => $device->id,
            'sensor_id' => $sensor->id,
            'min_value' => null,
            'max_value' => 50,
            'severity' => 'danger',
            'message' => 'Valor peligroso detectado',
            'name' => 'Danger Rule',
        ]);

        SensorReading::factory()->create([
            'sensor_id' => $sensor->id,
            'value' => 81,
        ]);

        SensorReading::factory()->create([
            'sensor_id' => $sensor->id,
            'value' => 82,
        ]);

        Mail::assertSent(DangerAlertMail::class, 1);
    }
}
