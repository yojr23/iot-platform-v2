# API Contract

This file tracks the initial API boundary needed for the future SPA.

Current local base:

- Backend location: `/back`
- API base: `http://localhost:8000/api`
- Current validated API route count: 72 after Fase 8A CRUD/admin migration.

## Phase 8A API Additions - 2026-05-13

- Devices now expose JSON admin CRUD: `POST /api/devices`, `PUT /api/devices/{device}`, `DELETE /api/devices/{device}`.
- Sensors now expose JSON admin CRUD: `POST /api/sensors`, `PUT /api/sensors/{sensor}`, `DELETE /api/sensors/{sensor}`.
- Sensor readings now support `from`/`to` filters and export: `GET /api/sensors/{sensor}/readings/export`.
- Catalogs now expose JSON CRUD: `/api/labs`, `/api/sensor-types`, `/api/device-types`.
- User roles now expose JSON endpoints: `GET /api/users`, `PATCH /api/users/{user}/role`.
- Metrics/profile/alert detail now expose SPA endpoints: `GET /api/metrics`, `GET /api/profile`, `GET /api/alerts/{alert}`.
- Host validation: `php artisan route:list --path=api` shows 72 routes.

## Phase 7 Docker Consumer Notes - 2026-05-13

- Docker keeps the same browser-facing API base by default: `http://localhost:8000/api`.
- For the final Phase 7 validation, the stack was brought up with alternate host ports to avoid local conflicts:
  - Backend health/API: `http://localhost:18000/api`
  - Frontend Vite: `http://localhost:15173`
  - MySQL: `localhost:13306`
  - Redis: `localhost:16379`
- Inside Docker, the frontend validates backend connectivity against the internal service URL `http://back:8000`.
- `GET /api/health` is the canonical Docker/backend health endpoint and is used by the backend healthcheck plus manual host/container validation.
- `GET /api/dashboard/public` remains present and should stay documented because it is still used for compatibility/public dashboard payloads.
- Queue exists as an optional Compose profile and was validated with the same alternate host-port override set used for the main stack.

## Existing APIs Detected

