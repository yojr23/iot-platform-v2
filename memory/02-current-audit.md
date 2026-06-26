# Current Audit

This audit summarizes the repository state before moving or refactoring code.

## Folder Classification

| Path | Classification | Notes |
|---|---|---|
| `app/` | back | Laravel application code: controllers, models, services, observers, events, mail, notifications. |
| `routes/api.php` | back/api | Existing JSON and IoT API routes. Some routes still point to web-oriented controllers. |
| `routes/web.php` | back legacy / pending | Blade routes, Laravel UI auth, forms, redirects, web session behavior. |
| `resources/views/` | front legacy / pending | Blade UI coupled to Laravel runtime. Not independent frontend. |
| `resources/js` | front current coupled to Laravel Vite | JavaScript entrypoints and bootstrap code. Much UI logic still lives inline in Blade. |
| `resources/css` | front current coupled to Laravel Vite | Tailwind source file exists but is not the main visible UI system. |
| `resources/sass` | front current coupled to Laravel Vite | Bootstrap/Sass app styles. |
| `public/index.php` | back | Laravel HTTP entrypoint. |
| `public/css` | mixed assets/build | Static CSS served by Laravel public directory. |
| `public/build` | mixed assets/build | Vite build output tied to current Laravel layout. |
| `database/` | back/database | Migrations, seeders, factories, SQLite local database file. |
| `config/` | back | Laravel configuration. |
| `bootstrap/` | back | Laravel bootstrap files and cache folder. |
| `artisan` | back | Laravel CLI entrypoint. |
| `composer.json` | back | PHP dependencies and Laravel scripts. |
| `composer.lock` | back | PHP lockfile. |
| `package.json` | build frontend current tied to Laravel | Current asset build dependencies and scripts. |
| `vite.config.js` | build frontend current tied to Laravel | Uses `laravel-vite-plugin`. |
| `package-lock.json` | build frontend current tied to Laravel | Node lockfile. |
| `script_datos.py` | back/data-processing or tools/iot-simulator | Python IoT simulator that calls backend APIs. |
| `tests_python/` | back/data-processing tests | Python tests for simulator. |
| `tests/` | back/tests | Laravel PHPUnit tests. |
| `phpunit.xml` | back/tests | PHPUnit configuration. |
| `.env` | config | Local runtime config; must not be moved into frontend. |
| `.env.testing` | config | Test environment config. |
| `.env.example` | config | Needs later split/normalization for front/back. |
| `docs/` | shared/docs | API, compliance, architecture, and project documentation. |
| `README.md` | shared/docs | Main project instructions and architecture notes. |
| `ANALISIS_PROYECTO.md` | shared/docs | Formal technical analysis. |
| `storage/` | back/runtime | Logs, cache, sessions, local runtime files. |

## Technologies Detected

- PHP 8.2+.
- Laravel 12.
- Laravel Sanctum.
- Laravel UI.
- Blade.
- Bootstrap.
- Chart.js.
- Pusher.
- Laravel Echo / Pusher JS partial setup.
- Vite with `laravel-vite-plugin`.
- SQLite by default in Laravel config.
- MySQL is contemplated in docs/env examples.
- Redis is configured and used in parts of the system, especially latest sensor readings cache.
- Python is used for the IoT simulator.
- No Dockerfile or `docker-compose.yml` was found in the initial audit.

## Current Couplings

- Blade uses `route()`, `asset()`, `csrf_token()`, `auth()`, `env()`, and Laravel model/config access.
- `resources/views/layouts/app.blade.php` initializes Pusher and reads backend configuration.
- `resources/views/dashboard.blade.php` contains large inline JavaScript and consumes partial APIs.
- Some web controllers return JSON responses in addition to rendering Blade views.
- Some API routes point to controllers that can return views.
- `AlertRuleController` has actions registered in API routes, but those actions return Blade views.
- `SensorApiController` mixes request validation, API key handling, persistence, Redis caching, logging, events, and response mapping.
- `script_datos.py` contains endpoint defaults and an API key fallback.

## Important Existing Routes

Current API routes include authentication, IoT sensor discovery, sensor reading ingestion, sensor readings query, devices query/status, active alerts, and internal local metrics.

The dashboard preference endpoints currently exist under web/session routes:

- `GET /dashboard/preferences`
- `POST /dashboard/preferences`

These need API equivalents before the SPA fully replaces Blade.
