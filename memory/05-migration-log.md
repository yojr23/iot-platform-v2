# Migration Log

## 2026-05-12

### Estado

Se creo memoria tecnica del refactor. No se modifico logica de aplicacion.

### Cambios realizados

- Creada carpeta `/memory`.
- Documentado contexto del proyecto.
- Documentadas decisiones arquitectonicas.
- Documentadas reglas de refactor.
- Documentado contrato API inicial.
- Documentados riesgos pendientes.
- Documentadas notas operativas para futuras sesiones de Codex.

### Archivos modificados

- `memory/00-project-context.md`
- `memory/01-architecture-decisions.md`
- `memory/02-current-audit.md`
- `memory/03-refactor-rules.md`
- `memory/04-api-contract.md`
- `memory/05-migration-log.md`
- `memory/06-pending-risks.md`
- `memory/07-codex-working-notes.md`

### Pendiente

- Validar contrato API contra `routes/api.php` y `routes/web.php`.
- Decidir oficialmente si el frontend sera Vue 3 + Bootstrap 5 o Vite JS modular + Bootstrap.
- Definir estrategia exacta de autenticacion: Sanctum token Bearer vs Sanctum cookie SPA.
- Crear plan de Fase 0 ejecutable.

## 2026-05-12 - Fase 0

### Estado

Se ejecuto auditoria read-only de Fase 0 y se actualizo la memoria persistente. No se modifico logica de aplicacion.

### Cambios realizados

- Leidos todos los archivos existentes de `/memory`.
- Ejecutado `php artisan route:list`; se detectaron 92 rutas.
- Listadas 43 vistas Blade bajo `resources/views`.
- Auditados retornos `view()`, `redirect()`, `back()` y `response()->json`.
- Revisadas rutas en `routes/web.php` y `routes/api.php`.
- Revisados `package.json`, `vite.config.js` y `composer.json`.
- Actualizado contrato API con inventario real de rutas API y brechas para SPA.
- Actualizados riesgos pendientes con hallazgos de CORS, Pusher, rutas API problematicas y auth.
- Creado checklist persistente de refactor.
- Creado estado funcional persistente.
- Creado registro de progreso de sesion.

### Archivos modificados

- `memory/04-api-contract.md`
- `memory/05-migration-log.md`
- `memory/06-pending-risks.md`
- `memory/07-codex-working-notes.md`
- `memory/08-refactor-checklist.md`
- `memory/09-session-progress.md`
- `memory/10-functional-state.md`

### Hallazgos principales

- El proyecto mantiene 92 rutas Laravel, incluyendo rutas web Blade y rutas API parciales.
- Hay 43 vistas Blade.
- `config/cors.php` no existe actualmente.
- Las rutas API de `alert-rules` usan un controlador web que retorna vistas o redirecciones.
- La API de autenticacion solo cubre login, me y logout.
- No existe `/front` ni `/back` fisico todavia.
- No existen Dockerfiles ni `docker-compose.yml`.

### Pendiente

- Fase 1 debe decidir oficialmente estrategia de autenticacion SPA.
- Fase 1 debe crear endpoints headless faltantes para registro, reset y verificacion si se requieren.
- Fase 2 debe reemplazar rutas API problematicas que retornan vistas/redirecciones.

## 2026-05-12 - Fase 1

### Estado

Se implemento autenticacion headless base con Sanctum Bearer tokens. La suite completa de pruebas pasa.

### Cambios realizados

- Definida estrategia inicial de auth SPA: Sanctum Bearer token.
- Ajustado `AuthApiController` para endpoints headless:
  - `POST /api/auth/register`
  - `POST /api/auth/login`
  - `GET /api/auth/me`
  - `POST /api/auth/logout`
  - `POST /api/auth/forgot-password`
  - `POST /api/auth/reset-password`
  - `GET /api/auth/verify-email/{id}/{hash}`
  - `POST /api/auth/resend-verification`
- Login API ahora rechaza usuarios no verificados con HTTP 403.
- Registro API crea usuario no verificado, dispara `Registered`, devuelve token Bearer y payload seguro de usuario.
- Reset password usa `FRONT_URL` para generar enlaces hacia la futura SPA.
- Verificacion de email usa una URL firmada bajo `/api/auth/verify-email/{id}/{hash}`.
- Agregado `config/cors.php` para permitir frontend local por `FRONT_URL`/`CORS_ALLOWED_ORIGINS`.
- Agregado `front_url` en `config/app.php`.
- Documentadas variables `FRONT_URL` y `CORS_ALLOWED_ORIGINS` en `.env.example`.
- Creada prueba de feature `AuthApiHeadlessTest`.
- Corregido `DashboardMetricsServiceTest` para resolver `DashboardMetricsService` desde el contenedor e inyectar `AlertService`.

### Archivos modificados

- `.env.example`
- `app/Http/Controllers/Api/AuthApiController.php`
- `app/Notifications/ResetPasswordNotification.php`
- `app/Notifications/VerifyEmailNotification.php`
- `config/app.php`
- `config/cors.php`
- `routes/api.php`
- `tests/Feature/AuthApiHeadlessTest.php`
- `tests/Unit/DashboardMetricsServiceTest.php`
- `memory/01-architecture-decisions.md`
- `memory/04-api-contract.md`
- `memory/05-migration-log.md`
- `memory/06-pending-risks.md`
- `memory/07-codex-working-notes.md`
- `memory/08-refactor-checklist.md`
- `memory/09-session-progress.md`
- `memory/10-functional-state.md`

### Pruebas

- Primero se confirmo ciclo rojo con `php artisan test tests/Feature/AuthApiHeadlessTest.php`: 7 fallos esperados por endpoints faltantes/comportamiento pendiente.
- Luego paso `php artisan test tests/Feature/AuthApiHeadlessTest.php`: 7 tests, 27 assertions.
- Paso suite auth relacionada: `php artisan test tests/Feature/AuthApiHeadlessTest.php tests/Feature/ApiAuthTokenTest.php tests/Feature/RegistrationEmailVerificationTest.php tests/Feature/PasswordResetTest.php`: 15 tests, 60 assertions.
- Paso `php artisan test tests/Unit/DashboardMetricsServiceTest.php`: 3 tests, 8 assertions.
- Paso suite completa `php artisan test`: 83 tests, 240 assertions.

