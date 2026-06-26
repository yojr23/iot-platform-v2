# Pending Risks

## High Risks

- Blade cannot be moved directly to `/front`.
- Some future API routes could regress to web-oriented controllers if new endpoints are added without checking this file.
- The legacy Blade frontend still depends on Laravel helpers and remains in the repo until Fase 6B.
- The legacy Blade dashboard still contains a large amount of inline JavaScript.
- Legacy realtime is still initialized from Blade/backend variables in preserved views, even though the SPA already has its own Echo/Pusher integration.
- Legacy Pusher initialization still exists in `back/resources/views/layouts/app.blade.php` and `back/resources/views/sensors/index.blade.php`.
- CORS may fail when frontend and backend run on separate ports.
- `config/cors.php` now exists for local frontend origin, but staging/production origins must still be configured deliberately.
- Sanctum must be configured correctly for a SPA.
- Environment variables can expose secrets if migrated incorrectly.
- `script_datos.py` contains endpoint defaults and an API key fallback.
- Docker local ya existe, pero la configuracion actual esta pensada para desarrollo y todavia requiere endurecimiento para produccion.
- `storage/logs/laravel.log` can be large and must be treated as runtime data.
- `.DS_Store` and `__pycache__` artifacts should be cleaned in a separate phase.

## Medium Risks

- `package.json` and `vite.config.js` are currently tied to Laravel Vite.
- `resources/js/bootstrap.js` uses `process.env.MIX_PUSHER_*`, while Vite expects `import.meta.env.VITE_*`; this is a migration risk for realtime config.
- `public/build` and `public/css` mix frontend assets with the Laravel public directory.
- Existing tests may expect Blade views and web-route behavior.
- Some controllers mix JSON responses and Blade rendering.
- Full JSON CRUD for devices, sensors, labs, sensor-types, and device-types is still pending.
- Some public compatibility endpoints remain unauthenticated to avoid breaking Blade-era dashboard calls.
- The initial Vue SPA uses localStorage for Sanctum Bearer tokens, following ADR-009. This is functional but keeps XSS impact higher than an HttpOnly cookie strategy.
- Phase 3 placeholder screens call some admin-only endpoints; non-admin users may see controlled 403 errors until role-aware navigation and full UX are added.
- Phase 4 screens now call admin-only endpoints for alert rules and config; non-admin users get controlled errors, but role-aware navigation is still pending.
- Alert rule forms rely on `/api/alert-rules/create` metadata because standalone catalog APIs for labs, sensor-types and device-types are not yet available.
- Devices and sensors are read-only in Vue because full JSON CRUD endpoints are still pending.
- Login/register/password/email verification are wired to API, but manual browser validation with real local data is still required.
- `npm install` for `/front` reported 2 moderate npm vulnerabilities; review before production and avoid blind `npm audit fix --force`.
- `POST /api/config/email/test` is covered with `Mail::fake()` for request/response behavior, but real SMTP delivery still requires environment validation.
- Email Blade templates should remain in backend because they are server-side email templates, not SPA UI.
- Redis is used opportunistically for latest readings; Docker must include Redis or the backend must tolerate absence in development.
- The OpenAPI document may not fully match the live route list.
- Route naming changes can break Blade during the transition if compatibility is not maintained.
- `GET /api/user` duplicates the purpose of `GET /api/auth/me` and should be rationalized before the SPA depends on both.
- Rutas web legacy todavia existen y siguen apuntando a Blade mientras no se complete Fase 6B.
- La SPA debe validarse manualmente pantalla por pantalla antes de borrar Blade dinamico o rutas web reemplazadas.

## Operational Risks

