# FINAL RECONCILIATION v2
## PRINCIPAL ENGINEER / ARCHITECTURE REVIEW BOARD

Repository:

```text
yojr23/iot-platform-v2
```

Branch:

```text
refraccion
```

Repository baseline audited:

```text
0092a6962b547f767bb60fc8c2ad7d0475cba1a5
```

Application SHA audited:

```text
4b148252415b54f8ba969b563aaaa0f5f3761470
```

Post-audit Windows remediation source base:

```text
d6192b7669818889c059626c2655878470c1dc72
```

---

# CERTIFICATION BASIS

This reconciliation does **not** constitute a new independent full repository/runtime audit.

Its evidence basis is:

```text
AUDIT-A1
+
AUDIT-A2
+
AUDIT-B
+
AUDIT-C
```

with:

```text
active source evidence
schema/migration evidence
Graphify evidence
current CI evidence
historical Mac runtime certification
```

Current full-stack runtime state was not independently re-executed during reconciliation.

Therefore:

```text
CERTIFICATION BASIS:
Static source/schema evidence
+
historical runtime evidence
+
current CI evidence

CURRENT FULL RUNTIME RECERTIFICATION:
PENDING
```

Important rule:

```text
active-source contradiction
CAN reopen a gate without a new runtime test

but

runtime-dependent PASS/CLOSED claims
must be re-certified on Mac before final closure
```

---

# 1. INPUT QUALITY CHECK

## 1.1 AUDIT-A1

Domain:

```text
Code / Architecture / Event / IoT / Realtime / Frontend
```

Quality:

```text
STRONG
```

Contains:

- repository baseline;
- Graphify analysis;
- architecture topology;
- evidence-backed findings;
- failure matrix;
- Gate 9 review;
- Gate 10 review;
- remediation plan;
- unresolved evidence;
- Agent-H challenge;
- final verdict.

A1 produced 8 findings.

---

## 1.2 AUDIT-A2

Same domain as A1.

Quality:

```text
STRONG
```

A2 adds useful evidence around:

- device-time vs receipt-time semantics;
- alert lifecycle ordering;
- Gate 9 URL validation;
- PLAN metadata drift.

A2 produced 9 findings.

---

## 1.3 AUDIT-B

Domain:

```text
Data / Database / Schema / Relationships
```

Quality:

```text
STRONG
```

Covers:

- schema inventory;
- relationships;
- FK/unique/index/cascade rules;
- RBAC physical model;
- IoT persistence identity;
- temporal mappings;
- timestamps;
- migration parity;
- query/index review;
- remediation.

B produced 7 findings.

---

## 1.4 AUDIT-C

Domain:

```text
Security / RBAC / Channels
```

Quality:

```text
STRONG
```

Covers:

- authentication;
- functional RBAC;
- hierarchy protections;
- IDOR/BOLA;
- lab scope;
- alert mutations;
- REST ↔ WS security;
- realtime read/write capabilities;
- configuration security;
- negative-test coverage.

C produced 7 findings.

---

## 1.5 A1/A2 BASELINE COMPATIBILITY

Both audited:

```text
HEAD:
0092a696...

Application SHA:
4b148252...
```

Result:

```text
BASELINE COMPATIBLE
```

Graph metadata contains a superficial discrepancy between the regeneration commit and report source SHA, but both Audit-A executions established no application-source drift.

Final:

```text
GRAPH STATUS:
CURRENT
```

relative to the audited application SHA.

---

# 2. MERGE LOG

## 2.1 AUDIT-A1 ↔ AUDIT-A2

```text
A1 findings:
8

A2 findings:
9

Merged finding groups:
7

Unique A1 findings retained:
1

Unique A2 findings retained:
2

Rejected A1 findings:
0

Rejected A2 findings:
0

Unresolved A1/A2 contradictions:
0
```

Direct merges:

```text
A1:F-A-001 + A2:A-01
→ Redis stream retention

A1:F-A-002 + A2:A-03
→ alert queue worker topology

A1:F-A-003 + A2:A-02
→ Python spool terminal exhaustion

A1:F-A-004 + A2:A-06
→ Gate 9 incomplete certification

A1:F-A-005 + A2:A-05
→ alert replay/lifecycle projection

A1:F-A-006 + A2:A-07
→ observability/readiness

A1:F-A-007 + A2:A-08
→ maintainability hotspots
```

Unique A1:

```text
Redis-consumer mechanical duplication
```

Unique A2:

```text
device timestamp / received_at semantics

PLAN metadata drift
```

---

## 2.2 CANONICAL AUDIT-A

Canonical Audit-A contains:

```text
10 findings
```

One important correction is made relative to the previous reconciliation.

### Python spool severity

Previous reconciliation:

```text
MEDIUM
```

Final reconciliation:

```text
HIGH
```

Reason:

The event remains physically durable in SQLite, but after retry exhaustion it leaves the automatic processing path indefinitely.

For an IoT monitoring system:

```text
accepted telemetry
→ backend outage ~15+ minutes
→ telemetry stops progressing automatically
→ manual SQLite recovery required
```

is a material availability/data-continuity failure.

Because there is currently no supported replay workflow, no strong DLQ alerting, and no proven recovery SLA, the operational severity is better represented as:

```text
HIGH
```

It may later be downgraded if the platform gains:

- automated replay;
- DLQ monitoring;
- bounded recovery SLA;
- operator runbook;
- tested recovery evidence.

---

## 2.3 GLOBAL MERGE

```text
Canonical Audit-A:
10 findings

Audit-B:
7 findings

Audit-C:
7 findings
```

Cross-domain merges:

```text
B:F-B-002
+
C:F-C-001
→ missing resource-assignment model
  + missing scoped authorization


B:F-B-005
+
C:F-C-007
→ dormant role.permissions.manage capability
```

Final master findings:

```text
22
```

---

# 3. CONTRADICTION MATRIX

## 3.1 A1 ↔ A2

| Topic | A1 | A2 | Final resolution |
|---|---|---|---|
| Python spool exhaustion | MEDIUM | HIGH | **HIGH** |
| Redis retention | HIGH | HIGH | HIGH |
| Alert worker | HIGH | HIGH | HIGH |
| Gate 9 | REOPEN | REOPEN | REOPEN |
| Alert projection | replay window | lifecycle ordering + replay | MERGED |
| Observability | readiness gaps | broader operational gaps | MERGED |
| Maintainability | MEDIUM | LOW | LOW |
| `received_at` | timezone fixed | semantic conflation remains | BOTH TRUE |
| Gate 10 | REOPEN | REOPEN | REOPEN |