| Method | Route | State | Auth Required | Frontend Consumer | Current/Suggested Controller | Notes |
|---|---|---|---|---|---|---|
| POST | `/api/auth/login` | existing | no | Login page | `Api/AuthApiController@login` | Returns Bearer token. |
| POST | `/api/auth/register` | existing | no | Register page | `Api/AuthApiController@register` | Creates unverified user, fires `Registered`, returns Bearer token and safe user payload. |
| GET | `/api/auth/me` | existing | yes | Session bootstrap/profile | `Api/AuthApiController@me` | Protected by Sanctum. |
| POST | `/api/auth/logout` | existing | yes | Logout action | `Api/AuthApiController@logout` | Revokes current token. |
| POST | `/api/auth/forgot-password` | existing | no | Forgot password page | `Api/AuthApiController@forgotPassword` | Sends reset link. Reset URL targets `FRONT_URL`. |
| POST | `/api/auth/reset-password` | existing | no | Reset password page | `Api/AuthApiController@resetPassword` | Resets password by token and email. |
| GET | `/api/auth/verify-email/{id}/{hash}` | existing | signed URL | Email verification page/link | `Api/AuthApiController@verifyEmail` | Signed API route for email verification. |
| POST | `/api/auth/resend-verification` | existing | yes | Email verification prompt | `Api/AuthApiController@resendVerificationEmail` | Protected by Sanctum Bearer token. |
| GET | `/api/health` | existing | no | App startup, Docker healthchecks | `Api/HealthController@show` | Added in Phase 2. |
| GET | `/api/dashboard/metrics` | existing | yes | Dashboard summary/cards | `Api/DashboardController@metrics` | Added in Phase 2. |
| GET | `/api/dashboard/public` | existing | no | Public dashboard data / compatibility | `Api/DashboardController@publicData` | Public summary payload used for compatibility and unauthenticated dashboard scenarios. |
| GET | `/api/dashboard/preferences` | existing | yes | Dashboard layout restore | `Api/DashboardPreferenceController@show` | Added in Phase 2. |
| PUT | `/api/dashboard/preferences` | existing | yes | Dashboard layout save | `Api/DashboardPreferenceController@store` | Added in Phase 2. |
| GET | `/api/iot/sensors` | existing | API key | IoT simulator/device clients | `Api/SensorApiController@iotIndex` | Uses `X-Device-Key`, query, or input API key. |
| POST | `/api/sensors/{sensor}/readings` | existing | API key | IoT simulator/device clients | `Api/SensorApiController@storeReading` | Ingests telemetry. |
| GET | `/api/sensors` | existing | yes in private group, also via IoT list public with API key | Dashboard/selectors | `Api/SensorApiController@index` | Route exists under `auth:sanctum`; IoT route uses `iotIndex`. |
| GET | `/api/sensors/{sensor}` | existing | yes | Sensor detail page | `Api/SensorApiController@show` | Added in Phase 2. |
| GET | `/api/sensors/{sensor}/latest-readings` | existing | no currently | Dashboard charts | `Api/SensorApiController@latestReadings` | Public dashboard visualization route today. |
| GET | `/api/sensors/{sensor}/readings` | existing | yes | Sensor history page | `Api/SensorApiController@readings` | Paginated readings. |
| GET | `/api/sensors/all/readings` | existing | yes | Dashboard charts overview | `Api/SensorApiController@allReadings` | Route order fixed in Phase 2. |
| GET | `/api/devices` | existing | yes | Devices list/dashboard | `Api/DeviceApiController@index` | Paginated. |
| GET | `/api/devices/{device}` | existing | yes | Device detail | `Api/DeviceApiController@show` | Includes device relations. |
| POST | `/api/devices/{device}/status` | existing | yes/admin | Device controls | `Api/DeviceApiController@updateStatus` | Updates `status` and `is_active`. |
| GET | `/api/devices/{device}/sensors` | existing | no currently | Dashboard selector | `Api/DeviceApiController@sensors` | Normalized to API controller in Phase 2. |
| GET | `/api/devices/{device}/sensor-list` | existing | yes | Legacy API alias for sensor selector | `Api/DeviceApiController@sensors` | Kept for compatibility; JSON only. |
| GET | `/api/alerts/active` | existing | no currently | Global alert feed/dashboard | `Api/AlertController@active` | Public dashboard route today; JSON only. |
| GET | `/api/alerts` | existing | yes | Alerts index/history | `Api/AlertController@index` | Added in Phase 2. |
| GET | `/api/alerts/unresolved` | existing | yes | Alerts unresolved page/badges | `Api/AlertController@unresolved` | Added in Phase 2. |
| PATCH | `/api/alerts/{alert}/resolve` | existing | yes | Resolve alert action | `Api/AlertController@resolve` | Added in Phase 2. |
| POST | `/api/alerts/resolve-all` | existing | yes | Bulk resolve action | `Api/AlertController@resolveAll` | Added in Phase 2. |
| GET | `/api/alert-rules` | existing | yes/admin | Alert rule admin page | `Api/AlertRuleController@index` | Normalized to JSON in Phase 2. |
| POST | `/api/alert-rules` | existing | yes/admin | Create alert rule | `Api/AlertRuleController@store` | Added in Phase 2. |
| GET | `/api/alert-rules/create` | existing compatibility | yes/admin | Alert rule create metadata | `Api/AlertRuleController@create` | Compatibility path now returns JSON metadata instead of Blade. |
| POST | `/api/alert-rules/store` | existing compatibility | yes/admin | Legacy API create alias | `Api/AlertRuleController@store` | Kept for compatibility; JSON only. |
| GET | `/api/alert-rules/{alertRule}` | existing | yes/admin | Edit/view alert rule | `Api/AlertRuleController@show` | Added in Phase 2. |
| PUT | `/api/alert-rules/{alertRule}` | existing | yes/admin | Update alert rule | `Api/AlertRuleController@update` | Added in Phase 2. |
| DELETE | `/api/alert-rules/{alertRule}` | existing | yes/admin | Delete alert rule | `Api/AlertRuleController@destroy` | Normalized to JSON in Phase 2. |
| GET | `/api/config/public` | existing | no | Frontend bootstrap config | `Api/ConfigController@publicConfig` | Whitelisted safe public values only. |
| GET | `/api/config/alerts` | existing | yes/admin | Alert config page | `Api/ConfigController@alerts` | Added in Phase 2. |
| PUT | `/api/config/alerts` | existing | yes/admin | Alert config save | `Api/ConfigController@updateAlerts` | Added in Phase 2. |
| GET | `/api/config/email` | existing | yes/admin | Email config page | `Api/EmailConfigController@show` | Does not expose SMTP password. |
| PUT | `/api/config/email` | existing | yes/admin | Email config save | `Api/EmailConfigController@update` | SMTP password optional if already configured. |
| POST | `/api/config/email/test` | existing | yes/admin | Test email action | `Api/EmailConfigController@test` | Sends test email using current settings; does not expose exception details. |
| GET | `/dashboard/preferences` | existing under web/session | yes web session | Dashboard layout legacy | `DashboardPreferenceController@show` | Kept for Blade compatibility. |
| POST | `/dashboard/preferences` | existing under web/session | yes web session | Dashboard layout legacy | `DashboardPreferenceController@store` | Kept for Blade compatibility. |