- Revision de seguridad 2026-05-14: el endpoint `/api/internal/metrics/api-performance` fue endurecido con `auth:sanctum` y `admin`; queda pendiente revisar si otros endpoints publicos de compatibilidad deben protegerse despues de retirar Blade.
- La ingestion IoT todavia acepta `API_KEY` global legacy ademas de claves por dispositivo; si se filtra, debe rotarse y eventualmente retirarse.
- El password SMTP no se expone por API, pero sigue almacenado en `system_settings`; antes de produccion conviene cifrar valores sensibles o moverlos a secretos de entorno.
- A one-step folder move could break Composer autoload, PHPUnit paths, Vite input paths, Laravel public path assumptions, and deployment scripts.
- Local `.env` values may not match Docker service names such as `db` and `redis`.
- If the frontend uses browser-visible base URLs incorrectly, container-to-container names such as `http://back:8000` may leak into browser requests and fail.
- If Bearer token auth is selected, token storage strategy must be documented to reduce XSS impact.
- If cookie-based Sanctum SPA auth is selected, CORS, CSRF, cookie domain, and `SANCTUM_STATEFUL_DOMAINS` must be configured together.
- `FRONT_URL` is now defined in backend config, but each environment must set it correctly before email/reset/verification links are used outside local development.
- Frontend development requires the backend API at the URL configured by `VITE_API_BASE_URL`; browser requests must use a browser-reachable URL such as `http://localhost:8000/api`, not an internal Docker service name.
- Browser autoplay policies can block alert sounds until the user interacts with the page.
- Realtime delivery still needs manual validation with real Pusher credentials and an alert generated from backend/simulator.
- Docker Desktop o daemon Docker disponible sigue siendo requisito para validar `docker compose`.
- La validacion Docker de host se hizo con puertos alternos `18000/15173/13306/16379`; si el host ya ocupa los puertos default hay que repetir overrides consistentes en todo el stack y en el profile `queue`.
- El contenedor backend usa PHP 8.4, mientras que el host local hoy ejecuta tests con PHP 8.5; esa divergencia de runtime debe vigilarse hasta alinear entornos.
- `docker compose exec back php artisan test` bajo el runtime normal de Compose no es reproducible: hereda MySQL/Redis reales y puede fallar por restricciones de negocio/triggers. La validacion estable requiere `APP_ENV=testing`, SQLite en memoria y stores `array/sync`.
- Las migraciones MySQL del backend requieren `--log-bin-trust-function-creators=1` en el servicio `db` Docker para crear triggers sin privilegios `SUPER`.
- Redis expuso problemas de aislamiento entre tests en contenedor; la suite ahora usa DB 15 y `flushdb()` defensivo, pero cualquier cambio futuro en bootstrap de tests puede reintroducir contaminacion de cache.
- `docker compose config` ya no expone secretos via `env_file`, pero `back/.env` sigue montado localmente y debe mantenerse fuera de control de versiones.
- `queue` existe como profile opcional y quedo `Up`, pero todavia requiere validacion funcional con jobs reales para cerrar operacion end-to-end.
- `queue` ya fue validado con un smoke job de infraestructura, pero el sistema todavia no tiene un workload de negocio propio encolado que sirva como evidencia operativa permanente.
- `back/.env` local tiene credenciales Pusher configuradas, pero `front/.env` no existe; la SPA local real sigue en fallback polling mientras no se carguen `VITE_PUSHER_*`.
- La validacion manual completa de la SPA no se ejecuto en Fase 7B; existe checklist, pero el estado operativo de UI sigue parcialmente pendiente.
- El veredicto actual para limpieza Blade es `NO-GO`.
- Siguen sin migrar completamente `user roles`, `metrics`, `sensor-types`, `device-types`, `labs`, `profile`, `alerts.show` y parte del CRUD/export de sensores/dispositivos.
- Borrar rutas web antes de migrar esos modulos administrativos rompera accesos que la SPA aun resuelve mediante puentes legacy.
- Borrar vistas Blade de correo/backend sigue siendo de alto riesgo porque no son reemplazos de UI.
- La limpieza Blade sigue pendiente; las rutas web legacy y `back/resources/views` todavia existen.
- Pusher real sigue pendiente de credenciales y prueba manual.
- Token Bearer en localStorage mantiene riesgo XSS mayor que una estrategia HttpOnly cookie.
- Frontend build sigue con chunk JS > 500 kB.
- Frontend build mantiene warning Sass `legacy-js-api`.
- Los comandos npm siguen mostrando warning no bloqueante de `pyenv` en esta maquina.
- Algunas funcionalidades administrativas siguen solo en Blade o parcialmente migradas y eso bloquea una eliminacion agresiva de vistas web.
- `routes/channels.php` and `config/broadcasting.php` are not present/published; current realtime channels are public and any private channel migration needs deliberate backend setup.
- If `VITE_PUSHER_APP_KEY` is configured without cluster or websocket host, the frontend will keep polling fallback instead of initializing Echo.
- Frontend build now warns that the main JS chunk is larger than 500 kB after adding Echo/Pusher; code splitting should be reviewed before production.
- Backend operations now run from `/back`; any local script, IDE task, CI job, or deployment step that still assumes `php artisan` or `composer` from repo root will fail until updated.
- `.env.testing`, PHPUnit cache, and Laravel runtime paths now live under `/back`; external tooling that assumed root-relative paths must be updated deliberately.
- Blade legacy now lives physically under `/back/resources/views`; removing it is still risky because email templates and some web routes remain server-side.

## Resolved Or Reduced In Phase 1

- Headless auth no longer lacks register, forgot-password, reset-password, verify-email, and resend-verification endpoints.
- `config/cors.php` was added with local frontend origin support.
- `FRONT_URL` was added to backend config and `.env.example`.
- The known full-suite failure in `DashboardMetricsServiceTest` was fixed by resolving `DashboardMetricsService` through the service container.

## Resolved Or Reduced In Phase 2