### Timestamp resolution

The following is closed:

```text
received_at timezone conversion bug
```

The following remains open:

```text
device_timestamp
vs
server received_at
```

These are separate issues.

---

## 3.2 CANONICAL A ↔ B ↔ C

| Topic | A | B | C | Resolution |
|---|---|---|---|---|
| Lab/procedure scope | handoff | assignment absent | authorization absent | HIGH end-to-end defect |
| MQTT event identity | strong | strong | — | defended |
| Alternative ingress identity | architecture gap | duplicate-prone | — | HIGH retained |
| Alert resolve | — | assignment impossible | global mutation | HIGH |
| Role permission management | — | mismatch | mismatch | static-RBAC decision |
| WS client writes | no domain mutation | — | `client-*` allowed | MEDIUM |
| Gate 10 closed | contradicted | data gaps | security gaps | REOPEN |

---

# 4. MASTER FINDINGS

# F-001

**Severity:** HIGH  
**Status:** [OBSERVED]

**Title:** Processing-critical Redis streams can trim unconsumed events.

Producer-side retention can destroy application-stream entries independently of downstream consumer progress after the DB outbox has already become `published`.

Impact:

```text
persisted event
→ published to Redis
→ DB says published
→ consumer remains offline
→ Redis trims entry
→ no automatic reconstruction owner
```

Owner:

```text
PLAN-1 / T-1.1
```

---

# F-002

**Severity:** HIGH  
**Status:** [OBSERVED]

**Title:** Required alert-evaluation worker is absent from documented standard worker topology.

Current path:

```text
SensorReading
→ ShouldQueue EvaluateSensorReadingAlerts
→ database queue
```

but the queue worker is not part of the normal documented `workers` profile.

Impact:

```text
telemetry works
realtime readings work
alerts silently stop evaluating
```

Owner:

```text
PLAN-1 / T-1.2
```

---

# F-003

**Severity:** HIGH  
**Status:** [OBSERVED]

**Title:** Temporary backend outage can terminally strand valid telemetry in local SQLite DLQ.

Current retry policy can exhaust after roughly fifteen minutes of persistent backend failure.

The event remains physically stored but no longer progresses automatically.

Current architecture lacks:

- supported spool DLQ replay;
- automatic re-drive;
- strong operational alerting;
- bounded recovery SLA.

Owner:

```text
PLAN-1 / T-1.3
```

---

# F-004

**Severity:** MEDIUM  
**Status:** [OBSERVED]

**Title:** Gate 9 does not certify the complete active product router.

Missing or insufficient certification includes:

```text
/devices/:id
/alerts/:id
/config/general
/config/alerts
/config/email
/config/diagnostics
```

and other role/page identity conditions.

Owner:

```text
PLAN-1 / T-1.4
```

---

# F-005

**Severity:** MEDIUM  
**Status:** [OBSERVED]

**Title:** Alert frontend projection is neither permanently replay-idempotent nor lifecycle-monotonic.

Problems:

```text
dedup identity expires after 500 events

resolved alert
→ delayed trigger
→ alert may reactivate
```

Owner:

```text
PLAN-1 / T-1.5
```

---

# F-006

**Severity:** MEDIUM  
**Status:** [OBSERVED]

**Title:** Current liveness/readiness signals do not expose several correctness-critical failures.

Missing signals include:

- queue backlog;
- oldest queue age;
- alert worker presence;
- local spool backlog;
- local DLQ;
- Redis retention headroom;
- stale raw events;
- Debezium freshness;
- Reverb health.

Owner:

```text
PLAN-1 / T-1.6
```

---

# F-007

**Severity:** MEDIUM  
**Status:** [OBSERVED]

**Title:** Device event time and server receipt time are semantically conflated.

Current architecture can effectively make:

```text
device_timestamp
=
received_at
```

which prevents clean differentiation between:

- device clock;
- network delay;
- backend receipt;
- backend processing.

Owners:

```text
PLAN-1 / T-1.7
PLAN-2 / T-2.8
```

---

# F-008

**Severity:** LOW  
**Status:** [OBSERVED]

**Title:** Several source units remain responsibility hotspots.

Main examples:

```text
SensorApiController
SensorMonitorBoard
DomainEventBroadcastConsumer
EventPipelineMetricsService
```

No direct correctness failure follows merely from size.

Owner:

```text
PLAN-1 / T-1.8
```

---

# F-009

**Severity:** LOW  
**Status:** [OBSERVED]

**Title:** Redis-consumer reliability mechanics remain duplicated.

Repeated mechanics include:

- group setup;
- reclaim;
- ACK;
- delivery count;
- DLQ transition.

Owner:

```text
PLAN-1 / T-1.8
```

---

# F-010

**Severity:** LOW  
**Status:** [CONTRADICTED]

**Title:** PLAN/certification metadata no longer fully describes active implementation and evidence.

Examples include:

- stale HEAD/run references;
- Sanctum ability claims;
- closed-gate claims broader than tested scenarios.

Owner:

```text
PLAN-1 / T-1.9
```

---

# F-011

**Severity:** HIGH  
**Status:** [OBSERVED]

**Title:** Sensor updates can desynchronize persistent ingestion identity from the Sensor aggregate.

Changing:

```text
sensor.device_id
or
identity-bearing sensor name
```

does not atomically transition the corresponding temporal mapping.

Impact:

```text
Device A emits event
→ old open mapping resolves Sensor S
→ Sensor S was moved to Device B
→ resulting domain context says Device B/Lab B
```

Owner:

```text
PLAN-2 / T-2.1
```

---

# F-012

**Severity:** HIGH  
**Status:** [OBSERVED]

**Title:** Required laboratory resource authorization boundary does not exist end-to-end.

Current persisted user model has:

```text
User → Role
```

but not:

```text
User → Lab assignment
```

and authorization therefore evaluates global permissions rather than ownership/scope.

Affected:

- device REST access;
- sensor REST access;
- reading REST access;
- private sensor channels;
- alert visibility;
- alert mutation;
- global realtime channels.

Owners:

```text
PLAN-2 / T-2.2
PLAN-3 / T-3.1
PLAN-3 / T-3.3
```

