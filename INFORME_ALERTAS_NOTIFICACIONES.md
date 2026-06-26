# Informe de alertas y notificaciones

Fecha de análisis: 2026-05-08  
Proyecto: `iot-platform-v2`

## 1. Estructura general del proyecto

### Framework y stack 

- Backend principal: Laravel 12 / PHP 8.2 (`composer.json`).
- Autenticación web: Laravel UI (`Auth::routes(['verify' => true])`) y Laravel Sanctum para API.
- Frontend: Blade, Bootstrap, Chart.js, Vite, `pusher-js`, `laravel-echo`.
- Tiempo real: eventos Laravel broadcast hacia Pusher; también hay polling HTTP como fallback.
- Simulador externo: `script_datos.py`, Python, ingesta lecturas por API.
- Colas: Laravel Queue configurada con driver `database`, pero no hay jobs propios para alertas/correos.

### Árbol hasta dos niveles

```text
.
├── app
│   ├── Console
│   ├── Events
│   ├── Http
│   ├── Listeners
│   ├── Mail
│   ├── Models
│   ├── Notifications
│   ├── Observers
│   ├── Providers
│   └── Services
├── bootstrap
│   └── cache
├── config
├── database
│   ├── factories
│   ├── migrations
│   └── seeders
├── docs
│   └── api
├── public
│   ├── build
│   └── css
├── resources
│   ├── css
│   ├── js
│   ├── lang
│   ├── sass
│   └── views
├── routes
├── storage
│   ├── app
│   └── framework
├── tests
│   ├── Feature
│   └── Unit
├── tests_python
└── alertas_notiifcaciones
```

### Propósito aparente por directorio

- `app/Http/Controllers`: controladores web y API. Aquí están los puntos de entrada de dashboard, alertas, reglas, sensores, configuración y autenticación.
- `app/Services`: lógica de aplicación. `Alerts/AlertService.php` evalúa reglas y crea alertas; `Notifications/NotificationService.php` coordina broadcast y correo.
- `app/Observers`: side effects de Eloquent. `SensorReadingObserver` evalúa alertas al crear lecturas; `AlertObserver` dispara notificaciones al crear alertas.
- `app/Events`: eventos broadcast para lecturas, alertas y estado de dispositivos.
- `app/Mail` y `app/Notifications`: correos propios de alerta y notificaciones de Laravel para verificación/restablecimiento.
- `app/Models`: modelos Eloquent de telemetría, alertas, usuarios y configuración.
- `database/migrations`: esquema de usuarios, sensores, reglas, alertas, configuración y colas.
- `resources/views`: Blade web. Contiene dashboard, vistas de alertas, configuración SMTP y plantilla HTML de correo.
- `resources/js`: bootstrap JS/Vite; la mayor parte del JS operativo está inline en Blade.
- `routes`: rutas web, API y consola.
- `tests`: pruebas de reglas, correos de alerta, verificación/restablecimiento y API.

## 2. Funcionalidades de alertas y notificaciones

### 2.1 Evaluación de reglas por lectura de sensor

Implementación principal:

- `app/Http/Controllers/Api/SensorApiController.php`
  - `storeReading(Request $request, Sensor $sensor)` delega a `store`.
  - `store(Request $request, Sensor $sensor)` valida API key, crea `SensorReading` con `$sensor->readings()->create(...)` y emite `NewSensorReading`.
- `app/Observers/SensorReadingObserver.php`
  - `created(SensorReading $sensorReading)` llama `$sensorReading->checkForAlert()`.
- `app/Models/SensorReading.php`
  - `triggeredAlertRules()` resuelve `AlertService`.
  - `checkForAlert()` resuelve `AlertService::createAlertsForReading`.
- `app/Services/Alerts/AlertService.php`
  - `triggeredRulesForReading(SensorReading $reading)` filtra reglas por tipo de sensor, dispositivo opcional, sensor opcional y umbrales.
  - `createAlertsForReading(SensorReading $reading)` crea registros en `alerts` y evita duplicados por `(sensor_reading_id, alert_rule_id)`.

