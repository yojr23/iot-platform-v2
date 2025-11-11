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
                'value' => 'smtp',
                'type' => 'string',
                'group' => 'mail',
                'description' => 'Driver de envío de correos',
                'is_public' => false,
            ],
            [
                'key' => 'mail_host',
                'value' => 'smtp.gmail.com',
                'type' => 'string',
                'group' => 'mail',
                'description' => 'Servidor SMTP',
                'is_public' => false,
            ],
            [
                'key' => 'mail_port',
                'value' => '587',
                'type' => 'integer',
                'group' => 'mail',
                'description' => 'Puerto del servidor SMTP',
                'is_public' => false,
            ],
            [
                'key' => 'mail_username',
                'value' => 'juniorrincon1992@gmail.com',
                'type' => 'string',
                'group' => 'mail',
                'description' => 'Usuario del correo electrónico',
                'is_public' => false,
            ],
            [
                'key' => 'mail_password',
                'value' => 'wxnszzchlpqkogre',
                'type' => 'string',
                'group' => 'mail',
                'description' => 'Contraseña del correo electrónico',
                'is_public' => false,
            ],
            [
                'key' => 'mail_encryption',
                'value' => 'tls',
                'type' => 'string',
                'group' => 'mail',
                'description' => 'Tipo de encriptación',
                'is_public' => false,
            ],
            [
                'key' => 'mail_from_address',
                'value' => 'juniorrincon1992@gmail.com',
                'type' => 'string',
                'group' => 'mail',
                'description' => 'Dirección de correo remitente',
                'is_public' => false,
            ],
            [
                'key' => 'mail_from_name',
                'value' => 'SINOA',
                'type' => 'string',
                'group' => 'mail',
                'description' => 'Nombre del remitente',
                'is_public' => false,
            ],
            [
                'key' => 'mail_to',
                'value' => 'juniorrincon1992@hotmail.com',
                'type' => 'string',
                'group' => 'mail',
                'description' => 'Dirección de correo para alertas',
                'is_public' => false,
            ],
            [
                'key' => 'mail_enabled',
                'value' => '1',
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

            // Configuración General
            [
                'key' => 'app_name',
                'value' => 'SINOA',
                'type' => 'string',
                'group' => 'general',
                'description' => 'Nombre de la aplicación',
                'is_public' => true,
            ],
            [
                'key' => 'app_url',
                'value' => 'http://localhost',
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
