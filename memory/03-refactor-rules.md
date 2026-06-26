# Refactor Rules

These rules are mandatory for future Codex sessions working on the `iot-platform-v2` front/back split.

## General Rules

- Do not perform a massive `git mv` without an explicit migration phase.
- Do not delete Blade until an equivalent frontend screen exists and is validated.
- Do not break existing routes without creating and validating an API alternative.
- Do not move resources without updating imports, routes, build config, and tests.
- Do not expose secrets to the frontend.
- Do not put private keys, API keys, mail credentials, database credentials, or server Pusher secrets in `VITE_*`.
- Do not duplicate business logic in the frontend.
- Do not let the frontend query the database directly.
- Do not mix presentation logic with domain logic.
- Do not change functional behavior without recording it in `memory/05-migration-log.md`.
- Do not introduce microservices yet.
- Do not add unnecessary complexity before stabilizing the front/back separation.

## Backend Rules

- Every new endpoint intended for the frontend must return JSON.
- Use appropriate HTTP status codes.
- Keep business logic in services, models, observers, listeners, jobs, actions, or dedicated domain/application classes.
- Avoid oversized controllers with validation, domain behavior, persistence, logging, events, and response mapping all mixed together.
- Keep temporary compatibility with Blade routes during the migration.
- Protected APIs must use `auth:sanctum` or the authentication scheme officially selected for the SPA.
- Avoid returning Blade views from routes under `/api`.
- Prefer thin controllers that validate requests, call application services, and return response resources/DTOs.
- Backend secrets remain in backend environment variables only.
- Email Blade templates may remain in the backend because they are server-rendered mail templates, not the main UI.

## Frontend Rules

- Consume data only through backend APIs.
- Centralize HTTP calls in an API client layer.
- Do not hardcode `localhost` in components.
- Use `VITE_API_BASE_URL` or a Vite proxy for backend API calls.
- Handle API errors visibly and consistently.
- Do not store secrets in the frontend bundle.
- If Vue is adopted, organize code by pages, components, layouts, stores, api, and realtime.
- Do not call Laravel route helpers or rely on Blade-injected state from the SPA.
- Do not duplicate alert evaluation, ingestion, authorization, or persistence logic in the client.

## Docker Rules

- Frontend and backend must have separate Dockerfiles.
- `docker-compose.yml` must start frontend and backend as independent services.
- The frontend must communicate with the backend through HTTP APIs and broadcasting.
- Database and Redis must be internal services.
- Development and production configuration differences must be documented.
- Do not bake `.env` secrets into images.
- Prefer environment variables and compose service names for container-to-container communication.

## Migration Hygiene

- Record meaningful behavior changes in `memory/05-migration-log.md`.
- Add new risks to `memory/06-pending-risks.md`.
- Promote repeated working notes into architecture decisions or formal docs.
- Verify routes and tests after each migration phase.