## APIs Needed Or Pending

| Method | Route | State | Auth Required | Frontend Consumer | Suggested Controller | Notes |
|---|---|---|---|---|---|---|
| GET | `/api/health` | existing | no | App startup, Docker healthchecks | `Api/HealthController@show` | Added in Phase 2. |
| GET | `/api/dashboard/metrics` | existing | yes | Dashboard summary cards | `Api/DashboardController@metrics` | Replaces planned `/api/dashboard/summary` name. |
| GET | `/api/dashboard/public` | existing | no | Public dashboard data / compatibility | `Api/DashboardController@publicData` | Public summary payload kept for compatibility. |
| GET | `/api/dashboard/preferences` | existing | yes | Dashboard layout restore | `Api/DashboardPreferenceController@show` | API replacement for web route. |
| PUT | `/api/dashboard/preferences` | existing | yes | Dashboard layout save | `Api/DashboardPreferenceController@store` | Idempotent update for SPA. |
| GET | `/api/alerts` | existing | yes | Alerts index/history | `Api/AlertController@index` | Supports `resolved` filter and pagination. |
| GET | `/api/alerts/unresolved` | existing | yes | Alerts unresolved page/badges | `Api/AlertController@unresolved` | Paginated. |
| PATCH | `/api/alerts/{id}/resolve` | existing | yes | Resolve alert action | `Api/AlertController@resolve` | Returns updated alert resource. |
| POST | `/api/alerts/resolve-all` | existing | yes | Bulk resolve action | `Api/AlertController@resolveAll` | Returns `resolved_count`. |
| GET | `/api/alert-rules` | existing | yes/admin | Alert rule admin page | `Api/AlertRuleController@index` | JSON resource collection. |
| POST | `/api/alert-rules` | existing | yes/admin | Create alert rule | `Api/AlertRuleController@store` | JSON create endpoint. |
| GET | `/api/alert-rules/{id}` | existing | yes/admin | Edit/view alert rule | `Api/AlertRuleController@show` | JSON resource. |
| PUT | `/api/alert-rules/{id}` | existing | yes/admin | Update alert rule | `Api/AlertRuleController@update` | JSON update endpoint. |
| DELETE | `/api/alert-rules/{id}` | existing | yes/admin | Delete alert rule | `Api/AlertRuleController@destroy` | JSON delete endpoint. |
| GET | `/api/config/public` | existing | no | Frontend bootstrap config | `Api/ConfigController@publicConfig` | Only non-secret whitelisted values. |
| GET | `/api/config/alerts` | existing | yes/admin | Alert config page | `Api/ConfigController@alerts` | Does not expose secrets. |
| PUT | `/api/config/alerts` | existing | yes/admin | Alert config save | `Api/ConfigController@updateAlerts` | Validated with FormRequest. |
| GET | `/api/config/email` | existing | yes/admin | Email config page | `Api/EmailConfigController@show` | Returns `password_configured`, never SMTP password. |
| PUT | `/api/config/email` | existing | yes/admin | Email config save | `Api/EmailConfigController@update` | Sensitive server-only config; password optional when already configured. |
| POST | `/api/config/email/test` | existing | yes/admin | Test email action | `Api/EmailConfigController@test` | Return JSON status; logs internal error only. |
| GET | `/api/devices` | existing/partial CRUD | yes | Device list | `Api/DeviceController@index` | Existing list route exists. Full CRUD still needs normalization. |
| POST | `/api/devices` | pending | yes/admin | Device create page | `Api/DeviceController@store` | JSON equivalent of web create. |
| PUT | `/api/devices/{id}` | pending | yes/admin | Device edit page | `Api/DeviceController@update` | JSON equivalent of web update. |
| DELETE | `/api/devices/{id}` | pending | yes/admin | Device delete action | `Api/DeviceController@destroy` | JSON equivalent of web delete. |
| GET | `/api/sensors` | existing/partial CRUD | yes | Sensor list/selectors | `Api/SensorController@index` | Current API list exists but full CRUD needs normalization. |
| POST | `/api/sensors` | pending | yes/admin | Sensor create page | `Api/SensorController@store` | JSON equivalent of web create. |
| PUT | `/api/sensors/{id}` | pending | yes/admin | Sensor edit page | `Api/SensorController@update` | JSON equivalent of web update. |
| DELETE | `/api/sensors/{id}` | pending | yes/admin | Sensor delete action | `Api/SensorController@destroy` | JSON equivalent of web delete. |
| GET | `/api/labs` | pending | yes | Lab selectors/admin | `Api/LabController@index` | Needed by device create/edit. |
| POST | `/api/labs` | pending | yes/admin | Lab create | `Api/LabController@store` | JSON CRUD. |
| PUT | `/api/labs/{id}` | pending | yes/admin | Lab update | `Api/LabController@update` | JSON CRUD. |
| DELETE | `/api/labs/{id}` | pending | yes/admin | Lab delete | `Api/LabController@destroy` | JSON CRUD. |
| GET | `/api/sensor-types` | pending | yes | Sensor type selectors/admin | `Api/SensorTypeController@index` | Needed by sensors and alert rules. |
| POST | `/api/sensor-types` | pending | yes/admin | Sensor type create | `Api/SensorTypeController@store` | JSON CRUD. |
| PUT | `/api/sensor-types/{id}` | pending | yes/admin | Sensor type update | `Api/SensorTypeController@update` | JSON CRUD. |
| DELETE | `/api/sensor-types/{id}` | pending | yes/admin | Sensor type delete | `Api/SensorTypeController@destroy` | JSON CRUD. |
| GET | `/api/device-types` | pending | yes | Device type selectors/admin | `Api/DeviceTypeController@index` | Needed by devices. |
| POST | `/api/device-types` | pending | yes/admin | Device type create | `Api/DeviceTypeController@store` | JSON CRUD. |
| PUT | `/api/device-types/{id}` | pending | yes/admin | Device type update | `Api/DeviceTypeController@update` | JSON CRUD. |
| DELETE | `/api/device-types/{id}` | pending | yes/admin | Device type delete | `Api/DeviceTypeController@destroy` | JSON CRUD. |
| GET | `/api/sensors/{id}/readings/export` | pending | yes | Sensor readings export | `Api/SensorReadingExportController@show` | Replace web download route with clear API. |

