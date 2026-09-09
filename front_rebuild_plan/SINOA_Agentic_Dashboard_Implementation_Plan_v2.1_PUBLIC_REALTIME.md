# SINOA Lab Blue Workspace implementation plan for coding agents

Version: 2.1 — repository-aligned public-realtime engineering edition, 9 September 2026.  
Prepared for: José Vicente Rincón Celis.  
Source: `SINOA_Plan_Desarrollo_Diseno_Final.docx`, version 1.0, 8 September 2026.  
Intended project: `yojr23/iot-platform-v2`, branch `refraccion`, subject to verification at execution time.  
Status: implementation specification updated against the current `refraccion` repository baseline. The public dashboard route, public telemetry endpoints, public Echo/Pusher channels, current Vue/Bootstrap/Chart.js stack, authenticated preference endpoints, and existing realtime ownership have been verified. **On 9 September 2026, the approved guest scope was narrowed to the public realtime graph only; section 0.1 and `FRONT_REBUILD_PLAN_ADJUSTMENTS_v1.1.md` override prior broad-public-dashboard wording.** This document still does not claim that the Lab Blue Workspace rebuild itself has been implemented or that its future acceptance tests have passed.

## 0.1 Approved guest mode and public graph boundary

Guests use the same Lab Blue dashboard workspace as authenticated users: responsive shell, toolbar, device/sensor selection, reading/freshness display, time-range control, main chart, chart list, graph statistics/inspector, and an ephemeral workspace draft. Guests do not receive a legacy dashboard, a login wall before graph monitoring, or a separate realtime implementation.

The public dashboard feature surface is deliberately narrow. Its server-owned graph bootstrap may return only approved graph-source identity and metadata, and its bounded graph-series operation may return only samples and graph metadata for an approved sensor and exact UTC window. The current repository can truthfully project device/sensor labels and IDs plus `sensorType.unit`; it has no persisted precision, thresholds, cadence, or quality policy, so those fields must not be fabricated. The sole public domain stream is the approved public sensor-reading stream, currently compatible with `sensor.{id}`. Public scope must be enforced by the same backend visibility policy in all three paths.

## v1.3 pre-Stage-6 corrections — authoritative over v1.2 and the mockup

Evidence: `docs/implementation/pre-stage6-evidence.md`, `docs/implementation/reading-time-semantics.md`.

- Canonical public entry is the **Vue SPA at `FRONT_URL/dashboard`** (Blade dashboard retired). `/api/iot/sensors` = credentialed ingestion, `/api/health` = anonymous liveness only; neither is guest product data. Transitional public APIs are cut atomically with the Stage 6 replacement, not before.
- **No invented scientific semantics or graph limits.** Threshold/precision/quality/cadence/stale/coverage have no owner and are not V1. Any raw/1m/2,000/50,000 bounds are illustrative until measured.
- **Ownership:** live projection store key = `sensorId`; historical graph query layer key = `authorizationScope + sensorId + from + to + aggregation`; **Pinia does not own Echo channels or subscription release** — `echo.js`/`channelRegistry.js`/`useSensorRealtime.js` do.
- **Authenticated restricted sensors** keep realtime via the private `sensor.{id}` channel; guests never subscribe to alert/event/device-status channels.
- **Time semantics = classification C (mixed/ambiguous); Stage 6 BLOCKED** until resolved. Implementation begins only on `PRE-STAGE-6 GATE: PASS`.

## v1.2 repository-audit corrections — authoritative for implementation

This addendum reconciles the visual brief with the checked Laravel/Vue code. It supersedes conflicting examples, RF wording, component proposals, and Stage 6–10 text below.

1. **Guest UI parity is visual and behavioral for graph monitoring, not a requirement to reuse a page tree.** Guest and authenticated users must see the same Lab Blue graph shell, toolbar, selectors, range control, primary chart/chart list, reading context, and graph empty/error states. Alerts, events, preferences, restricted sources, and administration are capability slots only: guests see an omitted/access-required variant with no request or subscription. Capture paired guest/auth graph screenshots at target widths and a guest network trace; never use a second legacy dashboard or a login wall for basic public monitoring.
2. **The public product entry point must be singular.** `back/routes/web.php` currently serves a public Blade dashboard with summary metrics, alerts, and unrestricted selection data, while `front/src/views/DashboardView.vue` is a separate SPA dashboard. Before public API cutover, choose the deployed Lab Blue entry point and retire, redirect, or authenticate the other route in the same release. This is a Stage 6 blocker, not Stage 10 housekeeping. Retained authenticated Blade sensor pages must obtain labels/unit from their authorized page data before the reading event is reduced.
3. **The anonymous product surface is exactly graph bootstrap, bounded graph series, and approved sensor-reading delivery.** Delete/protect `/api/config/public`, which today exposes alert settings and the obsolete polling interval; review `/api/iot/sensors` as an ingestion/internal endpoint; and classify `/api/health` as owned infrastructure liveness or protect it. `/api/alerts/active` and the alert channel remain a Stage 7 migration blocker. Route scans must prove the final product-domain boundary, not merely demonstrate that the Vue page stopped calling old endpoints.
4. **UTC requires a migration decision, not formatting.** The application defaults to `America/Bogota`, existing `reading_time` storage has no documented UTC invariant, and legacy ingestion accepts local timestamps. Probe known instants through the DB/application connection, document/migrate historical interpretation, normalize or reject future ambiguous values, then freeze second-precision `YYYY-MM-DDTHH:mm:ssZ` and half-open `[from,to)` windows. Add and benchmark `sensor_readings(sensor_id, reading_time, id)`; range series/statistics come from the indexed database, never from the 120-entry Redis latest-reading cache.
5. **P0 graph semantics are restricted to proven data.** P0 supplies labels, unit, values/timestamps, gaps, aggregation, and min/max/mean/count from returned valid readings. The current schema has no public threshold, precision, expected cadence, quality/validity, completeness, stale cutoff, or device-connectivity contract. Thus RF04 is P0 statistics only; RF11 is P0 unit/range/sample information only; RF12 distinguishes loading, no data, error, and browser transport state only. The threshold bands, exact temperature examples, quality percentage, cadence/frequency, stale formula, precision rounding, and disconnected-device copy later in this document are **P1 design placeholders**, blocked until a named server owner, public-safety decision, migration/configuration, contract, and tests exist.
6. **Visibility has current, sensor-wide semantics.** `PublicGraphVisibility` checks only `public_monitoring_enabled === true` at REST and browser delivery. Enabling permits the sensor's bounded stored history and later delivery; disabling suppresses facts not yet broadcast. Operational status/device/lab never decides visibility. Do not claim a time-bounded visibility window without a new policy/schema.
7. **The reduced public event is a contract cutover.** `NewSensorReading` must contain only reading identity, sensor ID, value, reading timestamp, and the versioned envelope. It cannot retain device/lab/unit fields as a fallback. Migrate legacy event consumers first; resolve labels/unit through graph bootstrap or authorized local metadata.

The supplied marketplace graph has no skills named `ponytail`, `caveman`, or `zero-hallucination`. Their requested intent is preserved as implementation discipline: one small vertical slice first, one owner per responsibility, no public compatibility fallback, and no capability asserted without repository evidence.

The closed policy is explicit, per-sensor, and fail-closed: add `sensors.public_monitoring_enabled BOOLEAN NOT NULL DEFAULT FALSE`; do not infer visibility from sensor/device operational status, Lab, or Device; and never bulk-enable existing records. `App\Services\Monitoring\PublicGraphVisibility` is the only owner of `$sensor->public_monitoring_enabled === true`, through `isPublic`, `publicSensorsQuery`, and `requirePublic`. It filters the bootstrap, rejects restricted/guessed series IDs with `404`, and is evaluated by `DomainEventBroadcastConsumer` before a public `NewSensorReading` dispatch. Ingestion, outbox, Redis Stream, and the internal reading projection still retain every sensor fact. Restricted facts are acknowledged without public delivery; `NewSensorReading` itself must not perform a policy lookup or create a public-only cache. Its public payload is limited to reading identity, sensor ID, value, timestamp, and envelope; the approved bootstrap, not the realtime event, provides labels and unit.

Public dashboard feature APIs and channels must not disclose global metrics, generic inventory/history, alerts, events, device status, preferences, account data, administration data, or restricted sensors. `alerts` and `device-status` are authenticated/authorized capabilities, not guest subscriptions. Existing infrastructure health/configuration endpoints are outside this product decision and must not be expanded in this work.

The mockup's layout remains authoritative for the guest graph experience, but not every illustrated control becomes public functionality. Alerts, recent events, account/admin areas, and other authorized-only modules render a clear access-required state or are omitted without making a public request. Search, laboratory/system selectors, chart gear/kebab actions, reports, rules, and configuration are outside P0 until backed by an API, authorization rule, and acceptance criterion. Guest navigation exposes only real public destinations. A guest header has a sign-in action, never an Admin identity.

This section is the source of truth when it conflicts with sections 1, 2.4, 3, 8, 10, 13, 16, 17, 18, or 19. The detailed execution constraints are maintained in `front_rebuild_plan/FRONT_REBUILD_PLAN_ADJUSTMENTS_v1.1.md`.

## 1. Mission and source authority

Implement the SINOA **Lab Blue Workspace** dashboard as one responsive web experience for desktop, tablet, and mobile. Combine the dominant chart of **Adaptive Laboratory, alternative 1**, with the chart selection and scientific detail of **Research Workspace, alternative 3**. These alternatives evolved from the 10C scientific and 10E adaptive dashboard directions. Preserve one auxiliary column so the primary signal retains enough space.

The operator must be able to select a sensor, interpret its latest measurement and trend, identify alerts, and organize a personal workspace without losing laboratory context. This is an operational monitoring interface. Data identity, freshness, units, and uncertainty must be apparent before secondary details.

**Public realtime monitoring is a P0 product invariant.** Registration is not required to inspect public sensor charts or receive their live updates. The existing `/dashboard` experience remains publicly reachable for telemetry that the backend classifies as public-safe. Authentication adds persistence, personalization, restricted resources, and privileged actions; it does not unlock the basic ability to monitor public laboratory telemetry.

This Markdown preserves the requirements, numerical specifications, priorities, acceptance criteria, phases, open decisions, and sources of the Word document. Sections on agent execution, implementation algorithms, brand voice governance, component APIs, and design maintenance expand that source. Newly proposed defaults are identified as such and must be reconciled with actual repository contracts.

The Word document contains the conceptual desktop and mobile image. That image is a layout reference, not a screenshot of implemented software or evidence of a Figma update. All essential layout and visual instructions are specified textually here so a coding agent can work without the image. If the image is available during implementation, save the authorized reference in the repository's existing design assets location and compare screenshots against it. Do not reconstruct numerical data from pixels. Exact tokens and domain rules in this specification take precedence over incidental inconsistencies in the mockup.

### 1.1 Execution boundaries

