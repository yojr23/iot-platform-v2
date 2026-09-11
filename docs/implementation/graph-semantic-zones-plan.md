# Graph Semantic Zones — AlertRule as threshold source of truth

**Branch:** `refraccion` · **Status:** planning · **Author decision date:** 2026-09-10

## The decision (supersedes "thresholds have no owner")

The chart must **not** color its plot area by notification side effects
("email sent / not sent"). The semantic origin is the **alert rule and its
severity**, evaluated server-side.

Alert thresholds already have an internal domain owner in `AlertRule` and are
evaluated by `AlertService`. What is *not* yet defined is their **graph-safe
normalized projection** and their **public exposure policy**. The Lab Blue
chart must not invent thresholds; it must derive visual semantic zones from the
applicable alert-rule configuration through **one server-owned normalization
contract**. Alert severity determines graph semantics; notification delivery
does not. `warning` maps to the amber visual state, `danger` maps to
critical/red and is currently email-eligible, while an email suppression or
delivery failure does **not** downgrade a danger condition.

## Semantic mapping (frozen)

| Zone | Origin | Meaning |
|---|---|---|
| Green | No warning/danger rule violated | known-normal range |
| Amber | `warning` rule | advisory condition |
| Red | `danger` rule | critical condition |
| Info | `info` rule | needs a deliberate visual decision; do **not** auto-merge into green |
| Neutral | **no applicable rule** | "limits not configured" — never assert Normal |

Precedence when multiple rules cover the same value: **danger > warning > info > normal**.

## Verified current state (code, not assumption)

- `AlertRule` (`back/app/Models/AlertRule.php`): `sensor_type_id`, `device_id`,
  `sensor_id`, `min_value`, `max_value`, `severity` — all fillable. No
  per-rule `send_email` column.
- `AlertService::triggeredRulesForReading` (`back/app/Services/Alerts/AlertService.php`):
  resolves applicable rules by `sensor_type_id` + optional `device_id` +
  optional `sensor_id`, requires min or max defined, and violates on
  **`value <= min_value`** or **`value >= max_value`** (lines 66–70). This is
  the authoritative boundary semantics today.
- `PublicGraphController::bootstrap` exposes only `device{id,name}` and
  `sensor{id,name,unit}` — **no** thresholds/severity/rule data.
- `SensorReadingChart.vue:79` explicitly states "never render threshold bands".
- `NotificationService` sends email only when `severity === danger`, with rate
  limit and SMTP-failure handling — a side effect, downstream of severity.

## Boundary-semantics — FROZEN (GRAPH-004)

**Decision (2026-09-10): keep AlertService's inclusive semantics** —
`value <= min_value` and `value >= max_value` trigger. It is the shipped,
tested domain owner; the normalizer and chart align to *this* comparison, not
the Word example. The normalizer derives zones from the **same** comparison the
evaluator uses — no second threshold interpretation in Vue. AlertService
evaluation is **not** changed.

## Info visualization — FROZEN (GRAPH-006)

The chart's plot-area background is the primary signal: **green / amber / red**
painted under the line so the reader instantly sees whether the current value
sits in a normal (green), warning (amber), or danger (red) region. `info` rules
render as a separate informational band (SINOA brand/blue tint) that does **not**
claim normal and does **not** raise alarm — the three core zones stay
green/amber/red.

## Public exposure policy (GRAPH-002 / DOC-002)

A guest may learn only the **graph-safe** projection: ordered severity
intervals (`{ from, to, severity }`) needed to paint the plot area and label
boundaries for a **public** sensor. No rule ids, no scope (device/sensor_type
targeting), no notification policy, no other-sensor data. Same
`PublicGraphVisibility` gate as bootstrap/series. Restricted sensors expose
nothing.

## Architecture: one normalizer, reuse AlertService scoping