## Authentication Strategy

The initial SPA authentication strategy is now decided:

- Accepted strategy: Sanctum Bearer tokens from personal access tokens.
- Deferred option: Sanctum SPA cookie/session mode.

The deferred cookie option would affect CORS, CSRF, `SANCTUM_STATEFUL_DOMAINS`, cookie domains, and local Docker development.

## Phase 0 Validated Route Inventory - 2026-05-12

`php artisan route:list` currently reports 92 routes.

### Current API Routes

| Method | Route | State | Auth Required | Current Action | Contract Notes |
|---|---|---|---|---|---|
| POST | `/api/auth/login` | existing | no | `API\AuthApiController@login` | Token login exists; unverified users are rejected with HTTP 403. |
| POST | `/api/auth/register` | existing | no | `API\AuthApiController@register` | Added in Phase 1. Creates unverified user and returns Bearer token. |
| GET | `/api/auth/me` | existing | yes | `API\AuthApiController@me` | Protected by `auth:sanctum`. |
| POST | `/api/auth/logout` | existing | yes | `API\AuthApiController@logout` | Protected by `auth:sanctum`. |
| POST | `/api/auth/forgot-password` | existing | no | `API\AuthApiController@forgotPassword` | Added in Phase 1. Sends reset link to SPA URL. |
| POST | `/api/auth/reset-password` | existing | no | `API\AuthApiController@resetPassword` | Added in Phase 1. Resets password by token and email. |
| GET | `/api/auth/verify-email/{id}/{hash}` | existing | signed URL | `API\AuthApiController@verifyEmail` | Added in Phase 1. Named `api.auth.verify-email`. |
| POST | `/api/auth/resend-verification` | existing | yes | `API\AuthApiController@resendVerificationEmail` | Added in Phase 1. Protected by `auth:sanctum`. |
| GET | `/api/iot/sensors` | existing | API key | `API\SensorApiController@iotIndex` | IoT discovery route. |
| POST | `/api/sensors/{sensor}/readings` | existing | API key | `API\SensorApiController@storeReading` | IoT ingestion route. |
| GET | `/api/sensors/{sensor}/latest-readings` | existing | no currently | `API\SensorApiController@latestReadings` | Public dashboard route today; auth decision needed for SPA. |
| GET | `/api/devices/{device}/sensors` | existing | no currently | `API\DeviceApiController@sensors` | Normalized in Phase 2. |
| GET | `/api/alerts/active` | existing | no currently | `Api\AlertController@active` | Normalized in Phase 2. |
| GET | `/api/internal/metrics/api-performance` | existing/local only | no currently, guarded by local env in controller | `Api\InternalMetricsController@apiPerformance` | Returns 403 outside local. |
| GET | `/api/user` | existing | yes | closure | Duplicates `/api/auth/me` conceptually; decide whether to keep. |
| GET | `/api/devices` | existing | yes | `API\DeviceApiController@index` | List/paginate devices. |
| GET | `/api/devices/{device}` | existing | yes | `API\DeviceApiController@show` | Device details. |
| POST | `/api/devices/{device}/status` | existing | yes/admin | `API\DeviceApiController@updateStatus` | Status toggle API exists. |
| GET | `/api/sensors/{sensor}/readings` | existing | yes | `API\SensorApiController@readings` | Sensor readings page; route order may shadow `/api/sensors/all/readings`. |
| GET | `/api/sensors` | existing | yes | `API\SensorApiController@index` | Auth-protected sensor list. |
| GET | `/api/sensors/all/readings` | existing | yes | `API\SensorApiController@allReadings` | Route order fixed in Phase 2. |
| GET | `/api/alert-rules/create` | existing compatibility | yes/admin | `Api\AlertRuleController@create` | Returns JSON metadata since Phase 2. |
| GET | `/api/alert-rules` | existing | yes/admin | `Api\AlertRuleController@index` | Returns JSON resource collection since Phase 2. |
| POST | `/api/alert-rules/store` | existing compatibility | yes/admin | `Api\AlertRuleController@store` | Legacy alias kept, JSON only since Phase 2. |
| DELETE | `/api/alert-rules/{alertRule}` | existing | yes/admin | `Api\AlertRuleController@destroy` | Returns JSON since Phase 2. |
| GET | `/api/devices/{device}/sensor-list` | existing compatibility | yes | `API\DeviceApiController@sensors` | JSON alias normalized in Phase 2. |