- The source brief identifies **Vue 3, Bootstrap 5, and Chart.js**. Verify installed versions and existing wrappers before implementation; do not migrate frameworks to realize this design.
- Preserve `/dashboard` as a public monitoring route. Do not add a login requirement to basic public telemetry.
- Preserve the public graph transport only: an approved public sensor-reading channel compatible with `sensor.{id}`. `alerts` and `device-status` are not public dashboard channels under the approved guest boundary.
- **No frontend polling, no polling fallback, and no permanent hybrid realtime model.** One bounded graph hydration and lifecycle-triggered one-shot recovery are allowed; steady-state graph updates use Echo/WebSocket.
- Share the graph engine, realtime pipeline, sensor projection, chart primitives, scientific semantics, tokens, and responsive design system across guest and authenticated modes. They do not need the same page composition or component tree; capability-specific regions may differ without creating a guest-only data path.
- Verify the starting branch, commit, actual dashboard route, API contracts, authorization, and available scripts. Paths and interfaces below are proposals, not a verified repository inventory.
- Preserve existing supported behavior and navigation. Dashboard actions configure visualizations; removing a widget never deletes a sensor, device, measurement, or alert.
- P0 requirements RF01–RF08 and RF11–RF13 define the shared public-graph release. RF09–RF10 remain P0 authenticated dashboard capabilities and must not initiate guest data requests. P1 requirements RF14–RF15 stay in a visible backlog until the core is stable.
- Exclude a native application, firmware changes, a complete rule editor, and a new notification platform. Link existing modules when available.
- Do not invent endpoints, historical measurements, sample completeness, backend capabilities, or claims that tests passed.
- Follow applicable repository instructions and existing user authorization. A plan document does not itself authorize production deployment or destructive changes.

## 2. Agent execution protocol

Work as a senior engineer responsible for implementation, data correctness, UI consistency, accessibility, and a reviewable handoff. Progress in small working increments. Make routine reversible decisions using repository conventions. Ask a focused question only when a missing product decision materially blocks a correct implementation; continue independent work while recording that dependency.

### 2.1 Discover before changing

1. Read applicable `AGENTS.md` files and repository documentation. Respect instruction scope; do not overwrite existing instructions with this document.
2. Record branch, commit, working tree changes, package manager, lockfile, runtime requirements, installed Vue/Bootstrap/Chart.js versions, and supported browser targets. Preserve unrelated work.
3. Trace the existing dashboard from router to page, shared shell, chart wrapper, API adapter, store/composables, authentication, error handling, and styles. Use targeted file searches rather than dumping the whole repository.
4. Inventory current dashboard capabilities and capture baseline screenshots with authorized or synthetic data. Record current failures separately from regressions introduced by this work.
5. Map every RF requirement to an existing implementation, a change, or an API gap. Document existing HTTP paths and payload examples with secrets and personal information removed.
6. Identify existing font files, approved logo, icon library, CSS variables, localization keys, UI primitives, test tools, and design documents. Extend these before introducing alternatives.
7. Resolve the phase 0 decisions in section 18. If the actual stack differs from the brief, report the evidence and adapt the implementation within scope; do not silently create a second frontend.

### 2.2 Work cycle and durable progress

For each phase: inspect the affected code, state a short implementation decision, make a coherent change, run the smallest meaningful verification, inspect the resulting UI where applicable, and update progress. Share decision summaries and evidence; lengthy private deliberation is unnecessary.

Maintain a repository-native progress document, proposed path `docs/dashboard/implementation-progress.md`, containing phase status, RF coverage, modified areas, verified commands/results, actual commit, unresolved dependencies, and the next executable action. Distinguish `planned`, `implemented`, `verified`, and `blocked`; checked boxes require evidence. Reuse the project's existing task format if present.

Keep a small decision log for tradeoffs involving ownership, time semantics, persistence, dependencies, and design changes. On continuation, read the progress log and current diff before restarting work. Do not redo completed investigation without a concrete uncertainty.

### 2.3 Required completion report

Report the implemented behavior, affected entry points, requirement coverage, screenshots and test evidence, known limitations, and remaining backend dependencies. Separate tests executed from tests planned or blocked. Do not describe a static mockup as an integrated dashboard, or local storage as cross-device persistence. Prepare a reviewable change/PR when supported and authorized; preserve the project's deployment gate.


### 2.4 Verified repository baseline and public-realtime ownership

The following facts are confirmed in the current `refraccion` repository and are execution constraints for this rebuild:

| Concern | Verified owner / contract |
| --- | --- |
| Public route | `/dashboard` → `front/src/views/DashboardView.vue`; no authentication requirement on the route. |
| Current dashboard composition | `front/src/components/dashboard/*`, especially `SensorMonitorBoard.vue`. |
| Dashboard API adapter | `front/src/api/dashboard.js`. |
| Realtime singleton | `front/src/realtime/echo.js`. |
| Channel ownership | `front/src/realtime/channelRegistry.js` with reference-counted listeners. |
| Sensor realtime adapter | `front/src/realtime/useSensorRealtime.js`. |
| Alert realtime adapter | `front/src/realtime/useAlertsRealtime.js`. |
| Shared alert projection | `front/src/stores/alerts.js` using Pinia. |
| Authentication projection | `front/src/stores/auth.js` using Pinia. |
| Current public dashboard surface | `/api/dashboard/public`, `/api/sensors/{sensor}/latest-readings`, and `/api/devices/{device}/sensors` currently expose data too broadly for the approved target and must be reduced to a scoped graph bootstrap plus a bounded graph-series contract. `/api/alerts/active` is not a guest dependency. |
| Authenticated preferences | `GET /api/dashboard/preferences`, `PUT /api/dashboard/preferences`. |
| Public realtime channel | Only the reading channel for a server-approved graph sensor, currently compatible with `sensor.{id}`. `alerts` and `device-status` are authenticated/authorized capabilities, not guest subscriptions. |
| Frontend stack | Vue 3, Bootstrap 5, Chart.js 4, vue-chartjs, Pinia, Laravel Echo, Pusher JS, Vite, Vitest, Playwright. |

**Reuse rule:** migrate or extend these owners. Do not create a parallel dashboard data layer, second Echo connection manager, second alert projection, or another state-management framework merely to match proposed paths in this specification.

**Gate 6 integration:** the Lab Blue Workspace rebuild must consume the shared sensor-reading projection introduced by the realtime cutover. The current `SensorMonitorBoard.vue` polling loop is transitional debt and must not become part of the new dashboard architecture.

#### Guest versus authenticated capability model

One dashboard supports both modes:

| Capability | Guest | Authenticated |
| --- | ---: | ---: |
| Open `/dashboard` | Yes | Yes |
| List public graph sources | Yes, only through the scoped graph bootstrap | Yes, plus authorized sources |
| Receive public sensor updates through Echo/WebSocket | Yes, only for approved graph sensors | Yes, according to authorization |
| Inspect public live charts and bounded history | Yes, only through the graph-series contract | Yes, according to authorization |
| Add/remove/reorder charts in the current draft/session | Yes | Yes |
| Save workspace to server / restore cross-session preferences | No; authentication CTA | Yes |
| See alert/event/device-status data | No; access-required state without sensitive data | Only when authorized |
| Access restricted sensors/resources | No | Only when authorized |
| Administrative/edit actions | No | Capability/role dependent |

Guest workspace editing is ephemeral by default. Do not label it **Saved** unless persistence is actually confirmed. If the guest chooses Save, begin the existing authentication flow while preserving the current draft where practical.

#### Public-scope security invariant

Public visibility is decided by the backend, not by hiding items in Vue. `PublicGraphVisibility` applies the explicit per-sensor `public_monitoring_enabled === true` rule to the public graph bootstrap, graph-series response, and browser broadcast producer. It must not use operational status as a proxy. Alerts, events, device status, preferences, and restricted resources are not public dashboard data and must not be discoverable through a guest endpoint or channel payload.


## 3. Product requirements and traceability

| ID | Priority | Requirement | Acceptance condition |
| --- | --- | --- | --- |
| RF01 | P0 | Select device and sensor | Guests see only server-approved public sensors for the selected device; authenticated users may additionally see authorized restricted sensors. |
| RF02 | P0 | Latest reading and live status | Public sensors update in real time without registration; value, unit, timestamp, freshness, and connection state all belong to the active sensor. |
| RF03 | P0 | Time-series chart | Offer `1m`, `5m`, `1h`, `6h`, `24h`; default `5m`; render missing-data gaps. |
| RF04 | P0 | Thresholds and statistics | Min, max, and mean use the selected period; display units and configured limits. |
| RF05 | P0 | My charts | Selection synchronizes chart, reading, and inspector; guests can organize an ephemeral draft; one active main chart is mounted on mobile. |
| RF06 | P0 | Add and remove charts | Validate device/sensor; removal affects only the widget and offers Undo. |
| RF07 | P0 | Reorder widgets | Desktop drag plus Move up/Move down alternatives for keyboard and mobile. |
| RF08 | P0 | Save workspace | Guests may edit a session draft but server persistence requires authentication. Authenticated save restores order, selection, ranges, and configuration; Saved appears only after confirmed persistence/load. |
| RF09 | P0 authenticated capability | Active alerts | Authorized count and list agree within the authenticated scope and may show a prioritized critical alert before the main chart. Guest mode shows an access-required/omitted state and makes no alert request or subscription. |
| RF10 | P0 authenticated capability | Recent events | Authorized users see date/time, device, event, value, severity, and a working history destination. Guest mode shows an access-required/omitted state and makes no event request. |
| RF11 | P0 | Scientific details | Show unit, range, frequency, quality, and sample information; show missing-data states honestly. |
| RF12 | P0 | Operational states | Loading, empty, error, reconnecting, and stale states have clear messages and actions. |
| RF13 | P0 | Permissions | Public-read scope is server-defined; restricted reading, persistence, editing, and administration reflect authenticated capabilities and are authorized on the server. |
| RF14 | P1 | Duplicate and export | Explicit duplication creates a new stable ID; export uses the selected sensor and period. |
| RF15 | P1 | Reading distribution | Optional histogram in Details uses the same period as the signal. |

**Cross-cutting P0 invariant — Public realtime access:** a guest can open `/dashboard`, select a public sensor, hydrate its bounded history, and continue receiving live chart updates over Echo/WebSocket without registering or logging in. Authentication must not create a second telemetry implementation.

Every visible action must work. If a P1 feature is absent, omit its control or expose a deliberate explained unavailable state only when useful; avoid decorative buttons that do nothing. An unavailable full-history route is an RF10 dependency to resolve, not a reason to ship a dead link.

## 4. Brand identity and product voice

### 4.1 Brand character

SINOA is precise, calm, technical, and practical. Its UI helps laboratory operators and researchers make sense of sensor conditions. Use clear nouns, short action labels, explicit units, and actionable operational messages. Retain the approved **SINOA** wordmark and existing technical pulse symbol. Do not invent a new logo or assign a new expansion to the acronym.

The visual direction is a cool light canvas, white surfaces, restrained blue actions, readable dense measurements, and consistent grouping. Blue signals brand and selection. Green, amber, and red communicate domain states with text and icons. The most prominent value is the selected sensor's actual reading; warnings elsewhere must not recolor it as if its own state changed.

Preserve these invariants across later pages and future agent sessions:

