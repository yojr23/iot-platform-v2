<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';

$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\Mail;
use App\Mail\DangerAlertMail;

Mail::send('emails.alert', [
    'device' => 'Sensor X',
    'location' => 'Sala 1',
    'sensor' => 'Temperatura',
    'alert_message' => 'Alerta de peligro',
    'value' => '100'
], function ($message) {
    $message->to(env('MAIL_TO'))
            ->subject('Prueba de correo');
});

echo "Correo enviado exitosamente.";