### Pendiente

- Fase 2 debe normalizar APIs funcionales que hoy siguen ligadas a controladores web o Blade.
- Fase 3 debe definir almacenamiento del Bearer token en la SPA minimizando riesgo XSS.

## 2026-05-12 - Fase 2

### Estado

Se implementaron y normalizaron endpoints API JSON para reemplazar dependencias Blade pantalla por pantalla en una futura SPA. No se eliminaron vistas Blade, no se movieron carpetas, no se creo `/front`, no se creo `/back` y no se crearon Dockerfiles.

### Validacion previa de Fase 1

- `php artisan route:list` ejecuto correctamente y reporto 97 rutas antes de Fase 2.
- `php artisan route:list --path=api/auth` mostro 8 rutas auth API.
- `php -l` no reporto errores en los archivos de Fase 1.
- Suite auth relacionada paso con 15 tests y 60 assertions.
- Suite completa previa paso con 83 tests y 240 assertions.
- No se detectaron errores criticos de Fase 1.

### Cambios realizados

- Creado `GET /api/health`.
- Creado `GET /api/dashboard/metrics`.
- Agregados endpoints API para dashboard preferences: `GET` y `PUT /api/dashboard/preferences`.
- Agregado `GET /api/sensors/{sensor}`.
- Reordenado y conectado `GET /api/sensors/all/readings` a `SensorApiController@allReadings`.
- Normalizados `GET /api/devices/{device}/sensors` y `/sensor-list` hacia `DeviceApiController@sensors`.
- Creado controlador API dedicado para alertas con:
  - `GET /api/alerts`
  - `GET /api/alerts/unresolved`
  - `GET /api/alerts/active`
  - `PATCH /api/alerts/{alert}/resolve`
  - `POST /api/alerts/resolve-all`
- Creado controlador API dedicado para reglas de alerta con CRUD JSON.
- Conservados aliases de compatibilidad `/api/alert-rules/create` y `/api/alert-rules/store`, ahora devolviendo JSON.
- Creado `GET /api/config/public` con valores seguros para frontend.
- Creados `GET` y `PUT /api/config/alerts`.
- Creados `GET`, `PUT` y `POST /api/config/email/test`.
- Agregados FormRequests para reglas de alerta, configuracion de alertas y configuracion email.
- Agregados JsonResources para alertas, reglas, dispositivos y sensores.
- Dispositivos y metadatos relacionados dejan de exponer `api_key` en respuestas normalizadas.
- `POST /api/config/email/test` devuelve errores genericos al cliente y registra detalles internamente.

### Archivos modificados

- `app/Http/Controllers/Api/AlertController.php`
- `app/Http/Controllers/Api/AlertRuleController.php`
- `app/Http/Controllers/Api/ConfigController.php`
- `app/Http/Controllers/Api/DashboardController.php`
- `app/Http/Controllers/Api/DashboardPreferenceController.php`
- `app/Http/Controllers/Api/EmailConfigController.php`
- `app/Http/Controllers/Api/HealthController.php`
- `app/Http/Controllers/Api/DeviceApiController.php`
- `app/Http/Controllers/Api/SensorApiController.php`
- `app/Http/Requests/Api/StoreAlertRuleRequest.php`
- `app/Http/Requests/Api/UpdateAlertRuleRequest.php`
- `app/Http/Requests/Api/UpdateAlertConfigRequest.php`
- `app/Http/Requests/Api/UpdateEmailConfigRequest.php`
- `app/Http/Resources/AlertResource.php`
- `app/Http/Resources/AlertRuleResource.php`
- `app/Http/Resources/DeviceResource.php`
- `app/Http/Resources/SensorResource.php`
- `routes/api.php`
- `tests/Feature/Phase2ApiEndpointsTest.php`
- `memory/04-api-contract.md`
- `memory/05-migration-log.md`
- `memory/06-pending-risks.md`
- `memory/08-refactor-checklist.md`
- `memory/09-session-progress.md`
- `memory/10-functional-state.md`

### Pruebas

- Ciclo rojo confirmado con `php artisan test tests/Feature/Phase2ApiEndpointsTest.php`: 11 fallos esperados por rutas faltantes, rutas Blade y exposicion de `api_key`.
- Paso `php artisan test tests/Feature/Phase2ApiEndpointsTest.php`: 12 tests, 100 assertions.
- Paso suite de regresion focalizada de dispositivos, sensores, seguridad y reglas: 24 tests, 76 assertions.
- Paso suite combinada de Phase2 y preferencias dashboard: 13 tests, 110 assertions.
- Paso `php artisan test`: 95 tests, 340 assertions.
- `php artisan route:list` ejecuto correctamente y reporto 115 rutas.
- `php artisan route:list --path=api` reporto 44 rutas API.
- `rg` no encontro `view()`, `redirect()` ni `back()` en `app/Http/Controllers/Api`, `app/Http/Controllers/API` ni `routes/api.php`.

### Pendiente

- Full CRUD JSON de devices, sensors, labs, sensor-types y device-types queda para una fase posterior.
- Exportacion API clara de lecturas queda pendiente.
- Reconsiderar autenticacion de endpoints publicos mantenidos por compatibilidad con Blade cuando la SPA pueda usar Bearer tokens.
- Racionalizar `GET /api/user` vs `/api/auth/me`.

## 2026-05-12 - Fase 3

### Estado

Se inicializo `/front` como SPA Vue 3 + Vite + Bootstrap 5. No se movio Laravel a `/back`, no se eliminaron vistas Blade, no se modificaron rutas web, no se crearon Dockerfiles y no se inicio Fase 4.

### Validacion previa

- Leidos los archivos de `/memory` como fuente de verdad.
- `php artisan route:list --path=api` ejecuto correctamente y reporto 44 rutas API.
- `php artisan test` paso antes de crear el frontend con 95 tests y 340 assertions.
- Se confirmo que Fase 2 estaba reflejada en `memory/04-api-contract.md`, `memory/08-refactor-checklist.md` y `memory/10-functional-state.md`.