```
AlertRule (domain, source of truth)
   └─ AlertService rule-resolution (existing owner: scope + min/max/severity)
        └─ RuleToGraphZones normalizer (NEW, server-owned, single owner)
             ├─ public projection  → PublicGraphController bootstrap/series band field
             └─ authenticated projection → authenticated graph view-model
                  └─ SensorReadingChart draws zones + boundary labels (no re-eval in Vue)
```

Vue must not reimplement AlertService. The normalizer transforms
min/max/severity into **ordered, non-overlapping intervals with precedence
resolved**, and both the public and authenticated paths consume the same
normalizer output.

## Issue backlog

Full table lives in the issue tracker; the execution-critical open items:

**Immediate execution order (the 6 that unblock the rest):**

1. **GRAPH-001** — Derive graph semantic zones from `AlertRule` (normal/warning/danger from real applicable rules).
2. **GRAPH-004 + GRAPH-005 + GRAPH-006** — Freeze exact interval boundary semantics, overlapping-rule precedence, and Info visualization.
3. **GRAPH-002** — Public-safe rule-band projection for public sensors.
4. **GRAPH-008** — Single `RuleToGraphZones` normalizer (Vue never re-evaluates).
5. **GRAPH-003 + GRAPH-009 + GRAPH-010** — Chart.js paints real zones; boundary lines/labels; Y-axis domain includes observed values **and** configured limits.
6. **GRAPH-011** — Selected-sensor semantic state (Normal/Advertencia/Crítico) from that sensor's rule.

**Also open (P0):**

- **GRAPH-007** — No green "normal" when no rules exist; neutral "limits not configured".
- **GRAPH-012** — Global alerts stay independent of selected-sensor state (a critical alert on another sensor must not turn the selected reading red).
- **GRAPH-013** — Rule metadata in Sensor Inspector when available.
- **DOC-001 / DOC-002** — Update frontend plans: AlertRule is threshold source of truth; document the public-safe projection contract.
- **ALERT-004** — Document severity-vs-notification: amber=warning, red=danger; email delivery does not decide color.

**Tests (P0):**

- **TEST-001** — Rule boundaries vs chart zones (values just below/at/above min/max).
- **TEST-002** — Multiple warning/danger rules + overlap precedence, fragmented regions.
- **TEST-003** — Rule edit → next bootstrap/recovery reflects new zones without a code reload.
- **TEST-004** — Danger stays red when email is suppressed or SMTP fails.
- **TEST-005** — No-rules → neutral graph state (never green without evidence).

**Decisions / lower priority:**

- **ALERT-005** (P1, decision) — Per-rule `send_email` policy: only needed if two `danger` rules must differ in email behavior. Not built today.
- **DATA-003/004/005** (P1, blocked) — Signal quality / sampling frequency / per-sensor precision: do not invent until an expected-cadence/validity contract exists.

## Worked example

Two rules on one sensor:

```
Warning:  max_value = 28, severity = warning
Danger:   max_value = 30, severity = danger

          30               +inf
           |   DANGER / RED
-----------+--------------------
      28   |
           | WARNING / AMBER
-----------+--------------------
           | NORMAL / GREEN
          -inf
```

With AlertService's shipped `>= max` semantics: `value >= 30` is danger,
`28 <= value < 30` is warning, `value < 28` is normal. If instead the Word's
`warning: 28..30, danger: > 30` is chosen, the normalizer and AlertService must
both move to that boundary — pick once (GRAPH-004).

## Done when

- One server-owned normalizer converts applicable `AlertRule`s into ordered,
  precedence-resolved severity intervals; both public and authenticated graph
  paths consume it.
- The chart paints green/amber/red/neutral from those intervals and labels the
  boundaries; the Y-axis domain includes configured limits.
- Selected-sensor semantic state is computed from that sensor's rule, never
  from another sensor's alert or from email delivery.
- No-rules renders neutral, not green.
- Tests TEST-001..005 pass, including danger-stays-red-when-email-fails.
- Frontend plans + public-projection contract updated (DOC-001/002).