Fragmento clave:

```php
$belowMin = $minDefined && $reading->value <= $alertRule->min_value;
$aboveMax = $maxDefined && $reading->value >= $alertRule->max_value;

return $belowMin || $aboveMax;
```

Observaciones:

- Los umbrales son inclusivos (`<= min_value`, `>= max_value`).
- La vista dice "menor a este valor" y "supera este valor", pero el backend dispara también si el valor es exactamente igual al umbral.
- Las reglas aplican por `sensor_type_id` y pueden restringirse por `device_id` y/o `sensor_id`.
- `alert_threshold` en configuración se describe como umbral de minutos sin comunicación, pero no se usa para generar alertas en ningún job o servicio actual.

### 2.2 Creación de alerta y notificación en tiempo real

Implementación:

- `app/Models/Alert.php`
  - Modelo de persistencia de alerta; define scopes `withContext`, `active`, `resolved`.
- `app/Observers/AlertObserver.php`
  - `created(Alert $alert)` limpia caché de dashboard, llama `broadcastNewAlert` y luego `notifyDangerAlertByEmail`.
- `app/Services/Notifications/NotificationService.php`
  - `broadcastNewAlert(Alert $alert)` ejecuta `event(new NewAlertTriggered($alert))`.
- `app/Events/NewAlertTriggered.php`
  - `ShouldBroadcastNow`.
  - Canal público: `alerts`.
  - Payload: id, mensaje, severidad, valor, sensor, tipo, unidad, dispositivo, laboratorio, timestamp.

Fragmento clave:

```php
public function created(Alert $alert): void
{
    $this->clearDashboardAlertCaches();
    $this->notificationService->broadcastNewAlert($alert);
    $this->notificationService->notifyDangerAlertByEmail($alert);
}
```

Dónde se consume:

- `resources/views/layouts/app.blade.php`
  - Inicializa `window.pusher`.
  - Suscribe al canal `alerts`.
  - Procesa `App\\Events\\NewAlertTriggered`.
  - Actualiza badges, reproduce sonido, muestra popup y hace polling a `/api/alerts/active`.
- `resources/views/dashboard.blade.php`
  - Consume `window.AppAlerts.subscribe(updateAlertsUI)`.
  - Tiene fallback por polling a `/api/alerts/active` cada 5 segundos si el módulo global no existe.
- `resources/views/dashboard/partials/alerts-card.blade.php`
  - Render inicial de últimas alertas activas.
- `resources/views/layouts/partials/navbar.blade.php`
  - Badge de alertas no resueltas.

### 2.3 Correo de alerta de peligro

Implementación:

- `app/Services/Notifications/NotificationService.php`
  - `notifyDangerAlertByEmail(Alert $alert)` solo envía si `alertRule.severity === danger`.
  - Carga relaciones: `alertRule`, `sensorReading.sensor.sensorType`, `sensorReading.sensor.device.lab`.
  - Aplica rate limit con `Cache::add` usando key `danger_alert_email:{rule}:{sensor}:{device}`.
  - Construye DTO ad hoc `$alertDetails`.
  - Llama `Alert::sendDangerAlertEmail($alertDetails)`.
- `app/Models/Alert.php`
  - `sendDangerAlertEmail($alertDetails)` arma datos, resuelve destinatario, muta configuración global `config(['mail' => ...])`, instancia `DangerAlertMail` y ejecuta `Mail::send`.
- `app/Mail/DangerAlertMail.php`
  - `build()` usa `view('emails.alert')` y subject `Alerta de Peligro Detectada`.
- `resources/views/emails/alert.blade.php`
  - Plantilla HTML de alerta con dispositivo, ubicación, sensor, tipo, regla, umbrales, valor, fecha y mensaje.

Destinatario:

1. `SystemSetting::get('mail_to')`
2. `config('mail.recipient_email')`
3. `MAIL_TO_ALERT`
4. `MAIL_TO`
5. `RECIPIENT_EMAIL`
6. `recipient_email`