1. Context precedes measurement: device, sensor, unit, and timestamp remain identifiable.
2. Critical information is visible without opening a customization panel.
3. A single clear primary action per action region; **Add chart** is the workspace primary action, **Save** is secondary. For guests, Save is an authentication/persistence CTA rather than a requirement to monitor public telemetry.
4. Details progressively disclose below or beside the main signal; they never squeeze it into an unreadable thumbnail.
5. Color reinforces a label; no status is communicated by color alone.
6. Missing, stale, invalid, and disconnected are distinct states, not a generic zero.
7. One reusable component vocabulary and token system supports every viewport.
8. Product copy explains the operator's situation and next action without exposing internal implementation terms.

Avoid gradients, glass effects, neon, decorative photographs, 3D effects, oversized rounded cards, ornamental illustrations, heavy shadows, playful emoji, exaggerated success messages, and marketing slogans inside operational flows.

### 4.2 Language and terminology

This engineering plan is in English. The original UI is Spanish. Preserve the application's configured locale; do not translate the deployed interface to English merely because the implementation instructions are English. The following bilingual examples define meaning and tone, not a requirement to build a new localization framework. Reuse the existing localization approach; if none exists, centralize new strings in a small module compatible with project conventions.

| Meaning / proposed key | Spanish product copy | English equivalent |
| --- | --- | --- |
| `dashboard.title` | Mi tablero | My workspace |
| `dashboard.charts.title` | Mis gráficas | My charts |
| `dashboard.charts.add` | Agregar gráfica | Add chart |
| `dashboard.charts.remove` | Quitar gráfica | Remove chart |
| `dashboard.charts.undo` | Deshacer | Undo |
| `dashboard.charts.edit` | Editar | Edit |
| `dashboard.charts.moveUp` | Subir | Move up |
| `dashboard.charts.moveDown` | Bajar | Move down |
| `dashboard.save.action` | Guardar | Save |
| `dashboard.save.dirty` | Cambios sin guardar | Unsaved changes |
| `dashboard.save.pending` | Guardando… | Saving… |
| `dashboard.save.confirmed` | Guardado | Saved |
| `dashboard.save.failed` | No se pudo guardar el tablero. Tus cambios siguen disponibles. | Could not save the workspace. Your changes are still available. |
| `dashboard.save.conflict` | El tablero cambió en otra sesión. Revisa los cambios antes de guardar. | The workspace changed in another session. Review the changes before saving. |
| `dashboard.data.empty` | No hay datos en este período. | No data in this period. |
| `dashboard.data.unavailable` | No disponible | Not available |
| `dashboard.data.missing` | Sin dato | No reading |
| `dashboard.data.stale` | Datos antiguos | Stale data |
| `dashboard.connection.live` | EN VIVO | LIVE |
| `dashboard.connection.reconnecting` | Reconectando… | Reconnecting… |
| `dashboard.connection.offline` | Sin conexión | Disconnected |
| `dashboard.details.title` | Detalle del sensor | Sensor details |
| `dashboard.quality.label` | Calidad de señal | Signal quality |
| `dashboard.quality.help` | Muestras válidas respecto a las esperadas en el período. | Valid samples as a share of expected samples in this period. |
| `dashboard.retry` | Reintentar | Retry |
| `dashboard.permission.denied` | No tienes acceso a este sensor. | You do not have access to this sensor. |

Use sentence case except the existing SINOA wordmark and deliberate compact LIVE badge. Do not use an exclamation mark to intensify alerts. State the observed condition, device, value/unit, time, and available action. Example: `Temperatura alta · Nodo 7 · 31,2 °C`; format the decimal separator using the active locale. Never label a server failure “sensor failure” without evidence.

Keep sensor, device, chart, workspace, alert, event, and quality as distinct terms. “Remove chart” changes presentation; “delete sensor” is outside scope. “Signal quality” retains the source label but always explains sample completeness; never use Wi-Fi bars or RF terminology to imply radio strength.

## 5. Information architecture and responsive layout

### 5.1 Decision hierarchy

| Priority | Content | Design consequence |
| --- | --- | --- |
| 1 | Active critical alert and sensor context | Place the critical banner before the plot; identify its device independently. |
| 2 | Current value, freshness, time chart, thresholds | Allocate the largest readable area; avoid decorative summaries above it. |
| 3 | Chart selection and active-sensor statistics | Keep selection close and clearly marked; align statistics with the chosen period. |
| 4 | Secondary signals, history, organization | Use lower rows, the single right column, and progressive disclosure on mobile. |

An alert from **Node 7** must never change **Reactor 1** into a critical sensor unless Reactor 1 independently satisfies its own authoritative condition. Workspace-level and active-sensor status have separate data selectors.

### 5.2 Desktop reference at 1440 CSS pixels

- Global header: **64 px** high.
- Global navigation sidebar: **208 px** wide.
- Remaining content: **24 px** horizontal padding on each side.
- Main content column: flexible; auxiliary column: **280 px**; gutter: **16 px**.
- Main column width at 1440: `1440 − 208 − 48 − 280 − 16 = 888 px`.
- Main plot area: at least **280 px high**, preferably **320 px**, excluding titles, controls, legend, and statistics.
- Main column order: `WorkspaceToolbar`, `CriticalAlertBanner`, `SensorChartCard`, three `SparklineCard` components, `RecentEvents`.
- Right column order: `ChartList`, `SensorInspector`, `ActiveAlerts`.
- The right column is not a second global navigation sidebar. Move it below the chart when space is insufficient.

### 5.3 Breakpoint contract

| CSS width | Navigation | Composition |
| --- | --- | --- |
| ≥ 1400 px | 208 px sidebar | Main + 280 px inspector; three secondary signals. |
| 1200–1399 px | 208 px sidebar | Two columns only if main chart retains ≥ 620 px width; otherwise move inspector below. |
| 992–1199 px | 64 px navigation rail | One primary reading column; auxiliary modules below in two columns if they fit. |
| 768–991 px | Collapsible menu | Full-width chart; two-column auxiliary area only where content fits. |
| < 768 px | Top bar and bottom navigation | Single column; one active signal; collapsible details and history. |

These width tiers align with Bootstrap's documented responsive system; composition additionally depends on usable container width. Preserve installed Bootstrap settings if customized and document the mapping. [Bootstrap breakpoints](https://getbootstrap.com/docs/5.3/layout/breakpoints/)

With the reference sidebar and padding, two content columns require at least `620 + 16 + 280 = 916 px` of inner width. Therefore the reference full layout begins at a viewport of approximately **1172 px**, and the source 1200 px tier normally has sufficient room. Actual scrollbars, embedding, custom navigation, zoom, and parent constraints can reduce that space: measure the content container rather than relying on a viewport assumption alone.

### 5.4 Mobile reference at 390 CSS pixels

Preserve this meaningful DOM/reading order:

1. Mobile top bar.
2. Workspace title and Add chart action.
3. Active critical banner, when present.
4. Device and sensor selection.
5. Current reading and freshness.
6. Time-range control.
7. Main chart.
8. Active-sensor statistics.
9. My charts.
10. Collapsible sensor details.
11. Collapsible recent events.

Retain access to all active alerts on mobile, proposed as a compact summary and expandable list near My charts/details. Do not hide noncritical alerts merely because the desktop right column disappears. Full alerts/history routes remain available through working navigation.

Use **16 px** horizontal page padding, **16 px** card padding, and fixed bottom navigation of **64 px plus safe-area inset**. Reserve its full height and breathing room at the bottom of the document. At **320 px**, selectors and statistics wrap into two rows. Prevent global horizontal scrolling and clipped controls.

At the reference **390 × 844** portrait viewport and default text size, fit the alert, selectors, reading, and useful plot area into the first content viewport as far as the source composition allows. Proposed compact mobile plot height: **200–240 px**, adjusted after measuring the actual chrome. Long alert messages, translated content, or enlarged text may require scrolling; preserve legibility and complete critical content rather than clipping to force a screenshot target. Show supplementary details by normal vertical scroll.

### 5.5 Layout implementation recipe

- Keep one data orchestration layer and one logical main chart. Responsive CSS changes placement; viewport-specific navigation components may differ without duplicating business logic.
- Use semantic `header`, `nav`, `main`, sections, and an appropriately labelled auxiliary region. Keep DOM order understandable on its own.
- Use CSS Grid for the overall content and small grids/flex rows for controls. Apply `min-width: 0` to flexible grid children; long IDs and localized labels must wrap or truncate with a discoverable full value.
- Use a composition class from an existing responsive utility or container measurement if needed. Do not make rendering dependent on hardcoded device names or user-agent sniffing.
- At narrow widths, collapse the inspector into accessible sections. Preserve selected widget, draft, focus intent, and range across resizes.
- Avoid duplicate hidden canvases, double requests, and simultaneous desktop/mobile stores. Do not remount the entire page on a width change.
- Use a consistent layer scheme for header, bottom navigation, dropdowns, toast, and modal; integrate with Bootstrap's existing stacking system rather than introducing arbitrary high `z-index` values.
- Verify keyboard focus follows visual meaning when grid placement changes; do not use positive `tabindex` to repair layout order.

## 6. Design tokens and visual grammar

### 6.1 Authoritative semantic palette

| CSS token | Value | Purpose |
| --- | --- | --- |
| `--sinoa-bg` | `#F8FAFC` | Application background. |
| `--sinoa-surface` | `#FFFFFF` | Cards, bars, panels. |
| `--sinoa-surface-muted` | `#F1F5F9` | Secondary surfaces and skeletons. |
| `--sinoa-selected` | `#EFF6FF` | Selected navigation and chart. |
| `--sinoa-brand` | `#1E40AF` | Logo, principal reading, selected prominent titles. |
| `--sinoa-action` | `#2563EB` | Primary actions, links, selection. |
| `--sinoa-action-hover` | `#1D4ED8` | Primary action hover. |
| `--sinoa-series` | `#3B82F6` | Main signal; use action blue when more contrast is needed. |
| `--sinoa-cyan` | `#0891B2` | Secondary signal accent. |
| `--sinoa-text` | `#0F172A` | Primary text. |
| `--sinoa-text-secondary` | `#475569` | Labels and descriptions. |
| `--sinoa-text-muted` | `#64748B` | Metadata; do not make essential information appear disabled. |
| `--sinoa-border` | `#E2E8F0` | Decorative separators and card borders. |
| `--sinoa-control-border` | `#64748B` | Functional control outline when needed for identification. |
| `--sinoa-success` | `#15803D` | Normal-state text and icons. |
| `--sinoa-warning` | `#A16207` | Warning text; use `#F59E0B` for warning accent/line. |
| `--sinoa-danger` | `#DC2626` | Critical text and icons. |
| `--sinoa-focus` | `#1E40AF` | 2 px focus outline, 2 px offset. |

State backgrounds: normal `#22C55E` at **12%** opacity; warning `#F59E0B` at **14%**; critical `#DC2626` at **12%**. Critical banner background `#FEF2F2`, border `#FECACA`. Plot threshold bands behind the signal and label threshold values. Validate contrast against the actual composited colors; the light decorative border does not replace a functional control boundary.

### 6.2 Copyable CSS baseline

Integrate into the existing theme entry point or proposed `front/src/styles/sinoa-tokens.css`. This is a starting implementation, not evidence of an existing file. Named additions encode source values and prevent repeated literals.