### Current Web Routes That Need SPA/API Replacement

| Module | Current Web Routes | Replacement Need |
|---|---|---|
| Auth | `/login`, `/register`, `/logout`, `/email/verify*`, `/password/*`, `/sanctum/csrf-cookie` | Complete headless auth API and decide Bearer vs cookie flow. |
| Dashboard | `/dashboard`, `/dashboard/preferences` | `/api/dashboard/summary`, `/api/dashboard/preferences`. |
| Devices | `/devices*`, status toggle, communication registration | JSON CRUD and action endpoints. |
| Sensors | `/sensors*`, download, date filter | JSON CRUD, readings query/filter/export endpoints. |
| Alerts | `/alerts`, `/alerts/unresolved`, resolve, resolve-all | JSON alert list, unresolved, resolve, resolve-all endpoints. |
| Alert rules | `/alert-rules*` | JSON CRUD; current API routes are not acceptable because they use web controller methods. |
| Configuration | `/config`, `/config/user-roles`, `/email-config*`, `/metrics*` | JSON config, user role, email/SMTP, and metrics endpoints. |
| Catalogs | `/labs*`, `/sensor-types*`, `/device-types*` | JSON CRUD for selector/admin pages. |

### Auth API Gap Closed During Phase 1

Before Phase 1, API auth only covered:

- `POST /api/auth/login`
- `GET /api/auth/me`
- `POST /api/auth/logout`

Phase 1 added:

- `POST /api/auth/register`
- `POST /api/auth/forgot-password`
- `POST /api/auth/reset-password`
- `GET /api/auth/verify-email/{id}/{hash}`
- `POST /api/auth/resend-verification`

`php artisan route:list --path=api/auth` now reports 8 auth API routes.

### Health Endpoint Note

Laravel still exposes `GET /up`. Phase 2 added `GET /api/health` for API clients, Docker healthchecks, and frontend startup checks.

## Phase 2 Validated Route Inventory - 2026-05-12

Historical note for Phase 2: `php artisan route:list` reported 115 routes at that point. Current validated state after the `/back` split and Phase 7 Docker closeout is 45 API routes via `cd back && php artisan route:list --path=api`.