Punto importante: `mail_enabled` existe en configuración y UI, pero `Alert::sendDangerAlertEmail` y `NotificationService::notifyDangerAlertByEmail` no lo consultan. Hoy una alerta `danger` puede enviar correo aunque el toggle de correo esté desactivado.

### 2.4 Correo de restablecimiento de contraseña

Implementación:

- `routes/web.php`
  - `Auth::routes(['verify' => true])` registra rutas de auth, incluyendo password reset.
- `app/Http/Controllers/Auth/ForgotPasswordController.php`
  - Usa `SendsPasswordResetEmails`.
- `app/Models/User.php`
  - `sendPasswordResetNotification($token)` llama `$this->notify(new ResetPasswordNotification($token))`.
- `app/Notifications/ResetPasswordNotification.php`
  - Extiende `Illuminate\Auth\Notifications\ResetPassword`.
  - Sobrescribe `buildMailMessage($url)` en español.
- `database/migrations/0001_01_01_000000_create_users_table.php`
  - Crea `password_reset_tokens` con `email`, `token`, `created_at`.

Dónde se invoca:

- Desde el flujo estándar de Laravel UI: formulario Blade `resources/views/auth/passwords/email.blade.php`, ruta `password.email`, trait `SendsPasswordResetEmails`, modelo `User`.

### 2.5 Correo de verificación de usuario

Implementación:

- `app/Models/User.php`
  - Implementa `MustVerifyEmail`.
  - Usa `Notifiable`.
  - `sendEmailVerificationNotification()` llama `$this->notify(new VerifyEmailNotification())`.
- `app/Notifications/VerifyEmailNotification.php`
  - Extiende `Illuminate\Auth\Notifications\VerifyEmail`.
  - Sobrescribe `buildMailMessage($url)` en español.
- `app/Providers/EventServiceProvider.php`
  - `Registered::class => SendEmailVerificationNotification::class`.
- `app/Http/Controllers/Auth/RegisterController.php`
  - Crea usuarios y redirige a `verification.notice` si no están verificados.
  - Restringe dominios de correo a `gmail.com`, `hotmail.com`, `outlook.com`, `unab.edu.co`.

Dónde se invoca:

- Desde `POST /register` por Laravel UI. El evento `Registered` dispara la notificación estándar que termina usando el override del modelo.

### 2.6 Email de prueba y configuración SMTP

Implementación:

- `app/Http/Controllers/EmailConfigController.php`
  - `index()` lee settings SMTP desde `SystemSetting`.
  - `update()` guarda mailer, host, port, username, password, encryption, from y `mail_to`.
  - `testEmail()` arma configuración dinámica y envía `Mail::raw(...)`.
- `resources/views/config/email_config.blade.php`
  - Formulario SMTP, credenciales, remitente, destinatario de alertas y modal de prueba.
- `send_test_email.php`
  - Script manual fuera de rutas; usa `Mail::send('emails.alert', ...)`.

Observación: `EmailConfigController::testEmail` usa una forma de configuración antigua (`driver`, `host`, etc.) y sobrescribe `config(['mail' => $mailSettings])`, mientras que `Alert::sendDangerAlertEmail` usa `mail.default`, `mail.from` y `mail.mailers.smtp`. Hay dos estilos de configuración dinámica.

### 2.7 SMS, push u otros canales

No se encontró implementación de SMS ni push nativo móvil. `config/services.php` contiene entradas genéricas para Postmark, SES, Resend y Slack, pero el código actual solo usa correo Laravel y Pusher para eventos web en tiempo real.

## 3. Colas, jobs y programación

### Colas

- `config/queue.php` define `QUEUE_CONNECTION=database` por defecto.
- `database/migrations/0001_01_01_000002_create_jobs_table.php` crea `jobs`, `job_batches`, `failed_jobs`.
- `composer.json` define script `dev` que levanta `php artisan queue:listen --tries=1`.

Hallazgos:

- No existen clases en `app/Jobs`.
- No hay `dispatch(...)`, `Bus::...` ni jobs de alertas.
- `NewAlertTriggered` y `NewSensorReading` implementan `ShouldBroadcastNow`, por lo que no dependen de worker de cola.
- `DeviceStatusUpdated` implementa `ShouldBroadcast`, pero no se encontró ningún dispatch de este evento.
- `UpdateDeviceLastCommunication` importa `ShouldQueue`, pero la clase no lo implementa.
- El envío de correo de alerta ocurre de forma síncrona en el observer de Eloquent.

### Jobs programados

- `routes/console.php` solo registra `inspire`.
- No se encontró schedule para evaluar umbrales ni comunicación caída.
- `app/Console/Commands/PurgeFutureSensorReadings.php` purga lecturas futuras, pero no genera alertas.

## 4. Dependencias y acoplamientos

### Acoplamiento backend

- `SensorReading` conoce `AlertService` vía `app(AlertService::class)`. El modelo de telemetría invoca lógica de dominio de alertas.
- `SensorReadingObserver` ejecuta reglas cada vez que se crea una lectura. El punto de separación real está en eventos/observers, no en controladores.
- `AlertObserver` mezcla invalidación de caché de dashboard, broadcast y correo. Es un punto de orquestación con tres responsabilidades.
- `NotificationService` depende de `Alert`, `SystemSetting`, `Cache`, `Log` y del evento `NewAlertTriggered`.
- `Alert::sendDangerAlertEmail` introduce side effects en el modelo: lee configuración, muta `config()`, construye mailable y llama `Mail::send`.
- `AlertService` depende de modelos de telemetría (`SensorReading`, `AlertRule`, relaciones `Sensor`, `Device`, `Lab`) para decidir reglas.
- `DashboardMetricsService` depende de `AlertService` para conteos y listas de alertas.

### Acoplamiento frontend/visualización

- `resources/views/layouts/app.blade.php` consulta `SystemSetting` directamente para `alert_sound_enabled`.
- El layout global crea `window.AppAlerts`, maneja Pusher, polling, audio, popups y badges.
- `resources/views/dashboard.blade.php` consume `window.AppAlerts`, pero también conserva fallback propio de polling y renderizado de alertas.
- La vista de sensores `resources/views/sensors/index.blade.php` instancia Pusher por su cuenta y se suscribe a canales `sensor.{id}`.
- Las vistas acceden a relaciones profundas de alerta (`$alert->sensorReading->sensor->device->lab`) para renderizar.

### Modelos mezclados o compartidos

- `alerts` no guarda snapshot de contexto; depende de `sensor_readings`, `alert_rules`, `sensors`, `devices`, `labs` para reconstruir historial.
- `alert_rules` referencia directamente `sensor_types`, `devices`, `sensors`.
- `system_settings` mezcla `mail`, `alerts` y `general`; sirve como configuración global transversal.
- `User` no pertenece al dominio de alertas, pero sí al dominio de notificaciones de cuenta por email.

## 5. Modelos de datos y migraciones

### Dominio claro de alertas/notificaciones

`alert_rules`

- Migración base: `database/migrations/2025_04_29_134330_create_alert_rules_table.php`.
- Campos: `id`, `sensor_type_id`, `min_value`, `max_value`, `severity`, `message`, `name`, timestamps.
- Migraciones posteriores:
  - `2025_06_03_000001_update_alert_rules_table.php`: cambia default de `name`.
  - `2025_06_03_000002_update_alert_rules_severity.php`: default `severity = info`.
  - `2025_06_04_120000_add_device_and_sensor_to_alert_rules_table.php`: agrega `device_id`, `sensor_id` nullable con `cascadeOnDelete`.

`alerts`

- Migración: `database/migrations/2025_04_29_134330_create_alerts_table.php`.
- Campos: `id`, `sensor_reading_id`, `alert_rule_id`, `resolved`, `resolved_at`, timestamps.
- No guarda severidad, mensaje, valor ni destinatario como snapshot; todo se reconstruye por relaciones.

`system_settings`

- Migración: `database/migrations/2025_11_11_020348_create_system_settings_table.php`.
- Campos: `key`, `value`, `type`, `group`, `description`, `is_public`.
- Contiene configuración de mail y alertas, pero no es exclusiva del dominio.

