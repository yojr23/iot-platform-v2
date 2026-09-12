# SINOA IoT Platform Security Hardening Implementation Plan

Repository: yojr23/iot-platform-v2
Target branch: refraccion
Audited baseline commit: 23ca2e1dc79a9beb771e3e244e1a7167655b339b
Primary scope: authentication, authorization, BOLA/IDOR prevention, token lifecycle,
secret handling, IoT credential security, rate limiting, realtime authorization, data
minimization, logging, security regression coverage.

## Global Constraints (mandatory execution rules)

- Do not modify the anonymous public graph contract except where security tests prove an exposure.
- Do not introduce polling. The project remains event-driven.
- Do not bypass the transactional outbox / CDC / Redis Streams event pipeline.
- Do not "fix" backend authorization only in the Vue UI. Every permission boundary must be enforced server-side.
- Never use client-provided role fields as authorization evidence.
- Never log raw credentials, tokens, password fragments, API-key fragments, reset tokens, or SMTP passwords.
- Every security fix must have a failing regression test first (TDD).
- Run focused tests after every task; full backend/frontend suites at the end.
- Keep public monitoring functional for sensors explicitly marked public.
- Do not rewrite Git history or rotate external credentials automatically (operator actions).
- Do not mark PLAN.md security gates closed until acceptance tests pass.

## Target authorization model

Capability matrix (Guest / Unverified / Standard verified / Admin):
- Public graph bootstrap + series, login/verify/reset: all YES.
- Private dashboard, device list/detail, private sensor series/readings, alerts list, private realtime channels: NO / NO / YES* / YES.
- Resolve one alert, resolve all, device/sensor CRUD, config/users/roles: NO / NO / NO / YES.

YES* = only resources the authorization policy allows. Centralize the rule (ResourceAccessService + policies) so REST and WebSockets cannot diverge. Admin = global access. Standard verified user = read private operational data if product intentionally permits internal visibility. Future lab-scoped access must be addable via a mapping without rewriting controllers/channels.

## Tasks

### Task 1 — Block pre-verification private access (SEC-AUTH-001)
register() must NOT issue a token. Return 201 {message, verification_required:true, user}. Private route group requires auth:sanctum + verified. Do not block logout/resend/signed-verify. Frontend registration redirects to "verify your email". Tests: register response has no access_token; unverified token cannot reach /devices /sensors /alerts /dashboard/metrics; verified user can.

### Task 2 — Make Sanctum abilities enforceable (SEC-AUTH-002)
Abilities: read, alerts:resolve, admin. Admin token may keep *. Add ability middleware to token-based operational mutations. Policies/admin middleware stay authoritative. Tests: standard read PAT cannot resolve/resolve-all; admin can.

### Task 3 — Lock down alert state mutations (SEC-ALERT-001)
Create AlertPolicy (viewAny/view = verified; resolve/resolveAll = is_admin). Controller authorize('resolve',$alert) + gate before bulk. Preserve AlertLifecycleService + event/outbox emission. Tests: guest 401, unverified blocked, standard verified 403 on resolve + resolve-all, admin success with events preserved.

### Task 4 — Centralize Device/Sensor/Realtime authorization (SEC-BOLA-001/002, SEC-RT-001)
Create ResourceAccessService (canViewDevice/canViewSensor/canReceiveAlerts/canReceiveDeviceStatus), SensorPolicy, DevicePolicy. Apply SensorPolicy to show/readings/series/latest/export/graph-zones; DevicePolicy to index/show/status-snapshot/sensor-list. Reuse same service in channels.php closures (no "sensor exists"/true). Public channels unchanged. Tests: REST matrix + broadcast tests for sensor.{id}, alerts, device-status.

### Task 5 — Minimize private topology exposure (SEC-BOLA-002, SEC-CONFIG-001)
DeviceResource/SensorResource/AlertResource conditional fields by audience; ConfigController minimize. No full nested model serialization. Tests: guest/standard/admin payload shapes.

### Task 6 — Revoke sessions on password reset and role downgrade (SEC-TOKEN-001)
On successful reset: $user->tokens()->delete(). Revoke tokens on admin privilege change. Tests: old token 401 after reset; admin token unusable after downgrade; logout still revokes.