### Cambios realizados

- Creada carpeta `/front`.
- Creado proyecto Vue 3 con Vite en JavaScript para reducir friccion inicial.
- Instaladas dependencias de frontend: Vue, Vite, Bootstrap, Axios, Vue Router, Pinia, Chart.js, vue-chartjs, Laravel Echo, Pusher JS y Sass.
- Creado `front/.env.example` con solo variables publicas `VITE_*`.
- Configurado proxy Vite `/api` hacia `http://localhost:8000`.
- Importado Bootstrap y estilos globales desde `front/src/main.js`.
- Creado cliente API centralizado con Axios y estrategia Bearer token.
- Creado store Pinia de autenticacion con `login`, `logout`, `fetchUser`, `initializeAuth`, `setToken` y `clearAuth`.
- Creado store de alertas para preparar badge de alertas no resueltas.
- Creado router con rutas publicas y protegidas.
- Creado layout base, navbar y componentes base reutilizables.
- Creada pantalla login funcional contra `/api/auth/login`.
- Creadas pantallas base para register, forgot-password, reset-password y verify-email.
- Creado dashboard protegido que consume `/api/dashboard/metrics`.
- Creadas vistas placeholder protegidas para sensores, detalle de sensor, alertas, reglas de alerta y configuracion.
- Creada estructura inicial para realtime con Laravel Echo/Pusher sin implementar aun la integracion completa.
- Creado verificador `npm run test:structure` para validar estructura minima de Fase 3.

## 2026-05-13 - Fase 7

### Estado

Se cerro Fase 7 con Docker local funcional para desarrollo. No se elimino Blade, no se inicio Fase 6B y no se hizo commit.

### Cambios realizados

- Creado `back/Dockerfile` con PHP 8.4 CLI, Composer y extensiones necesarias para Laravel.
- Creado `front/Dockerfile` con Node 20 Alpine para Vite en modo desarrollo.
- Creado `docker-compose.yml` con servicios `back`, `front`, `db`, `redis` y `queue` opcional por profile.
- Creado `.dockerignore` raiz, `back/.dockerignore` y `front/.dockerignore`.
- Ajustado el servicio `db` con `--log-bin-trust-function-creators=1` para permitir migraciones con triggers en MySQL Docker.
- Eliminado `env_file` del servicio `back`/`queue` en Compose para evitar expansion de secretos al ejecutar `docker compose config`.
- Agregado verificador `front/scripts/verify-phase7.mjs` y script `npm run test:phase7`.
- Agregada prueba backend `back/tests/Feature/Phase7DockerReadinessTest.php`.
- Aislado Redis de tests en `back/tests/TestCase.php` usando DB 15 y `flushdb()` defensivo.
- Creado `memory/11-functional-inventory.md`.
- Creado `memory/12-stable-blade-comparison.md`.
- Actualizados README y archivos de `/memory` con el estado real post-Docker.

### Archivos creados

- `back/Dockerfile`
- `front/Dockerfile`
- `docker-compose.yml`
- `.dockerignore`
- `back/.dockerignore`
- `front/.dockerignore`
- `front/scripts/verify-phase7.mjs`
- `back/tests/Feature/Phase7DockerReadinessTest.php`
- `memory/11-functional-inventory.md`
- `memory/12-stable-blade-comparison.md`

### Archivos modificados

- `.env.example`
- `README.md`
- `back/.env.example`
- `back/tests/TestCase.php`
- `front/package.json`
- `memory/04-api-contract.md`
- `memory/05-migration-log.md`
- `memory/06-pending-risks.md`
- `memory/07-codex-working-notes.md`
- `memory/08-refactor-checklist.md`
- `memory/09-session-progress.md`
- `memory/10-functional-state.md`

### Comandos ejecutados

- Baseline host:
  - `cd back && php artisan route:list --path=api`
  - `cd back && php artisan test`
  - `cd front && npm run build`
  - `cd front && npm run test:structure`
  - `cd front && npm run test:phase4`
  - `cd front && npm run test:phase5`
  - `cd front && npm run test:phase7`
- Docker/Compose:
  - `docker compose config`
  - `COMPOSE_PROJECT_NAME=iotplatformv2 BACK_PORT=18000 FRONT_PORT=15173 MYSQL_PORT=13306 REDIS_PORT=16379 docker compose build`
  - `COMPOSE_PROJECT_NAME=iotplatformv2 BACK_PORT=18000 FRONT_PORT=15173 MYSQL_PORT=13306 REDIS_PORT=16379 docker compose up -d`
  - `docker compose -p iotplatformv2 ps`
  - `curl http://localhost:18000/api/health`
  - `docker compose -p iotplatformv2 exec -T back php artisan route:list --path=api`
  - `docker compose -p iotplatformv2 exec -T back php artisan migrate --force`
  - `docker compose -p iotplatformv2 exec -T front npm run build`
  - `docker compose -p iotplatformv2 exec -T front npm run test:phase7`
  - `docker compose -p iotplatformv2 exec -T front node -e "fetch('http://back:8000/api/health')..."`
  - `docker compose -p iotplatformv2 exec -T back php artisan test`
  - `docker compose -p iotplatformv2 exec -T back env APP_ENV=testing DB_CONNECTION=sqlite DB_DATABASE=:memory: CACHE_STORE=array SESSION_DRIVER=array QUEUE_CONNECTION=sync APP_URL=http://localhost:18000 FRONT_URL=http://localhost:15173 php artisan test`
  - `COMPOSE_PROJECT_NAME=iotplatformv2 BACK_PORT=18000 FRONT_PORT=15173 MYSQL_PORT=13306 REDIS_PORT=16379 docker compose --profile queue up -d`
  - `docker compose -p iotplatformv2 logs queue --tail=50`

### Resultados

- Validacion host backend:
  - `cd back && php artisan route:list --path=api`: 45 rutas API.
  - `cd back && php artisan test`: 99 tests, 377 assertions.
- Validacion host frontend:
  - `npm run build`: pasa.
  - `npm run test:structure`: pasa.
  - `npm run test:phase4`: pasa.
  - `npm run test:phase5`: pasa.
  - `npm run test:phase7`: pasa.