`password_reset_tokens`

- Creada junto a `users`.
- Pertenece a notificaciones de cuenta/restablecimiento, no a alertas IoT.

### Modelos que habría que duplicar/sincronizar al separar

- `User`: necesario para login, verificación y restablecimiento si el módulo de notificaciones asume destinatarios de usuarios.
- `SensorReading`: origen del evento que dispara reglas; al separar conviene publicar un evento/DTO de lectura en vez de compartir tabla directamente.
- `Sensor`, `SensorType`, `Device`, `Lab`: necesarios para contexto de reglas y mensaje. Podrían sincronizarse como catálogo mínimo o resolverse vía API.
- `AlertRule`: probablemente pertenece al módulo nuevo de alertas.
- `Alert`: pertenece al módulo nuevo, pero necesita snapshot para preservar historial independiente.
- `SystemSetting`: separar en configuración propia del módulo de notificaciones/alertas o migrar a variables/secret manager.

## 6. Configuración y servicios externos

### Archivos

- `config/mail.php`: mailers Laravel (`smtp`, `ses`, `postmark`, `resend`, `sendmail`, `log`, `array`, `failover`, `roundrobin`). También expone compatibilidad `host`, `port`, `username`, `password`, `encryption`.
- `config/services.php`: tokens para Postmark, SES, Resend y Slack, pero no hay uso activo de esos canales en alertas.
- `.env.example`: documenta `MAIL_*`, aliases legacy `SMTP_SERVER`, `EMAIL_USER`, `EMAIL_PASS`, `BROADCAST_DRIVER`, `PUSHER_*`, `RECIPIENT_EMAIL`.
- `.env`: contiene valores reales para `MAIL_*`, `PUSHER_*`, `QUEUE_CONNECTION`, `CACHE_STORE`, `REDIS_*`, `API_KEY`. No se reproducen aquí por seguridad.
- `.env.testing`: define `MAIL_MAILER` y `RECIPIENT_EMAIL` para pruebas.
- `database/seeders/SystemSettingsSeeder.php`: contiene valores SMTP y destinatarios por defecto. Hay credenciales reales en texto plano; deben rotarse y retirarse del repositorio.

### Plantillas de correo

- `resources/views/emails/alert.blade.php`: plantilla HTML de alerta de peligro.
- Las notificaciones `ResetPasswordNotification` y `VerifyEmailNotification` usan `MailMessage` de Laravel, no plantillas Blade propias.

### Terceros

- SMTP/Gmail: configurado por UI y seeders.
- Pusher: usado para canales públicos de alertas y lecturas.
- SES/Postmark/Resend: configurables por Laravel, pero no hay integración específica en código de aplicación.
- Slack/SMS/push: no implementados.

## 7. Puntos de entrada

### Web

- `GET /alerts` -> `AlertController@index`.
- `GET /alerts/unresolved` -> `AlertController@unresolved`.
- `PUT /alerts/{alert}/resolve` -> `AlertController@resolve`.
- `POST /alerts/mark-all-resolved` -> `AlertController@markAllAsResolved`.
- `GET /alert-rules/create` -> `AlertRuleController@create`.
- `POST /alert-rules` -> `AlertRuleController@store`.
- `DELETE /alert-rules/{alertRule}` -> `AlertRuleController@destroy`.
- `GET /config` -> `ConfigController@index`.
- `POST /config` -> `ConfigController@update`.
- `GET /email-config` -> `EmailConfigController@index`.
- `PUT /email-config` -> `EmailConfigController@update`.
- `POST /email-config/test` -> `EmailConfigController@testEmail`.
- Auth Laravel UI: registro, login, verificación de email, restablecimiento y confirmación de contraseña.

### API