### Mandatory dual closure contract

F-012 cannot close until **both** sides are complete.

#### STRUCTURAL CLOSURE — PLAN-2

Must provide:

```text
persisted User → Lab assignment
FK integrity
uniqueness
indexes
deterministic resource → Lab lineage
```

#### SECURITY CLOSURE — PLAN-3

Must provide:

```text
REST scope enforcement
alert scope enforcement
channel scope enforcement
negative two-lab tests
```

Completing only one side does **not** close F-012.

---

# F-013

**Severity:** HIGH  
**Status:** [OBSERVED]

**Title:** Stable logical-event idempotency is not mandatory across every accepted telemetry ingress surface.

Canonical raw-first MQTT path:

```text
STRONG
```

Compatibility endpoint:

```text
POST /api/sensors/{sensor}/readings
```

directly creates readings and bypasses raw receipt/outbox identity.

Repository documentation explicitly describes it as a compatibility path, not the canonical durable ingestion architecture.

Owner:

```text
PLAN-2 / T-2.3
```

---

# F-014

**Severity:** MEDIUM  
**Status:** [OBSERVED]

**Title:** RBAC seeding is additive and may preserve obsolete grants.

Current `upsert()` behavior does not guarantee that a removed permission mapping disappears from an already-seeded database.

Owner:

```text
PLAN-2 / T-2.4
```

---

# F-015

**Severity:** LOW  
**Status:** [PARTIAL — SOURCE REMOVED; DB UPGRADE VERIFICATION PENDING]

**Title:** `role.permissions.manage` advertises functionality that the product does not implement.

There is no runtime Role→Permission editor/API.

The current repository overwhelmingly implements static predefined RBAC.

Final architectural decision:

```text
DYNAMIC ROLE→PERMISSION EDITING:
NO
```

Therefore:

```text
remove/deprecate role.permissions.manage
+
make source-controlled RBAC authoritative
```

Owner:

```text
PLAN-2 / T-2.5
```

---

# F-016

**Severity:** MEDIUM  
**Confidence:** MEDIUM  
**Status:** [INFERRED]

**Title:** Two active high-growth query shapes appear to lack suitable leading indexes.

Candidates:

```text
alerts(resolved, created_at, id)

domain_event_outboxes(
    event_type,
    aggregate_type,
    aggregate_id,
    id
)
```

Must be validated using real MySQL `EXPLAIN ANALYZE`.

Owner:

```text
PLAN-2 / T-2.6
```

---

# F-017

**Severity:** LOW  
**Status:** [OBSERVED]

**Title:** Several lifecycle/state vocabularies remain weakly constrained physically.

Examples:

```text
raw_sensor_events.status
raw_event_outboxes.status
domain_event_outboxes.status
alert_rules.severity
system_settings.type
```

Owner:

```text
PLAN-2 / T-2.7
```

---

# F-018

**Severity:** HIGH  
**Status:** [OBSERVED]

**Title:** Standard operator can resolve alerts globally, including bulk resolve-all.

Current capability:

```text
alert.resolve
```

has no laboratory ownership condition.

`resolveAll()` begins from global active alerts.

Owner:

```text
PLAN-3 / T-3.2
```

Dependency:

```text
F-012
```

---

# F-019

**Severity:** MEDIUM  
**Status:** [SOURCE FIXED — MAC LIVE REVERB VERIFICATION PENDING]

**Title:** Reverb permits browser-originated `client-*` traffic despite a read-only application contract.

No backend/domain mutation path was demonstrated.

This is therefore:

```text
unnecessary writable realtime surface
```

not arbitrary backend write access.

Owner:

```text
PLAN-3 / T-3.4
```

---

# F-020

**Severity:** MEDIUM  
**Status:** [SOURCE FIXED — MAC PASSWORD-FLOW VERIFICATION PENDING]

**Title:** API authentication endpoints permit a weaker password policy than hardened web endpoints.

API:

```text
min:8
```

Web policy includes materially stronger requirements.

Owner:

```text
PLAN-3 / T-3.5
```

---

# F-021

**Severity:** LOW  
**Status:** [CONTRADICTED]

**Title:** Documentation claims Sanctum ability defense-in-depth that active routes do not currently enforce.

RBAC remains active, so no independent privilege bypass is established.

Owner:

```text
PLAN-3 / T-3.6
```

---

# F-022

**Severity:** LOW  
**Status:** [CLOSED — WINDOWS VERIFIED]

**Title:** Role-management frontend offers some transitions the backend hierarchy correctly rejects.

Backend remains secure.

The defect is frontend/backend authorization presentation mismatch.

Owner:

```text
PLAN-3 / T-3.7
```

---

# 5. CROSS-DOMAIN ROOT CAUSES

## RC-1 — Capability RBAC exists without resource ownership

Current architecture can answer:

```text
Does this role have alert.resolve?
```

It cannot answer:

```text
May this user resolve THIS alert?
```

Root findings:

```text
F-012
F-018
```

---

## RC-2 — Durable DB publication ends before durable downstream processing

```text
DB outbox
→ Redis application stream
→ DB outbox marked published
```

but Redis retention remains independently destructive.

Root finding:

```text
F-001
```

---

## RC-3 — Deployment topology did not evolve with application architecture

Alert evaluation moved to async queue processing.

Deployment still treats that queue process as optional.

Root:

```text
F-002
```

---

## RC-4 — Stable event identity is correctly designed but not universal

Canonical MQTT/raw-first path is strong.

Compatibility/direct path is not.

Root:

```text
F-013
```

---

## RC-5 — Certification scope exceeds tested scope

Affected claims include:

```text
Gate 9
Gate 10
desktop full product
mobile full product
Sanctum ability enforcement
```

The implementations are not necessarily bad.

The previous closure statements are broader than the supporting evidence.

---

# 6. FINAL ARCHITECTURE VERDICT

```text
Code architecture:
CONDITIONAL

Maintainability:
CONDITIONAL

Event pipeline:
FAIL

IoT ingestion:
FAIL

Redis consumer reliability:
FAIL

Realtime:
CONDITIONAL

Frontend architecture:
CONDITIONAL

Desktop:
PENDING

Mobile:
PENDING
```

The architecture is not fundamentally broken.

Strong mechanisms already exist:

- transactional outboxes;
- stable MQTT identity;
- durable SQLite spool;
- CDC;
- Redis consumer groups;
- XAUTOCLAIM;
- crash-before-ACK handling;
- Echo singleton;
- Pinia projection ownership.

Release closure is blocked by a narrower set of concrete failure boundaries.

---

# 7. FINAL DATABASE VERDICT

```text
Schema:
FAIL

FK integrity:
CONDITIONAL

Relationships:
FAIL

Indexes:
CONDITIONAL

IoT data integrity:
FAIL

Migration reproducibility:
CONDITIONAL


usuario:
EQUIVALENT

rol:
EQUIVALENT

actividades:
permissions table
(semantic equivalent; no physical `actividades` table)


RBAC conceptual data model:
FAIL
```

Strong database areas remain:

- raw-event identity;
- alert uniqueness;
- reading provenance;
- RBAC pivot uniqueness;
- temporal mapping lookup;
- reading range indexes.

RBAC terminology clarification:

```text
Usuario → Rol → Actividades
users.role_id → roles → role_permissions → permissions
```

The physical `permissions` table is the catalogue of system activities/actions.
It fulfills the conceptual “actividades” entity; the schema does not contain a
separate table literally named `actividades`.

Primary failures are semantic:

```text
missing User → Lab scope
sensor mapping lifecycle drift
noncanonical ingestion idempotency
```

---

# 8. FINAL SECURITY VERDICT

```text
Authentication:
CONDITIONAL

Functional RBAC:
CONDITIONAL

Lab scoping:
FAIL

IDOR resistance:
FAIL

Privilege escalation protection:
PASS

REST authorization:
FAIL

Channel read authorization:
FAIL

Channel write authorization:
FAIL

REST ↔ WS consistency:
CONDITIONAL
```

REST and WebSocket are largely aligned at the global permission layer.

The problem is that both lack the required resource scope.

---

# 9. GATE 9 VERDICT

```text
Gate 9:
REOPEN
```

Reasons:

- incomplete router coverage;
- incomplete role-specific surfaces;
- incomplete page identity verification;
- permissive prefix route matching;
- full desktop not certified;
- full mobile not certified;
- live screenshot evidence not independently available to this reconciliation.

This is a **certification gap**, not proof that the UI is visually poor.

---

# 10. GATE 10 VERDICT

```text
Gate 10:
REOPEN
```

Concrete active-source contradictions exist:

```text
F-001
stream retention

F-002
missing required alert worker

F-003
terminal spool exhaustion

F-013
alternative ingestion path without stable identity
```

Historical Gate 10 evidence remains valid for scenarios actually tested.

Required response:

```text
TARGETED RECERTIFICATION
```

not repeating the full historic 35-phase process.

---

# 11. PLAN.MD CLOSURE MATRIX

## Current authority

`PLAN.md` is the completed historical 35-phase baseline. It is not the current
release authority. `NEW_PLAN.md` is the active, open remediation authority.

Therefore:

```text
PLAN.md STATUS:
HISTORICAL BASELINE — SUPERSEDED
```

The following matrix covers only items explicitly surfaced by:

```text
A1
A2
B
C
```

It must **not** be interpreted as the current exhaustive remediation inventory.

```text
FULL PLAN.md ITEM COUNT:
UNVERIFIED
```

Referenced items:

| PLAN area | Status |
|---|---|
| HEAD CI green | CLOSED + VERIFIED |
| application source freeze | CLOSED + VERIFIED |
| Graphify current | CLOSED + VERIFIED |
| transactional outbox | CLOSED + VERIFIED |
| CDC replacing polling relay | CLOSED + VERIFIED |
| XAUTOCLAIM tested recovery | CLOSED + VERIFIED |
| H5 immediate duplicate collapse | CLOSED + VERIFIED |
| MQTT subscriber recovery | CLOSED + VERIFIED |
| timezone correction | CLOSED + VERIFIED |
| Echo singleton/refcount/no polling | CLOSED + VERIFIED |
| Redis retention safety | OPEN |
| alert-worker topology | CONTRADICTED |
| Python long-outage recovery | OPEN |
| full Gate 9 route certification | CONTRADICTED |
| desktop full certification | PARTIAL |
| mobile full certification | PARTIAL |
| sensor mapping lifecycle | OPEN |
| User→Lab assignment | OPEN |
| REST scope | OPEN |
| channel scope | OPEN |
| scoped alert resolve | OPEN |
| ingestion-path idempotency | PARTIAL |
| RBAC seed convergence | PARTIAL |
| dynamic role-permission management | SOURCE REMOVED / DB VERIFY PENDING |
| API password parity | SOURCE FIXED / MAC VERIFY PENDING |
| WS client-write policy | SOURCE FIXED / MAC LIVE VERIFY PENDING |
| alert lifecycle replay safety | PARTIAL |
| pipeline observability | PARTIAL |
| timestamp semantic separation | PARTIAL |
| query index coverage | PARTIAL |
| state constraints | OPEN |
| Sanctum ability claim | CONTRADICTED |
| role assignment UX parity | CLOSED / WINDOWS VERIFIED |

### PLAN status

```text
PLAN.md:
HISTORICAL BASELINE — SUPERSEDED

NEW_PLAN.md:
ACTIVE / OPEN
```

Release closure remains open until the active findings have fresh evidence-based closure.

---

# 12. PLAN-1
## CODE / ARCHITECTURE / EVENT / FRONTEND

# T-1.1 — Consumer-safe stream retention

**Master:** F-001  
**Environment:** Either implementation / Mac verification  
**Release priority:** P0  
**Complexity:** MEDIUM-HIGH

Actions:

1. remove producer-controlled destructive trimming from processing-critical streams or make it consumer-progress-aware;
2. define durable re-drive/reconciliation;
3. add retention-headroom metrics;
4. test consumer outage beyond retention threshold.

Exit:

```text
consumer outage
→ retention threshold exceeded
→ restart
→ zero logical loss
```

---

# T-1.2 — Required alert evaluator

**Master:** F-002  
**Environment:** Mac  
**Release priority:** P0  
**Complexity:** LOW

Make queue worker part of the required standard topology.

Add E2E:

```text
MQTT violating reading
→ SensorReading
→ queue
→ Alert
→ domain outbox
→ Reverb
→ browser
```

---

# T-1.3 — Automatic long-outage spool recovery

**Master:** F-003  
**Environment:** Either implementation / Mac verification  
**Release priority:** P0  
**Complexity:** MEDIUM

