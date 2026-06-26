# Project Context

## What is iot-platform-v2

`iot-platform-v2` is an IoT platform for device and sensor management, telemetry ingestion, alert rule evaluation, real-time dashboard updates, and email/broadcast notifications.

The current codebase is a Laravel monolith with Blade views and partial JSON APIs. Laravel currently serves both the backend behavior and the main user interface.

## Current state

- Main backend: Laravel 12 / PHP 8.2+.
- Current UI: Blade templates, Laravel web routes, Laravel helpers, server-rendered data, and inline JavaScript.
- Partial APIs already exist under `routes/api.php`.
- The dashboard consumes some `/api/...` endpoints, but it still depends on Blade-rendered state and Laravel helpers.
- The IoT simulator is a Python script that posts readings to backend API endpoints.

The current UI depends on:

- Blade templates.
- Laravel route names and `route()`.
- `asset()`, `csrf_token()`, `auth()`, and backend environment/config values.
- Server-injected variables.
- Inline JavaScript inside Blade files.
- Web session/CSRF behavior in some flows.

## Refactor goal

The target is to separate frontend and backend inside the same repository:

- `/back` will contain Laravel as an API REST + Broadcasting backend.
- `/front` will contain a modern SPA that consumes only backend APIs.

The future frontend must not depend on Blade, Laravel models, Laravel helpers, direct database access, or internal backend files.

The future backend must expose clear JSON endpoints and broadcasting channels for frontend consumption.

## Target initial architecture

```text
/
  front/
  back/
  docker-compose.yml
  .env.example
  README.md
  docs/
  memory/
```

## Migration principle

The separation must be incremental and safe.

Do not perform a massive `git mv` before preparing and validating the API boundary. The safe order is:

1. Document the current state.
2. Define API contracts.
3. Add or normalize JSON endpoints.
4. Create the independent frontend.
5. Migrate screens gradually.
6. Keep Blade temporarily as legacy UI until each screen has an equivalent SPA screen.
7. Only then remove or retire Blade routes and views.

The goal is not to create microservices now. The goal is a clean front/back split that can evolve later.