- `POST /api/sensors/{sensor}/readings` -> `SensorApiController@storeReading`; punto de inicio de alertas por telemetría.
- `GET /api/iot/sensors` -> `SensorApiController@iotIndex`.
- `GET /api/alerts/active` -> `AlertFeedController@active`; usado por dashboard/layout.
- `GET /api/sensors/{sensor}/latest-readings` -> `SensorApiController@latestReadings`; dashboard/monitores.
- `GET /api/devices/{device}/sensors` -> `DashboardController@getSensors`.
- `GET/POST/DELETE /api/alert-rules...` -> `AlertRuleController`, protegido por Sanctum/admin. Nota: algunas acciones devuelven vistas/redirects, no JSON.

### Websockets/eventos

- `NewAlertTriggered` -> canal `alerts`, evento `App\\Events\\NewAlertTriggered`.
- `NewSensorReading` -> canal `sensor.{sensor_id}`, evento `App\\Events\\NewSensorReading`.
- `DeviceStatusUpdated` -> canal `device-status`, pero no se encontró invocación.
- `DeviceCommunicationReceived` no se broadcast; lo consume `UpdateDeviceLastCommunication`.

## 8. Flujo actual de interacción

1. `script_datos.py` descubre sensores en `GET /api/iot/sensors` y publica lecturas en `POST /api/sensors/{sensor}/readings`.
2. `SensorApiController@store` valida dispositivo activo, API key y payload.
3. El controlador crea `SensorReading`.
4. `SensorReadingObserver@created` llama `$sensorReading->checkForAlert()`.
5. `SensorReading::checkForAlert()` resuelve `AlertService`.
6. `AlertService::triggeredRulesForReading()` busca reglas aplicables e inclusivamente compara min/max.
7. `AlertService::createAlertsForReading()` crea `Alert` por cada regla disparada y evita duplicados para esa lectura/regla.
8. `AlertObserver@created` borra caches de dashboard.
9. El mismo observer emite `NewAlertTriggered` vía `NotificationService::broadcastNewAlert`.
10. El navegador autenticado recibe el evento por Pusher en canal `alerts`; `window.AppAlerts` actualiza badges, reproduce sonido y muestra popup.
11. Dashboard actualiza tarjeta/listado desde `window.AppAlerts` o por polling a `/api/alerts/active`.
12. Si la severidad es `danger`, `NotificationService::notifyDangerAlertByEmail` aplica rate limit y llama `Alert::sendDangerAlertEmail`.
13. `Alert::sendDangerAlertEmail` arma configuración SMTP dinámica, crea `DangerAlertMail` y envía el correo.

## 9. Puntos de dolor para separación

1. Envío de correo dentro del modelo `Alert`: el modelo tiene side effects, muta configuración global y conoce infraestructura SMTP.
2. Email síncrono en observer: el request de ingesta puede pagar el costo del correo; no hay job, retry ni dead-letter específico.
3. `mail_enabled` no se respeta: existe toggle y setting, pero no gobierna el envío real.
4. Configuración y secretos en base de datos/seeders: `SystemSettingsSeeder` incluye credenciales sensibles en texto plano. Esto bloquea una separación segura si se replica tal cual.
5. `SystemSetting` es un saco global: mezcla mail, alertas y configuración general; el módulo nuevo necesitaría límites claros.
6. Alertas sin snapshot: `alerts` depende de reglas/lecturas/sensores/dispositivos/labs vivos. Separar el módulo exigiría sincronizar varias tablas o almacenar snapshots/eventos.
7. Lógica de dominio desde modelo de telemetría: `SensorReading::checkForAlert()` acopla telemetría a alertas por service locator.
8. Observer con muchas responsabilidades: `AlertObserver` invalida caché, broadcast y correo en un solo hook.
9. Frontend inline y global: `layouts/app.blade.php` contiene el motor global de alertas; `dashboard.blade.php` contiene render y fallback. Separar exige un cliente JS modular o contrato de API/eventos estable.
10. Pusher configurado en Blade con `env()`: no hay frontera limpia de configuración frontend; además no se observó `config/broadcasting.php` en el repositorio.
11. Reglas admin web/API comparten controlador orientado a Blade: rutas API de `alert-rules` pueden devolver vistas/redirects.
12. Variables de configuración no usadas: `alert_threshold` y `sensor_update_interval` se configuran en UI, pero no alimentan jobs/servicios de alertas ni el intervalo real hardcodeado del dashboard.
13. Evento `NewSensorReading::handle()` contiene una llamada a `checkForAlert()`, pero el evento no se usa como listener; parece código muerto o una intención antigua.
14. `DeviceStatusUpdated` existe como broadcast, pero no se invoca; puede confundir el mapa de eventos.
15. Caché de dashboard con keys fijas: `AlertObserver` borra `dashboard:active_alerts_list:10` y `:20`, pero otros límites quedarían obsoletos.