### Task 7 — Replace long-lived browser PAT storage (SEC-TOKEN-002/003)
Prefer Sanctum first-party SPA cookie auth (HttpOnly, Secure, SameSite, CSRF, withCredentials). Remove localStorage bearer. IoT ingestion stays header-token. Transitional: if cookie migration deferred, set finite PAT expiration (never null). Tests: session auth, CSRF-negative, frontend auth lifecycle, Playwright.

### Task 8 — Harden IoT device credentials (SEC-IOT-001/002, SEC-MODEL-001)
Header-only device key (X-Device-Key); remove query/body transport. Remove global legacy config('app.api_key') fallback (per-device only). Hash device keys (api_key_hash + prefix/key-id), plaintext returned once, rotate endpoint. Add api_key to Device $hidden. Tests: query rejected, body rejected, header accepted, global rejected, cross-device rejected, key absent from serialization, rotation.

### Task 9 — Fix ingestion and login rate-limit keys (SEC-RATE-001, SEC-ENUM-001)
IoT write limiter: by IP and IP+sensorId (never attacker-controlled key alone). Login: per IP + per normalized email. Tests: fake-key rotation doesn't reset write limit; email rotation doesn't bypass IP login limiter.

### Task 10 — Encrypt SMTP secrets at rest (SEC-SECRET-001)
SecretSettingService (Crypt::encryptString/decryptString). SystemSetting secret helpers; group reads never return decrypted secrets. EmailConfigController encrypts on write, decrypts only at use, password absent from API. Idempotent migration of existing plaintext. Tests: DB value != plaintext, /config/email returns password_configured only, mail runtime gets correct secret, logs clean.

### Task 11 — Redact secret fragments + sensitive exceptions from logs (SEC-LOG-001/002)
Remove token-prefix logging in EnsureIngestionToken. Production logs favor exception class/SQL state/DB code/route/request-id over raw getMessage(). Tests: sentinel secrets absent from logs.

### Task 12 — Bound sensor exports and inventory queries (SEC-EXPORT-001, SEC-INV-001)
Export: mandatory date range, max 31 days, max 50000 rows, index-compatible filters, 422 on cap exceed. Inventory index(): paginate with max page size. Tests: range validation, row cap, pagination.

### Task 13 — Strengthen password + account-enumeration controls (SEC-ENUM-001, SEC-PASS-001)
Password::min(12)->mixedCase->letters->numbers->symbols->uncompromised (passphrase-friendly acceptable if documented + compromised check). Forgot-password generic response. Rate-limit resend/reset. Tests: weak rejected, strong accepted, forgot-password known==unknown response.

### Task 14 — Decide and enforce registration policy
Choose ONE: (A) closed admin-invite-only (signed expiring invite, recommended) or (B) public self-registration (no private access pre-verify, least-privilege). Do not rely on email-domain as authz. Tests for chosen policy. Update README/PLAN.

### Task 15 — Harden SMTP test destination (SEC-SMTP-001)
Admin-only defense-in-depth. UpdateEmailConfigRequest validates allowed ports/hosts; block loopback/link-local/metadata unless required. Tests: blocked-target, approved relay, no password in logs.

### Task 16 — Fix public graph effective-window semantics (SEC-PUBLIC-001)
Response exposes requested_window + effective_window + truncated + truncation_reason (max_window vs sample_limit). Frontend uses effective_window. Public stays anonymous. Tests: clamp + sample-cap semantics.

### Task 17 — CI security gates and branch protection (SEC-BRANCH-001)
CI jobs: backend tests, vitest, build, Playwright auth smoke, dependency scan, secret scan, static analysis, no-polling guard. Branch protection is operator action (documented, not automated).

### Task 18 — Historical secret remediation (operator + scan)
Secret scan current tree + history; add patterns to scanner config. Rotation + history rewrite are operator actions. PLAN distinguishes removed/rotated/scrubbed/scan-clean; only final state CLOSED.

### Task 19 — Global authorization regression matrix
One table-driven test over all sensitive routes × Guest/Unverified/Standard/Admin. Required in CI.

### Task 20 — Security regression tests for realtime
Guest/unverified cannot auth private channel; standard gets only permitted sensors; unauthorized sensor rejected; admin ok; public sensor realtime only via public path; private never on public channel; downgrade/revocation blocks subsequent auth. /api/broadcasting/auth authoritative.

### Task 21 — Security regression tests for data leakage
Sentinel values (SMTP/device key/ingestion token/bearer). Assert absent from unauthorized responses, logs, serialized models.

### Task 22 — Update PLAN.md security closure truthfully
Closure table Control/Status/Evidence. Operator-evidence items stay open. Keep event-driven Gate 10 work separate from security closure.

