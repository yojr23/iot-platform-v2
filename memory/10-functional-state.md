# Functional State

## Backend
- Ubicacion: `/back`
- Estado: funcional
- Como levantar:
  - `cd back`
  - `php artisan serve --host=127.0.0.1 --port=8000`
- Validado:
  - `cd back && php artisan route:list --path=api`
  - `cd back && php artisan test`
- Rutas API: 72
- Tests: 105 pasando, 449 assertions
- Problemas conocidos:
  - `php artisan serve` no puede abrir puerto desde el sandbox; la validacion de escucha en `127.0.0.1:8000` se hizo con `lsof` y un proceso `php` ya activo con `cwd` en `back/public`.
  - Warning PHP deprecado de `ReflectionMethod::setAccessible()` via `nunomaduro/collision` al correr tests en PHP 8.5.
  - Validacion manual de Pusher real sigue pendiente.

## Frontend
- Ubicacion: `/front`
- Estado: funcional
- Como levantar:
  - `cd front`
  - `npm run dev`
- Como construir:
  - `cd front`
  - `npm run build`
- Checks:
  - `cd front && npm run test:structure`
  - `cd front && npm run test:phase4`
  - `cd front && npm run test:phase5`
  - `cd front && npm run test:phase7`
- Problemas conocidos:
  - `npm run build` muestra warning no bloqueante de Sass `legacy-js-api`.
  - `npm run build` advierte chunk JS principal mayor a 500 kB.
  - Los comandos npm muestran warning no bloqueante de `pyenv` por `~/.pyenv/shims` no escribible.

## API
- Base local: `http://localhost:8000/api`
- Estado: funcional
- Endpoints principales:
  - `GET /api/health`
  - `/api/auth/*`
  - `GET /api/dashboard/metrics`
  - `GET /api/dashboard/public`
  - `GET|PUT /api/dashboard/preferences`
  - `GET /api/sensors`
  - `GET /api/sensors/{sensor}`
  - `GET /api/sensors/{sensor}/latest-readings`
  - `GET /api/sensors/{sensor}/readings`
  - `POST /api/sensors/{sensor}/readings`
  - `GET /api/devices`
  - `GET /api/devices/{device}`
  - `GET /api/devices/{device}/sensors`
  - `POST /api/devices/{device}/status`
  - `GET /api/alerts`
  - `GET /api/alerts/active`
  - `GET /api/alerts/unresolved`
  - `PATCH /api/alerts/{alert}/resolve`
  - `POST /api/alerts/resolve-all`
  - CRUD JSON de `/api/alert-rules`
  - `GET /api/config/public`
  - `GET|PUT /api/config/alerts`
  - `GET|PUT /api/config/email`
  - `POST /api/config/email/test`
- Pendientes:
  - CRUD JSON completo para devices, sensors y catalogos.
  - Export API de lecturas.
  - Revisar si endpoints publicos de compatibilidad deben endurecerse despues de retirar Blade.

## Auth
- Estado: funcional con Bearer token segun implementacion actual
- Flujo actual:
  - `POST /api/auth/login`
  - `GET /api/auth/me`
  - `POST /api/auth/logout`
  - `POST /api/auth/register`
  - `POST /api/auth/forgot-password`
  - `POST /api/auth/reset-password`
  - `GET /api/auth/verify-email/{id}/{hash}`
  - `POST /api/auth/resend-verification`
- Riesgo:
  - token en localStorage documentado; mantiene riesgo XSS mayor que una estrategia HttpOnly cookie.

## Realtime / Pusher
- Estado: implementado en frontend
- Canales detectados:
  - `alerts`
  - `sensor.{id}`
- Eventos detectados:
  - `NewAlertTriggered`
  - `NewSensorReading`
- Fallback:
  - polling cada 5 segundos sobre `/api/alerts/active`
- Pendiente:
  - prueba manual con la SPA realmente configurada con `VITE_PUSHER_*`
- Problemas conocidos:
  - el sonido puede quedar bloqueado por politicas de autoplay hasta que el usuario interactue con la pagina.
  - `back/.env` local tiene credenciales Pusher configuradas, pero `front/.env` no existe; la SPA local actual sigue en fallback polling y no hay validacion realtime UI end-to-end.

## Blade Legacy
- Estado: conservado temporalmente
- Ubicacion: `/back/resources/views`
- Motivo:
  - evitar eliminacion agresiva antes de validacion manual completa
  - mantener emails/notificaciones Blade del backend