```css
:root {
  --sinoa-bg: #F8FAFC;
  --sinoa-surface: #FFFFFF;
  --sinoa-surface-muted: #F1F5F9;
  --sinoa-selected: #EFF6FF;
  --sinoa-brand: #1E40AF;
  --sinoa-action: #2563EB;
  --sinoa-action-hover: #1D4ED8;
  --sinoa-series: #3B82F6;
  --sinoa-cyan: #0891B2;
  --sinoa-text: #0F172A;
  --sinoa-text-secondary: #475569;
  --sinoa-text-muted: #64748B;
  --sinoa-border: #E2E8F0;
  --sinoa-control-border: #64748B;
  --sinoa-success: #15803D;
  --sinoa-warning: #A16207;
  --sinoa-warning-accent: #F59E0B;
  --sinoa-danger: #DC2626;
  --sinoa-focus: #1E40AF;
  --sinoa-normal-band: rgb(34 197 94 / 12%);
  --sinoa-warning-band: rgb(245 158 11 / 14%);
  --sinoa-critical-band: rgb(220 38 38 / 12%);
  --sinoa-critical-bg: #FEF2F2;
  --sinoa-critical-border: #FECACA;
  --sinoa-font-ui: Inter, system-ui, sans-serif;
  --sinoa-font-data: "JetBrains Mono", ui-monospace, monospace;
  --sinoa-space-1: 4px;
  --sinoa-space-2: 8px;
  --sinoa-space-3: 12px;
  --sinoa-space-4: 16px;
  --sinoa-space-5: 20px;
  --sinoa-space-6: 24px;
  --sinoa-space-8: 32px;
  --sinoa-space-10: 40px;
  --sinoa-radius-main: 10px;
  --sinoa-radius-card: 8px;
  --sinoa-radius-control: 6px;
  --sinoa-border-width: 1px;
  --sinoa-shadow-card: 0 1px 2px rgb(15 23 42 / 4%),
                        0 4px 12px rgb(15 23 42 / 3%);
  --sinoa-motion-fast: 120ms;
  --sinoa-motion-normal: 180ms;
  --sinoa-touch-target: 44px;
  --sinoa-header-height: 64px;
  --sinoa-sidebar-width: 208px;
  --sinoa-rail-width: 64px;
  --sinoa-inspector-width: 280px;
  --sinoa-bottom-nav-height: 64px;
}

.sinoa-dashboard {
  color: var(--sinoa-text);
  background: var(--sinoa-bg);
  font-family: var(--sinoa-font-ui);
}

.sinoa-data {
  font-family: var(--sinoa-font-data);
  font-variant-numeric: tabular-nums;
}

.sinoa-dashboard :focus-visible {
  outline: 2px solid var(--sinoa-focus);
  outline-offset: 2px;
}

@media (max-width: 767.98px) {
  .sinoa-dashboard__content {
    padding-bottom: calc(var(--sinoa-bottom-nav-height)
      + env(safe-area-inset-bottom, 0px) + var(--sinoa-space-4));
  }
}

@media (prefers-reduced-motion: reduce) {
  .sinoa-dashboard { --sinoa-motion-fast: 0ms; --sinoa-motion-normal: 0ms; }
}
```

Use these tokens in both DOM styles and a chart-theme adapter; a canvas does not automatically inherit every CSS variable. Read computed styles after the theme is applied, resolve tokens to actual values, and refresh chart options only when theme/font conditions change. Do not duplicate a second hardcoded palette in JavaScript.

If Bootstrap Sass is already built locally, map approved theme values using its existing pipeline. If the project consumes compiled CSS, use scoped component/theme overrides and installed-version-supported variables. Changing only a generic primary CSS variable may not update all compiled component states. Inspect actual button, focus, disabled, hover, dropdown, and form styles. Avoid unscoped overrides that change unrelated pages without review.

### 6.3 Typography

Use **Inter** for UI and **JetBrains Mono** only for readings, units, timestamps, and identifiers. Use locally hosted approved font assets, `font-display: swap`, and the fallbacks above. Keep the existing asset license information. Do not introduce more font families. Numeric displays use tabular numerals.

| Role | Desktop | Mobile | Weight | Line height |
| --- | --- | --- | --- | --- |
| Page title | 30 px | 22 px | 600 | 1.2 |
| Main measurement | 48 px | 36 px | 700 | 1.1 |
| Card heading | 16 px | 15 px | 600 | 1.35 |
| Body and controls | 14 px | 14 px | 400–500 | 1.45 |
| Technical labels | 12 px | 12 px | 500 | 1.4 |
| Metadata | 12 px | 11–12 px | 400 | 1.4 |
| Secondary measurement | 24 px | 20 px | 700 | 1.2 |
| Chart axes and legend | 11–12 px | 11 px | 400–500 | 1.35 |

These are reference sizes, not a reason to block text scaling. Convert to the project's accessible sizing convention where appropriate and verify browser zoom. If 14 px mobile form text causes unwanted focus zoom in the supported browser, document and apply a localized 16 px input-text exception rather than disabling user zoom. Keep other typography roles consistent.

### 6.4 Geometry and interaction styling

Spacing scale: **4, 8, 12, 16, 20, 24, 32, 40 px**. Page padding: **24 px desktop / 16 px mobile**. Card padding: **20 px desktop / 16 px mobile**. Module gaps: **16–20 px**. Radius: **10 px primary card / 8 px secondary cards / 6 px controls**. Borders: **1 px**. Use the single subtle shadow defined above. Typography, proximity, and spacing establish hierarchy.

Reuse the existing outline icon family: **20 px**, **1.75–2 px** stroke. Icon-only controls require accessible names and desktop tooltips. Mobile touch targets are at least **44 × 44 px**, even when their glyph is smaller. Focus styling must survive overflow containers.

Transitions last **120–180 ms** for opening, focus, and selection; honor reduced motion. Do not animate each telemetry sample or cause layout movement when digits change. Avoid `transition: all` and decorative live pulsing. Save is secondary and disabled when no preference changes need persistence; never show Saved optimistically.

## 7. Chart semantics and scientific data rules

### 7.1 Visual chart contract

Place device, sensor, and unit before the primary reading. The same chart card contains freshness, timestamp, range controls, and statistics. Use a **2–2.5 px** line, no intermediate point markers by default, and a **4 px** last-point marker. Tooltips include date, time, explicit time zone, value, unit, and quality flag. Support touch activation and a textual latest-readings alternative.

The horizontal axis is chronological. Preserve long gaps; never turn `null` into zero. The Y domain includes configured thresholds and observed values with suitable padding. Do not crop excursions to make the series look stable. Use one physical quantity/unit per primary signal; do not silently overlay unrelated units on one axis. All range-dependent detail, statistics, and optional histograms use the same resolved time window.

### 7.2 Exact example thresholds

| Example zone | Condition | Rendering |
| --- | --- | --- |
| Normal | `20 ≤ T < 28 °C` | Soft green band and Normal label. |
| Warning | `28 ≤ T ≤ 30 °C` | Soft amber band and threshold line at 28 °C. |
| High critical | `T > 30 °C` | Soft red band and threshold line at 30 °C. |
| Below range | `T < 20 °C` | Out-of-range label; severity follows the server's configured lower rule. |

These are example settings, not universal sensor limits. Include lower rules when configured. The intervals do not overlap. The backend is authoritative for alerts; the frontend may classify a current reading from a versioned rule for presentation, but does not manufacture or resolve backend alert records. Changing a rule must not rewrite an old event's recorded severity or rule revision.

Do not globally color the main reading red because another device has an alert. Where useful, pair the brand-colored reading with its own status badge; use severity styling only for the actual selected sensor condition.

### 7.3 Definitions and time consistency

- **Current reading:** latest valid observation by timestamp. A late older sample cannot replace a newer observation. A newer invalid sample can inform quality/freshness without becoming a valid displayed value.
- **Minimum, maximum, mean:** valid original samples inside the resolved selected period, calculated before display decimation. Use the arithmetic mean for regular sampling; explicitly name an alternative aggregation if supplied by the backend.
- **Delta:** current valid reading minus the previous valid reading. State the comparison interval in the tooltip. This is not percentage change. No previous sample means delta is unavailable.
- **Signal quality:** valid expected samples divided by expected samples × 100, with visible period and denominator. This is sample completeness, not RF signal strength. Missing expected frequency or undefined validity means Not available.
- **Example:** five minutes sampled every two seconds contains **151 expected instants if both endpoints are included**. **148 / 151 ≈ 98.0%**. Compute from the agreed sampling schedule and interval policy; never hardcode these values.
- **Rounding:** preserve raw precision for calculations; round only for display using sensor precision and locale. Do not compare rounded values to thresholds.
- **Timestamp storage:** normalize to UTC; display the configured laboratory time zone and never silently substitute the browser zone.

Resolve an explicit `from`, `to`, interval inclusion policy, sensor ID, and request identity for each response. In a rolling live window, use a shared anchor so chart, sample counts, and statistics advance together. The live latest-value card may be newer than the last completed historical query; show its timestamp and update the range data consistently instead of implying exact synchronization prematurely. For a historical/frozen range, label the live latest reading as current if it remains visible; do not mislabel it as the endpoint of history.

If timestamps do not align perfectly to the expected schedule, agree a tolerance and unique sample-slot policy with the backend. Retries and duplicates must not increase the denominator or make completeness exceed 100%. Do not blindly clamp an invalid result to hide a counting error. For irregular sampling, expose the backend's documented coverage calculation or Not available rather than claiming the regular-sampling formula applies.

### 7.4 Chart implementation sequence

