<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SystemSettingsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $settings = [
            // Configuración de Email
            [
                'key' => 'mail_mailer',
                'value' => env('MAIL_MAILER', 'smtp'),
                'type' => 'string',
                'group' => 'mail',
                'description' => 'Driver de envío de correos',
                'is_public' => false,
            ],
            [
                'key' => 'mail_host',
                'value' => env('MAIL_HOST', 'smtp.gmail.com'),
                'type' => 'string',
                'group' => 'mail',
                'description' => 'Servidor SMTP',
                'is_public' => false,
            ],
            [
                'key' => 'mail_port',
                'value' => env('MAIL_PORT', '587'),
                'type' => 'integer',
                'group' => 'mail',
                'description' => 'Puerto del servidor SMTP',
                'is_public' => false,
            ],
            [
                'key' => 'mail_username',
                'value' => env('MAIL_USERNAME', ''),
                'type' => 'string',
                'group' => 'mail',
                'description' => 'Usuario del correo electrónico',
                'is_public' => false,
            ],
            [
                'key' => 'mail_password',
                'value' => env('MAIL_PASSWORD', ''),
                'type' => 'string',
                'group' => 'mail',
                'description' => 'Contraseña del correo electrónico',
                'is_public' => false,
            ],
            [
                'key' => 'mail_encryption',
                'value' => env('MAIL_ENCRYPTION', 'tls'),
                'type' => 'string',
                'group' => 'mail',
                'description' => 'Tipo de encriptación',
                'is_public' => false,
            ],
            [
                'key' => 'mail_from_address',
                'value' => env('MAIL_FROM_ADDRESS', ''),
                'type' => 'string',
                'group' => 'mail',
                'description' => 'Dirección de correo remitente',
                'is_public' => false,
            ],
            [
                'key' => 'mail_from_name',
                'value' => env('MAIL_FROM_NAME', 'SINOA'),
                'type' => 'string',
                'group' => 'mail',
                'description' => 'Nombre del remitente',
                'is_public' => false,
            ],
            [
                'key' => 'mail_to',
                'value' => env('MAIL_TO', ''),
                'type' => 'string',
                'group' => 'mail',
                'description' => 'Dirección de correo para alertas',
                'is_public' => false,
            ],
            [
                'key' => 'mail_enabled',
                'value' => env('MAIL_ENABLED', '1'),
                'type' => 'boolean',
                'group' => 'mail',
                'description' => 'Estado del sistema de envío de emails',
                'is_public' => false,
            ],

            // Configuración de Alertas
            [
                'key' => 'alert_threshold',
                'value' => '5',
                'type' => 'integer',
                'group' => 'alerts',
                'description' => 'Umbral de alerta en minutos (tiempo sin comunicación)',
                'is_public' => false,
            ],
            [
                'key' => 'sensor_update_interval',
                'value' => '2000',
                'type' => 'integer',
                'group' => 'alerts',
                'description' => 'Intervalo de actualización de sensores en milisegundos',
                'is_public' => false,
            ],
            [
                'key' => 'alert_sound_enabled',
                'value' => '1',
                'type' => 'boolean',
                'group' => 'alerts',
                'description' => 'Habilita sonido en el dashboard al dispararse alertas',
                'is_public' => false,
            ],

            // Configuración General
            [
                'key' => 'app_name',
                'value' => env('APP_NAME', 'SINOA'),
                'type' => 'string',
                'group' => 'general',
                'description' => 'Nombre de la aplicación',
                'is_public' => true,
            ],
            [
                'key' => 'app_url',
                'value' => env('APP_URL', 'http://localhost'),
                'type' => 'string',
                'group' => 'general',
                'description' => 'URL base de la aplicación',
                'is_public' => false,
            ],
        ];

        foreach ($settings as $setting) {
            DB::table('system_settings')->updateOrInsert(
                ['key' => $setting['key']],
                $setting
            );
        }
    }
}