### Phase 2 API Additions And Normalizations

| Method | Route | State | Auth Required | Controller | Notes |
|---|---|---|---|---|---|
| GET | `/api/health` | existing | no | `Api\HealthController@show` | Public JSON health check. |
| GET | `/api/dashboard/metrics` | existing | yes | `Api\DashboardController@metrics` | Counts devices, sensors, active/unresolved alerts, latest readings, system status. |
| GET | `/api/dashboard/preferences` | existing | yes | `Api\DashboardPreferenceController@show` | API route for layout preferences. |
| PUT | `/api/dashboard/preferences` | existing | yes | `Api\DashboardPreferenceController@store` | API update for layout preferences. |
| GET | `/api/sensors/{sensor}` | existing | yes | `API\SensorApiController@show` | Sensor detail JSON. |
| GET | `/api/sensors/all/readings` | existing | yes | `API\SensorApiController@allReadings` | Reordered before `{sensor}` binding and moved away from web controller. |
| GET | `/api/devices/{device}/sensors` | existing | no currently | `API\DeviceApiController@sensors` | Public compatibility route kept for current dashboard. |
| GET | `/api/devices/{device}/sensor-list` | existing | yes | `API\DeviceApiController@sensors` | Authenticated legacy API alias. |
| GET | `/api/alerts` | existing | yes | `Api\AlertController@index` | Paginated `AlertResource` collection. |
| GET | `/api/alerts/unresolved` | existing | yes | `Api\AlertController@unresolved` | Paginated unresolved alerts. |
| GET | `/api/alerts/active` | existing | no currently | `Api\AlertController@active` | Kept public for legacy dashboard compatibility. |
| PATCH | `/api/alerts/{alert}/resolve` | existing | yes | `Api\AlertController@resolve` | JSON update. |
| POST | `/api/alerts/resolve-all` | existing | yes | `Api\AlertController@resolveAll` | JSON bulk action. |
| GET | `/api/alert-rules/create` | existing compatibility | yes/admin | `Api\AlertRuleController@create` | Now returns metadata JSON, not Blade. |
| GET | `/api/alert-rules` | existing | yes/admin | `Api\AlertRuleController@index` | JSON resource collection. |
| POST | `/api/alert-rules` | existing | yes/admin | `Api\AlertRuleController@store` | JSON create endpoint. |
| POST | `/api/alert-rules/store` | existing compatibility | yes/admin | `Api\AlertRuleController@store` | Legacy alias preserved; JSON only. |
| GET | `/api/alert-rules/{alertRule}` | existing | yes/admin | `Api\AlertRuleController@show` | JSON detail endpoint. |
| PUT | `/api/alert-rules/{alertRule}` | existing | yes/admin | `Api\AlertRuleController@update` | JSON update endpoint. |
| DELETE | `/api/alert-rules/{alertRule}` | existing | yes/admin | `Api\AlertRuleController@destroy` | JSON delete endpoint. |
| GET | `/api/config/public` | existing | no | `Api\ConfigController@publicConfig` | Safe public frontend settings only. |
| GET | `/api/config/alerts` | existing | yes/admin | `Api\ConfigController@alerts` | Alert settings JSON. |
| PUT | `/api/config/alerts` | existing | yes/admin | `Api\ConfigController@updateAlerts` | Validated alert settings update. |
| GET | `/api/config/email` | existing | yes/admin | `Api\EmailConfigController@show` | Does not return SMTP password. |
| PUT | `/api/config/email` | existing | yes/admin | `Api\EmailConfigController@update` | Password optional if one already exists. |
| POST | `/api/config/email/test` | existing | yes/admin | `Api\EmailConfigController@test` | Sends test email, returns generic JSON errors. |

### Still Pending After Phase 2

- Full JSON CRUD for devices is still pending beyond list/show/status/sensors.
- Full JSON CRUD for sensors is still pending beyond list/show/readings/ingestion.
- JSON CRUD for labs, sensor-types, and device-types remains pending.
- `GET /api/sensors/{id}/readings/export` remains pending.
- `GET /api/user` still duplicates `/api/auth/me`; rationalize before the SPA depends on both.
- Authentication for currently public dashboard compatibility endpoints (`/api/alerts/active`, `/api/devices/{device}/sensors`, `/api/sensors/{sensor}/latest-readings`) should be revisited when the SPA can send Bearer tokens consistently.

## Phase 3 Frontend Consumer Notes - 2026-05-12