1. Normalize and validate source data in an adapter/domain function. Preserve invalid flags and gap information.
2. Calculate or receive authoritative full-period statistics and coverage before reducing drawing points. For large historical ranges, request aggregates from the backend when already supported.
3. Build a draw-only series with timestamps and `null` gaps. If missing intervals are represented only by widely separated valid samples, explicitly insert appropriate gap separators using the agreed gap policy; disabling gap spanning alone cannot detect absent points.
4. Reduce drawing points according to canvas width and dataset size while preserving peaks, troughs, and segment boundaries. Validate the installed Chart.js decimation prerequisites; do not enable optimizations on data that violates their assumptions.
5. Mount one Chart.js instance per mounted chart canvas, reuse it for updates, and destroy it with its subscriptions on unmount. Disable repeated animation for streaming updates. These lifecycle and performance choices should use the existing wrapper where it handles them correctly. [Chart.js performance](https://www.chartjs.org/docs/latest/general/performance.html)
6. Use the installed time-scale/date adapter if present. Confirm time parsing and displayed laboratory zone; do not add a competing date library without a concrete need.
7. Implement threshold bands through the existing annotation capability or a small scoped chart plugin. Draw only inside `chartArea`, use scale coordinates, handle reversed pixel direction, save/restore the canvas context, and render the data line above the fills. Plugin lifecycle hooks support custom drawing. [Chart.js plugins](https://www.chartjs.org/docs/latest/developers/plugins.html)
8. Provide labelled HTML measurement/statistics and a recent-readings table. Do not make canvas tooltips the only way to inspect values.
9. Verify thresholds, gaps, resized canvases, font loading, zoom, touch, and the 24-hour dataset against semantic fixtures.

## 8. Interaction flows and state machines


### 8.0 Public guest mode and authenticated enhancement

Public graph monitoring is not a degraded static preview. A guest receives the same Lab Blue graph workspace and graph projection as an authenticated user. Alert/event/device-status panels are authorized capabilities: guest mode renders their access-required or omitted state without a public request.

Guest flow:

```text
open /dashboard
→ load scoped public graph bootstrap metadata
→ subscribe through the existing Echo/Pusher transport
→ bounded public graph-series hydration
→ shared sensor-reading projection
→ continuous live chart updates
```

Authentication changes capabilities, not the fundamental telemetry pipeline:

```text
same dashboard + same public projection
→ authentication
→ preserve current draft/selection when practical
→ enable server-backed workspace persistence
→ expose additional restricted capabilities only when authorized
```

Do not fork the page into `PublicDashboard` and `AuthenticatedDashboard` implementations with duplicated selection, chart, recovery, or store logic.



### 8.1 Selection and editing

`selectedWidgetId` is the single authority for the active chart. Derive active device, sensor, range, inspector, and reading from that widget. Avoid separately writable selected sensor values that can drift out of sync.

Selecting a chart changes the active widget immediately. Display cached data only for its exact authorized identity and range, with appropriate freshness. Changing a widget's device clears an incompatible sensor before a new query is issued. A partially configured selection cannot request the previous sensor under the new device label.

**Add:** open a dialog/panel with device, sensor, and optional title; populate only authorized options; validate the device/sensor relationship; create a stable widget ID and select it on confirmation. Prevent accidental duplicates. A deliberate duplicate is a separate P1 operation.

**Remove:** remove only the widget from the local draft, offer Undo, and select the next sensible neighboring item when the active one is removed. Preserve the removed widget's ID, configuration, order position, and former selection in the undo record. Removing the last widget shows an empty state with Add chart. Undo must respect any intervening edits and current permissions; do not restore unauthorized content.

**Reorder:** enter Edit mode, then enable desktop drag handles and Move up/Move down actions. Use stable IDs as rendering keys. Announce the new position after a keyboard move. Disable boundary moves. Outside Edit mode, avoid pointer handling that captures mobile scrolling. Reordering changes the draft, not server-side sensor order.

### 8.2 Save state contract

| State | UI and behavior |
| --- | --- |
| Clean | Save disabled; Saved only after confirmed load/save, not for an unsaved default workspace. |
| Dirty | Show Unsaved changes; preserve order, selection, and configuration in a local draft. |
| Saving | Prevent duplicate submission; keep telemetry and navigation within the dashboard responsive. |
| Saved | Apply returned revision and confirmation time; mark only the submitted changes as persisted. |
| Save error | Keep draft, explain failure, and offer Retry. |
| Revision conflict | Preserve local draft and remote state; offer reload/review, never silent overwrite. |
| Leave with changes | Offer Save, Discard, or Keep editing. Clean navigation has no confirmation. |

For an unauthenticated guest, workspace changes remain an ephemeral draft. A Save action may open login/register while preserving the draft, but it must not show Saved before authenticated server persistence succeeds.

Do not implement these as unrelated booleans permitting contradictory states. A discriminated state plus draft/baseline comparison, or the existing equivalent, is easier to reason about. Loading, connection, and save states are independent: a save error does not replace a healthy chart with a full-page failure.

### 8.3 Loading, freshness, and connection

Initial loading uses skeletons matching final dimensions, without fake measurements. Empty history says No data in this period and offers an appropriate range action. Partial failure affects only the failed module. Permission loss clears restricted data and moves to a valid authorized selection or an explicit empty state.

Proposed stale threshold: `age > max(3 × expectedPeriodMs, 10_000 ms)`, configurable per agreed contract. Replace LIVE with Stale data while keeping the last valid value and timestamp. If expected period is unknown, use a documented server policy or show unknown freshness; do not assume the example two-second rate.

Disconnected requires confirmed transport/device status. Missing readings alone do not prove physical failure. Distinguish browser/API connectivity from device connectivity in messages. Reconnect with bounded progressive backoff and jitter where compatible with the existing transport; refill the missing interval, deduplicate it, and update freshness based on actual sample times.

## 9. Frontend architecture and component contracts

### 9.1 Proposed repository organization

Reuse existing locations if they already express these responsibilities. Choose `.ts` or `.js` according to the project; the type examples below describe contracts and do not mandate a TypeScript migration.

| Proposed path | Responsibility |
| --- | --- |
| `front/src/features/dashboard/pages/DashboardPage.vue` | Compose page and orchestrate feature state. |
| `front/src/features/dashboard/components/*.vue` | Presentational and interaction components. |
| `front/src/features/dashboard/composables/useDashboardWorkspace.*` | Draft, baseline, save, revision, and restore. |
| `front/src/features/dashboard/composables/useSensorSeries.*` | Query lifecycle, identity, cancellation, and cache. |
| `front/src/features/dashboard/composables/useLiveReadings.*` | Existing live transport, freshness, reconnect, cleanup. |
| `front/src/features/dashboard/composables/useSensorStatistics.*` | Validity-aware aggregation and coverage presentation. |
| `front/src/features/dashboard/composables/useAlertFeed.*` | Alert records, active counts, and recent events. |
| `front/src/features/dashboard/services/dashboardRepository.*` | Adapt real backend contracts to feature contracts. |
| `front/src/features/dashboard/domain/types.*` | Domain interfaces and boundary validation contracts. |
| `front/src/features/dashboard/domain/thresholds.*` | Versioned threshold interpretation. |
| `front/src/features/dashboard/domain/statistics.*` | Pure calculations and sample handling. |
| `front/src/styles/sinoa-tokens.css` | Canonical visual values or aliases into the existing theme. |

Components receive data and emit user intent; composables encapsulate reusable stateful behavior. Keep teardown with the effects it owns. [Vue composables](https://vuejs.org/guide/reusability/composables.html)

### 9.2 Component API and UX responsibilities

Names are proposals; retain equivalent existing components.

| Component/group | Inputs | Outputs / responsibilities |
| --- | --- | --- |
| `DashboardPage`, `AppShell` | Authorized scope, route, view model | Orchestrate state, route lifecycle, semantic layout. |
| `DesktopSidebar`, `MobileTopBar`, `MobileBottomNav` | Current route, accessible navigation entries | Navigate existing routes; show current destination without duplicating global functions. |
| `WorkspaceToolbar` | Title, dirty/save state, edit permission | Add chart, save draft, enter/leave Edit mode. |
| `AddChartDialog` | Authorized devices/sensors, validation status | Confirm validated input or cancel; manage focus and field errors. |
| `ChartList`, `ChartListItem` | Widgets, selected ID, edit mode, capabilities | Select, remove, reorder; visible and programmatic selected state. |
| `SensorChartCard` | Active sensor view model | Compose context, reading, controls, plot, thresholds, statistics. |
| `DeviceSensorSelector` | Current IDs, permitted options, loading state | Device/sensor change intents; independent labels and dependency reset. |
| `ReadingHero` | Value, unit, precision, timestamp, freshness, delta | Format accessible HTML values; do not calculate domain status itself. |
| `TimeRangeControl` | Current range, supported ranges | Emit a valid range; keyboard and touch selection. |
| `SensorChart` | Draw series, threshold rules, resolved period, theme | Canvas lifecycle and value interaction; no direct HTTP calls. |
| `ThresholdLegend` | Threshold intervals and units | Explain band meanings with text; include configured lower limits. |
| `SensorStats` | Period, min/max/mean, valid count, aggregation | Display consistent statistics and unavailable states. |
| `SensorInspector`, `SignalQuality` | Metadata, bounds, frequency, counts, capabilities | Show scientific context and completeness definition. |
| `SparklineCard` | Secondary sensor summary and optional small series | Lightweight overview; selecting it activates its widget. |
| `CriticalAlertBanner` | Highest-priority authorized active critical alert | Device-specific condition and working action. |
| `ActiveAlerts`, `AlertItem` | Authorized alert records, count, scope | Consistent count/list, severity text/icon, view action. |
| `RecentEvents` | Events, laboratory zone, loading/error state | Date/time, device, event, value, severity, full-history action. |
| `LoadingSkeleton`, `EmptyState`, `InlineError` | State-specific label and available action | Reusable accessible operational states. |
| `ConnectionStatus`, `SaveStatus` | Independent typed status | Accurate connection/freshness and persistence messaging. |

### 9.3 State ownership

| State category | Examples | Lifetime / storage |
| --- | --- | --- |
| Server data | Authorized devices, sensor metadata, series, alerts | Existing scoped cache/store; freshness and invalidation policies. |
| UI state | Open panel, edit mode, tooltip, focus target | Component/feature state; usually not persisted. |
| Workspace preferences | Widgets, order, chosen ranges, selected widget | Draft plus confirmed baseline; persist by user and laboratory. |
| Ephemeral resources | Chart instances, timers, abort controllers, sockets | Owner-managed resources; never serialize into workspace JSON. |

Do not introduce another state-management framework or a monolithic dashboard-global store. Reuse Pinia only where shared cross-component ownership is required (for example sensor-reading and alert projections); keep transient UI state in components/composables. Scope all caches by authorization context. A viewport change is a composition change, not a new workspace or second independent request pipeline.

## 10. Data contracts and backend integration

### 10.1 Minimum proposed domain contract

These names define required meaning, not verified endpoint field names. Map actual transport payloads in one adapter.

| Entity | Minimum fields |
| --- | --- |
| Workspace | `id`, `schemaVersion`, `revision`, `selectedWidgetId`, `widgets[]`, `updatedAt`; ownership/scope either in envelope or authorization context. |
| Widget | Stable `id`, `deviceId`, `sensorId`, optional `title`, `order`, `timeRange`. |
| Sensor | `id`, `deviceId`, `label`, `unit`, `precision`, `expectedPeriodMs`, `thresholds`, `capabilities`. |
| Reading | `sensorId`, UTC `timestamp`, nullable `value`, `qualityFlag`, optional `sequence`. |
| SeriesResult | `sensorId`, `from`, `to`, `points[]`, `aggregation`, `originalSampleCount`, statistics, coverage. |
| Alert | `id`, `deviceId`, optional `sensorId`, `severity`, `status`, `message`, `value`, `raisedAt`, `resolvedAt`, `ruleRevision`. |

Validate boundary data with existing tools: finite values, parseable timestamps, supported ranges, stable IDs, device/sensor relationship, valid rule bounds, and known schema versions. Missing metadata is distinct from an empty string or zero. Do not strip unknown future fields silently during save unless the backend contract explicitly allows it.

### 10.2 Illustrative type shape

```ts
type TimeRange = '1m' | '5m' | '1h' | '6h' | '24h';
type Revision = string; // Adapter representation; preserve server semantics.

interface DashboardWidget {
  id: string;
  deviceId: string;
  sensorId: string;
  title?: string;
  order: number;
  timeRange: TimeRange;
}

interface DashboardWorkspace {
  id: string;
  schemaVersion: number;
  revision: Revision;
  selectedWidgetId: string | null;
  widgets: DashboardWidget[];
  updatedAt: string; // UTC ISO timestamp from server.
}

interface RequestContext {
  laboratoryId: string;
  signal?: AbortSignal;
}

interface DashboardRepository {
  loadWorkspace(context: RequestContext): Promise<DashboardWorkspace>;
  saveWorkspace(
    input: {
      workspaceId: string;
      schemaVersion: number;
      expectedRevision: Revision;
      preferences: {
        selectedWidgetId: string | null;
        widgets: DashboardWidget[];
      };
    },
    context: RequestContext
  ): Promise<DashboardWorkspace>;
}
```

Extend these with actual errors and metadata after phase 0. A first-ever workspace creation may need a distinct create operation or an explicitly defined absent-revision contract; do not fabricate a revision just to satisfy this interface. Client-provided laboratory IDs do not grant access; the server checks the authenticated principal and scope.

### 10.3 Required operations

For guests, load the server-approved graph bootstrap, hydrate one bounded graph series for the active public sensor, and use the public sensor-reading transport. Guests do not query alerts, events, device status, preferences, or generic inventory/history APIs. For authenticated users, reuse the graph pipeline and additionally load/save preferences and access authorized alerts, events, restricted sources, and operations. Reuse the current API client, error mapping, and existing live transport. These operation names do not imply REST URLs.

Document a real mapping table during implementation: feature operation → actual method/path or transport message → adapter → permission → response schema → failure behavior. If an operation is missing, record a backend task and implement it only within authorized project scope. Do not wire production UI to an invented URL that merely resembles the proposed model.

### 10.4 Authorization and privacy

Public graph monitoring requires no account, but the server still defines the public scope. Enforce the same `PublicGraphVisibility` decision on graph bootstrap, graph-series, and reading broadcasts; restricted or guessed public-series IDs receive `404`. Save preferences per authenticated user and laboratory. Validate public/restricted scope, sensor access, and action permissions on every server operation, including export and preference updates. Hidden buttons are not an authorization boundary. Read-only users can inspect allowed data; edit controls match actual capabilities.

Workspace persistence contains IDs and view configuration, never session tokens, credentials, or raw sensitive telemetry. Optional local drafts must be explicitly supported, keyed by user/laboratory/workspace/schema, and cleared on logout. Do not show one user's cached content after another signs in. Avoid logging raw measurements or identifiers beyond the project's approved telemetry policy.

## 11. Race-safe requests and live data

### 11.1 Request identity and cancellation

Each series request is tied to scope, widget/sensor, resolved period, and a monotonically increasing request generation. Cancel obsolete requests when selection changes, and also reject obsolete completions: transport cancellation alone does not guarantee an already completed callback cannot run. `AbortController` supports cancelling compatible requests. [MDN AbortController](https://developer.mozilla.org/en-US/docs/Web/API/AbortController)

Implementation recipe:

1. Compute an immutable request key including laboratory/authorization context, sensor ID, range, resolved `from/to`, and aggregation mode where relevant.
2. Increment the active generation and abort the previous owner request. Clear old errors. Keep cached data only when it belongs to the new key.
3. Snapshot the generation/key and start the query with its signal.
4. On success, commit only if the owner remains mounted, generation/key still match, and returned sensor/period are valid for the request.
5. On failure, ignore deliberate aborts; surface a current-key failure only. Never replace the new sensor's state with the old request's error.
6. In `finally`, clear loading only for the current generation. Clean up on route exit, scope change, and unmount.

A matching `sensorId` alone is insufficient: the operator may have switched away and back with a different time range while the first response remained pending.

### 11.2 Stream normalization and merge

Use the existing Laravel Echo/Pusher-compatible WebSocket transport. Public telemetry channels are first-class for guests. Do not start a new transport per component and do not add a polling fallback. Filter incoming samples by authorized scope and sensor identity.

Normalize UTC timestamps, reject non-finite numeric values, preserve quality flags, and deduplicate according to the actual timestamp/sequence contract. A late sample may fill historical data without replacing the latest measurement. If multiple legitimate samples share a timestamp, preserve sequence identity instead of dropping them arbitrarily. Resolve duplicate conflicts deterministically using the backend contract.

Retain a bounded buffer for the selected window plus the minimum overlap required for reconnection. Prune by time/count; avoid unbounded arrays. Share one freshness clock at suitable granularity rather than adding a timer to every row. Clean up subscriptions and listeners when their owning scope disappears.

There is no steady-state frontend polling. On initial load and lifecycle recovery, perform one bounded REST hydration/recovery request while the live subscription remains authoritative; on reconnection, refill the missing interval with safe overlap and deduplicate. Distinguish replayed history from a genuinely current live sample.

## 12. Workspace persistence in detail

### 12.1 Baseline and draft algorithm

Maintain an immutable confirmed baseline and an editable draft of persistible preferences. Calculate dirty state from normalized preference content, excluding telemetry, expanded panels, request flags, and server timestamps. Persist selection and ranges because the source requires their restoration; do not accidentally persist tooltip/focus state.

On save:

1. Validate draft IDs, allowed sensor relationships, order, range, widget count limit, schema, and selected ID existence; an empty workspace has `selectedWidgetId: null`.
2. Snapshot the draft, baseline revision, and local edit generation. Serialize only the approved preference schema.
3. Submit once using server-supported conditional revision semantics. Keep live readings active.
4. On success, apply the server's canonical representation and revision as the confirmed baseline.
5. If no newer local edits occurred, replace the draft with the confirmed representation and show Saved.
6. If newer edits occurred during the request, preserve those edits and remain Dirty. If canonicalization changes IDs or ordering, reconcile through stable identity or temporarily disable configuration editing during save; document the choice. Never mark post-submit edits as saved.
7. On failure, preserve draft and baseline. On conflict, retain both local intent and the newer remote state for explicit resolution.

An existing ETag contract can use `If-Match` for conditional updates; a failed HTTP precondition is normally represented by `412`. A custom revision API may use a documented conflict response such as `409`. Use the actual server protocol and preserve opaque ETags exactly. [MDN If-Match](https://developer.mozilla.org/en-US/docs/Web/HTTP/Reference/Headers/If-Match)

### 12.2 Conflict and failure handling

Offer Reload remote version with clear notice that unsaved local edits will be discarded, or Review changes when that capability is implemented. Keep a recoverable local draft while the user decides. Do not retry a conflict with the newest revision automatically, because that would turn a conflict into a silent overwrite.

If a request times out after the server may have committed it, reconcile with a reload or existing idempotency mechanism before blindly retrying. A cancelled client request does not undo a server save. Repeated saves must not create duplicate workspaces.

Model schema evolution explicitly. Parse only supported schemas; migrate with tested pure transforms when compatible, preserve stable IDs, and do not overwrite a future unknown schema. Handle revoked or removed sensors during load with an explanatory invalid-widget state and permitted repair/removal actions; do not silently lose the user's saved arrangement.

### 12.3 Navigation and local drafts

Use existing router guards for Save / Discard / Keep editing when dirty. Browser/tab closing has limited browser-native prompt behavior; do not promise custom three-button text for an unload prompt. A local recovery draft is optional and must be isolated and cleared as described in section 10.4. No confirmation is needed when there are no unsaved changes.

If no backend preference operation exists, build the authorized backend support or report the dependency. A local draft may preserve current-session work but does not satisfy confirmed cross-device persistence. Never display Saved to server based on a local write.

## 13. Alerts, scientific inspector, and secondary modules

### 13.1 Alert scope and priority

Derive active count and active list from the same authorized scope and status definition. These are authenticated dashboard capabilities; guests do not receive alert records, counts, banners, or an alert subscription. In the same visual location, guest mode may show a neutral access-required state without an alert identity or count. Use backend priority if available. Otherwise document a proposed stable sort by severity, then most recent raised time, then ID. Show one leading critical alert before the chart only when the viewer is authorized.

The banner must name its device/sensor, condition, value/unit where available, and timestamp. A View action opens the existing alert detail or authorized relevant sensor. Do not add acknowledgement, dismissal, or resolution actions unless their domain permissions and endpoints exist. Hiding a banner is not the same as resolving an alert.

Retain each event's rule revision and recorded severity. Handle active/resolved transitions by stable alert ID, not message matching. A selected-sensor badge filters only that sensor; a global alert list can include other authorized sensors and must label its scope.

### 13.2 Inspector and secondary signals

Show selected-sensor unit, configured range, sampling frequency, completeness, valid/expected sample counts, and capability-dependent details. Missing metadata displays No reading or Not available as appropriate. Do not synthesize values from the illustration.

Desktop supports three secondary sparkline cards. Their summaries and selected states must identify the corresponding widgets. On mobile, mount one main chart and use lightweight summary rows; avoid rendering hidden secondary canvases just to match desktop markup.

Recent events are an authenticated capability. Authorized records include laboratory-local date/time, device, event description, value/unit when applicable, and severity. Guest mode uses the same layout slot only for an access-required/omitted state and makes no event request. Use accessible rows on desktop and readable stacked records on small screens. Keep important event text discoverable without horizontal page overflow.

P1 export should carry sensor/device identity, unit, selected range, time zone/UTC semantics, aggregation, and quality fields where allowed. P1 duplication creates a new stable widget ID. P1 histogram uses the same valid source period and documents binning; neither feature may block core P0 completion.

## 14. Preserve brand, UI, and UX in the repository

“Persist the brand” means that future contributors and agents can reproduce the same interface rules from versioned sources. It is separate from saving an operator's workspace preferences.

### 14.1 Durable design artifacts to create or extend during implementation

| Proposed artifact | Required content | Maintenance rule |
| --- | --- | --- |
| `docs/design/sinoa-brand.md` | Brand purpose, identity assets, voice, terminology, examples, anti-patterns. | Update when language or identity decisions change. |
| `docs/design/sinoa-ui-system.md` | Semantic palette, typography, geometry, responsive hierarchy, states, accessibility. | Reference runtime token names; avoid a competing undocumented palette. |
| `docs/design/sinoa-components.md` | Component APIs, variants, states, keyboard behavior, examples. | Update alongside component behavior. |
| `docs/dashboard/data-contracts.md` | Actual endpoint mapping, types, time/quality/threshold semantics, save revision. | Record actual repository evidence and backend ownership. |
| `docs/dashboard/implementation-progress.md` | Phase/RF status, checks, screenshots, remaining actions. | Update at each completed increment. |
| Existing ADR location or `docs/decisions/` | Nontrivial decisions and approved exceptions. | Explain rationale, affected requirements, and migration effects. |
| Existing locale location | Centralized strings and formatter conventions. | Avoid near-duplicate inline labels. |
| Existing UI examples or component gallery | Normal, warning, critical, stale, empty, denied, dirty, conflict, narrow view. | Reuse installed tooling; do not require a new platform solely for examples. |
| Existing visual regression location | Deterministic desktop/mobile reference captures. | Update intentionally with a described design change. |

These are future implementation outputs; this delivery creates the plan only. Merge equivalent documents rather than multiplying sources of truth.

### 14.2 Source-of-truth rules

- Runtime tokens own exact visual values; design documentation explains their meaning and usage. If machine-readable design tokens already exist, generate CSS/chart/Figma mappings from that source rather than introducing a second canonical file.
- Shared UI primitives own behavior and variants. Dashboard consumers compose them instead of cloning markup and redefining styles.
- Localization resources own operator-facing strings. Domain formatters own dates, units, precision, and unavailable values.
- Domain contracts own thresholds, freshness, quality, and status semantics. Styling never changes those meanings.
- If Figma is later updated, map its variables/styles to the same semantic tokens and record the design reference/version. Do not claim synchronization without actually performing and checking it.
- Scope new brand application deliberately. Preserve existing global navigation behavior; extend the approved SINOA theme to adjacent pages incrementally with evidence.

### 14.3 Agent instruction integration

When implementing, add a short link to the approved design documents in the nearest applicable existing agent/developer instructions, if within scope. Preserve all existing content and instruction precedence. Do not replace the root `AGENTS.md` with this plan or create conflicting instructions at several levels.

Suggested additive text:

```markdown
For SINOA dashboard changes, read the approved brand, UI system, component,
and dashboard contract documents before editing. Reuse semantic tokens and
shared components. Preserve the configured UI locale, data identity, freshness,
threshold semantics, and responsive hierarchy. Record intentional deviations
with rationale and update the relevant design example and acceptance evidence.
```

### 14.4 Prevent design drift

During review, check for arbitrary hex colors, duplicate font declarations, local spacing scales, inconsistent alert labels, dead controls, and duplicated desktop/mobile logic. Integrate lightweight lint rules only when the existing tooling supports them and they reduce a demonstrated maintenance risk. Allow documented exceptions, such as approved chart colors or accessibility adjustments; never optimize for a rule at the expense of readability.

Add a reusable component state gallery or fixtures in existing tooling. Use deterministic sensor series, timestamps, and locale for screenshot comparisons. A baseline update must explain what changed; do not accept every new screenshot simply because code changed. Review keyboard and data behavior independently because screenshot similarity cannot establish either.

Every future UI change should answer: Which user task improves? Which existing component/token covers it? Does it preserve context and status semantics? What happens on mobile, missing data, keyboard input, and save failure? Update only the relevant evidence rather than running unrelated exhaustive checks.

## 15. Performance and accessibility

### 15.1 Performance budgets and lifecycle

Proposed laboratory budgets from the source: cached selection interaction **under 200 ms** and an operational dashboard **within 3 seconds** on an agreed device/network profile. They are targets, not measured results. Before enforcing them, define measurement start/end, dataset size, cache conditions, reference hardware, network, and widget limit.

Keep one Chart.js instance per mounted chart, bounded sample buffers, no overlapping polling, and no duplicate subscriptions after navigation or resize. Verify a 24-hour range and the agreed maximum widget count. Decimate draw data while retaining accurate full-period statistics; prefer existing backend range aggregation for very large responses. Monitor request counts and memory after repeated selection changes rather than adding speculative optimizations.

Prioritize the selected chart and useful first viewport. Lazy-mount optional detail visualizations when opened. Preserve peak visibility and unit/time semantics when optimizing. Avoid deep reactive traversal of large arrays if the existing Vue integration offers a simpler controlled update mechanism compatible with the installed version.

### 15.2 Accessibility acceptance

Target WCAG 2.2 AA: normal text contrast at least **4.5:1**, large text **3:1**, and relevant non-text controls/states **3:1** where applicable. The source's internal **44 px** mobile touch target exceeds the AA **24 px** target-size criterion, subject to the standard's exceptions. Validate actual composed states rather than claiming the palette alone guarantees compliance. [W3C WCAG quick reference](https://www.w3.org/WAI/WCAG22/quickref/)

Use semantic headings, a main landmark, visible labels, meaningful link text, current navigation indication, and a skip link if supported by the shell. Group range choices accessibly using native controls or the appropriate existing component. Selected state must be available programmatically. Do not apply ARIA tab semantics to arbitrary buttons unless the full interaction pattern is implemented.

The chart has HTML measurements/statistics and a recent-readings table. Announce significant status transitions and save outcomes sparingly; do not send every two-second sample to an `aria-live` region. Do not steal focus when a new sample or alert arrives. Give the operator control over inspection tooltips.

Dialogs need an accessible name, appropriate initial focus, contained keyboard focus while modal, Escape behavior where appropriate, and focus restoration to the opener. Prevent background interaction while modal. Reuse the established accessible modal implementation. [W3C modal dialog pattern](https://www.w3.org/WAI/ARIA/apg/patterns/dialog-modal/)

Verify keyboard-only operation, 200% zoom, narrow reflow, visible focus, reduced motion, mobile screen reader navigation, and fixed bars not obscuring focused controls. Dragging has an equivalent Move up/Move down action. Mobile input text and zoom behavior must remain usable.

## 16. Development phases and concrete execution gates

Execute in order while maintaining a usable route. Estimate calendar time after phase 0 reveals data availability, existing tests, and branch debt. Prefer small coherent changes: tokens/shell → real chart → persistent workspace → alerts/operation → mobile polish and verification.

### Phase 0 — Repository and contract diagnosis

Tasks: confirm branch/commit; verify the existing public `/dashboard`; map route, shell, chart implementation, versions, public/authenticated scope, Echo/Pusher transport, preferences, UI assets, tests, and current failures. Resolve time zone, quality, ownership, maximum widgets, and threshold semantics. Map RF01–RF15 to code and API dependencies.

Deliverables: evidence-based code-to-design map, actual contract inventory, decision log, baseline screenshots, implementation sequence adapted to repository structure.

Exit gate: verified entry points and runnable commands; missing capabilities are explicit. No invented API dependency is treated as implemented. A backend gap may block its dependent slice while tokens and shell proceed.

### Phase 1 — Brand foundations and responsive shell

Tasks: integrate tokens, approved fonts/logo/icons, shared control styles, global navigation composition, content grid, card primitives, responsive tiers, safe-area spacing, and basic empty/skeleton states. Add durable brand/UI documentation and link it from appropriate developer instructions.

Deliverables: usable responsive shell and component state examples with labelled synthetic fixtures.

Exit gate: no global horizontal overflow at 320, 390, 768, 1024, and 1440 px; typography and navigation remain usable; visual tokens have one authority.

### Phase 2 — One sensor vertical slice

Tasks: implement the first vertical slice as **public realtime graph monitoring**: connect a guest-visible public graph source to scoped graph metadata, a bounded UTC graph series, resolved range, chart, Echo/WebSocket reading updates, threshold bands, statistics, completeness, and timestamp formatting. Add request cancellation/generation checks and state-specific error handling. Do not use broad public inventory, latest-reading, alert, or event endpoints as a substitute for the graph contract.

Deliverables: one functioning real-sensor workflow with consistent chart/reading/inspector identity and reusable domain functions.

Exit gate: rapid selection cannot show stale responses; nulls and gaps render honestly; stats match valid originals; empty/error/permission states work. Fixture data is confined to tests/examples.

### Phase 3 — Workspace editing and persistence

Tasks: add/select/remove/undo/reorder widgets for both guests and authenticated users; preserve selected range and ID; keep guest changes as an ephemeral draft; for authenticated users implement confirmed save/reload, permissions, conflict resolution, navigation guard, and supported recovery.

Deliverables: actual preference adapter and any authorized necessary backend work, schema/revision documentation, save-state UI.

Exit gate: saved order, configuration, and selection restore after reload; save failure preserves draft; conflict cannot silently overwrite; edits during save remain dirty when appropriate.

### Phase 4 — Alerts and scientific operation

Tasks: authenticated critical banner/active counts/events plus authorized extensions; guest access-required/omitted states for those slots; inspector, three desktop secondary signals, freshness, reconnect/refill, partial failures, and revoked-access handling.

Deliverables: operational multi-sensor dashboard with independent workspace and active-sensor status.

Exit gate: an alert from another device does not corrupt selected-sensor state; stale/disconnected are distinct; replay/reconnect does not duplicate samples; all operational actions have real destinations.

### Phase 5 — Mobile and accessibility completion

Tasks: one-column ordering, chart-first reference viewport, compact summary rows, collapsible details/events, mobile alert access, bottom navigation, keyboard reordering, dialogs, focus management, textual chart data, and motion preferences.

Deliverables: desktop/tablet/mobile screenshots and manual keyboard/touch/screen reader observations.

Exit gate: RF01–RF13 are reachable at 390 px and functional at 320 px; no focus trap or bar obstruction; DOM order is logical; text scaling and long labels remain usable.

### Phase 6 — Risk-based verification

Tasks: run the matrix in section 17, using installed test tools and deterministic fixtures. Add tests for genuine risks: race conditions, threshold boundaries, draft/revision behavior, domain validity, reconnect, and authorization. Run required project build/lint/type checks and distinguish baseline failures.

Deliverables: requirement-to-test evidence, visual captures, performance measurements with profile, known-issue list.

Exit gate: P0 criteria pass with evidence; no unresolved blocking regression. Do not create superficial tests that merely repeat implementation constants. Broaden tests only for a concrete risk or required project gate.

### Phase 7 — Reviewable delivery and rollout preparation

Tasks: finalize contracts, brand/component docs, operator guide for selecting/organizing/saving, and rollout/rollback instructions. If compatible with the existing architecture, use a configuration flag to keep the previous dashboard available during validation.

Deliverables: coherent reviewed change, screenshot evidence, verified commands, confirmed P0 status, separate P1 backlog, and rollback procedure.

Exit gate: critical issues closed, review requirements met, and rollback is executable. Deploy only under existing explicit authorization; otherwise deliver the complete reviewable result. Observe runtime errors, request failures, and save failures through existing approved monitoring after authorized rollout.

Rollback should preserve saved preferences or handle schema compatibility explicitly. Reverting the UI must not delete preferences or measurement history. Reuse existing migrations and release practices rather than inventing a parallel deployment process.

## 17. Verification matrix and definition of done

Tests below are required implementation evidence, not tests already executed against the repository for this Markdown delivery.

| Area | Scenario | Required result |
| --- | --- | --- |
| Public guest realtime | Open `/dashboard` with no token/session, select a public sensor, and keep the page open while readings arrive. | Chart updates through the shared Echo/WebSocket projection; no login and no recurring REST polling are required. |
| Guest/auth transition | Configure a guest draft, authenticate, and return to the dashboard. | Public telemetry continues through the same projection; draft/selection is preserved where practical and server Save becomes available without duplicate subscriptions. |
| Public scope security | Request or subscribe to a restricted sensor as a guest. | Restricted resource is not discoverable/exposed; public payloads contain no private credentials/user fields. |
| Sensor selection | Rapidly switch across three sensors; old success and failure arrive last. | Only the current request updates reading/chart/error/loading state. |
| Device dependency | Change device while previous sensor request is pending. | Incompatible sensor clears; no mixed device label and old sensor value. |
| Time range | Switch 5m → 24h → 5m during concurrent requests. | Selected period, series, statistics, and inspector agree. |
| Data ordering | Nulls, invalid flags, duplicates, late samples, same-time sequence values. | Visible gaps, correct deduplication, stable latest valid observation. |
| Thresholds | Values 19.9, 20, 27.9, 28, 30, 30.1 against example rules. | Below-range rule; normal at 20/27.9; warning at 28/30; critical at 30.1. |
| Precision | Unrounded value crosses a boundary but formatted value appears on it. | Classification uses raw value; display/context avoids misleading inference. |
| Statistics | Invalid readings and draw decimation in selected period. | Min/max/mean use valid originals; traceable sample counts and aggregation. |
| Completeness | 148 valid expected slots of 151, unknown frequency, irregular cadence. | About 98.0% for defined example; unavailable/documented policy otherwise. |
| Workspace edits | Add, select, remove active/last, Undo, reorder by keyboard/touch. | Stable IDs; no sensor deletion; expected next selection; usable empty state. |
| Save success | Edit, save, reload in same authorized scope. | Restored order, selection, ranges, and returned revision. |
| Save race | Edit again while previous save is in flight. | New edits remain dirty; no false Saved state. |
| Save failure | Network error, timeout after possible commit, schema rejection. | Draft preserved; safe retry/reconciliation; useful message. |
| Save conflict | Another session changes the baseline revision. | No silent overwrite; remote reload/review decision is explicit. |
| Connection | Disconnect transport, stale samples, reconnect and refill. | Truthful freshness; no inferred hardware failure; no duplicate interval. |
| Alert identity | Critical Node 7 alert while Reactor 1 is selected and normal. | Global banner identifies Node 7; Reactor 1 reading/status remains its own. |
| Alert consistency | New active alert and resolved transition. | Count/list/banners share scope and status rules. |
| Permissions | Query or save an out-of-scope sensor; revoke access mid-session. | Server denies access; restricted data clears; no stale cache disclosure. |
| Responsive | 320, 390, 768, 1024, 1280, 1440 CSS px. | No page overflow or clipped controls; usable main plot and hierarchy. |
| Mobile reference | 390 × 844 portrait, standard fixture/default text. | Critical context, controls, reading, useful chart visible early; details below. |
| Text growth | 200% zoom, long device names, localized labels, keyboard focus. | Reflow without lost content or obscured focus. |
| Accessibility | Keyboard, screen reader, reduced motion, drag alternative, contrast. | Logical order, visible focus, meaningful names, textual data equivalent. |
| Browsers | Chrome/Edge, Firefox, Safari, Android and iOS browsers. | All P0 flows work without blocking failures; document actual versions tested. |
| Performance | 24h data, agreed widget cap, repeated changes and route exits. | Bounded memory, no duplicate polling/subscriptions, measured budgets. |
| Brand consistency | Normal, alert, stale, empty, save-error views at desktop/mobile widths. | Approved palette, type, spacing, copy, and component behavior remain consistent. |

### 17.1 Meaningful test structure

Use the repository's existing framework. Unit tests should exercise pure domain boundaries and state transitions. Component tests should verify accessible intent and rendering states. Integration/E2E tests should cover the real request/revision flows using safe deterministic test fixtures or an authorized environment. Mock fixtures must reflect actual contracts and be labelled synthetic.

Fix time, locale, viewport, and data for visual comparisons. Use manual checks for keyboard/touch/screen readers and real browser behavior where automation is insufficient. Record screenshots by requirement, viewport, and state. Do not commit sensitive production captures. Do not add a new testing framework unless the current tools cannot verify a required risk.

### 17.2 Definition of done

- One responsive web dashboard implements the selected hierarchy for desktop and mobile using shared business logic for both guests and authenticated users.
- RF01–RF13 are implemented and verified, including guest realtime monitoring without registration and authenticated persistence as a capability extension. RF14–RF15 are clearly tracked separately without misleading controls.
- Actual API contracts, authorization, errors, timestamp/quality rules, and persistence semantics are documented.
- Preferences save and restore reliably; revision conflicts and schema compatibility have explicit behavior.
- Visual evidence covers target widths and significant states. Keyboard and mobile flows are manually checked; critical transitions have meaningful automated coverage.
- Fonts, tokens, logo, copy, component contracts, and agent guidance are versioned so later work preserves SINOA's design.
- Required project checks pass, or concrete pre-existing failures and accepted exceptions are documented accurately.
- An operator guide and rollout/rollback procedure exist. No claim of deployed software or updated Figma is made without the corresponding completed action.

## 18. Open decisions and dependency handling

Close these in phase 0 using repository evidence and current product ownership. Proposed contracts remain proposals until confirmed.

| Decision | What to establish | If missing |
| --- | --- | --- |
| Entry point | **Confirmed:** `/dashboard` is the current public route. Record the exact execution SHA before coding. | Preserve public access while rebuilding; do not redirect guests to login for basic telemetry. |
| Authentication | **Confirmed split:** public telemetry requires no login; authentication adds persistence/restricted capabilities. | Preserve one dashboard and capability-based enhancement. |
| Workspace ownership | **Confirmed baseline:** guest draft is ephemeral; authenticated persistence is personal per user with the current preference owner. Laboratory/shared semantics still require explicit policy if added. | Do not create a second preference owner. |
| Preferences | **Partially confirmed:** authenticated GET/PUT preference operations exist, but the richer revision/schema/conflict contract in this plan is not fully implemented. | Extend the existing preference controller/model if RF08 requires revision semantics. |
| Widget cap | Maximum saved and simultaneously rendered widgets. | Agree a limit based on operation and measurement; do not hardcode an arbitrary restriction. |
| Transport | **Confirmed current baseline, target narrowed:** Laravel Echo/Pusher-compatible public channels exist, but the approved guest target permits only a server-approved sensor-reading graph channel. | Enforce one graph visibility policy; no frontend polling/fallback; use bounded graph hydration and lifecycle recovery. |
| Sampling | Expected cadence, timestamp source, inclusion policy, slot tolerance. | Show unavailable completeness/freshness where undefined. |
| Quality | Validity flags and exact completeness/aggregation definition. | Do not invent a percentage. |
| Thresholds | Bound inclusivity, lower rules, revision and alert authority. | Display only known configured rules; record gaps. |
| Time zone | Configured laboratory zone and daylight-saving behavior. | Make uncertainty visible; do not silently assume browser zone. |
| Existing navigation | Alerts, history, devices and account destinations. | Map real routes; implement necessary in-scope destinations or report the gap. |
| Design references | Approved logo/fonts and accessible mockup/Figma reference. | Use approved existing assets and this exact textual specification; record missing reference. |

Do useful independent work while a dependency is unresolved. Backend gaps should not prevent creating tokens or the shell, but their dependent RF requirements cannot be marked complete prematurely.

## 19. Reusable coding-agent kickoff prompt

Copy this section into an implementation task together with access to the repository and this complete Markdown file.

> Implement SINOA Lab Blue Workspace using this specification. Start by reading applicable repository instructions and verifying the current `refraccion` SHA. Preserve the already-confirmed public `/dashboard`, public-safe telemetry APIs, Echo/Pusher realtime ownership, Pinia projections, and authenticated preference owner. The expected stack is Vue 3, Bootstrap 5, and Chart.js from the source brief; verify it and reuse the existing architecture.
>
> Preserve the main chart from alternative 1 and chart selection/scientific details from alternative 3. Build one responsive web dashboard with shared business logic, the specified tokens and typography, a single desktop auxiliary column, and a chart-first mobile hierarchy. Preserve the configured product locale and SINOA's calm, precise operational voice.
>
> Work through phases 0–7. Begin with tokens and a responsive shell, then one real sensor vertical slice, then workspace editing and confirmed persistence, then alerts, scientific detail, reconnect, and mobile/accessibility completion. Separate server data, transient UI state, preference draft/baseline, and lifecycle resources. Do not invent endpoints, percentages, or measurements.
>
> Public realtime graph monitoring is mandatory: guests must be able to select server-approved graph sensors and watch their charts update live without registration. Public APIs/channels may expose only graph bootstrap metadata, bounded graph series, and approved sensor-reading events; they must never expose alerts, events, device status, preferences, generic inventory/history, or restricted sensors. Do not introduce frontend polling or a polling fallback. Protect against obsolete responses, invalid/out-of-order samples, misleading gap interpolation, unscoped caches, and lost updates. Render authorized alert/event slots as access-required or omitted for guests. Preserve unsaved edits on failure and revision conflict. Never delete sensors when removing chart widgets.
>
> Persist brand and UX guidance in existing repository design documentation, shared tokens/components, localization resources, meaningful state examples, and appropriate additive agent instructions. Keep a concise progress/decision log so another session can continue from verified state.
>
> Verify the requirement matrix with the project's existing tools and safe deterministic fixtures. Provide desktop/mobile evidence, actual checks/results, RF01–RF13 completion, separate P1 backlog, and concrete remaining dependencies. Continue routine authorized work autonomously. Deliver a reviewable implementation and rollback plan; follow existing authorization for deployment and external actions.

## 20. Source preservation map and references

### 20.1 Coverage of the original Word document

| Original content | Preserved and expanded here |
| --- | --- |
| Cover, concept, objective, scope, version, recipient, technical caveat | Sections 1–2; conceptual image relationship described in section 1. |
| 01 Functional requirements | Section 3, all RF01–RF15 and P0/P1 boundaries. |
| 02 Responsive structure and hierarchy | Section 5, exact dimensions, widths, ordering, mobile safe area. |
| 03 Colors and semantic tokens | Section 6, complete palette and state fills plus CSS baseline. |
| 04 Typography and visual elements | Sections 4 and 6, both fonts, exact sizes, geometry, icons, motion, actions. |
| 05 Chart and interpretation rules | Section 7, exact threshold boundaries, stats, quality, 151/148 example. |
| 06 Interaction flows and states | Sections 8, 11, and 12, all edit/save/loading/freshness states. |
| 07 Frontend components and structure | Section 9, all named components/composables and proposed paths. |
| 08 Contracts, security, performance | Sections 10–12 and 15, entities, operations, scope, time and budgets. |
| 09 Development phases | Section 16, all phases 0–7, integration sequence and rollback. |
| 10 Verification matrix and accessibility | Sections 15 and 17, all original scenarios plus implementation risks. |
| 11 Done, open decisions, agent instruction, sources | Sections 17–20, preserved and expanded. |
| Additional requested implementation and lasting brand/UI/UX guidance | Sections 2, 4, 6, 9–14, 16, and 19. |

### 20.2 Product source

The source Word document attributes product requirements to the user-provided brief **“PROMPT MAESTRO — DISEÑO UI/UX DEL DASHBOARD SINOA”** and the subsequent selection of alternatives 1 and 3. This conversion directly reads the supplied Word document; it does not assert a fresh repository audit, completed Figma changes, or executed application tests.

### 20.3 Official technical references

Consulted for the original specification and/or this expanded edition on 8 September 2026. They support technical mechanisms; SINOA's composition, token values, workflow choices, and proposed budgets are product decisions in this plan. Check version compatibility against the actual installed packages during implementation.

- [Vue composables](https://vuejs.org/guide/reusability/composables.html): stateful logic organization and lifecycle ownership.
- [Bootstrap breakpoints](https://getbootstrap.com/docs/5.3/layout/breakpoints/): responsive width tiers.
- [Chart.js performance](https://www.chartjs.org/docs/latest/general/performance.html): drawing performance and decimation considerations.
- [W3C WCAG 2.2 quick reference](https://www.w3.org/WAI/WCAG22/quickref/): accessibility criteria and exceptions.
- [Chart.js plugins](https://www.chartjs.org/docs/latest/developers/plugins.html): custom drawing extension mechanism.
- [MDN AbortController](https://developer.mozilla.org/en-US/docs/Web/API/AbortController): request cancellation mechanism.
- [W3C modal dialog pattern](https://www.w3.org/WAI/ARIA/apg/patterns/dialog-modal/): modal keyboard/focus behavior.
- [MDN If-Match](https://developer.mozilla.org/en-US/docs/Web/HTTP/Reference/Headers/If-Match): conditional update semantics.
