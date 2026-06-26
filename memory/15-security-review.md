# Security Review - 2026-05-14

## Executive Summary

Se revisaron configuracion `.env`, exposicion de secretos, escalamiento de usuarios, rutas admin/API, controles de escritura y manipulacion de base de datos. No se encontraron secretos reales expuestos en salida de comandos ni se imprimieron valores de `.env`.

El hallazgo accionable corregido en esta sesion fue el endpoint interno de metricas: ahora requiere `auth:sanctum` y `admin`.

## Cambios aplicados

| ID | Cambio | Estado | Evidencia |
|---|---|---|---|
| FIX-001 | `front/.env` creado/configurado solo con variables publicas `VITE_*` | Aplicado | `front/.env` existe y esta ignorado por Git |
| FIX-002 | `back/.env` ajustado para `APP_URL`, `FRONT_URL`, CORS, Sanctum y broadcasting sin tocar secretos existentes | Aplicado | claves verificadas sin imprimir valores |
| FIX-003 | `.gitignore` raiz reforzado para `back/.env*` local y `front/.env*` local | Aplicado | `git check-ignore -v back/.env front/.env` |
| FIX-004 | `/api/internal/metrics/api-performance` ahora requiere `auth:sanctum` + `admin` | Aplicado | `back/routes/api.php` |
| FIX-005 | Prueba agregada para bloquear metricas internas a guest/no-admin | Aplicado | `back/tests/Feature/AdminAccessTest.php` |

## Controles verificados

| Area | Estado | Evidencia |
|---|---|---|
| Escalamiento por mass assignment | Mitigado | `User::$fillable` no incluye `is_admin`; test `SecurityPrivilegeEscalationTest` pasa |
| Cambios directos de `is_admin` desde modelo | Mitigado | `User::updating` exige actor admin; test pasa |
| Cambios directos de `is_admin` en MySQL | Mitigado en MySQL | triggers `users_before_insert_block_is_admin` y `users_before_update_block_is_admin` |
| Rutas API admin | Mitigado | CRUD sensible bajo `auth:sanctum` + `admin` |
| Escrituras de dispositivos/sensores | Mitigado | rutas `POST/PUT/DELETE` requieren `admin`; validaciones limitan campos aceptados |
| SMTP password en API | Mitigado parcialmente | API devuelve `password_configured`, no password |
| API key IoT en logs | Mitigado parcialmente | payload se enmascara en flujo de ingestion |
| SQL injection en endpoints revisados | Mitigado | consultas usan Eloquent/Query Builder y tests de SQLi pasan |
| Secretos en frontend | Mitigado | `front/.env` solo contiene `VITE_*`; no se copian secretos backend |

## Hallazgos y riesgos pendientes

### SEC-001 - Endpoints publicos de telemetria/datos operativos

Se conservan endpoints publicos por compatibilidad:
- `GET /api/dashboard/public`
- `GET /api/sensors/{sensor}/latest-readings`
- `GET /api/devices/{device}/sensors`
- `GET /api/alerts/active`

Impacto: un tercero con acceso de red puede consultar parte del estado operacional sin autenticacion.

Recomendacion: cuando la SPA ya use Bearer token en todas las pantallas, mover estos endpoints a `auth:sanctum` o crear payloads publicos minimizados.

### SEC-002 - API key global IoT legacy

La ingestion permite clave por dispositivo y tambien clave global legacy (`API_KEY`).

Impacto: si la clave global se filtra, podria enviar lecturas a cualquier sensor activo.

Recomendacion: mantener per-device keys como mecanismo principal, rotar la global y planear retirar compatibilidad global.

### SEC-003 - Bearer token en `localStorage`

La SPA guarda el Bearer token en `localStorage`.

Impacto: XSS en frontend podria extraer tokens.

Recomendacion: endurecer CSP y evaluar migrar a Sanctum cookie HttpOnly cuando la SPA sea estable.

### SEC-004 - Password SMTP almacenado como setting

El password SMTP no se expone por API, pero queda almacenado en `system_settings`.

Impacto: acceso directo a DB expone credenciales SMTP.

Recomendacion: cifrar valores sensibles en `SystemSetting` o mover secretos a variables de entorno/secret manager.

### SEC-005 - `.env.testing` contiene `APP_KEY` de pruebas

`back/.env.testing` contiene una `APP_KEY` de testing versionada.

Impacto: bajo si solo se usa para tests; no debe reutilizarse en staging/produccion.

Recomendacion: documentar que `APP_KEY` de testing no es secreto operacional y nunca usarla fuera de test.

## Pruebas ejecutadas

- `php artisan test tests/Feature/AdminAccessTest.php tests/Feature/SecurityPrivilegeEscalationTest.php tests/Feature/SecurityAccessControlTest.php tests/Feature/SecuritySqlInjectionTest.php tests/Feature/SecurityRateLimitTest.php`
  - Resultado: 17 tests, 41 assertions, pasa.
- `php artisan test`
  - Resultado: 105 tests, 449 assertions, pasa.
- `npm run build`
  - Resultado: pasa; mantiene warnings no bloqueantes de Sass `legacy-js-api` y chunk JS mayor a 500 kB.
- `php artisan route:list --path=api`
  - Resultado: 72 rutas API.

## Veredicto

El sistema tiene controles razonables contra escalamiento de usuarios y escrituras administrativas no autorizadas. El principal riesgo corregido fue el endpoint interno de metricas sin autenticacion. Los riesgos pendientes no bloquean desarrollo local, pero deben resolverse antes de produccion o antes de exponer la API fuera de una red controlada.