The initial Vue 3 SPA scaffold was created under `/front` and is wired to consume the Phase 1 and Phase 2 APIs through `front/src/api/*`.

### Endpoints Consumed By The Initial SPA

| Frontend area | Endpoint(s) | Status | Notes |
|---|---|---|---|
| Auth store/login | `POST /api/auth/login`, `GET /api/auth/me`, `POST /api/auth/logout` | wired | Uses Sanctum Bearer token from `access_token` or `token`, stored in localStorage for the initial implementation. |
| Register placeholder | `POST /api/auth/register` | wired | Displays API success/error state; full UX/parity belongs to Phase 4. |
| Forgot/reset password | `POST /api/auth/forgot-password`, `POST /api/auth/reset-password` | wired | Reset view reads `token` and `email` from query parameters. |
| Email verification | `GET /api/auth/verify-email/{id}/{hash}` | wired | Passes signed query parameters through to the API. |
| Dashboard placeholder | `GET /api/dashboard/metrics` | wired | Displays core summary values and recent readings when present. |
| Sensors placeholder | `GET /api/sensors`, `GET /api/sensors/{sensor}`, `GET /api/sensors/{sensor}/latest-readings` | wired | Read-only initial screens. |
| Alerts placeholder/navbar badge | `GET /api/alerts`, `GET /api/alerts/unresolved` | wired | Badge uses unresolved count from API response metadata when available. |
| Alert rules placeholder | `GET /api/alert-rules` | wired | Read-only initial list; create/edit/delete UX remains Phase 4. |
| Config placeholder | `GET /api/config/public`, `GET /api/config/alerts` | wired | Alert config may return 403 for non-admin users and is handled as a controlled warning. |
| Realtime scaffold | Pusher/Echo env and `authEndpoint: /api/broadcasting/auth` | prepared | Full channel/event validation remains Phase 5. |

### Phase 3 API Mismatches Or Follow-Ups

- No blocking endpoint mismatch was found while creating the SPA scaffold.
- The frontend deliberately depends on `/api/auth/me`, not duplicate `/api/user`.
- Admin-only endpoints such as `/api/alert-rules` and `/api/config/alerts` may return 403 for non-admin users. The Phase 3 placeholders show controlled errors; role-aware navigation can be refined in Phase 4.
- Full JSON CRUD for devices, sensors, labs, sensor-types, and device-types is still pending and should be considered before migrating the corresponding Blade admin screens.

## Phase 4 Frontend Consumer Notes - 2026-05-12

The Vue SPA now contains functional screens for auth, dashboard, sensors, devices, alerts, alert rules, and configuration. The frontend still uses only the API layer in `front/src/api/*`.

### Endpoints Actively Used By Vue In Phase 4

| Area | Endpoint(s) | Status | Notes |
|---|---|---|---|
| Login/session | `POST /api/auth/login`, `GET /api/auth/me`, `POST /api/auth/logout` | used | Bearer token auth through centralized Axios client. |
| Register | `POST /api/auth/register` | used | Shows success/error state. Manual verification flow remains email-link based. |
| Password reset | `POST /api/auth/forgot-password`, `POST /api/auth/reset-password` | used | Reset page accepts `token` and `email` query params from backend email links. |
| Email verification | `GET /api/auth/verify-email/{id}/{hash}` | used | Passes signed query parameters through to backend. |
| Dashboard | `GET /api/dashboard/metrics` | used | Metrics cards, chart input and recent readings table. |
| Dashboard active alerts | `GET /api/alerts/active` | used | Polled every 5 seconds as fallback until Phase 5 realtime. |
| Dashboard devices | `GET /api/devices` | used | Device status summary component. |
| Sensors | `GET /api/sensors`, `GET /api/sensors/{sensor}`, `GET /api/sensors/{sensor}/latest-readings` | used | List, local filtering, detail, table and Chart.js graph. |
| Devices | `GET /api/devices` | used | Read-only `/devices` page. Full CRUD remains pending because API CRUD is not complete. |
| Alerts | `GET /api/alerts`, `GET /api/alerts/unresolved`, `GET /api/alerts/active`, `PATCH /api/alerts/{alert}/resolve`, `POST /api/alerts/resolve-all` | used | List filters, individual resolve and bulk resolve. |
| Alert rules | `GET /api/alert-rules`, `GET /api/alert-rules/create`, `POST /api/alert-rules`, `PUT /api/alert-rules/{id}`, `DELETE /api/alert-rules/{id}` | used | CRUD modal; metadata endpoint provides devices, sensors and sensor types. |
| Alert config | `GET /api/config/public`, `GET /api/config/alerts`, `PUT /api/config/alerts` | used | Public config display plus alert settings form. |
| Email config | `GET /api/config/email`, `PUT /api/config/email`, `POST /api/config/email/test` | used | Does not expose SMTP password; form shows `password_configured`. |