- Validacion Docker:
  - `docker compose config`: pasa sin exponer secretos desde `env_file`.
  - `docker compose build`: pasa con backend en PHP 8.4.
  - `docker compose up -d`: pasa con puertos alternos `18000/15173/13306/16379`.
  - `docker compose ps`: `back`, `front`, `db`, `redis` arriba; `queue` arriba al activar el profile.
  - `curl http://localhost:18000/api/health`: responde JSON OK.
  - `docker compose exec back php artisan migrate --force`: pasa, no quedan migraciones pendientes.
  - `docker compose exec back php artisan route:list --path=api`: 45 rutas API.
  - `docker compose exec front npm run build`: pasa.
  - `docker compose exec front npm run test:phase7`: pasa.
  - `docker compose exec front node -e "fetch('http://back:8000/api/health')..."`: responde `200`, conectividad interna `front -> back` validada.
- Validacion tests backend en contenedor:
  - `docker compose exec back php artisan test`: falla con 21 fallos, 78 passed, 270 assertions porque hereda runtime MySQL/Redis y dispara restricciones de negocio/triggers para admin fixtures.
  - `docker compose exec back env APP_ENV=testing ... php artisan test`: pasa con 99 tests y 379 assertions usando SQLite en memoria y cache/sesion/queue aislados.

### Warnings no bloqueantes

- Intento inicial con PHP 8.2 en Docker fallo por compatibilidad con `\Pdo\Mysql::ATTR_SSL_CA`.
- Intento con PHP 8.5 en Docker quedo descartado por restricciones de dependencias Composer.
- `npm run build` mantiene warning Sass `legacy-js-api`.
- `npm run build` advierte chunk JS principal mayor a 500 kB.
- Local host en PHP 8.5 sigue mostrando deprecacion `ReflectionMethod::setAccessible()` via `nunomaduro/collision`.
- `docker compose logs queue --tail=50` no produjo salida util aun cuando el servicio queda `Up`.
- El primer intento de validar `queue` sin repetir overrides de puertos alternos choco con el puerto `3306`; la validacion final se hizo repitiendo el mismo set de overrides del stack base.
- El build Docker del backend emite warnings PSR-4 por clases bajo namespace `API\*` almacenadas en `app/Http/Controllers/Api/*`.

### Estado de queue

- `queue` queda definido y validado como servicio opcional por profile.
- Comando validado: `COMPOSE_PROJECT_NAME=iotplatformv2 BACK_PORT=18000 FRONT_PORT=15173 MYSQL_PORT=13306 REDIS_PORT=16379 docker compose --profile queue up -d`
- Estado final observado: `Up`.
- Sigue pendiente validar workload real de colas con un job funcional o envio real de mail bajo Docker.

### Pendiente

- No iniciar Fase 6B hasta validacion manual suficiente de la SPA y de los puentes legacy.
- Validacion manual con credenciales reales de Pusher.
- Completar paridad SPA para catalogos admin, roles y metricas antes de eliminar Blade agresivamente.
- Creado `front/.gitignore` para excluir `node_modules`, `dist` y `.env`.

### Archivos creados

- `front/.env.example`
- `front/.gitignore`
- `front/index.html`
- `front/package.json`
- `front/package-lock.json`
- `front/vite.config.js`
- `front/scripts/verify-phase3.mjs`
- `front/src/main.js`
- `front/src/App.vue`
- `front/src/api/client.js`
- `front/src/api/auth.js`
- `front/src/api/dashboard.js`
- `front/src/api/alerts.js`
- `front/src/api/alertRules.js`
- `front/src/api/sensors.js`
- `front/src/api/devices.js`
- `front/src/api/config.js`
- `front/src/assets/styles/main.scss`
- `front/src/assets/sounds/.gitkeep`
- `front/src/components/base/BaseButton.vue`
- `front/src/components/base/BaseAlert.vue`
- `front/src/components/base/BaseInput.vue`
- `front/src/components/base/LoadingSpinner.vue`
- `front/src/components/layout/AppLayout.vue`
- `front/src/components/layout/NavBar.vue`
- `front/src/layouts/AppLayout.vue`
- `front/src/layouts/AuthLayout.vue`
- `front/src/router/index.js`
- `front/src/stores/auth.js`
- `front/src/stores/alerts.js`
- `front/src/realtime/echo.js`
- `front/src/realtime/useAlertsRealtime.js`
- `front/src/views/auth/LoginView.vue`
- `front/src/views/auth/RegisterView.vue`
- `front/src/views/auth/ForgotPasswordView.vue`
- `front/src/views/auth/ResetPasswordView.vue`
- `front/src/views/auth/EmailVerificationView.vue`
- `front/src/views/DashboardView.vue`
- `front/src/views/SensorsView.vue`
- `front/src/views/SensorDetailView.vue`
- `front/src/views/AlertsView.vue`
- `front/src/views/AlertRulesView.vue`
- `front/src/views/ConfigView.vue`
- `front/src/views/NotFoundView.vue`

### Archivos de memoria actualizados

- `memory/04-api-contract.md`
- `memory/05-migration-log.md`
- `memory/06-pending-risks.md`
- `memory/07-codex-working-notes.md`
- `memory/08-refactor-checklist.md`
- `memory/09-session-progress.md`
- `memory/10-functional-state.md`

### Comandos ejecutados

- `php artisan route:list --path=api`
- `php artisan test`
- `node -v`
- `npm -v`
- `npm run test:structure`
- `npm install`
- `npm run build`
- `npm run dev -- --port 5173`
- `git status --short --untracked-files=all`
- `git diff -- package-lock.json`

### Pruebas y validaciones

- `npm run test:structure` paso.
- `npm run build` paso con Vite 5.4.21.
- `npm run dev -- --port 5173` fallo dentro del sandbox con `listen EPERM`, pero paso con permisos escalados y levanto `http://127.0.0.1:5173/`; el proceso fue detenido despues de validar.
- `php artisan route:list --path=api` reporto 44 rutas API.
- `php artisan test` paso al final con 95 tests y 340 assertions.