- `/api/alert-rules/create`, `/api/alert-rules`, `/api/alert-rules/store`, and `DELETE /api/alert-rules/{alertRule}` now point to an API controller and return JSON instead of Blade views or redirects.
- `GET /api/sensors/all/readings` is now registered before dynamic sensor routes and points to `SensorApiController@allReadings`.
- `GET /api/devices/{device}/sensors` and `/sensor-list` now point to `DeviceApiController@sensors` instead of web controllers.
- `GET /api/alerts/active` now points to `Api\AlertController@active`.
- Device API responses normalized in Phase 2 avoid exposing `api_key`.
- Public config endpoint is whitelist-based and does not expose SMTP password, server Pusher secret, APP_KEY, API keys, tokens, or database credentials.

## Resolved Or Reduced In Phase 3

- `/front` now exists as a separate Vue 3 + Vite project without moving Laravel or deleting Blade.
- Frontend API calls are centralized in `front/src/api/*` instead of being hardcoded inside components.
- The SPA uses only public `VITE_*` variables in `front/.env.example`; no backend secrets were copied to the frontend.
- Basic protected routing, auth store, login screen, layout, navbar, and placeholder screens are in place for Phase 4 migration.
- Bootstrap 5 is installed and imported in the SPA independently from Laravel Vite assets.

## Resolved Or Reduced In Phase 4

- Auth views now have functional API-backed forms for login, register, forgot-password, reset-password and email verification.
- Dashboard now consumes real metrics, active alerts, devices and recent readings, including Chart.js visualization.
- Active alerts polling every 5 seconds is present as fallback until realtime is migrated in Phase 5.
- Sensors now have list filtering, detail view, readings table and readings chart.
- Devices now have a read-only Vue route and navbar entry.
- Alerts now support filters, individual resolve and resolve-all actions through the API.
- Alert rules now support list/create/edit/delete through JSON API.
- Config now supports alert settings, SMTP/email settings and test email without exposing SMTP password.
- Blade legacy files remain untouched and are only documented as candidates for removal after manual validation.

## Resolved Or Reduced In Phase 5

- Vue now initializes Laravel Echo/Pusher only when public `VITE_PUSHER_*` configuration is present.
- The frontend does not expose `PUSHER_APP_SECRET`, backend `APP_KEY`, mail password, database password, tokens, or private keys.
- Alert realtime updates feed the Pinia alerts store, navbar badge, dashboard active alerts and alerts page state.
- Toast/popup for incoming alerts exists in Vue and tolerates incomplete payloads.
- Alert sound is controlled by `/api/config/public` and defaults to disabled if public config cannot be loaded.
- Polling for `/api/alerts/active` remains as fallback and reconciliation.
- `SensorDetailView` is prepared for `sensor.{id}` realtime readings using the backend event already present.

## Resolved Or Reduced In Phase 6

- Laravel now lives physically under `/back`, reducing ambiguity between SPA code and backend code.
- Backend `.env.example` is now separated in `/back/.env.example`, and the repo root `.env.example` is only an orchestration guide.
- `SANCTUM_STATEFUL_DOMAINS` defaults were updated in `/back/config/sanctum.php` to include `localhost:5173`, `127.0.0.1:5173`, `localhost:8000`, and `127.0.0.1:8000`.
- Backend runtime directories (`vendor`, `storage`, `public/build`, `.env`) are now ignored under `/back` in the root `.gitignore`.
- README now documents `cd back` and `cd front` workflows explicitly.

## Resolved Or Reduced In Phase 7B

- Docker base was revalidated successfully with `back`, `front`, `db` and `redis`.
- The optional `queue` profile is no longer only "up"; it processed a real controlled smoke job successfully.
- The remaining realtime blocker is now clearly operational: backend credentials exist, but the SPA local environment still lacks `VITE_PUSHER_*`.
- A formal manual validation checklist and a Blade cleanup readiness report now exist, reducing ambiguity about whether Fase 6B can start.

## Resolved Or Reduced In Phase 8A

- Full JSON CRUD endpoints now exist for devices and sensors, including admin-protected create/update/delete.
- Device and sensor SPA screens no longer rely on Blade links for create/edit/delete/show.
- Sensor readings now support date filters and JSON export through the API/SPA.
- JSON CRUD endpoints and SPA admin screens now exist for labs, sensor-types, and device-types.
- User roles now have JSON API and SPA management.
- Technical metrics now have a SPA route backed by `GET /api/metrics`.
- Profile now has a SPA view backed by `GET /api/profile`.
- Individual alert detail now has `GET /api/alerts/{alert}` and `/alerts/:id` in Vue.
- Config admin actions now route to SPA modules for catalogs, roles, and metrics.
- Backend regression coverage for this parity block is in `back/tests/Feature/SpaParityApiTest.php`.
- Realtime/Pusher real validation and business queue workload validation are still operational tasks and were not closed by this CRUD/UI migration.
