# Task 8 — Authenticated Graph Catalog

## Delivered

- Added `GET /api/dashboard/graph-catalog` inside the existing verified Sanctum API group with the `api-read` throttle.
- Added `DashboardGraphCatalogController`, returning the complete, unpaginated device graph catalog. Its base query selects only `devices.id`/`devices.name` and eagerly loads only the graph-required sensor identity/type columns.
- The authenticated projection includes restricted sensors and maps their existing `RuleToGraphZones` result to the same safe graph shape as the public bootstrap: `id`, `name`, `unit`, `bands`, and rule-id-free `boundaries`.
- The catalog deliberately excludes API keys, network topology, serial numbers, device/sensor statuses, public-visibility state, type ids, and rule ids.
- Added `getAuthenticatedGraphCatalog(config = {})` to the frontend graph API client.
- Dashboard loading still fetches the public bootstrap for everyone. Authenticated users fetch and merge the complete authenticated graph catalog; guests make no authenticated-catalog request.

## Tests and verification

TDD evidence:

- Backend feature test was added first and failed with the expected 404 before the route/controller existed, then passed after implementation.
- Dashboard regression test was updated first and failed because the authenticated request had not been made, then passed after the frontend wiring was added.

Commands run:

- `back/php artisan test tests/Feature/DashboardGraphCatalogControllerTest.php` — pass: 2 tests, 20 assertions.
- `front/npm run test:unit -- src/views/DashboardView.test.js` — pass: 3 tests.
- `back/php artisan test` — pass: 428 tests / 1511 assertions (reported as 422 deprecations plus 6 non-deprecation passes; no failures).
- `front/npm run test:unit` — pass: 39 files, 199 tests.
- `front/npm run build` — pass.
- `back/php artisan route:list --path=dashboard/graph-catalog` — confirms the new GET/HEAD route.
- `git diff --check` — pass.

The environment emits existing PHP 8.5 PDO deprecation warnings. The full frontend suite also emits its existing jsdom canvas warnings while all tests pass. `vendor/bin/pint --test` reports the pre-existing unordered import block in `back/routes/api.php`; no broad import-only formatting change was made.

## Self-review

- Confirmed guest requests receive 401 and ordinary verified authenticated users receive 200.
- Confirmed a 101-device catalog is returned without pagination and a `public_monitoring_enabled = false` sensor is present.
- Confirmed the response carries zone bands/boundaries from the established `RuleToGraphZones` owner and removes `rule_id`.
- Confirmed response-body regression checks reject credential, topology, status, visibility, and internal type fields.
- Confirmed the actual `SensorMonitorBoard` prop receives the authenticated device and restricted sensor, while guests use the public bootstrap only.
- All changes remain unstaged; no commit or push was performed.