### Observaciones

- `npm install` requirio permisos escalados por restriccion de red inicial (`ENOTFOUND registry.npmjs.org`).
- `npm install` reporto 2 vulnerabilidades moderadas en dependencias npm; no se ejecuto `npm audit fix --force` para evitar cambios mayores automaticos.
- `npm run build` mostro warning no bloqueante de Sass `legacy-js-api`.
- Los comandos npm muestran warning no bloqueante de `pyenv` sobre `~/.pyenv/shims` no escribible.

### Pendiente

- Probar login manual en navegador con backend real corriendo en `localhost:8000`.
- Fase 4 debe migrar pantallas Blade a Vue con paridad funcional.
- Fase 5 debe implementar y validar Pusher/Echo en la SPA.
- Revisar vulnerabilidades npm moderadas antes de produccion.

## 2026-05-12 - Fase 4

### Estado

Se migraron progresivamente las pantallas principales hacia Vue funcional usando APIs existentes. No se elimino Blade, no se movio Laravel a `/back`, no se crearon Dockerfiles y no se inicio Fase 5.

### Validacion previa

- Leida `/memory` como fuente de verdad.
- `php artisan route:list --path=api` paso y reporto 44 rutas API.
- `php artisan test` paso antes de tocar pantallas con 95 tests y 340 assertions.
- `cd front && npm run build` paso antes de tocar pantallas.
- `cd front && npm run test:structure` paso antes de tocar pantallas.

### Cambios realizados

- Agregado `npm run test:phase4` con verificacion de estructura de componentes, ruta `/devices` y ausencia de `localhost` hardcodeado en vistas/API criticas.
- Mejorado manejo de errores API con `getValidationErrors`.
- Agregadas utilidades de formato y normalizacion de respuestas.
- Dashboard migrado a componentes funcionales:
  - metricas reales desde `/api/dashboard/metrics`;
  - alertas activas desde `/api/alerts/active` con polling cada 5 segundos;
  - estado de dispositivos desde `/api/devices`;
  - grafica Chart.js y tabla de ultimas lecturas.
- Auth views completadas para login, register, forgot password, reset password y email verification.
- Alertas migradas con filtros, listado, resolver una alerta y resolver todas.
- Sensores migrados con listado, filtro local, detalle, tabla de lecturas y grafica.
- Dispositivos migrados con ruta protegida `/devices`, navbar y listado read-only.
- Reglas de alerta migradas con CRUD funcional mediante modal y metadata de `/api/alert-rules/create`.
- Configuracion migrada con formularios de alertas, SMTP/email y prueba de email.
- Se mantuvo Blade legacy intacto y solo se documentaron candidatos a eliminacion.
- Corregida una asercion fragil en `tests/Feature/Phase2ApiEndpointsTest.php`: el test de alertas ya no depende de que una alerta creada primero aparezca en `data.0` cuando el endpoint ordena por `created_at`.

### Archivos creados

- `front/scripts/verify-phase4.mjs`
- `front/src/utils/formatters.js`
- `front/src/components/dashboard/MetricsCards.vue`
- `front/src/components/dashboard/ActiveAlertsCard.vue`
- `front/src/components/dashboard/DeviceStatusList.vue`
- `front/src/components/dashboard/SensorChart.vue`
- `front/src/components/dashboard/RecentReadingsTable.vue`
- `front/src/components/alerts/AlertFilters.vue`
- `front/src/components/alerts/AlertItem.vue`
- `front/src/components/alerts/AlertList.vue`
- `front/src/components/sensors/SensorFilters.vue`
- `front/src/components/sensors/SensorList.vue`
- `front/src/components/sensors/SensorReadingsChart.vue`
- `front/src/components/sensors/SensorReadingsTable.vue`
- `front/src/components/devices/DeviceList.vue`
- `front/src/components/devices/DeviceStatusBadge.vue`
- `front/src/components/alert-rules/AlertRuleForm.vue`
- `front/src/components/alert-rules/AlertRuleList.vue`
- `front/src/components/alert-rules/AlertRuleModal.vue`
- `front/src/views/DevicesView.vue`

### Archivos modificados

- `front/package.json`
- `front/src/api/client.js`
- `front/src/api/alertRules.js`
- `front/src/assets/styles/main.scss`
- `front/src/components/layout/NavBar.vue`
- `front/src/router/index.js`
- `front/src/views/auth/LoginView.vue`
- `front/src/views/DashboardView.vue`
- `front/src/views/AlertsView.vue`
- `front/src/views/SensorsView.vue`
- `front/src/views/SensorDetailView.vue`
- `front/src/views/AlertRulesView.vue`
- `front/src/views/ConfigView.vue`
- `tests/Feature/Phase2ApiEndpointsTest.php`
- `memory/04-api-contract.md`
- `memory/05-migration-log.md`
- `memory/06-pending-risks.md`
- `memory/07-codex-working-notes.md`
- `memory/08-refactor-checklist.md`
- `memory/09-session-progress.md`
- `memory/10-functional-state.md`

### Pruebas y validaciones

- Ciclo rojo: `npm run test:phase4` fallo inicialmente por componentes/ruta faltantes.
- `npm run test:phase4` paso despues de la implementacion.
- `npm run build` paso con Vite.
- `npm run test:structure` paso.
- `php artisan route:list --path=api` paso con 44 rutas API.
- `php artisan test` fallo una vez por una asercion fragil de orden en `Phase2ApiEndpointsTest`.
- `php artisan test tests/Feature/Phase2ApiEndpointsTest.php --filter=alerts_index` paso despues de corregir la asercion.
- `php artisan test` final paso con 95 tests y 340 assertions.

### Blade candidates for removal

- `resources/views/auth/login.blade.php`
  - Motivo: login SPA funcional contra `/api/auth/login`.
  - Reemplazo Vue: `front/src/views/auth/LoginView.vue`.
  - Estado: candidato, requiere validacion manual.
- `resources/views/auth/register.blade.php`
  - Motivo: registro SPA consume `/api/auth/register`.
  - Reemplazo Vue: `front/src/views/auth/RegisterView.vue`.
  - Estado: candidato, requiere validacion manual.
