# Codex Working Notes

This file is used for temporary operational notes during the refactor.

## Rules

- Do not store secrets.
- Do not store tokens.
- Do not store passwords.
- Do not store real API keys.
- Do not replace formal documentation with notes.
- If a note becomes a decision, move it to `memory/01-architecture-decisions.md`.
- If a note becomes a risk, move it to `memory/06-pending-risks.md`.
- If a note becomes an implemented change, move it to `memory/05-migration-log.md`.

## Current Notes

- The user wants to separate front and back into independent folders.
- The user wants separate containers for frontend and backend.
- The user plans a future internal backend division into modules or microservices.
- The user is considering Vue 3 + Bootstrap 5 to replace Blade.
- Phase 0 audit and planning has been completed.
- Phase 1 auth headless has been completed with Sanctum Bearer tokens.
- Phase 2 API normalization has been completed.
- Phase 3 was authorized by the user and completed on 2026-05-12.
- Existing untracked file before this memory task: `INFORME_ALERTAS_NOTIFICACIONES.md`.
- Blade legacy sigue presente, pero el backend ya fue movido a `/back` y Docker local ya existe.
- API routes for alert-rules now return JSON; Blade web routes remain unchanged.
- Some full CRUD APIs for catalog/admin screens are still pending.
- `/front` now contains a Vue 3 + Vite + Bootstrap 5 scaffold with Pinia, Vue Router, Axios, Chart.js, Laravel Echo and Pusher JS installed.
- The SPA auth implementation follows ADR-009: Sanctum Bearer token stored in localStorage for now.
- Fase 4 was authorized by the user and completed on 2026-05-12.
- Vue now has functional screens for auth, dashboard, sensors, devices, alerts, alert rules and config.
- Blade was not deleted in Fase 4; candidate views are documented for manual validation.
- Fase 5 must not start until the user explicitly authorizes it.
- Fase 5 was authorized by the user and completed on 2026-05-12.
- Vue now has Echo/Pusher integration for `alerts` and prepared sensor realtime on `sensor.{id}`.
- Polling remains as fallback; live Pusher validation with real credentials/simulator remains pending.
- Fase 6 was authorized by the user and completed on 2026-05-13.
- Laravel was moved physically to `/back`; `/front`, `/memory`, `/docs`, simulator and shared docs stayed at repo root.
- Root `package.json` / `vite.config.js` were classified as Laravel legacy asset pipeline files and moved into `/back`.
- Fase 7 fue autorizada y cerrada el 2026-05-13.
- Docker local validado con puertos alternos `18000` (back), `15173` (front), `13306` (db) y `16379` (redis) para evitar choques con servicios locales.
- `docker compose config`, `build`, `up -d`, health, migraciones, `front -> back` interno y build frontend en contenedor quedaron validados.
- El servicio `queue` existe como profile opcional y quedo `Up` al repetir el mismo set de overrides del stack base.
- `docker compose exec back php artisan test` bajo runtime normal falla por restricciones de negocio sobre MySQL/triggers; el comando reproducible en contenedor usa `APP_ENV=testing` + SQLite en memoria.
- Baseline host actual: `cd back && php artisan route:list --path=api` => 45 routes, `cd back && php artisan test` => 99 tests / 377 assertions.
- Baseline contenedor testing aislado: `docker compose exec back env APP_ENV=testing ... php artisan test` => 99 tests / 379 assertions.
- La comparacion con la version Blade estable usa como referencia principal el commit `8d2c7d8` (`version estable`) y como apoyo `back/resources/views` + `back/routes/web.php`.