## Recommended implementation order
1, 3, 2, 6, 4, 5, 8, 9, 10, 11, 12, 13/14, 7, 15, 16, 17-21, 22.

## Verification
Backend: cd back && php artisan test (focused --filter per task).
Frontend: cd front && npx vitest run && npm run build.
Browser: Playwright register/verify/login/logout/private-read/forbidden-write/admin-write/stale-after-reset, desktop+mobile.
Source: rg localStorage front/src; rg 'api_key|access_token|token_prefix' back; rg 'getMessage' back; rg 'DB::raw|whereRaw' back; rg 'v-html|innerHTML' front/src.

---

## Closure status (updated 2026-09-11 — evidence-based, not optimistic)

Session 1 delivered the P0 authentication/authorization controls. Backend suite: 358 tests, 0 failed, 0 skipped (1270 assertions). Each control below has a regression test and a commit.

| Control | Risk ID | Status | Evidence |
|---|---|---|---|
| Pre-verification private access blocked | SEC-AUTH-001 | PASS | register issues no token; `verified` route group; AuthApiHeadlessTest; commit c72c411 |
| Alert mutation RBAC (API + Blade) | SEC-ALERT-001 | PASS | AlertPolicy admin-only; AlertAuthorizationTest + AlertResolveTransitionTest; commit 076c350 |
| Token abilities enforced on writes | SEC-AUTH-002 | PASS | ability:alerts:resolve,admin middleware; AuthorizationMatrixTest; commit e582f71 |
| Tokens revoked on reset + role change | SEC-TOKEN-001 | PASS | tokens()->delete() in reset + both UserRoleControllers; AuthSecurityTest; commit 968c000 |
| Sensor/Device BOLA policy | SEC-BOLA-001/002 | PASS | ResourceAccessService + SensorPolicy/DevicePolicy; Sensor/DeviceAuthorizationTest; commit 68ed5a3 |
| Private broadcast authorization | SEC-RT-001 | PASS | channels.php delegates to ResourceAccessService; BroadcastAuthorizationTest; commit 68ed5a3 |
| Private topology payload minimization | SEC-BOLA-002/CONFIG-001 | NOT STARTED | Task 5 |
| Browser bearer token → HttpOnly session | SEC-TOKEN-002/003 | NOT STARTED | Task 7 |
| Per-device IoT creds (header-only, hashed) | SEC-IOT-001/002, MODEL-001 | NOT STARTED | Task 8 |
| Rate-limit key hardening | SEC-RATE-001, ENUM-001 | NOT STARTED | Task 9 |
| SMTP secret encrypted at rest | SEC-SECRET-001 | NOT STARTED | Task 10 |
| Log secret/exception redaction | SEC-LOG-001/002 | NOT STARTED | Task 11 |
| Bounded exports/inventory | SEC-EXPORT-001, INV-001 | NOT STARTED | Task 12 |
| Password policy + enumeration | SEC-PASS-001, ENUM-001 | NOT STARTED | Task 13 |
| Registration policy decision | — | NOT STARTED | Task 14 |
| SMTP destination hardening | SEC-SMTP-001 | NOT STARTED | Task 15 |
| Public graph effective-window semantics | SEC-PUBLIC-001 | NOT STARTED | Task 16 |
| CI security gates | SEC-BRANCH-001 | OPERATOR EVIDENCE REQUIRED | Task 17 |
| Branch protection | SEC-BRANCH-001 | OPERATOR EVIDENCE REQUIRED | Task 17 |
| Full-history secret rotation/scrub | — | OPERATOR EVIDENCE REQUIRED | Task 18 |
| Global authz regression matrix | — | PARTIAL | AuthorizationMatrixTest covers alerts; full route×role matrix pending (Task 19) |
| Realtime regression suite | — | PARTIAL | BroadcastAuthorizationTest covers the 3 private channels (Task 20 broader cases pending) |
| Data-leakage sentinel suite | — | NOT STARTED | Task 21 |

**Note:** an implementation pattern was discovered — this codebase has parallel Blade (web) and Api controllers sharing the same domain services (AlertController, UserRoleController, likely Sensor/Device). Every backend authorization task MUST check `routes/web.php` for a twin controller and apply the same guard. Tasks 3 and 6 both required a fix-round for exactly this. Future tasks (5, 8, 12) should audit both surfaces from the start.