### Phase 4 API Mismatches Or Follow-Ups

- Devices are read-only in Vue because `POST /api/devices`, `PUT /api/devices/{id}`, and `DELETE /api/devices/{id}` remain pending.
- Sensors are read-only in Vue because `POST /api/sensors`, `PUT /api/sensors/{id}`, and `DELETE /api/sensors/{id}` remain pending.
- Labs, sensor-types and device-types still do not have standalone JSON CRUD endpoints; alert rules use `/api/alert-rules/create` metadata as a temporary source for selector data.
- Admin-only screens may return 403 for non-admin users. Vue surfaces controlled errors, but role-aware navigation/permissions should be refined before production.
- `/api/alerts/active`, `/api/devices/{device}/sensors`, and `/api/sensors/{sensor}/latest-readings` remain public compatibility endpoints; revisit authentication once Blade dashboard no longer depends on them.

## Phase 5 Realtime Contract Notes - 2026-05-12

The Vue SPA now has a realtime integration layer for the broadcast events already present in Laravel. No new backend endpoint was added in Phase 5.

### Broadcast Events Detected

| Area | Channel | Backend Event Class | Echo Listener | Payload fields | Notes |
|---|---|---|---|---|---|
| Alerts | `alerts` | `App\Events\NewAlertTriggered` | `NewAlertTriggered` | `id`, `message`, `severity`, `value`, `sensor_name`, `sensor_type`, `unit`, `device_name`, `lab_name`, `timestamp` | Public channel from `NewAlertTriggered::broadcastOn()`. Vue normalizes the flat payload into the alert shape used by the screens. |
| Sensor readings | `sensor.{id}` | `App\Events\NewSensorReading` | `NewSensorReading` | `reading_id`, `sensor_id`, `value`, `reading_time`, `sensor_name`, `sensor_type`, `unit`, `device_name`, `lab_name` | Public per-sensor channel. Vue detail screen subscribes when Echo is configured and keeps API load as fallback. |

### Endpoints Used By Realtime Frontend

| Method | Route | State | Auth Required | Frontend Consumer | Controller | Notes |
|---|---|---|---|---|---|---|
| GET | `/api/config/public` | existing | no | `AppLayout`, alerts store | `Api\ConfigController@publicConfig` | Used to read `alert_sound_enabled` safely. Defaults to sound off if unavailable. |
| GET | `/api/alerts/active` | existing | no currently | Alerts store, dashboard fallback | `Api\AlertController@active` | Polling fallback and reconciliation every 5 seconds. |
| GET | `/api/alerts`, `/api/alerts/unresolved` | existing | yes | Alerts page/navbar | `Api\AlertController` | Lists remain API-backed and can be refreshed after realtime events. |

### Realtime Follow-Ups

- `routes/channels.php` and `config/broadcasting.php` are not present/published in this repo. Current alert and sensor channels are public `Channel` instances.
- If private/presence channels are introduced later, backend broadcasting auth must be configured deliberately and the frontend `authEndpoint` must be verified.
- Live Pusher delivery still requires environment variables on both sides and manual validation with a generated alert.

## Phase 6 Physical Separation Notes - 2026-05-13

The backend was moved physically into `/back` while keeping the API contract stable for the SPA and external clients.

### Current Base Paths

- Backend runtime: `/back`
- Frontend SPA: `/front`
- API base URL for browser clients: `http://localhost:8000/api`
- API base URL for local frontend env example: `VITE_API_BASE_URL=http://localhost:8000/api`

### Phase 6 Validation

- `cd back && php artisan route:list --path=api` now reports 45 API routes.
- `cd back && php artisan test` now passes with 96 tests and 368 assertions.
- No API route path changed because of the move to `/back`.
- The documented extra route that must remain in the contract is `GET /api/dashboard/public`.

### Phase 6 Frontend Compatibility Notes

- `/front` still consumes the same REST endpoints after the backend move.
- No frontend component should import files from `/back`; communication remains HTTP-only through the API.
- Realtime still depends on the same public broadcast channels and public `VITE_PUSHER_*` variables.