## 10. Recomendaciones de frontera para el módulo independiente

- Crear un contrato de evento de entrada: `SensorReadingRecorded` con `reading_id`, `sensor_id`, `sensor_type_id`, `device_id`, `lab_id`, `value`, `reading_time` y contexto mínimo.
- Mover `AlertService` y modelos `AlertRule`/`Alert` al módulo de alertas, evitando que `SensorReading` invoque alertas directamente.
- Sustituir `Alert::sendDangerAlertEmail` por un servicio o job `SendDangerAlertEmail` con interfaz `AlertNotifier`.
- Encolar correo y broadcasts no críticos; agregar retries e idempotencia por `alert_id`.
- Persistir snapshot en `alerts`: severidad, mensaje, valor, unidad, sensor/device/lab names, regla, umbrales, detected_at, notification_status.
- Separar configuración: `notification_settings` o proveedor de configuración por módulo; secretos fuera de seeders y repo.
- Hacer cumplir `mail_enabled` en la capa de notificación.
- Extraer `window.AppAlerts` a `resources/js/modules/app-alerts.js` y compilar con Vite; dejar Blade solo como consumidor de datos iniciales.
- Definir API JSON para reglas de alerta si el módulo se administrará externamente.
- Agregar un job programado real si se requiere alerta por falta de comunicación usando `alert_threshold`.

## 11. Archivos clave para refactorización

- Backend core: `app/Services/Alerts/AlertService.php`, `app/Services/Notifications/NotificationService.php`, `app/Observers/SensorReadingObserver.php`, `app/Observers/AlertObserver.php`, `app/Models/Alert.php`, `app/Models/AlertRule.php`, `app/Models/SensorReading.php`.
- Entrada IoT/API: `app/Http/Controllers/Api/SensorApiController.php`, `routes/api.php`.
- UI y tiempo real: `resources/views/layouts/app.blade.php`, `resources/views/dashboard.blade.php`, `resources/views/dashboard/partials/alerts-card.blade.php`, `resources/views/sensors/index.blade.php`.
- Email: `app/Mail/DangerAlertMail.php`, `resources/views/emails/alert.blade.php`, `app/Http/Controllers/EmailConfigController.php`.
- Usuarios: `app/Models/User.php`, `app/Notifications/ResetPasswordNotification.php`, `app/Notifications/VerifyEmailNotification.php`, `app/Http/Controllers/Auth/*`.
- Configuración: `config/mail.php`, `config/queue.php`, `config/services.php`, `.env.example`, `.env.testing`, `database/seeders/SystemSettingsSeeder.php`.
- Migraciones: `2025_04_29_134330_create_alert_rules_table.php`, `2025_04_29_134330_create_alerts_table.php`, `2025_06_04_120000_add_device_and_sensor_to_alert_rules_table.php`, `2025_11_11_020348_create_system_settings_table.php`, `0001_01_01_000000_create_users_table.php`, `0001_01_01_000002_create_jobs_table.php`.
- Pruebas existentes útiles: `tests/Unit/SensorReadingAlertTest.php`, `tests/Unit/SensorReadingTriggeredRulesTest.php`, `tests/Feature/DangerAlertEmailTest.php`, `tests/Feature/AlertEmailTest.php`, `tests/Feature/AlertRuleValidationTest.php`, `tests/Feature/PasswordResetTest.php`, `tests/Feature/RegistrationEmailVerificationTest.php`.