- `resources/views/auth/passwords/email.blade.php`
  - Motivo: recuperacion SPA consume `/api/auth/forgot-password`.
  - Reemplazo Vue: `front/src/views/auth/ForgotPasswordView.vue`.
  - Estado: candidato, requiere validacion manual.
- `resources/views/auth/passwords/reset.blade.php`
  - Motivo: reset SPA consume `/api/auth/reset-password`.
  - Reemplazo Vue: `front/src/views/auth/ResetPasswordView.vue`.
  - Estado: candidato, requiere validacion manual.
- `resources/views/auth/verify.blade.php`
  - Motivo: verificacion SPA consume `/api/auth/verify-email/{id}/{hash}`.
  - Reemplazo Vue: `front/src/views/auth/EmailVerificationView.vue`.
  - Estado: candidato, requiere validacion manual.
- `resources/views/dashboard.blade.php`
- `resources/views/dashboard/partials/alerts-card.blade.php`
- `resources/views/dashboard/partials/monitors.blade.php`
- `resources/views/dashboard/partials/realtime-monitor.blade.php`
- `resources/views/dashboard/partials/summary-cards.blade.php`
  - Motivo: dashboard SPA consume metricas, alertas activas, dispositivos y lecturas.
  - Reemplazo Vue: `front/src/views/DashboardView.vue` y componentes `front/src/components/dashboard/*`.
  - Estado: candidato, requiere validacion manual.
- `resources/views/alerts/index.blade.php`
- `resources/views/alerts/active.blade.php`
- `resources/views/alerts/unresolved.blade.php`
  - Motivo: vista Vue lista, filtra y resuelve alertas.
  - Reemplazo Vue: `front/src/views/AlertsView.vue` y componentes `front/src/components/alerts/*`.
  - Estado: candidato, requiere validacion manual.
- `resources/views/alerts/rules/index.blade.php`
- `resources/views/alerts/rules/create.blade.php`
  - Motivo: reglas Vue tienen CRUD por API.
  - Reemplazo Vue: `front/src/views/AlertRulesView.vue` y componentes `front/src/components/alert-rules/*`.
  - Estado: candidato, requiere validacion manual.
- `resources/views/sensors/index.blade.php`
- `resources/views/sensors/show.blade.php`
  - Motivo: sensores Vue tienen lista, filtro, detalle, tabla y grafica de lecturas.
  - Reemplazo Vue: `front/src/views/SensorsView.vue`, `front/src/views/SensorDetailView.vue` y componentes `front/src/components/sensors/*`.
  - Estado: candidato, requiere validacion manual.
- `resources/views/devices/index.blade.php`
  - Motivo: dispositivos Vue tienen listado read-only.
  - Reemplazo Vue: `front/src/views/DevicesView.vue`.
  - Estado: candidato parcial, requiere validacion manual. `show/create/edit` no son candidatos todavia.
- `resources/views/config/index_config.blade.php`
- `resources/views/config/email_config.blade.php`
  - Motivo: configuracion Vue actualiza alertas, SMTP/email y prueba de email.
  - Reemplazo Vue: `front/src/views/ConfigView.vue`.
  - Estado: candidato, requiere validacion manual.

### Pendiente

- Validacion manual en navegador con datos reales.
- Role-aware navigation para ocultar pantallas admin a usuarios no admin.
- Full CRUD JSON para devices, sensors y catalogos.
- Fase 5 debe migrar Pusher/Echo real, sonido y toasts.

## 2026-05-12 - Fase 5

### Estado

Se integro Laravel Echo + Pusher en la SPA Vue para alertas realtime, toast/popup, badge dinamico y sonido controlado por configuracion publica. Se mantuvo polling como fallback. No se elimino Blade, no se movio Laravel a `/back`, no se crearon Dockerfiles y no se inicio Fase 6.

### Validacion previa

- Leida `/memory` como fuente de verdad.
- `php artisan route:list --path=api` paso y reporto 44 rutas API.
- `php artisan test` paso antes de tocar realtime con 95 tests y 340 assertions.
- `cd front && npm run build` paso antes de tocar realtime.
- `cd front && npm run test:phase4` paso antes de tocar realtime.

### Configuracion realtime detectada

- Blade inicializa Pusher en `resources/views/layouts/app.blade.php` con `PUSHER_APP_KEY`, `PUSHER_APP_CLUSTER` y `forceTLS: true`.
- Blade escucha `alerts` con evento `App\\Events\\NewAlertTriggered`.
- Blade tambien escucha `sensor.{id}` con evento `App\\Events\\NewSensorReading`.
- `app/Events/NewAlertTriggered.php` usa `ShouldBroadcastNow`, `Channel('alerts')` y payload plano seguro.
- `app/Events/NewSensorReading.php` usa `ShouldBroadcastNow`, `Channel('sensor.'.$sensor_id)` y payload de lectura.
- No existen `routes/channels.php` ni `config/broadcasting.php` publicados en el repo.

### Cambios realizados

- Ampliado `front/.env.example` con variables publicas `VITE_PUSHER_FORCE_TLS`, `VITE_PUSHER_HOST`, `VITE_PUSHER_PORT` y `VITE_PUSHER_SCHEME`.
- Fortalecido `front/src/realtime/echo.js` para no inicializar Echo sin variables publicas suficientes y para soportar host/puerto/scheme publicos sin secretos.
- Reescrito `front/src/realtime/useAlertsRealtime.js` con constantes del canal/evento real, estado de conexion, `subscribeAlerts`, `unsubscribeAlerts` y `reconnect`.
- Creado `front/src/realtime/useSensorRealtime.js` para suscripcion preparada a `sensor.{id}`.
- Expandido `front/src/stores/alerts.js` con `activeAlerts`, `latestAlert`, `realtimeStatus`, `soundEnabled`, `fetchActiveAlerts`, `resolveAlert`, `resolveAll` y `addRealtimeAlert`.
- Creado `front/src/components/alerts/AlertToast.vue`.
- Creado `front/src/utils/sound.js` con sonido Web Audio controlado por `alert_sound_enabled`.
- Integrado realtime en `AppLayout`, `NavBar`, `ActiveAlertsCard`, `AlertsView` y `SensorDetailView`.
- Agregado `npm run test:phase5` con verificacion estructural de realtime y ausencia de secretos obvios.