Implement:

- transient/permanent error classification;
- no irreversible retry exhaustion for transient failures;
- supported local DLQ replay;
- backlog/dead-letter metrics;
- outage recovery test longer than current exhaustion window.

---

# T-1.4 — Router-complete Gate 9

**Master:** F-004  
**Environment:** Windows + Mac verification  
**Priority:** P1  
**Complexity:** MEDIUM

Create one authoritative certification manifest:

```text
route
role
expected final route
expected page marker
viewport
```

No global `startsWith()` assumption.

---

# T-1.5 — Monotonic alert projection

**Master:** F-005  
**Environment:** Windows + Mac  
**Priority:** P1  
**Complexity:** LOW-MEDIUM

Regression cases:

```text
trigger → trigger
trigger → resolve
resolve → delayed trigger
trigger → resolve → delayed trigger
replay after >500 unrelated events
snapshot + stale event
```

---

# T-1.6 — Pipeline readiness

**Master:** F-006  
**Environment:** Either + Mac  
**Priority:** P1  
**Complexity:** MEDIUM

Expose:

- queue depth/age;
- worker heartbeat;
- spool pending/dead letters;
- retention headroom;
- stale raw events;
- consumer lag;
- Debezium freshness;
- Reverb probe.

---

# T-1.7 — Correct timestamp runtime semantics

**Master:** F-007  
**Environment:** Dependent  
**Dependency:** T-2.8  
**Priority:** P1

Runtime must preserve:

```text
device_timestamp
received_at
processed_at
```

as distinct concepts.

---

# T-1.8 — Reduce hotspots

**Masters:** F-008, F-009  
**Environment:** Either  
**Priority:** P3

Only after reliability fixes.

---

# T-1.9 — Final PLAN/certification reconciliation

**Master:** F-010  
**Environment:** Either  
**Priority:** FINAL

Update only after all relevant runtime evidence exists.

---

# 13. PLAN-2
## DATA / DATABASE / SCHEMA / RELATIONSHIPS

# T-2.1 — Transactional sensor identity transition

**Master:** F-011  
**Environment:** Either + Mac  
**Priority:** P0  
**Complexity:** MEDIUM

Identity-bearing update:

```text
close old mapping
+
update Sensor
+
create replacement mapping
```

must occur atomically.

---

# T-2.2 — Persist laboratorista operational Lab scope

**Master:** F-012  
**Environment:** Either + Mac  
**Priority:** P0 STRUCTURAL  
**Complexity:** HIGH

Final domain decision:

```text
DO NOT CREATE A FIRST-CLASS Procedure ENTITY.
```

The repository contains no Procedure model/table/FK/API; `labs.process_line` is the nearest existing operational concept but is plain Lab metadata.

Current target:

```text
User
→ assigned Labs
```

Must include:

- assignment relation;
- FK integrity;
- uniqueness;
- indexes;
- explicit admin/superadmin bypass semantics.

Do not use arbitrary `process_line` string comparison as a security boundary.

If future external business requirements establish a genuine normalized process/procedure authorization entity, add it separately.

---

# T-2.3 — Single canonical ingestion authority

**Master:** F-013  
**Environment:** Either + Mac  
**Priority:** P0/P1  
**Complexity:** LOW-MEDIUM

Canonical architecture remains:

```text
/api/ingestion/events
→ raw-first
→ outbox
→ CDC
→ Redis
→ normalization
```

`POST /api/sensors/{sensor}/readings` is a documented compatibility path, not canonical ingestion.

Decision:

```text
DO NOT BUILD A SECOND PARALLEL IDEMPOTENCY ARCHITECTURE.
```

If no external devices depend on it:

```text
deprecate
→ retire
```

If external clients still depend on it:

```text
retain temporarily
→ delegate/migrate toward raw-first
→ retire later
```

---

# T-2.4 — Authoritative static RBAC convergence

**Master:** F-014  
**Environment:** Either  
**Priority:** P1

Final RBAC model:

```text
roles/permissions/mappings
ARE source-controlled
```

Seeder/migration logic must converge exactly.

Removed grants must not survive upgrades accidentally.

---

# T-2.5 — Remove dormant dynamic-RBAC capability

**Master:** F-015  
**Environment:** Either  
**Priority:** P1

Final decision:

```text
role→permission mappings are NOT dynamically editable.
```

**Current status:** SOURCE FIXED; MySQL upgrade verification remains pending.

Current application supports predefined roles and user→role assignment, with no runtime role-permission editor.

Actions:

- remove/deprecate `role.permissions.manage`;
- retain predefined roles;
- keep user→role assignment dynamic;
- make role-permission matrix source-authoritative.

### Implementation evidence

**Completed source work** — commit `b427f93`:

- `back/database/seeders/RolePermissionSeeder.php` no longer seeds
  `role.permissions.manage`; the remaining predefined permission catalogue and
  `role_permissions` mappings remain source-controlled.
- `back/database/migrations/2026_09_21_000001_remove_role_permissions_manage_permission.php`
  deletes the legacy permission on upgrade and supplies a reversible `down()`
  migration.

**Automated regression present, execution pending:**

- `back/tests/Feature/RbacUserProvisioningTest.php` —
  `test_dynamic_role_permission_management_capability_is_not_seeded()` checks
  that the seed has no legacy permission and that a superadmin cannot receive
  it.
- **PENDING:** execute that PHPUnit test against MySQL, then apply the
  migration to an existing database containing the legacy row and prove that
  neither the permission nor its pivot survives. This Windows evidence set did
  not include a runnable PHP/MySQL runtime.

---

# T-2.6 — Evidence-backed index optimization

**Master:** F-016  
**Environment:** Mac  
**Priority:** P1

Use `EXPLAIN ANALYZE`.

Do not add speculative indexes.

---

# T-2.7 — State constraints

**Master:** F-017  
**Environment:** Mac  
**Priority:** P2

Add CHECK constraints only for truly closed vocabularies.

---

# T-2.8 — Timestamp schema semantics

**Master:** F-007  
**Environment:** Either + Mac  
**Priority:** P1

Ensure persistence can distinguish:

```text
device_timestamp
server received_at
processed_at
```

without semantic overload.

---

# 14. PLAN-3
## SECURITY / RBAC / CHANNELS

# T-3.1 — Resource-scoped authorization authority

