# Architecture Refactor Audit (2026-05-04)

## Summary
This codebase is functional but has clear coupling and duplication hotspots.
The biggest issues are concentrated in dashboard/view JavaScript and sensor/alert backend boundaries.

## High-Priority Findings

1. Frontend alert logic was duplicated across layout and dashboard.
- Global concerns (sound, popup, channel subscription, badge updates) existed in multiple places.
- This caused drift and real-time regressions.
- Status: **partially fixed** by consolidating alert engine in layout and making dashboard a consumer.

2. Alert query logic duplicated across controllers/services.
- Repeated eager-load chains and resolved/active filters.
- Status: **fixed** with model scopes in `Alert` (`withContext`, `active`, `resolved`).

3. Dashboard controller had dead dependencies/imports.
- Unused imports and settings coupling that no longer applied to dashboard rendering.
- Status: **fixed**.

## Medium-Priority Findings

1. `SensorController` mixes multiple responsibilities.
- CRUD + analytics + export + date filtering + auxiliary APIs in one class.
- Should be split into:
  - `SensorCrudController`
  - `SensorReadingsController`
  - `SensorExportController`

2. `SensorApiController` is too large for one adapter.
- Ingestion auth, validation, logging, persistence, and response mapping are mixed.
- Should move domain logic to service classes:
  - `SensorIngestionService`
  - `SensorReadingsQueryService`
  - `ApiKeyValidationService`

3. Alert email behavior is implemented as model static behavior.
- `Alert::sendDangerAlertEmail(...)` introduces side-effects in model layer.
- Move to service/notification layer and inject into observer/listener.

## Proposed Target Architecture

- Controllers: thin HTTP adapters (validation + response mapping only)
- Services: domain/application use-cases
- Models: persistence + scopes + relations only
- Observers/Listeners: orchestration only, no heavy business logic
- Frontend: global modules for cross-cutting behavior (alerts), page scripts for page-specific rendering

## Suggested Phase Plan

1. Phase 1 (already started)
- Consolidate global alert engine and remove duplicated page alert engines.
- Standardize alert query scopes.

2. Phase 2
- Extract sensor ingestion domain services from `SensorApiController`.
- Add feature tests for ingestion success/failure and API key checks.

3. Phase 3
- Split `SensorController` by concern and normalize route ownership.
- Add policy-based authorization checks where needed.

4. Phase 4
- Move alert email side-effects from model to dedicated service/listener.
- Add contract tests for danger-email rate-limit behavior.

5. Phase 5
- Move large inline dashboard JavaScript to `resources/js/modules/*` and compile with Vite.

