<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\SensorReading;
use App\Models\Alert;
use App\Mail\DangerAlertMail;
use App\Models\Sensor;
use App\Models\AlertRule;
use App\Models\SystemSetting;

class AlertEmailTest extends TestCase
{
    use RefreshDatabase;

    // Nota: la conexión de base de datos para tests se controla desde .env.testing o phpunit.xml

    protected function setUp(): void
    {
        parent::setUp();

        config(['mail.recipient_email' => 'alerts@example.test']);
    }

    public function testDangerAlertEmailIsSent()
    {
        Mail::fake();

        $sensorReading = SensorReading::factory()->create([
            'value' => 100,
        ]);

        $alertDetails = [
            'device' => $sensorReading->sensor->device->name,
            'location' => $sensorReading->sensor->device->lab->name,
            'sensor' => $sensorReading->sensor->name,
            'alert_message' => 'Valor fuera de rango',
            'value' => $sensorReading->value,
        ];

        $sent = Alert::sendDangerAlertEmail($alertDetails);

        // Asegurarnos de que la función intente enviar el correo (devuelve true cuando lo logra)
        $this->assertTrue($sent);
    }

    public function testEmailIsSentWhenDangerAlertRuleIsTriggered()
    {
        Mail::fake();

        $sensor = Sensor::factory()->create();

        AlertRule::create([
            'sensor_type_id' => $sensor->sensor_type_id,
            'device_id' => $sensor->device_id,
            'sensor_id' => $sensor->id,
            'min_value' => null,
            'max_value' => 50,
            'severity' => 'danger',
            'message' => 'Nivel peligroso detectado',
            'name' => 'Danger Rule',
        ]);

        SensorReading::factory()->create([
            'sensor_id' => $sensor->id,
            'value' => 80,
        ]);

        Mail::assertSent(DangerAlertMail::class, 1);
    }

    public function testEmailIsNotSentForNonDangerAlerts()
    {
        Mail::fake();

        $sensor = Sensor::factory()->create();

        AlertRule::create([
            'sensor_type_id' => $sensor->sensor_type_id,
            'device_id' => $sensor->device_id,
            'sensor_id' => $sensor->id,
            'min_value' => null,
            'max_value' => 50,
            'severity' => 'warning',
            'message' => 'Advertencia de nivel elevado',
            'name' => 'Warning Rule',
        ]);

        SensorReading::factory()->create([
            'sensor_id' => $sensor->id,
            'value' => 80,
        ]);

        Mail::assertNotSent(DangerAlertMail::class);
    }

    public function testSendDangerAlertUsesSystemSettingRecipient()
    {
        Mail::fake();

        SystemSetting::set('mail_to', 'danger@example.test', 'string', 'mail');

        $sensorReading = SensorReading::factory()->create();

        $alertDetails = [
            'device' => $sensorReading->sensor->device->name,
            'location' => $sensorReading->sensor->device->lab->name,
            'sensor' => $sensorReading->sensor->name,
            'alert_message' => 'Valor fuera de rango',
            'value' => $sensorReading->value,
        ];

        Alert::sendDangerAlertEmail($alertDetails);

        Mail::assertSent(DangerAlertMail::class, function ($mail) {
            return $mail->hasTo('danger@example.test');
        });
    }

    public function testDangerAlertEmailEscapesHtmlInTemplate(): void
    {
        Mail::fake();

        $alertDetails = [
            'device' => '<script>alert("xss-device")</script>',
            'location' => '<b>Lab Norte</b>',
            'sensor' => '<img src=x onerror=alert("xss-sensor")>',
            'alert_message' => '<iframe src="javascript:alert(1)"></iframe>',
            'value' => '150',
        ];

        Alert::sendDangerAlertEmail($alertDetails);

        Mail::assertSent(DangerAlertMail::class, function ($mail) {
            $html = $mail->render();

            return str_contains($html, '&lt;script&gt;alert(&quot;xss-device&quot;)&lt;/script&gt;')
                && str_contains($html, '&lt;b&gt;Lab Norte&lt;/b&gt;')
                && str_contains($html, '&lt;img src=x onerror=alert(&quot;xss-sensor&quot;)&gt;')
                && ! str_contains($html, '<script>alert("xss-device")</script>')
                && ! str_contains($html, '<img src=x onerror=alert("xss-sensor")>');
        });
    }
}