**Master:** F-012  
**Environment:** Either + Mac  
**Dependency:** T-2.2  
**Priority:** P0

Authorization becomes:

```text
required permission
AND
resource.lab ∈ user.assigned_labs
```

with explicit privileged-role semantics.

Apply to:

- devices;
- sensors;
- readings;
- alerts.

---

# T-3.2 — Scoped alert resolution

**Master:** F-018  
**Environment:** Either + Mac  
**Dependency:** T-3.1  
**Priority:** P0

`resolveAll()` must operate on:

```text
authorized active alerts
```

not:

```text
all active alerts
```

---

# T-3.3 — Scoped realtime channels

**Master:** F-012  
**Environment:** Mac  
**Dependencies:** T-2.2 + T-3.1  
**Priority:** P0

Cross-lab sensor substitution must fail.

Global alert/device-status channels must be redesigned or filtered so that users only receive authorized Lab data.

---

# T-3.4 — Restore read-only realtime contract

**Master:** F-019  
**Environment:** Either + Mac  
**Priority:** P1  
**Complexity:** LOW

If browser→browser messaging is not required:

```text
accept_client_events_from=none
```

Add negative test.

**Current status:** SOURCE FIXED; live Reverb verification on macOS remains pending.

### Implementation evidence

**Completed source work** — commit `943c909`:

- `back/config/reverb.php` defaults
  `REVERB_APP_ACCEPT_CLIENT_EVENTS_FROM` to `none`.
- `back/.env.example` documents the same safe default for deployments.

**Automated regression present, execution pending:**

- `back/tests/Feature/ReverbClientEventPolicyTest.php` —
  `test_client_events_are_disabled_by_default()` asserts the configuration;
  `test_vendor_client_event_handler_rejects_client_events_when_disabled()`
  drives Reverb's handler and expects Pusher error `4301`.
- **PENDING:** run that PHPUnit file on macOS and attempt a live browser
  `client-*` event against Reverb. No live Reverb service was available in the
  Windows evidence run.

---

# T-3.5 — Shared password policy

**Master:** F-020  
**Environment:** Either + Mac  
**Priority:** P1  
**Complexity:** LOW

Use one rule factory for:

- API registration;
- API password reset;
- web registration;
- web reset.

**Current status:** SOURCE FIXED; password-flow verification on macOS remains pending.

### Implementation evidence

**Completed source work** — commit `4afdaea` (with network isolation for the
test suite in `259406f`):

- `back/app/Support/Security/PasswordPolicy.php` defines the shared confirmed
  12-character mixed-case, letter, number, symbol, and uncompromised rule.
- `back/app/Http/Controllers/Api/AuthApiController.php` consumes it for API
  registration and API password reset.
- `back/app/Http/Controllers/Auth/RegisterController.php` and
  `back/app/Http/Controllers/Auth/ResetPasswordController.php` consume it for
  the web registration and reset flows.

**Automated regressions present, execution pending:**

- `back/tests/Feature/AuthApiHeadlessTest.php` —
  `test_api_register_rejects_a_weak_password()` and
  `test_api_reset_password_rejects_a_weak_password_and_keeps_the_old_password()`.
- `back/tests/Feature/AuthSecurityTest.php` —
  `test_register_rejects_weak_password()` and
  `test_password_reset_rejects_weak_password()` for the web endpoints.
- Those PHP tests fake Have I Been Pwned range requests so the
  `uncompromised()` rule does not require external network access.
- **PENDING:** run the PHP feature tests and perform the browser/API password
  flows on macOS. PHP runtime evidence was not collected in this Windows run.

---

# T-3.6 — Resolve Sanctum ability drift

**Master:** F-021  
**Environment:** Either  
**Priority:** P2

Either implement meaningful PAT abilities or remove stale claims.

---

# T-3.7 — Align role-management UI

**Master:** F-022  
**Environment:** Windows  
**Priority:** P2

Do not offer role transitions backend will reject.

**Current status:** CLOSED — Windows Vitest verified.

### Implementation evidence

**Completed source work** — commit `399d158`, with the activities wording
clarification in `67f654e`:

- `front/src/security/roleTransitionPolicy.js` derives only backend-permitted,
  assignable transitions from the actor role and `user.role.assign` permission.
- `front/src/views/UserRolesView.vue` disables empty role controls and states:
  “Las actividades del sistema se representan mediante permisos asociados a
  cada rol.”

**Vitest evidence — executed on Windows:**

- `front/src/security/roleTransitionPolicy.test.js` covers the allowed and
  rejected hierarchy transitions plus the permission and assignability guards
  (8 cases).
- `front/src/views/UserRolesView.test.js` covers the matching rendered role
  controls and
  `explains that a role controls activities through its associated permissions`
  (5 cases).
- `npm.cmd run test:unit -- src/security/roleTransitionPolicy.test.js src/views/UserRolesView.test.js`
  completed with **2 files / 13 tests passing**.
- `npm.cmd run test:unit` completed with **53 files / 354 tests passing**.

**Pending non-Vitest evidence:** a live authenticated `/users` browser flow
against a running backend. The local browser probe could not enter the
protected route without an authenticated backend session; this does not reopen
the source-level Windows closure because the view DOM regression is executed.

---

# T-3.8 — Verify static role-permission immutability

**Master:** F-015  
**Environment:** Either + Mac  
**Dependency:** T-2.5  
**Priority:** P1

Because dynamic Role→Permission administration has been rejected:

- verify there is no runtime mutation surface;
- verify source-controlled seeding converges;
- verify no stale permission pivot survives upgrade;
- verify user→role assignment remains the only normal RBAC mutation.

### Implementation evidence

- The source changes and test fixture are shared with T-2.5:
  `back/database/seeders/RolePermissionSeeder.php`,
  `back/database/migrations/2026_09_21_000001_remove_role_permissions_manage_permission.php`,
  and `back/tests/Feature/RbacUserProvisioningTest.php`.
- **PENDING:** execute the PHPUnit regression and a MySQL upgrade-path test
  against a pre-existing legacy permission/pivot. Until that run is captured,
  this task remains partial even though the source removal is complete.

---

# T-3.9 — Full two-Lab security matrix

**Masters:** F-012, F-018, F-019, F-020  
**Environment:** Mac  
**Priority:** FINAL SECURITY GATE

Minimum:

```text
Lab A
Lab B

Operator A
Operator B

wrong-lab device
wrong-lab sensor
wrong-lab reading
wrong-lab alert
resolve wrong-lab alert
resolve-all
cross-lab sensor channel
alert channel
device-status channel
client-* write
password-policy bypass
```

---

# 15. FILE OWNERSHIP MATRIX

| Component | Owner |
|---|---|
| Redis publishers/consumers | PLAN-1 |
| `docker-compose.yml` worker topology | PLAN-1 |
| Python spool/recovery | PLAN-1 |
| Pinia realtime projections | PLAN-1 |
| Gate 9 harness | PLAN-1 |
| `SensorApiController` identity behavior | PLAN-2 until correctness fixes complete |
| temporal sensor mappings | PLAN-2 |
| User→Lab assignment | PLAN-2 |
| role/permission seed structures | PLAN-2 |
| indexes/constraints | PLAN-2 |
| `ResourceAccessService` | PLAN-3 |
| Alert authorization | PLAN-3 |
| scoped alert lifecycle mutation | PLAN-3 |
| `routes/channels.php` | PLAN-3 |
| Reverb write policy | PLAN-3 |
| password rules | PLAN-3 |
| role-management security UX | PLAN-3 |

Important:

```text
PLAN-1 must not refactor SensorApiController
while PLAN-2 is repairing its identity behavior.
```

---

# 16. CROSS-PLAN DEPENDENCY MAP

```text
T-2.2 ──────────> T-3.1
                     │
                     ├──> T-3.2
                     ├──> T-3.3
                     └──> T-3.9


T-2.8 ──────────> T-1.7


T-1.1 ──────────> T-2.6


T-3.7 ──────────┐
                 ├──> T-1.4
T-3.3 ──────────┘


T-1.1
T-1.2
T-1.3
T-1.4
T-1.5
T-1.7
T-2.1
T-2.3
T-2.6
T-3.9
    │
    └────────────> FINAL MAC RECERTIFICATION
```

---

# 17. EXECUTION PRIORITY BY RISK

Severity alone does not define execution order.

Use:

```text
impact
×
practical likelihood
×
remediation complexity
×
dependency value
```

| Finding | Impact | Practical likelihood | Complexity | Execution |
|---|---|---|---|---|
| F-002 alert worker | HIGH | HIGH | LOW | **P0 FIRST** |
| F-001 retention | HIGH | MEDIUM | MED-HIGH | **P0** |
| F-003 spool exhaustion | HIGH | MED-HIGH during outage | MEDIUM | **P0** |
| F-011 sensor mapping | HIGH | MEDIUM | MEDIUM | **P0** |
| F-012 Lab scope | HIGH | CERTAIN under current model | HIGH | **P0 STRUCTURAL** |
| F-018 global alert resolution | HIGH | CERTAIN | MEDIUM | **P0 after F-012** |
| F-013 compatibility ingress | HIGH | usage-dependent | LOW-MED | **P0/P1** |
| F-020 password parity | MEDIUM | directly reachable | LOW | **P1 EARLY** |
| F-005 alert projection | MEDIUM | LOW-MED | LOW-MED | **P1** |
| F-004 Gate 9 | MEDIUM | CERTAIN certification gap | MEDIUM | **P1** |

---

# 18. EXECUTION WAVES

## WAVE 0 — FAST RELEASE BLOCKERS

Execute first:

```text
T-1.2
required alert worker

T-3.5
password-policy parity

T-2.5
remove dormant role.permissions.manage

T-2.4
static RBAC convergence
```

---

## WAVE 1 — CORE DATA CORRECTNESS

```text
T-2.1
sensor mapping lifecycle

T-1.1
consumer-safe stream retention

T-1.3
long-outage spool recovery

T-2.3
single canonical ingestion authority
```

---

## WAVE 2 — RESOURCE SCOPE

```text
T-2.2
User → Lab persistence

↓

T-3.1
resource authorization

↓

T-3.2
scoped alert resolution

T-3.3
scoped channels

↓

T-3.9
two-Lab security verification
```

---

## WAVE 3 — REALTIME / CERTIFICATION QUALITY

```text
T-1.5
alert projection

T-1.6
readiness

T-2.8
timestamp persistence

T-1.7
timestamp runtime semantics

T-2.6
index validation
```

---

## FINAL CERTIFICATION WAVE

```text
T-1.4
full Gate 9 live certification

targeted Gate 10 Mac certification

full security matrix

physical MySQL parity

complete PLAN.md reconciliation
```

---

# 19. WINDOWS-CAPABLE NOW

Strong Windows candidates:

```text
T-1.4
Gate 9 manifest / Vitest logic

T-1.5
Pinia lifecycle regression tests

T-3.7
role-management frontend

T-1.9
documentation after implementation
```

Implementation may also begin on Windows for several PHP/schema tasks, but Windows static completion is not sufficient evidence for final closure where MySQL/Redis/MQTT/Reverb are required.

---

# 20. MAC REQUIRED FOR CLOSURE

Mac is mandatory for:

```text
Redis retention fault injection

queue-worker alert E2E

long backend outage

SQLite DLQ replay

real sensor-mapping transaction

MySQL schema parity

idempotency/concurrency

MySQL EXPLAIN ANALYZE

REST two-Lab security

WebSocket two-Lab security

Reverb client-write test

full live Playwright

targeted Gate 10
```

---

# 21. COULD NOT VERIFY

Current runtime evidence still required for:

- Redis stream lengths;
- Redis group lag;
- current XPENDING;
- current DLQ;
- Python spool backlog;
- Python local DLQ;
- DB jobs backlog;
- MQTT broker session persistence;
- Debezium offsets;
- Reverb runtime behavior;
- complete production screenshots;
- physical MySQL parity;
- actual role-permission rows;
- current DB timezone;
- alert/status query plans;
- local uncommitted changes.

These uncertainties do **not** invalidate source-proven failures.

They do limit runtime-dependent closure claims.

---

# 22. FINAL ADVERSARIAL PASS

## Findings merged

```text
A1:F-A-001 + A2:A-01 → F-001
A1:F-A-002 + A2:A-03 → F-002
A1:F-A-003 + A2:A-02 → F-003
A1:F-A-004 + A2:A-06 → F-004
A1:F-A-005 + A2:A-05 → F-005
A1:F-A-006 + A2:A-07 → F-006
A1:F-A-007 + A2:A-08 → F-008

B:F-B-002 + C:F-C-001 → F-012

B:F-B-005 + C:F-C-007 → F-015
```