- Pendiente:
  - Fase 6B de limpieza Blade y ajuste SPA para produccion
  - Fase 7B concluyo `NO-GO` para una limpieza Blade inmediata

## Docker
- Estado: funcional para desarrollo local
- Servicios:
  - `back`: validado
  - `front`: validado
  - `db`: validado
  - `redis`: validado
  - `queue`: validado con smoke job real; workload de negocio sigue pendiente
- Como levantar:
  - `docker compose build`
  - `docker compose up -d`
  - si hay conflicto de puertos:
    - `COMPOSE_PROJECT_NAME=iotplatformv2 BACK_PORT=18000 FRONT_PORT=15173 MYSQL_PORT=13306 REDIS_PORT=16379 docker compose up -d`
- Health:
  - default: `curl http://localhost:8000/api/health`
  - validado en esta fase con override: `curl http://localhost:18000/api/health`
- Migraciones:
  - `docker compose exec back php artisan migrate --force`
- Tests:
  - `docker compose exec front npm run build`
  - `docker compose exec front npm run test:phase7`
  - `docker compose exec back php artisan route:list --path=api`
  - `docker compose exec back env APP_ENV=testing DB_CONNECTION=sqlite DB_DATABASE=:memory: CACHE_STORE=array SESSION_DRIVER=array QUEUE_CONNECTION=sync APP_URL=http://localhost:8000 FRONT_URL=http://localhost:5173 php artisan test`
- Problemas conocidos:
  - `docker compose exec back php artisan test` bajo runtime normal puede fallar por heredar MySQL/Redis reales; usar entorno de testing explicito.
  - `queue` debe levantarse repitiendo el mismo set de overrides de puertos si el stack usa puertos alternos.
  - Pusher real SPA sigue pendiente por falta de `front/.env` con `VITE_PUSHER_*`.

## DB
- Motor: MySQL 8.0 en Docker
- Estado: funcional
- Nota sobre triggers:
  - el servicio `db` necesita `--log-bin-trust-function-creators=1` para que las migraciones con triggers pasen sin privilegio `SUPER`.

## Redis
- Estado: funcional
- Uso:
  - cache/lecturas oportunistas y soporte de runtime Laravel
- Nota sobre tests/cache:
  - la suite backend aislada en contenedor usa Redis DB 15 y `flushdb()` defensivo para evitar contaminacion entre tests.

## Comparacion Blade
- Estado: completada
- Archivo: `memory/12-stable-blade-comparison.md`
- Conclusiones cortas:
  - la SPA cubre auth, dashboard, sensores, dispositivos basicos, alertas, reglas y configuracion principal
  - catalogos admin, roles, metricas y parte del CRUD avanzado siguen parciales o legacy

## Readiness Fase 6B
- Estado: `NO-GO`
- Reporte: `memory/14-blade-cleanup-readiness.md`
- Motivos principales:
  - validacion manual de la SPA pendiente
  - realtime UI no configurado localmente en la SPA
  - modulos admin y CRUD avanzados siguen dependiendo de Blade
  - vistas Blade de backend/email no son candidatas a limpieza

## Manual Test Notes
- Checklist formal: `memory/13-manual-validation-checklist.md`
- Backend:
  - `cd back`
  - `php artisan serve --host=127.0.0.1 --port=8000`
- Frontend:
  - `cd front`
  - `npm run dev`
- Validar login:
  - entrar a `/login`
  - autenticar usuario verificado
  - confirmar redireccion a `/dashboard`
- Validar dashboard:
  - revisar metricas
  - revisar alertas activas
  - revisar dispositivos
  - revisar grafica y lecturas recientes
- Validar alertas:
  - abrir `/alerts`
  - aplicar filtros
  - resolver una alerta
  - resolver todas
- Validar sensores:
  - abrir `/sensors`
  - entrar a `/sensors/{id}`
  - revisar tabla y grafica
- Validar config:
  - abrir `/config`
  - guardar alertas
  - guardar SMTP/email
  - probar email
- Validar realtime:
  - configurar Pusher en `back/.env` y `front/.env`
  - generar alerta desde backend o simulador
  - verificar badge, toast, sonido y polling fallback
- Validar Docker:
  - `docker compose build`
  - `docker compose up -d`
  - `curl http://localhost:8000/api/health` o el puerto alterno configurado
  - `docker compose exec front npm run build`
  - `docker compose exec back php artisan migrate --force`