### Archivos creados

- `front/scripts/verify-phase5.mjs`
- `front/src/components/alerts/AlertToast.vue`
- `front/src/realtime/useSensorRealtime.js`
- `front/src/utils/sound.js`

### Archivos modificados

- `front/.env.example`
- `front/package.json`
- `front/src/components/dashboard/ActiveAlertsCard.vue`
- `front/src/components/layout/AppLayout.vue`
- `front/src/components/layout/NavBar.vue`
- `front/src/realtime/echo.js`
- `front/src/realtime/useAlertsRealtime.js`
- `front/src/stores/alerts.js`
- `front/src/views/AlertsView.vue`
- `front/src/views/SensorDetailView.vue`
- `memory/04-api-contract.md`
- `memory/05-migration-log.md`
- `memory/06-pending-risks.md`
- `memory/08-refactor-checklist.md`
- `memory/09-session-progress.md`
- `memory/10-functional-state.md`

### Pruebas y validaciones

- Ciclo rojo: `npm run test:phase5` fallo inicialmente porque faltaban `useSensorRealtime.js`, `AlertToast.vue` y `sound.js`.
- `npm run test:phase5` paso despues de la implementacion.
- `npm run build` paso con Vite; warning no bloqueante de Sass `legacy-js-api` y chunk JS mayor a 500 kB.
- `npm run test:structure` paso.
- `npm run test:phase4` paso.
- `php artisan route:list --path=api` paso con 44 rutas API.
- `php artisan test` paso con 95 tests y 340 assertions.

### Pendiente

- Validar manualmente una alerta real con Pusher configurado y simulador/backend generando eventos.
- Confirmar sonido en navegador despues de interaccion de usuario; autoplay puede bloquear audio antes de interaccion.
- Si se migran canales privados, publicar/configurar broadcasting y endpoint de auth.
- Revisar code splitting del frontend antes de produccion por warning de chunk mayor a 500 kB.

## 2026-05-13 - Fase 6 cierre

### Estado

Se cerro formalmente la separacion fisica del backend en `/back`. Laravel ya no vive en la raiz del repositorio. `/front` sigue funcionando como SPA Vue, `/memory` se mantiene en raiz y Docker queda explicitamente pendiente para Fase 7. Blade legacy no fue eliminado.

### Resumen del movimiento fisico

- Creada carpeta `/back`.
- Movido Laravel completo a `/back` usando `git mv` cuando fue posible.
- Movidos a `/back` los directorios y archivos backend:
  - `app/`
  - `bootstrap/`
  - `config/`
  - `database/`
  - `public/`
  - `resources/`
  - `routes/`
  - `storage/`
  - `tests/`
  - `artisan`
  - `composer.json`
  - `composer.lock`
  - `phpunit.xml`
  - `package.json`
  - `package-lock.json`
  - `vite.config.js`
  - `.env.example`
  - `.env.testing`
  - `send_test_email.php`
- Movidos tambien artefactos runtime/locales necesarios para mantener `/back` ejecutable:
  - `.env`
  - `vendor/`
  - `node_modules/`
  - `.phpunit.result.cache`

### Archivos ajustados

- `.gitignore`
- `.env.example` raiz convertido a guia/orquestacion
- `README.md`
- `back/.env.example` saneado para backend
- `back/config/sanctum.php`
- `memory/04-api-contract.md`
- `memory/05-migration-log.md`
- `memory/06-pending-risks.md`
- `memory/07-codex-working-notes.md`
- `memory/08-refactor-checklist.md`
- `memory/09-session-progress.md`
- `memory/10-functional-state.md`

### Comandos ejecutados

- `cd back && php artisan route:list --path=api`
- `cd back && php artisan test`
- `cd front && npm run build`
- `cd front && npm run test:structure`
- `cd front && npm run test:phase4`
- `cd front && npm run test:phase5`
- `cd back && php artisan serve --host=127.0.0.1 --port=8000`
- `lsof -nP -iTCP:8000 -sTCP:LISTEN`
- `lsof -a -p 84773 -d cwd`
- `git status --short --untracked-files=all`

### Resultado de pruebas

- `cd back && php artisan route:list --path=api`: 45 rutas API.
- `cd back && php artisan test`: 96 tests, 368 assertions.
- `cd front && npm run build`: pasa.
- `cd front && npm run test:structure`: pasa.
- `cd front && npm run test:phase4`: pasa.
- `cd front && npm run test:phase5`: pasa.
- `php artisan serve` dentro del sandbox no pudo abrir `127.0.0.1:8000` por restriccion de `listen`, pero la validacion con permisos elevados encontro el puerto ocupado y `lsof` confirmo un proceso `php` escuchando en `127.0.0.1:8000` con `cwd` en `back/public`.

### Estado real consolidado

- Backend fisicamente separado en `/back`.
- Frontend fisicamente separado en `/front`.
- API local sigue bajo `http://localhost:8000/api`.
- Rutas API actuales: 45.
- Backend tests actuales: 96 pasando, 368 assertions.
- Frontend build/checks: pasando.

### Decisiones tomadas

- Blade legacy NO fue eliminado todavia.
- Limpieza Blade queda para Fase 6B o fase posterior, despues de validacion manual.
- Docker queda para Fase 7.
- `package.json` y `vite.config.js` de la raiz se trataron como pipeline legacy de assets Laravel y se movieron a `/back`.

### Warnings no bloqueantes

- `npm run build` mantiene warning de Sass `legacy-js-api`.
- `npm run build` advierte chunk JS principal mayor a 500 kB.
- `php artisan test` muestra deprecacion de `ReflectionMethod::setAccessible()` via `nunomaduro/collision` en PHP 8.5.
- Los comandos npm muestran warning de `pyenv` por `~/.pyenv/shims` no escribible.

### Pendientes

- Fase 6B: limpieza Blade legacy y estrategia SPA para produccion.
- Fase 7: Dockerfiles y `docker-compose.yml`.
- Validacion manual de realtime/Pusher con credenciales reales y alerta generada.

