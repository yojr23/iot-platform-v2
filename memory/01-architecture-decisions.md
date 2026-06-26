# Architecture Decisions

This file records simple ADRs for the `iot-platform-v2` front/back refactor.

## ADR-001: Keep A Monorepo Initially

**Decision:** Frontend and backend will stay in the same repository, but in separate folders.

**Target:**

- `/front`
- `/back`

**Reason:** This supports gradual migration and avoids splitting the project before there is a clear API boundary.

**Status:** Accepted.

## ADR-002: Backend As REST API + Broadcasting

**Decision:** Laravel will stop serving the main UI with Blade over time and will expose JSON endpoints plus broadcasting capabilities.

**Reason:** This allows the frontend to run independently and prepares the backend for later modularization or service extraction.

**Status:** Accepted.

## ADR-003: Do Not Move Blade Directly To /front

**Decision:** Blade views must not be moved to `/front` as if they were independent frontend files.

**Reason:** Blade depends on Laravel runtime behavior, helpers, routes, session, CSRF, auth, injected variables, backend config, and backend-side logic.

**Status:** Accepted.

## ADR-004: Incremental Migration

**Decision:** Complete and normalize APIs first, then create the frontend, then retire Blade gradually.

**Reason:** This reduces the risk of breaking existing behavior while the frontend and backend boundary is being built.

**Status:** Accepted.

## ADR-005: Separate Environment Variables

**Decision:** The frontend may only use public variables, preferably `VITE_*`. The backend keeps secrets, database settings, server Pusher keys, API keys, mail credentials, and other sensitive configuration.

**Reason:** Anything bundled into the frontend can be exposed to users. Secrets must never be moved into frontend variables.

**Status:** Accepted.

## ADR-006: API-Only Frontend Communication

**Decision:** The frontend must communicate with the backend only through APIs and broadcasting channels.

**Reason:** The frontend must not access the database, internal files, Laravel models, service classes, or backend implementation details.

**Status:** Accepted.

## ADR-007: Docker Per Service

**Decision:** Frontend and backend will have independent Dockerfiles and will be started as separate services through `docker-compose`.

**Reason:** This prepares the project for modular deployment and later backend service division.

**Status:** Accepted.

## ADR-008: Frontend Framework

**Decision provisional:** The user plan proposes Vue 3 + Bootstrap 5 for the future SPA.

**Warning:** The initial audit did not detect Vue or React in the current project. The current frontend is Blade + Bootstrap + inline JavaScript + Vite assets. If Vue is adopted, it must be treated as a real migration from Blade to SPA, not as a file move.

**Status:** Proposed.

## ADR-009: SPA Authentication Uses Sanctum Bearer Tokens Initially

**Decision:** The initial headless SPA authentication strategy will use Laravel Sanctum personal access tokens sent as `Authorization: Bearer <token>`.

**Reason:** The project already had API login/me/logout using Sanctum Bearer tokens. Continuing that path completes Phase 1 with smaller risk than switching immediately to cookie-based Sanctum SPA auth, which would require coordinated CORS, CSRF, cookie domain, and stateful-domain changes.

**Consequences:**

- API login returns `token_type`, `access_token`, and a sanitized user payload.
- API registration returns a Bearer token for the newly registered unverified user so the SPA can call protected verification-resend endpoints.
- Login rejects unverified users with HTTP 403.
- Frontend token storage strategy must be designed carefully in Phase 3 to reduce XSS impact.
- Cookie-based Sanctum SPA auth can still be reconsidered later if product/security requirements change.

**Status:** Accepted in Phase 1.