## Severity changed

```text
F-003:
MEDIUM
→ HIGH
```

Reason:

operationally permanent removal from automatic telemetry processing after a realistic backend outage, with no supported replay or bounded recovery SLA.

```text
F-008:
MEDIUM
→ LOW
```

Reason:

responsibility concentration without demonstrated current correctness defect.

## Baseline claims contradicted

```text
PLAN fully CLOSED

Gate 9 CLOSED

Gate 10 CLOSED

Lab/procedure-scoped authorization

Sanctum abilities as an active defense layer

full desktop certification

full mobile certification
```

## Baseline claims defended

```text
transactional outbox

canonical MQTT stable identity

short-window crash recovery

XAUTOCLAIM mechanics

immediate H5 duplicate collapse

timezone correction

Echo singleton

channel refcounting

role hierarchy protections

privileged configuration boundaries
```

---

# 23. FINAL BUSINESS DECISIONS

## Q1 — Procedure

Final:

```text
DO NOT CREATE Procedure.
```

Current repository domain is:

```text
Lab
├─ name
├─ area
├─ process_line
└─ devices
```

There is no evidence of a separate Procedure aggregate.

`process_line` is not automatically equivalent to “procedure”.

For current remediation:

```text
authorization scope = Lab
```

unless a later explicit SINOA business requirement adds another normalized operational entity.

---

## Q2 — Direct sensor reading endpoint

Final:

```text
POST /api/sensors/{sensor}/readings

=
legacy / compatibility production surface
```

not canonical durable ingestion.

Target:

```text
/api/ingestion/events
=
single canonical ingestion authority
```

Do not implement two competing idempotency systems.

Only remaining operational verification:

```text
Are external deployed devices still calling the compatibility endpoint?
```

That changes migration timing, not target architecture.

---

## Q3 — Dynamic permissions

Final:

```text
NO
```

Role→Permission mappings remain source-controlled/static.

Runtime allows:

```text
User → predefined Role
```

but not:

```text
Role → arbitrary Permission mutation
```

`role.permissions.manage` should be removed/deprecated.

---

# 24. FINAL CLOSURE

```text
FINAL CLOSURE

Repository baseline audited:
0092a6962b547f767bb60fc8c2ad7d0475cba1a5

Application SHA audited:
4b148252415b54f8ba969b563aaaa0f5f3761470

Post-audit Windows remediation source base:
d6192b7669818889c059626c2655878470c1dc72

Graphify:
CURRENT


Status correction:
- SOURCE FIXED / macOS recertification pending: F-019, F-020
- PARTIAL / MySQL upgrade verification pending: F-015
- CLOSED / Windows Vitest verified: F-022
- All other finding statuses remain as recorded in their individual entries.

Current open ledger:
See the individual F-001 through F-022 statuses; this summary does not replace them.


Architecture:
CONDITIONAL

Event pipeline:
FAIL

IoT:
FAIL

Database:
FAIL

RBAC:
CONDITIONAL

Lab security:
FAIL

Channel security:
FAIL

Realtime:
CONDITIONAL

Desktop:
PENDING

Mobile:
PENDING


Gate 9:
REOPEN

Gate 10:
REOPEN


PLAN.md:
HISTORICAL BASELINE — SUPERSEDED

NEW_PLAN.md:
ACTIVE / OPEN


Plan-1 tasks:
9

Plan-2 tasks:
8

Plan-3 tasks:
9
```

---

# EVIDENCE PREVENTING FULL CLOSURE

Concrete blockers:

1. Processing-critical Redis streams can trim unprocessed work.
2. Standard worker topology can run without alert evaluation.
3. Backend outages can push accepted telemetry permanently outside automatic processing.
4. Sensor identity mappings can diverge during sensor reassignment.
5. User→Lab operational scope does not exist.
6. REST and realtime authorization therefore remain globally scoped.
7. Alert resolution and resolve-all are globally scoped.
8. Compatibility ingestion bypasses canonical event identity/outbox architecture.
9. Gate 9 does not certify the complete product.
10. Password-policy source changes await macOS password-flow recertification.
11. Reverb client-event source changes await live macOS Reverb recertification.
12. Full MySQL/Redis/MQTT/Reverb/browser recertification remains pending.

---

# FINAL RELEASE CONCLUSION

The repository is **not ready for final release closure**. `PLAN.md` remains the
completed historical baseline; `NEW_PLAN.md` remains the active remediation
authority.

Strong architectural mechanisms already exist and should be preserved:

```text
transactional outboxes
stable MQTT identity
durable local spool
CDC
Redis consumer groups
XAUTOCLAIM
idempotent raw processing
centralized Echo lifecycle
Pinia projection ownership
role hierarchy protections
```

Release closure is blocked by a smaller set of concrete correctness and authorization failures:

```text
consumer-safe event retention

mandatory alert-worker deployment

automatic long-outage telemetry recovery

sensor identity lifecycle

single canonical ingestion authority

persisted User → Lab scope

resource-scoped REST authorization

resource-scoped WebSocket authorization

scoped alert mutation

complete Gate 9 certification
```

The correct next step is:

```text
execute active NEW_PLAN remediation tasks
according to dependency waves

↓

targeted Mac runtime recertification

↓

active NEW_PLAN reconciliation

↓

close only evidence-backed findings

↓

release gate closed
```

Do not repeat the entire historical audit or 35-phase implementation plan unless new contradictory evidence appears.

---

## Task 1 evidence — Auth and canonical-ingestion rate-limit boundaries (2026-09-22)

**Finding:** registration shared the credential-stuffing limiter, while canonical
ingestion inherited `api-write`'s empty `{sensor}` bucket.
**Severity:** MEDIUM
**Scope / owner:** backend-observability-logging / Task 1
**Status:** SOURCE FIXED — MAC TEST EXECUTION PENDING

**Windows evidence:** static inspection confirms independent `auth-register` and
IP-only configurable `ingestion-events` boundaries; four route-level regressions
were authored before the source change, and `git diff --check` passes.
**Mac-required evidence:** execute the focused PHPUnit regression and load-test
the configured ingestion ceiling. `MAC_LOAD_VALIDATION_PENDING`; the 120/min
default is deliberately non-certified.