## 2026-05-13 - Fase 7B

### Estado

Se ejecuto validacion operacional antes de limpieza Blade. Docker base y queue quedaron validados; Pusher real UI y validacion manual completa de la SPA siguen pendientes. Veredicto actual para Fase 6B: `NO-GO`.

### Cambios realizados

- Revalidado Docker base con `back`, `front`, `db` y `redis`.
- Reconfirmado `GET /api/health`, `php artisan route:list --path=api`, migraciones y build frontend dentro de Docker.
- Reejecutadas pruebas backend host y en contenedor con entorno de testing explicito.
- Reejecutados `npm run build`, `test:structure`, `test:phase4`, `test:phase5` y `test:phase7` en `/front`.
- Auditada la infraestructura de queue; se confirmo que la aplicacion no tenia workload de negocio realmente encolado.
- Creado `back/app/Jobs/QueueSmokeJob.php` para validar infraestructura de cola sin tocar logica de dominio.
- Validado `queue` con profile y un smoke job real procesado por el worker.
- Auditada la configuracion realtime:
  - `back/.env` local tiene credenciales Pusher configuradas.
  - `front/.env` no existe, por lo que la SPA local sigue en fallback polling.
- Refinado `memory/11-functional-inventory.md` con columnas de validacion manual y Docker.
- Ampliado `memory/12-stable-blade-comparison.md` con impacto para limpieza Blade.
- Creado `memory/13-manual-validation-checklist.md`.
- Creado `memory/14-blade-cleanup-readiness.md` con veredicto `NO-GO`.

### Archivos creados

- `back/app/Jobs/QueueSmokeJob.php`
- `memory/13-manual-validation-checklist.md`
- `memory/14-blade-cleanup-readiness.md`

### Archivos modificados

- `memory/05-migration-log.md`
- `memory/06-pending-risks.md`
- `memory/08-refactor-checklist.md`
- `memory/09-session-progress.md`
- `memory/10-functional-state.md`
- `memory/11-functional-inventory.md`
- `memory/12-stable-blade-comparison.md`

### Comandos ejecutados

- `docker compose config`
- `COMPOSE_PROJECT_NAME=iotplatformv2 BACK_PORT=18000 FRONT_PORT=15173 MYSQL_PORT=13306 REDIS_PORT=16379 docker compose build`
- `COMPOSE_PROJECT_NAME=iotplatformv2 BACK_PORT=18000 FRONT_PORT=15173 MYSQL_PORT=13306 REDIS_PORT=16379 docker compose up -d`
- `docker compose -p iotplatformv2 ps`
- `curl http://localhost:18000/api/health`
- `docker compose -p iotplatformv2 exec -T back php artisan route:list --path=api`
- `docker compose -p iotplatformv2 exec -T back php artisan migrate --force`
- `docker compose -p iotplatformv2 exec -T front npm run build`
- `docker compose -p iotplatformv2 exec -T front npm run test:phase7`
- `docker compose -p iotplatformv2 exec -T back env APP_ENV=testing DB_CONNECTION=sqlite DB_DATABASE=:memory: CACHE_STORE=array SESSION_DRIVER=array QUEUE_CONNECTION=sync APP_URL=http://localhost:18000 FRONT_URL=http://localhost:15173 php artisan test`
- `COMPOSE_PROJECT_NAME=iotplatformv2 BACK_PORT=18000 FRONT_PORT=15173 MYSQL_PORT=13306 REDIS_PORT=16379 docker compose --profile queue up -d`
- `docker compose -p iotplatformv2 exec -T back php artisan tinker --execute="dispatch(new \\App\\Jobs\\QueueSmokeJob('queue-smoke-1778710446.txt', now()->toIso8601String()));"`
- `docker compose -p iotplatformv2 exec -T back test -f storage/app/queue-smoke-1778710446.txt && echo processed || echo missing`
- `docker compose -p iotplatformv2 logs queue --tail=50`
- `cd back && php artisan route:list --path=api`
- `cd back && php artisan test`
- `cd front && npm run build`
- `cd front && npm run test:structure`
- `cd front && npm run test:phase4`
- `cd front && npm run test:phase5`
- `cd front && npm run test:phase7`

### Resultado de pruebas

- Docker base: OK.
- `queue`: OK a nivel de infraestructura; el worker proceso `QueueSmokeJob` y genero evidencia en `storage/app/queue-smoke-1778710446.txt`.
- Host backend:
  - `php artisan route:list --path=api`: 45 rutas API.
  - `php artisan test`: 99 tests, 377 assertions.
- Contenedor backend con entorno de testing explicito:
  - `php artisan test`: 99 tests, 379 assertions.
- Frontend host:
  - `npm run build`: OK.
  - `npm run test:structure`: OK.
  - `npm run test:phase4`: OK.
  - `npm run test:phase5`: OK.
  - `npm run test:phase7`: OK.
- Frontend en contenedor:
  - `npm run build`: OK.
  - `npm run test:phase7`: OK.

### Estado de queue

- Validado con un job real controlado de infraestructura.
- El sistema aun no tiene un workload de negocio propiamente encolado que sirva como evidencia operativa permanente.

### Estado de Pusher

- Backend con credenciales locales configuradas.
- SPA local sin `front/.env`, por lo que no se pudo validar realtime UI end-to-end.
- El estado operativo real sigue siendo polling fallback.

### Veredicto para Fase 6B

- `NO-GO`

### Motivos del veredicto

- Validacion manual completa de la SPA pendiente.
- Realtime UI no configurado localmente en la SPA.
- Siguen sin migrar completamente `user roles`, `metrics`, `sensor-types`, `device-types`, `labs`, `profile`, `alerts.show` y parte del CRUD/export de sensores/dispositivos.
- Vistas Blade de correo/backend deben conservarse.

### Pendientes

- Ejecutar `memory/13-manual-validation-checklist.md`.
- Configurar `front/.env` con `VITE_PUSHER_*` y validar realtime end-to-end.
- Cerrar brechas de admin/catalogos/perfil/detalles/export antes de reabrir Fase 6B.
