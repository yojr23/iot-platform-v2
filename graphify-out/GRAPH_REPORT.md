# Graph Report - iot-platform-v2  (2026-09-11)

## Corpus Check
- 507 files · ~233,809 words
- Verdict: corpus is large enough that graph structure adds value.

## Summary
- 3058 nodes · 6494 edges · 260 communities (147 shown, 43 thin omitted)
- Extraction: 98% EXTRACTED · 2% INFERRED · 0% AMBIGUOUS · INFERRED: 117 edges (avg confidence: 0.85)
- Token cost: 0 input · 0 output

## Graph Freshness
- Built from commit: `0ad86b7d`
- Run `git rev-parse HEAD` and compare to check if the graph is stale.
- Run `graphify update .` after code changes (no API cost).

## Community Hubs (Navigation)
- main.py
- DomainEventBroadcastConsumerTest
- DeviceType
- SensorMonitorBoard.test.js
- DomainOutboxRelay
- AlertResolved
- run.mjs
- Sensor
- Device
- Illuminate\Http\Request
- Illuminate\Database\Eloquent\Factories\Factory
- Controller
- User
- NavBar.vue
- Illuminate\Http\JsonResponse
- TestCase
- api.php
- script_datos.py
- DashboardMetricsService
- AuthApiHeadlessTest.php
- SensorReading
- SensorsView.vue
- DevicesView.vue
- audit.md — architecture audit and implementation plan
- Final Audit Report — IoT Platform v2
- StoreAlertRuleRequest
- ConfigView.vue
- MATRIZ_TRAZABILIDAD_ICONTEC_ISO.md
- Illuminate\Support\Facades\Log
- CatalogAdminView.vue
- Alert
- SensorReadingProjectionService
- Alert model
- useAlertsRealtime.js
- AlertRulesView.vue
- RegisterController
- Illuminate\Queue\SerializesModels
- DomainEventOutbox
- SensorType
- verify-phase5.mjs
- echo.js
- RawSensorEvent
- SecurityRateLimitTest.php
- back/package.json
- PublicGraphControllerTest
- realtime-lifecycle.mjs
- AlertController
- RawStreamConsumer
- IoT Platform v2 — full migration plan (`refraccion`)
- client.js
- SensorMonitorBoard.vue
- SystemSetting
- DeviceService
- Closure
- dependencies
- Public Graph Visibility — Agentic Execution Plan
- FRONT REBUILD PLAN — Adjustments v1.1
- DomainEventBroadcastConsumer
- scripts
- Illuminate\Database\Seeder
- README.md (IoT Platform v2)
- EventServiceProvider.php
- Illuminate\Console\Command
- AlertRuleController
- RawStreamConsumerTest
- devDependencies
- scripts
- useDeviceStatusRealtime.js
- useSensorRealtime.js
- getApiErrorMessage
- NewSensorReading
- Illuminate\Database\Migrations\Migration
- vue
- RawOutboxRelay
- Gates 6 / 7 / 8 — completion evidence (authoritative)
- composer.json
- Fase 3: Vue 3 SPA Initialization
- verify-phase3.mjs
- static
- Illuminate\Support\Facades\Schema
- Illuminate\Database\Schema\Blueprint
- LabShell.vue
- NewAlertTriggered
- require
- useLabWorkspace
- Pre-Stage-6 Evidence Ledger
- verify-phase7.mjs
- index.js
- Pre-Stage-6 Task 1/3 — `sensor_readings.reading_time` semantics: resolved
- File map
- AlertRuleForm.vue
- ActiveAlertsCard.vue
- graphSeriesQuery.js
- graphZonesProjection.js
- Ingestion Service (Python)
- require-dev
- Lab
- Graph Semantic Zones — AlertRule as threshold source of truth
- IngestionApiTest
- useLabWorkspace.js
- BaseModal.vue
- SensorReadingService
- SINOA Lab Blue Workspace implementation plan for coding agents
- AppLayout.test.js
- SINOA Lab Blue frontend handoff
- DeviceDetailView.vue
- Fase 7B: Operational Validation Before Blade Cleanup
- 16. Development phases and concrete execution gates
- PublicGraphController.php
- config
- DashboardPreference
- PublicGraphSeriesService
- front/package.json
- verify-phase4.mjs
- Stage G1 — Architecture Decision Records
- Stage 0 — Evidence baseline (PLAN.md Stage 0 / audit.md G0)
- event-injection.mjs
- HasEventEnvelope
- Changelog
- Blade Cleanup Readiness Verdict: NO-GO
- Fase 7: Docker and Compose
- Phase 5 Realtime Contract Notes
- 2. Agent execution protocol
- zoneBackgroundPlugin.test.js
- Functional Inventory: Frontend SPA Table
- NewSensorReadingPayloadTest
- Gate 9 Evidence — source-level stabilization
- Lab Blue en dispositivos, sensores y alertas
- run-all.mjs
- 5. Information architecture and responsive layout
- Illuminate\Support\Str
- chartTheme.js
- UserRoleController
- G0D — Reuse & Ownership Freeze
- 10. Data contracts and backend integration
- Backend Ingestion Contract (POST /api/ingestion/events)
- Front/Back Separation Goal
- 14. Preserve brand, UI, and UX in the repository
- 6. Design tokens and visual grammar
- psr-4
- logging.php
- 7. Chart semantics and scientific data rules
- 8. Interaction flows and state machines
- 12. Workspace persistence in detail
- 20. Source preservation map and references
- 9. Frontend architecture and component contracts
- dashboard.js
- autoload-dev
- DeviceFactory
- DeviceStatusLogFactory
- DeviceTypeFactory
- vitest
- SensorDetailView.test.js
- DomainEventOutboxFactory.php
- ADR-009: SPA Auth Uses Sanctum Bearer Tokens Initially
- RawEventOutboxFactory.php
- RawSensorEventFactory
- SensorReadingFactory
- MigrationIntegrityTest
- SensorTypeFactory
- 11. Race-safe requests and live data
- 13. Alerts, scientific inspector, and secondary modules
- extra
- 15. Performance and accessibility
- app.blade.php
- console.php
- User model
- 17. Verification matrix and definition of done
- 4. Brand identity and product voice
- skills-research.md
- legacy.js
- ADR-005: Separate Environment Variables
- Functional Inventory: Backend API Table
- sanctum.php
- channels.php
- __init__.py
- Redis (Sensor Reading Cache)
- GET /api/dashboard/public (public compatibility endpoint)
- GET /api/internal/metrics/api-performance
- front/vite.config.js
- Backend Refactor Rules
- Docker Refactor Rules
- Frontend Refactor Rules
- General Refactor Rules
- Medium Risks
- Functional State: API
- Functional State: Auth
- Functional State: DB (MySQL)
- Functional State: Realtime/Pusher
- SEC-005: Testing APP_KEY Committed in .env.testing

## God Nodes (most connected - your core abstractions)
1. `Sensor` - 183 edges
2. `User` - 170 edges
3. `Device` - 150 edges
4. `TestCase` - 137 edges
5. `SensorReading` - 85 edges
6. `Alert` - 82 edges
7. `Controller` - 69 edges
8. `AlertRule` - 68 edges
9. `SensorType` - 68 edges
10. `getApiErrorMessage()` - 61 edges

## Surprising Connections (you probably didn't know these)
- `graphify-html-export agent` --semantically_similar_to--> `CLAUDE.md (repo guidance)`  [AMBIGUOUS] [semantically similar]
  .claude/agents/graphify-html-export.md → CLAUDE.md
- `audit.md — architecture audit and implementation plan` --semantically_similar_to--> `INGESTION_PIPELINE.md (Raw-First)`  [INFERRED] [semantically similar]
  audit.md → docs/INGESTION_PIPELINE.md
- `Two parallel ingestion paths (legacy + raw)` --semantically_similar_to--> `Raw-first ingestion pipeline architecture`  [INFERRED] [semantically similar]
  CLAUDE.md → docs/INGESTION_PIPELINE.md
- `DOCUMENTACION_PROYECTO.md (documentacion tecnica formal)` --semantically_similar_to--> `ANALISIS_PROYECTO.md (analisis tecnico y de cumplimiento)`  [INFERRED] [semantically similar]
  DOCUMENTACION_PROYECTO.md → ANALISIS_PROYECTO.md
- `Ingestion Service (Python)` --semantically_similar_to--> `script_datos.py (Python IoT Simulator)`  [INFERRED] [semantically similar]
  ingestion_service/README.md → memory/02-current-audit.md

## Import Cycles
- None detected.

## Hyperedges (group relationships)
- **Fase 6B Blade Cleanup Gating Decision** — memory_14_blade_cleanup_readiness_verdict_nogo, memory_13_manual_validation_checklist_checklist, memory_12_stable_blade_comparison_missing_features, memory_06_pending_risks_operational_risks, memory_08_refactor_checklist_fase_6b [EXTRACTED 1.00]
- **Sensor reading to alert notification pipeline** — code_back_app_http_controllers_api_sensorapicontroller, code_back_app_observers_sensorreadingobserver, code_back_app_models_sensorreading, code_back_app_services_alerts_alertservice, code_back_app_observers_alertobserver, code_back_app_services_notifications_notificationservice, code_back_app_events_newalerttriggered, code_back_app_mail_dangeralertmail [EXTRACTED 1.00]
- **Vue realtime/polling remediation targets (audit.md)** — code_front_src_components_layout_applayout, front_src_components_dashboard_activealertscard, front_src_components_dashboard_sensormonitorboard, code_front_src_realtime_echo, front_src_realtime_usealertsrealtime, front_src_realtime_usesensorrealtime [EXTRACTED 1.00]
- **ICONTEC/ISO normative compliance document set** — docs_icontec_compliance, docs_matriz_trazabilidad_icontec_iso, docs_evidencia_cumplimiento_codigo_estructura, docs_procedimiento_auditoria_normativa, docs_referencias_icontec, docs_plantilla_trabajo_icontec, documentacion_proyecto, analisis_proyecto [EXTRACTED 1.00]
- **Front/Back Migration Phase Roadmap (Fase 0 to Fase 8A)** — memory_05_migration_log_fase_0, memory_05_migration_log_fase_1, memory_05_migration_log_fase_2, memory_05_migration_log_fase_3, memory_05_migration_log_fase_4, memory_05_migration_log_fase_5, memory_05_migration_log_fase_6, memory_05_migration_log_fase_7, memory_05_migration_log_fase_7b, memory_05_migration_log_fase_8a [EXTRACTED 1.00]
- **SPA Auth & Security Boundary Design** — memory_01_architecture_decisions_adr_005_env_separation, memory_01_architecture_decisions_adr_006_api_only_communication, memory_01_architecture_decisions_adr_009_sanctum_bearer_auth, memory_15_security_review_sec_003_localstorage_bearer_token [INFERRED 0.85]

## Communities (260 total, 43 thin omitted)

### Community 0 - "main.py"
Cohesion: 0.07
Nodes (49): BaseModel, Client, BackendClient, BackendClientError, Any, Raised when backend ingestion endpoint cannot be reached or rejects payload., configure_logging(), main() (+41 more)

### Community 1 - "DomainEventBroadcastConsumerTest"
Cohesion: 0.22
Nodes (3): DomainEventBroadcastConsumerTest, Connection, RedisManager

### Community 2 - "DeviceType"
Cohesion: 0.09
Nodes (6): DeviceTypeController, ConfigController, DeviceController, DeviceTypeController, DeviceType, Illuminate\Support\Facades\Route

### Community 3 - "SensorMonitorBoard.test.js"
Cohesion: 0.25
Nodes (8): devices, fetchWindow, flush(), mountBoard(), mountedApps, resultForQuery, subscribeSensor, unsubscribeSensor

### Community 4 - "DomainOutboxRelay"
Cohesion: 0.15
Nodes (6): DomainEventPublisher, DomainOutboxRelay, DomainOutboxRelayTest, EventPublisherXaddShapeTest, SensorDataControllerTest, Mockery

### Community 5 - "AlertResolved"
Cohesion: 0.13
Nodes (9): AlertResolved, VersionedDomainEvent, DeviceCommunicationReceived, UpdateDeviceLastCommunication, Illuminate\Broadcasting\InteractsWithSockets, Illuminate\Broadcasting\PresenceChannel, Illuminate\Broadcasting\PrivateChannel, Illuminate\Contracts\Broadcasting\ShouldBroadcastNow (+1 more)

### Community 6 - "run.mjs"
Cohesion: 0.06
Nodes (33): /src/stores/alerts.js, alert(), countFor(), device(), deviceSensor(), installAuth(), json(), mockApi() (+25 more)

### Community 7 - "Sensor"
Cohesion: 0.06
Nodes (11): AlertRuleController, SensorController, AlertRule, Sensor, AlertRuleCascadeDeleteTest, DangerAlertEmailTest, SensorGraphZonesTest, AlertUniqueConstraintTest (+3 more)

### Community 8 - "Device"
Cohesion: 0.06
Nodes (8): Device, ApiRoutingRegressionTest, DeviceApiKeyVisibilityTest, DeviceApiStatusUpdateTest, DeviceSensorListLatestReadingTest, Gate6PublicSurfaceTest, SensorApiControllerTest, DashboardMetricsServiceTest

### Community 9 - "Illuminate\Http\Request"
Cohesion: 0.07
Nodes (12): DeviceApiController, SensorApiController, SensorDataController, SensorTypeController, EmailConfigController, DeviceResource, DeviceStatusSnapshotResource, SensorResource (+4 more)

### Community 10 - "Illuminate\Database\Eloquent\Factories\Factory"
Cohesion: 0.21
Nodes (5): AlertFactory, AlertRuleFactory, LabFactory, SensorFactory, Illuminate\Database\Eloquent\Factories\Factory

### Community 11 - "Controller"
Cohesion: 0.09
Nodes (17): HealthController, ProfileController, ConfirmPasswordController, ForgotPasswordController, LoginController, ResetPasswordController, VerificationController, Controller (+9 more)

### Community 12 - "User"
Cohesion: 0.05
Nodes (14): User, AdminAccessTest, AuthApiHeadlessTest, BroadcastChannelAuthorizationTest, ConfigGeneralUpdateTest, DeviceApiPaginationTest, SecurityAccessControlTest, SecurityPrivilegeEscalationTest (+6 more)

### Community 13 - "NavBar.vue"
Cohesion: 0.14
Nodes (12): adminItems, alertsStore, authStore, laboratoryItems, navItems, realtimeStatusClass, realtimeStatusLabel, router (+4 more)

### Community 14 - "Illuminate\Http\JsonResponse"
Cohesion: 0.09
Nodes (8): AlertFeedController, AuthApiController, ConfigController, EmailConfigController, DashboardPreferenceController, Illuminate\Auth\Events\PasswordReset, Illuminate\Auth\Events\Verified, Illuminate\Http\JsonResponse

### Community 15 - "TestCase"
Cohesion: 0.05
Nodes (19): AlertRuleNameTest, AlertTransportAuthorizationTest, ApiAuthTokenTest, ConfigSystemInfoTest, DataIntegrityDuplicationTest, DeviceCreateTest, DeviceUpdateTest, ExampleTest (+11 more)

### Community 16 - "api.php"
Cohesion: 0.09
Nodes (11): DashboardPreferenceController, IngestionController, InternalMetricsController, MetricsController, UserRoleController, MetricsController, TrackApiPerformance, ApiMetricsService (+3 more)

### Community 17 - "script_datos.py"
Cohesion: 0.12
Nodes (20): build_qc_checksum(), build_sensor_payload(), device_sender_worker(), get_api_key(), get_location(), get_node_id(), get_sensor_key(), get_sensors() (+12 more)

### Community 19 - "AuthApiHeadlessTest.php"
Cohesion: 0.09
Nodes (13): MailMessage, ResetPasswordNotification, MailMessage, VerifyEmailNotification, PasswordResetTest, RegistrationEmailVerificationTest, Illuminate\Auth\Notifications\ResetPassword, Illuminate\Auth\Notifications\VerifyEmail (+5 more)

### Community 20 - "SensorReading"
Cohesion: 0.16
Nodes (4): SensorReading, AlertService, RuleToGraphZones, Illuminate\Support\Collection

### Community 21 - "SensorsView.vue"
Cohesion: 0.06
Nodes (48): getSensorTypes(), createSensor(), deleteSensor(), exportSensorReadings(), getSensor(), getSensorLatestReadings(), getSensorReadings(), getSensors() (+40 more)

### Community 22 - "DevicesView.vue"
Cohesion: 0.07
Nodes (37): getDeviceTypes(), getLabs(), createDevice(), deleteDevice(), getDevices(), updateDevice(), applyDevicesPage(), authStore (+29 more)

### Community 23 - "audit.md — architecture audit and implementation plan"
Cohesion: 0.10
Nodes (29): POST /api/ingestion/events, audit.md — architecture audit and implementation plan, graphify-html-export agent, iot-back-implementador agent, CLAUDE.md (repo guidance), AlertController (API), IngestionController, RawSensorEventPublisher (+21 more)

### Community 24 - "Final Audit Report — IoT Platform v2"
Cohesion: 0.07
Nodes (26): C1: `.env.testing` committed with real APP_KEY, C2: Leaked env backup — CLOSED, CRITICAL (P0) — Fix Immediately, Execution GAPs (Noted, Cannot Fix on This Machine), Final Audit Report — IoT Platform v2, H1: SMTP password stored plaintext in `system_settings` table, H2: Default DB credentials in docker-compose.yml and .env.example, H3: No CI/CD pipeline found (+18 more)

### Community 25 - "StoreAlertRuleRequest"
Cohesion: 0.11
Nodes (7): StoreAlertRuleRequest, StoreRawIngestionEventRequest, UpdateAlertConfigRequest, UpdateEmailConfigRequest, UpdateGeneralConfigRequest, Illuminate\Foundation\Http\FormRequest, Illuminate\Validation\Validator

### Community 26 - "ConfigView.vue"
Cohesion: 0.09
Nodes (38): getValidationErrors(), unwrapData(), getAlertConfig(), getEmailConfig(), getGeneralConfig(), getRuntimeConfig(), getSystemInfo(), testEmailConfig() (+30 more)

### Community 27 - "MATRIZ_TRAZABILIDAD_ICONTEC_ISO.md"
Cohesion: 0.26
Nodes (16): ANALISIS_PROYECTO.md (analisis tecnico y de cumplimiento), EnsureUserIsAdmin middleware, TrackApiPerformance middleware, AppServiceProvider (rate limiters), DashboardMetricsService, DashboardMetricsServiceTest constructor non-conformity, ICONTEC/ISO alignment (not certification) declaration policy, NTC 1486 (presentacion de trabajos escritos) (+8 more)

### Community 28 - "Illuminate\Support\Facades\Log"
Cohesion: 0.09
Nodes (17): Carbon\Carbon, Dotenv\Dotenv, Illuminate\Database\QueryException, Illuminate\Foundation\Configuration\Exceptions, Illuminate\Foundation\Configuration\Middleware, Illuminate\Foundation\Testing\TestCase, Illuminate\Http\Response, Illuminate\Support\Carbon (+9 more)

### Community 29 - "CatalogAdminView.vue"
Cohesion: 0.10
Nodes (26): createDeviceType(), createLab(), createSensorType(), deleteDeviceType(), deleteLab(), deleteSensorType(), updateDeviceType(), updateLab() (+18 more)

### Community 30 - "Alert"
Cohesion: 0.09
Nodes (5): AlertController, Alert, AlertLifecycleService, AlertEmailTest, AlertResolveTransitionTest

### Community 32 - "Alert model"
Cohesion: 0.12
Nodes (18): DeviceStatusUpdated event, NewAlertTriggered event, NewSensorReading event, EmailConfigController, SensorController, DangerAlertMail, Alert model, AlertObserver (+10 more)

### Community 33 - "useAlertsRealtime.js"
Cohesion: 0.10
Nodes (37): alertsStore, authStore, deviceStatusesStore, route, startGlobalAlerts(), stopGlobalAlerts(), stopGlobalDeviceStatus(), { subscribeAlerts, unsubscribeAlerts } (+29 more)

### Community 34 - "AlertRulesView.vue"
Cohesion: 0.11
Nodes (23): createAlertRule(), deleteAlertRule(), getAlertRuleMetadata(), getAlertRules(), updateAlertRule(), closeModal(), deletingId, error (+15 more)

### Community 35 - "RegisterController"
Cohesion: 0.32
Nodes (3): RegisterController, Illuminate\Foundation\Auth\RegistersUsers, Illuminate\Support\Facades\Hash

### Community 36 - "Illuminate\Queue\SerializesModels"
Cohesion: 0.19
Nodes (13): Throwable, QueueSmokeJob, Throwable, RelayDomainOutboxJob, Throwable, RelayRawOutboxJob, Throwable, SendDangerAlertEmailJob (+5 more)

### Community 37 - "DomainEventOutbox"
Cohesion: 0.11
Nodes (8): DeviceStatusLog, DomainEventOutbox, RawEventOutbox, DeviceShowStatusLogsTest, DeviceStatusChangeTransitionTest, DeviceStatusSnapshotTest, Illuminate\Database\Eloquent\Factories\HasFactory, Illuminate\Database\Eloquent\Model

### Community 38 - "SensorType"
Cohesion: 0.10
Nodes (6): SensorTypeController, SensorType, AlertRuleValidationTest, AlertTriggerTransitionTest, SecuritySqlInjectionTest, SensorPublicMonitoringAdminTest

### Community 39 - "verify-phase5.mjs"
Cohesion: 0.11
Nodes (15): activeAlertsCard, alertsRealtime, alertsStore, alertsView, alertToast, appLayout, echo, envExample (+7 more)

### Community 40 - "echo.js"
Cohesion: 0.18
Nodes (15): booleanEnv(), createEcho(), disconnectEcho(), getEcho(), getEchoConfig(), handleAuthChange(), ADR-0002, notify() (+7 more)

### Community 41 - "RawSensorEvent"
Cohesion: 0.33
Nodes (3): RawSensorEvent, RawReadingNormalizerTest, RawSensorEventIdempotencyTest

### Community 42 - "SecurityRateLimitTest.php"
Cohesion: 0.15
Nodes (6): AppServiceProvider, ViewServiceProvider, SecurityRateLimitTest, Illuminate\Cache\RateLimiting\Limit, Illuminate\Support\Facades\RateLimiter, Illuminate\Support\ServiceProvider

### Community 43 - "back/package.json"
Cohesion: 0.05
Nodes (36): dependencies, chart.js, laravel-echo, lightweight-charts, pusher-js, devDependencies, axios, bootstrap (+28 more)

### Community 44 - "PublicGraphControllerTest"
Cohesion: 0.14
Nodes (3): PublicGraphControllerTest, ReadingTimeSemanticsTest, CarbonImmutable

### Community 45 - "realtime-lifecycle.mjs"
Cohesion: 0.28
Nodes (6): __dirname, fakeEchoPlugin(), main(), record(), results, root

### Community 47 - "RawStreamConsumer"
Cohesion: 0.24
Nodes (4): UsesRawRedisCommands, RawStreamConsumer, Illuminate\Redis\Connections\Connection, RuntimeException

### Community 48 - "IoT Platform v2 — full migration plan (`refraccion`)"
Cohesion: 0.10
Nodes (20): Cross-cutting — do before any prod-facing config, CURRENT STATUS — Gates 6/7/8 code-complete (2026-09-10), IoT Platform v2 — full migration plan (`refraccion`), Pre-Stage-6 execution corrections v1.3 — authoritative, Risk gates that can change this plan (`audit.md §22, §24`), Sequencing, Stage 0 — Close the evidence baseline (audit G0 / TASK-001), Stage 10 — Reliability, observability, retire legacy (audit P10 / TASK-012) (+12 more)

### Community 49 - "client.js"
Cohesion: 0.08
Nodes (29): AUTH_TOKEN_KEY, clearStoredToken(), getStoredToken(), setStoredToken(), getGraphBootstrap(), authStore, api, apps (+21 more)

### Community 50 - "SensorMonitorBoard.vue"
Cohesion: 0.07
Nodes (23): alerts, {
    auth,
    widgets,
    selectedId,
    selected,
    editing,
    saveState,
    message,
    removed,
    connection,
    catalog,
    sensor,
    points,
    viewModel,
    activeData,
    activeError,
    history,
    dirty,
    add,
    select,
    changeDevice,
    changeSensor,
    changeRange,
    remove,
    undo,
    move,
    save,
    load,
}, confirmAdd(), critical, dialog, displayViewModel, dragged, drop() (+15 more)

### Community 51 - "SystemSetting"
Cohesion: 0.07
Nodes (10): DangerAlertMail, SystemSetting, NotificationService, AlertEmailAsyncDeliveryTest, Phase2ApiEndpointsTest, NotificationServiceRateLimitTest, SystemSettingTest, Illuminate\Mail\Mailable (+2 more)

### Community 52 - "DeviceService"
Cohesion: 0.14
Nodes (3): DeviceService, DomainEventRecorder, DeviceServiceTest

### Community 53 - "Closure"
Cohesion: 0.32
Nodes (4): EnsureIngestionToken, EnsureUserIsAdmin, Closure, Illuminate\Support\Facades\Auth

### Community 54 - "dependencies"
Cohesion: 0.17
Nodes (12): dependencies, axios, bootstrap, chart.js, @fontsource/inter, @fontsource/jetbrains-mono, laravel-echo, pinia (+4 more)

### Community 55 - "Public Graph Visibility — Agentic Execution Plan"
Cohesion: 0.12
Nodes (16): Acceptance Matrix, Contract Freeze, Global Constraints, Public Graph Visibility — Agentic Execution Plan, Repository Change Map, Rollback, Task 0: Close the preflight evidence before changing a public contract, Task 1: Persist and administratively manage explicit visibility (+8 more)

### Community 56 - "FRONT REBUILD PLAN — Adjustments v1.1"
Cohesion: 0.12
Nodes (15): Architecture invariant — public graph only, Closed decision — explicit per-sensor public graph visibility, FRONT REBUILD PLAN — Adjustments v1.1, Mandatory adjustments, PLAN.md reconciliation — Stages 6 through 10, Revised Stage 6–10 sequence, Stage 10 — reliability, security, and rollout proof, Stage 6 — public graph cutover and Lab Blue graph foundation (+7 more)

### Community 58 - "scripts"
Cohesion: 0.29
Nodes (7): scripts, dev, post-autoload-dump, post-create-project-cmd, post-root-package-install, post-update-cmd, test

### Community 59 - "Illuminate\Database\Seeder"
Cohesion: 0.14
Nodes (8): AlertRuleSeeder, AlertSeeder, DeviceTypeSeeder, SensorTypeSeeder, SystemSettingsSeeder, UserSeeder, Illuminate\Database\Console\Seeds\WithoutModelEvents, Illuminate\Database\Seeder

### Community 60 - "README.md (IoT Platform v2)"
Cohesion: 0.17
Nodes (15): GET /api/alerts/active, GET /api/iot/sensors, POST /api/sensors/{sensor}/readings, iot-front-implementador agent, PurgeFutureSensorReadings command, DeviceApiController, SensorApiController, SensorReading model (+7 more)

### Community 61 - "EventServiceProvider.php"
Cohesion: 0.21
Nodes (6): AlertObserver, SensorReadingObserver, EventServiceProvider, Illuminate\Auth\Events\Registered, Illuminate\Auth\Listeners\SendEmailVerificationNotification, Illuminate\Foundation\Support\Providers\EventServiceProvider

### Community 62 - "Illuminate\Console\Command"
Cohesion: 0.17
Nodes (6): ConsumeDomainEvents, ConsumeRawEvents, InspectReadingTimeSemantics, PurgeFutureSensorReadings, RelayDomainOutbox, Illuminate\Console\Command

### Community 63 - "AlertRuleController"
Cohesion: 0.22
Nodes (3): AlertRuleController, UpdateAlertRuleRequest, AlertRuleResource

### Community 64 - "RawStreamConsumerTest"
Cohesion: 0.37
Nodes (3): Connection, RedisManager, RawStreamConsumerTest

### Community 65 - "devDependencies"
Cohesion: 0.25
Nodes (8): devDependencies, jsdom, @playwright/test, sass, vite, @vitejs/plugin-vue, vitest, ws

### Community 66 - "scripts"
Cohesion: 0.14
Nodes (14): scripts, audit:baseline, audit:events, audit:network, build, dev, dev:demo, preview (+6 more)

### Community 67 - "useDeviceStatusRealtime.js"
Cohesion: 0.22
Nodes (15): getDeviceStatusSnapshot(), onConnectionStateChange(), DEVICE_STATUS_CHANNEL, DEVICE_STATUS_EVENT, DEVICE_STATUS_EVENT_CLASS, error, eventPayload(), fetchStatusSnapshot() (+7 more)

### Community 68 - "useSensorRealtime.js"
Cohesion: 0.15
Nodes (19): graphPointToReading(), subscribeToSensor(), channelKey(), getChannelRefCount(), leaveChannelName(), listenOnChannel(), refCounts, echoMock (+11 more)

### Community 69 - "getApiErrorMessage"
Cohesion: 0.09
Nodes (31): getActiveAlerts(), getAlert(), getAlerts(), getUnresolvedAlerts(), resolveAlert(), resolveAllAlerts(), getApiErrorMessage(), options (+23 more)

### Community 70 - "NewSensorReading"
Cohesion: 0.10
Nodes (6): DeviceStatusUpdated, NewSensorReading, EventEnvelopeTest, Illuminate\Broadcasting\Channel, Illuminate\Redis\RedisManager, Illuminate\Support\Facades\Event

### Community 71 - "Illuminate\Database\Migrations\Migration"
Cohesion: 0.20
Nodes (3): UpdateAlertRulesTable, UpdateAlertRulesSeverity, Illuminate\Database\Migrations\Migration

### Community 72 - "vue"
Cohesion: 0.05
Nodes (50): getMetrics(), props, severityClass, alertsStore, currentAlert, currentDevice, currentMessage, currentSeverity (+42 more)

### Community 73 - "RawOutboxRelay"
Cohesion: 0.23
Nodes (3): RelayRawOutbox, RawOutboxRelay, RawSensorEventPublisher

### Community 74 - "Gates 6 / 7 / 8 — completion evidence (authoritative)"
Cohesion: 0.13
Nodes (14): Authenticated save state machine, Commands to run on the operator machine, GATE 6 — source closure (what was fixed), GATE 7 — what changed, GATE 8 — what changed, Gates 6 / 7 / 8 — completion evidence (authoritative), Guest draft → memory-only, OPEN / GAP (operator machine) (+6 more)

### Community 75 - "composer.json"
Cohesion: 0.22
Nodes (8): description, keywords, license, minimum-stability, name, prefer-stable, $schema, type

### Community 76 - "Fase 3: Vue 3 SPA Initialization"
Cohesion: 0.18
Nodes (11): Front SPA HTML Entrypoint, ADR-001: Keep A Monorepo Initially, ADR-008: Frontend Framework (Vue 3 + Bootstrap 5, proposed), Vite (laravel-vite-plugin), Migration Hygiene Rules, Phase 3 Frontend Consumer Notes, Fase 0: Read-only Audit and Real Contract, Fase 1: Headless Auth (Sanctum Bearer) (+3 more)

### Community 77 - "verify-phase3.mjs"
Cohesion: 0.18
Nodes (10): dependencies, emailVerification, envExample, missing, missingDependencies, packageJson, requiredDependencies, requiredFiles (+2 more)

### Community 81 - "LabShell.vue"
Cohesion: 0.16
Nodes (12): icons, paths, props, alerts, auth, links, logout(), open (+4 more)

### Community 83 - "require"
Cohesion: 0.22
Nodes (9): require, laravel/framework, laravel/sanctum, laravel/tinker, laravel/ui, php, predis/predis, pusher/pusher-php-server (+1 more)

### Community 84 - "useLabWorkspace"
Cohesion: 0.33
Nodes (13): useLabWorkspace(), add(), changeDevice(), changeRange(), changeSensor(), init(), load(), mark() (+5 more)

### Community 85 - "Pre-Stage-6 Evidence Ledger"
Cohesion: 0.15
Nodes (12): Commit-review correction — 9 September 2026, Pre-Stage-6 Evidence Ledger, PRE-STAGE-6 GATE ledger, Task 0 — Gate S5-R evidence, Task 1 — Vue SPA = single public dashboard entry point  ✅ code-complete, Task 2 — Anonymous/non-session route classification  ✅ code-complete, Task 3 — Reading-time semantics  ⛔ BLOCKS STAGE 6 (classification C), Task 4 — Graph-range composite index  ✅ code-complete (EXPLAIN pending) (+4 more)

### Community 86 - "verify-phase7.mjs"
Cohesion: 0.22
Nodes (8): __dirname, envExample, __filename, forbiddenKeys, frontDockerfile, frontRoot, repoRoot, requiredFiles

### Community 87 - "index.js"
Cohesion: 0.05
Nodes (40): forgotPassword(), register(), resendVerification(), resetPassword(), verifyEmail(), inputId, props, app (+32 more)

### Community 88 - "Pre-Stage-6 Task 1/3 — `sensor_readings.reading_time` semantics: resolved"
Cohesion: 0.15
Nodes (12): GAPs — exact commands to close each one, New-ingestion input semantics — HTTP boundary validation (unchanged by this task), Pre-Stage-6 Task 1/3 — `sensor_readings.reading_time` semantics: resolved, Reason 1 — input-type asymmetry inside `SensorReadingService::createReading()` (SQLite-provable, GAP-2 to confirm), Reason 2 — unpinned MySQL `TIMESTAMP` session timezone (MySQL-only, GAP-1 to confirm), Resolution (Pre-Stage-6 Task 1), Status of this document — historical context (superseded by "Resolution" above), The A/B/C classification and its evidence — historical (resolved, see above) (+4 more)

### Community 89 - "File map"
Cohesion: 0.15
Nodes (12): Explicitly deferred, File map, Gate S5-R Closure Implementation Plan, Gate S5-R exit checklist, Global Constraints, Task 1: Restore the committed latest-readings projection through one service, Task 2: Deliver sensor.reading.created to browsers exactly once, Task 3: Make RawReadingNormalizer use the same creation owner (+4 more)

### Community 90 - "AlertRuleForm.vue"
Cohesion: 0.16
Nodes (12): emit, fieldError(), filteredSensors, form, nullableNumber(), props, submit(), validationMessage() (+4 more)

### Community 91 - "ActiveAlertsCard.vue"
Cohesion: 0.20
Nodes (9): alerts, alertsStore, count, error, loading, flush(), getActiveAlerts, mountActiveAlertsCard() (+1 more)

### Community 92 - "graphSeriesQuery.js"
Cohesion: 0.17
Nodes (8): getGraphSeries(), toWindowParam(), connectionWatchers, echoMock, resyncWatchers, activeControllersByConsumer, buildGraphQueryKey(), useGraphSeriesQueryStore

### Community 93 - "graphZonesProjection.js"
Cohesion: 0.27
Nodes (11): boundariesFromRegions(), buildZonesViewModel(), KNOWN_SEVERITIES, moreSevere(), normalizeBoundaries(), normalizeRegions(), normalizeSeverity(), PRECEDENCE (+3 more)

### Community 94 - "Ingestion Service (Python)"
Cohesion: 0.22
Nodes (9): Ingestion Service (Python), pydantic (Python library), pytest (Python library), python-dotenv (Python library), requests (Python library), script_datos.py (Python IoT Simulator), Operational Risks, SEC-002: Global Legacy IoT API Key (+1 more)

### Community 95 - "require-dev"
Cohesion: 0.25
Nodes (8): require-dev, fakerphp/faker, laravel/pail, laravel/pint, laravel/sail, mockery/mockery, nunomaduro/collision, phpunit/phpunit

### Community 96 - "Lab"
Cohesion: 0.15
Nodes (4): LabController, LabController, Lab, DatabaseSeeder

### Community 97 - "Graph Semantic Zones — AlertRule as threshold source of truth"
Cohesion: 0.17
Nodes (11): Architecture: one normalizer, reuse AlertService scoping, Boundary-semantics — FROZEN (GRAPH-004), Done when, Graph Semantic Zones — AlertRule as threshold source of truth, Info visualization — FROZEN (GRAPH-006), Issue backlog, Public exposure policy (GRAPH-002 / DOC-002), Semantic mapping (frozen) (+3 more)

### Community 99 - "useLabWorkspace.js"
Cohesion: 0.33
Nodes (7): composeGraphSeries(), computeStats(), idCompare(), toPoint(), spark(), ranges, points()

### Community 100 - "BaseModal.vue"
Cohesion: 0.31
Nodes (7): close(), dialogEl, emit, focusableElements(), onKeydown(), props, titleId

### Community 101 - "SensorReadingService"
Cohesion: 0.29
Nodes (3): RawReadingNormalizer, SensorReadingService, DateTimeInterface

### Community 102 - "SINOA Lab Blue Workspace implementation plan for coding agents"
Cohesion: 0.20
Nodes (9): 0.1 Approved guest mode and public graph boundary, 18. Open decisions and dependency handling, 19. Reusable coding-agent kickoff prompt, 1.1 Execution boundaries, 1. Mission and source authority, 3. Product requirements and traceability, SINOA Lab Blue Workspace implementation plan for coding agents, v1.2 repository-audit corrections — authoritative for implementation (+1 more)

### Community 103 - "AppLayout.test.js"
Cohesion: 0.22
Nodes (8): flush(), getActiveAlerts, getPublicConfig, getRuntimeConfig, mountAppLayout(), mountedApps, subscribeAlerts, unsubscribeAlerts

### Community 104 - "SINOA Lab Blue frontend handoff"
Cohesion: 0.22
Nodes (8): Further implementation priorities, Implementation ownership, Intentional differences from the image, Repository and delivery, Run locally, SINOA Lab Blue frontend handoff, Verification, Visual hierarchy and brand

### Community 105 - "DeviceDetailView.vue"
Cohesion: 0.07
Nodes (34): apiClient, getDevice(), getDeviceSensors(), updateDeviceStatus(), getProfile(), getUsers(), updateUserRole(), asArray() (+26 more)

### Community 106 - "Fase 7B: Operational Validation Before Blade Cleanup"
Cohesion: 0.29
Nodes (8): Phase 8A API Additions (CRUD/Admin), Fase 7B: Operational Validation Before Blade Cleanup, Fase 8A: Admin CRUD/Catalog SPA Migration, QueueSmokeJob (queue infrastructure smoke test), High Risks, Fase 6B: Blade Legacy Cleanup and Production SPA Adjustment (Pending), Fase 8: Final Cleanup (Pending), Session Progress: Fase 8A Work Log

### Community 107 - "16. Development phases and concrete execution gates"
Cohesion: 0.22
Nodes (9): 16. Development phases and concrete execution gates, Phase 0 — Repository and contract diagnosis, Phase 1 — Brand foundations and responsive shell, Phase 2 — One sensor vertical slice, Phase 3 — Workspace editing and persistence, Phase 4 — Alerts and scientific operation, Phase 5 — Mobile and accessibility completion, Phase 6 — Risk-based verification (+1 more)

### Community 108 - "PublicGraphController.php"
Cohesion: 0.27
Nodes (3): PublicGraphController, PublicGraphVisibility, Illuminate\Database\Eloquent\Builder

### Community 109 - "config"
Cohesion: 0.29
Nodes (7): pestphp/pest-plugin, php-http/discovery, config, allow-plugins, optimize-autoloader, preferred-install, sort-packages

### Community 112 - "front/package.json"
Cohesion: 0.12
Nodes (16): allowScripts, esbuild@0.21.5, axios, bootstrap, chart.js, laravel-echo, pusher-js, sass (+8 more)

### Community 113 - "verify-phase4.mjs"
Cohesion: 0.29
Nodes (6): missing, navbar, requiredFiles, root, router, srcFilesToCheck

### Community 114 - "Stage G1 — Architecture Decision Records"
Cohesion: 0.25
Nodes (7): ADR-1 — Durable publication, ADR-2 — Client recovery, ADR-3 — DLQ / quarantine, ADR-4 — Internal realtime fan-out, Deployment / crash matrix / rollback, Spike evidence (ADR-1/ADR-3), Stage G1 — Architecture Decision Records

### Community 115 - "Stage 0 — Evidence baseline (PLAN.md Stage 0 / audit.md G0)"
Cohesion: 0.25
Nodes (7): 0.7 — Event-injection harness (`front/.audit-e2e/event-injection.mjs`), 0.8 — No-polling network assertion (`front/.audit-e2e/network-assertion.mjs`), Commands run (for reproduction), Honesty notes / what was NOT verified, M01–M09 verdicts, Stage 0 — Evidence baseline (PLAN.md Stage 0 / audit.md G0), What changed this session (all inside my owned paths)

### Community 116 - "event-injection.mjs"
Cohesion: 0.32
Nodes (6): __dirname, fakeEchoPlugin(), main(), record(), results, root

### Community 118 - "Changelog"
Cohesion: 0.29
Nodes (6): [0.1.0] - 2026-09-09, Added, Added, Changelog, Fixed, [Unreleased]

### Community 119 - "Blade Cleanup Readiness Verdict: NO-GO"
Cohesion: 0.29
Nodes (7): ADR-003: Do Not Move Blade Directly To /front, Blade Views (Legacy UI), Functional State: Blade Legacy, Legacy Views Still Dependent on Blade, Lost or Not-Found Features, Manual Validation Checklist (Not Yet Executed), Blade Cleanup Readiness Verdict: NO-GO

### Community 120 - "Fase 7: Docker and Compose"
Cohesion: 0.33
Nodes (7): ADR-007: Docker Per Service, Docker/Docker Compose (absent at audit time), Phase 6 Physical Separation Notes, Phase 7 Docker Consumer Notes, Fase 6: Physical Separation Into /back, Fase 7: Docker and Compose, Functional State: Docker

### Community 121 - "Phase 5 Realtime Contract Notes"
Cohesion: 0.29
Nodes (7): Pusher / Laravel Echo Realtime, Broadcast Event: NewAlertTriggered (channel alerts), Broadcast Event: NewSensorReading (channel sensor.{id}), Phase 4 Frontend Consumer Notes, Phase 5 Realtime Contract Notes, Fase 4: Progressive Screen Migration, Fase 5: Realtime (Echo/Pusher) Integration

### Community 122 - "2. Agent execution protocol"
Cohesion: 0.29
Nodes (7): 2.1 Discover before changing, 2.2 Work cycle and durable progress, 2.3 Required completion report, 2.4 Verified repository baseline and public-realtime ownership, 2. Agent execution protocol, Guest versus authenticated capability model, Public-scope security invariant

### Community 123 - "zoneBackgroundPlugin.test.js"
Cohesion: 0.38
Nodes (5): computeBoundaryY(), computeRegionRect(), chartArea, linearScale, zoneBackgroundPlugin

### Community 124 - "Functional Inventory: Frontend SPA Table"
Cohesion: 0.67
Nodes (3): Functional State: Frontend, Functional Inventory: Frontend SPA Table, Partially Migrated Features

### Community 126 - "Gate 9 Evidence — source-level stabilization"
Cohesion: 0.33
Nodes (5): Automated evidence, Browser evidence — 2026-09-10 (Playwright against the live Docker stack), Deliberately not claimed, Gate 9 Evidence — source-level stabilization, What is verified

### Community 127 - "Lab Blue en dispositivos, sensores y alertas"
Cohesion: 0.33
Nodes (5): Hallazgo previo separado, Implementación, Lab Blue en dispositivos, sensores y alertas, Mantenimiento, Verificación

### Community 128 - "run-all.mjs"
Cohesion: 0.33
Nodes (5): clean, __dirname, filter, lines, summary

### Community 129 - "5. Information architecture and responsive layout"
Cohesion: 0.33
Nodes (6): 5.1 Decision hierarchy, 5.2 Desktop reference at 1440 CSS pixels, 5.3 Breakpoint contract, 5.4 Mobile reference at 390 CSS pixels, 5.5 Layout implementation recipe, 5. Information architecture and responsive layout

### Community 131 - "chartTheme.js"
Cohesion: 0.33
Nodes (8): FALLBACK_TOKENS, hexToRgba(), readCssVar(), resolveChartTokens(), resolveZoneTokens(), ZONE_CSS_VARS, ZONE_FALLBACKS, ZONE_FILL_ALPHA

### Community 133 - "G0D — Reuse & Ownership Freeze"
Cohesion: 0.40
Nodes (4): Backend ownership, Frontend ownership, G0D — Reuse & Ownership Freeze, Polling inventory (delete only in cutover stages, after recovery proven — audit §7)

### Community 134 - "10. Data contracts and backend integration"
Cohesion: 0.40
Nodes (5): 10.1 Minimum proposed domain contract, 10.2 Illustrative type shape, 10.3 Required operations, 10.4 Authorization and privacy, 10. Data contracts and backend integration

### Community 135 - "Backend Ingestion Contract (POST /api/ingestion/events)"
Cohesion: 0.33
Nodes (6): Backend Ingestion Contract (POST /api/ingestion/events), data_jobs_service (future consumer, not implemented), MQTT Mode (mqtt_client.py), raw_sensor_events Processing Flow, Simulate Mode (python -m app.main --simulate), paho-mqtt (Python library)

### Community 136 - "Front/Back Separation Goal"
Cohesion: 0.33
Nodes (6): iot-platform-v2 Project, Incremental Migration Principle, Front/Back Separation Goal, ADR-002: Backend As REST API + Broadcasting, ADR-004: Incremental Migration, Laravel 12 / PHP 8.2+ Backend

### Community 137 - "14. Preserve brand, UI, and UX in the repository"
Cohesion: 0.40
Nodes (5): 14.1 Durable design artifacts to create or extend during implementation, 14.2 Source-of-truth rules, 14.3 Agent instruction integration, 14.4 Prevent design drift, 14. Preserve brand, UI, and UX in the repository

### Community 138 - "6. Design tokens and visual grammar"
Cohesion: 0.40
Nodes (5): 6.1 Authoritative semantic palette, 6.2 Copyable CSS baseline, 6.3 Typography, 6.4 Geometry and interaction styling, 6. Design tokens and visual grammar

### Community 139 - "psr-4"
Cohesion: 0.40
Nodes (5): autoload, psr-4, App\\, Database\\Factories\\, Database\\Seeders\\

### Community 140 - "logging.php"
Cohesion: 0.40
Nodes (4): Monolog\Handler\NullHandler, Monolog\Handler\StreamHandler, Monolog\Handler\SyslogUdpHandler, Monolog\Processor\PsrLogMessageProcessor

### Community 141 - "7. Chart semantics and scientific data rules"
Cohesion: 0.40
Nodes (5): 7.1 Visual chart contract, 7.2 Exact example thresholds, 7.3 Definitions and time consistency, 7.4 Chart implementation sequence, 7. Chart semantics and scientific data rules

### Community 142 - "8. Interaction flows and state machines"
Cohesion: 0.40
Nodes (5): 8.0 Public guest mode and authenticated enhancement, 8.1 Selection and editing, 8.2 Save state contract, 8.3 Loading, freshness, and connection, 8. Interaction flows and state machines

### Community 143 - "12. Workspace persistence in detail"
Cohesion: 0.50
Nodes (4): 12.1 Baseline and draft algorithm, 12.2 Conflict and failure handling, 12.3 Navigation and local drafts, 12. Workspace persistence in detail

### Community 144 - "20. Source preservation map and references"
Cohesion: 0.50
Nodes (4): 20.1 Coverage of the original Word document, 20.2 Product source, 20.3 Official technical references, 20. Source preservation map and references

### Community 145 - "9. Frontend architecture and component contracts"
Cohesion: 0.50
Nodes (4): 9.1 Proposed repository organization, 9.2 Component API and UX responsibilities, 9.3 State ownership, 9. Frontend architecture and component contracts

### Community 146 - "dashboard.js"
Cohesion: 0.29
Nodes (6): getDashboardMetrics(), getDashboardPreferences(), updateDashboardPreferences(), loadMetrics(), saveClick(), save()

### Community 147 - "autoload-dev"
Cohesion: 0.67
Nodes (3): autoload-dev, psr-4, Tests\\

### Community 151 - "vitest"
Cohesion: 0.07
Nodes (31): authStore, deviceStatuses, props, getDevices, mountDeviceStatusList(), mountedApps, visibleDevices, getActiveAlerts (+23 more)

### Community 152 - "SensorDetailView.test.js"
Cohesion: 0.10
Nodes (16): mountedApps, mountList(), onReadingBySensor, subscribeSensor, unsubscribeSensor, normalizeReading(), normalizeReadings(), useSensorReadingsStore (+8 more)

### Community 154 - "ADR-009: SPA Auth Uses Sanctum Bearer Tokens Initially"
Cohesion: 0.50
Nodes (5): ADR-009: SPA Auth Uses Sanctum Bearer Tokens Initially, Laravel Sanctum, Authentication Strategy: Sanctum Bearer Tokens, Bearer Token in localStorage Risk, SEC-003: Bearer Token in localStorage

### Community 158 - "MigrationIntegrityTest"
Cohesion: 0.26
Nodes (3): ExampleTest, MigrationIntegrityTest, PHPUnit\Framework\TestCase

### Community 160 - "11. Race-safe requests and live data"
Cohesion: 0.67
Nodes (3): 11.1 Request identity and cancellation, 11.2 Stream normalization and merge, 11. Race-safe requests and live data

### Community 161 - "13. Alerts, scientific inspector, and secondary modules"
Cohesion: 0.67
Nodes (3): 13.1 Alert scope and priority, 13.2 Inspector and secondary signals, 13. Alerts, scientific inspector, and secondary modules

### Community 162 - "extra"
Cohesion: 0.67
Nodes (3): extra, laravel, dont-discover

### Community 163 - "15. Performance and accessibility"
Cohesion: 0.67
Nodes (3): 15.1 Performance budgets and lifecycle, 15.2 Accessibility acceptance, 15. Performance and accessibility

### Community 187 - "User model"
Cohesion: 0.67
Nodes (3): User model, ResetPasswordNotification, VerifyEmailNotification

### Community 188 - "17. Verification matrix and definition of done"
Cohesion: 0.67
Nodes (3): 17.1 Meaningful test structure, 17.2 Definition of done, 17. Verification matrix and definition of done

### Community 189 - "4. Brand identity and product voice"
Cohesion: 0.67
Nodes (3): 4.1 Brand character, 4.2 Language and terminology, 4. Brand identity and product voice

### Community 193 - "ADR-005: Separate Environment Variables"
Cohesion: 0.67
Nodes (3): ADR-005: Separate Environment Variables, ADR-006: API-Only Frontend Communication, FIX-001: front/.env Configured With Only Public VITE_* Vars

### Community 194 - "Functional Inventory: Backend API Table"
Cohesion: 0.67
Nodes (3): Functional State: Backend, Functional Inventory: Backend API Table, Conserved Features (Blade to Vue Parity)

### Community 265 - "front/vite.config.js"
Cohesion: 0.33
Nodes (3): labDemoPlugin(), @vitejs/plugin-vue, ws

## Ambiguous Edges - Review These
- `NavBar.vue` → `State ownership target: WS -> Pinia -> components`  [AMBIGUOUS]
  audit.md · relation: conceptually_related_to
- `graphify-html-export agent` → `CLAUDE.md (repo guidance)`  [AMBIGUOUS]
  .claude/agents/graphify-html-export.md · relation: semantically_similar_to
- `NewSensorReading event` → `AlertService`  [AMBIGUOUS]
  INFORME_ALERTAS_NOTIFICACIONES.md · relation: calls

## Knowledge Gaps
- **806 isolated node(s):** `$schema`, `name`, `type`, `description`, `keywords` (+801 more)
  These have ≤1 connection - possible missing edges or undocumented components. (Counts symbols only; 1223 node(s) total have ≤1 connection when file, concept and rationale nodes are included.)
- **43 thin communities (<3 nodes) omitted from report** — run `graphify query` to explore isolated nodes.

## Suggested Questions
_Questions this graph is uniquely positioned to answer:_

- **What is the exact relationship between `NavBar.vue` and `State ownership target: WS -> Pinia -> components`?**
  _Edge tagged AMBIGUOUS (relation: conceptually_related_to) - confidence is low._
- **What is the exact relationship between `graphify-html-export agent` and `CLAUDE.md (repo guidance)`?**
  _Edge tagged AMBIGUOUS (relation: semantically_similar_to) - confidence is low._
- **What is the exact relationship between `NewSensorReading event` and `AlertService`?**
  _Edge tagged AMBIGUOUS (relation: calls) - confidence is low._
- **Why does `Sensor` connect `Sensor` to `DomainEventBroadcastConsumerTest`, `Device`, `Illuminate\Http\Request`, `User`, `TestCase`, `DashboardMetricsService`, `SensorReading`, `StoreAlertRuleRequest`, `Illuminate\Support\Facades\Log`, `DomainEventOutbox`, `SensorType`, `RawSensorEvent`, `SecurityRateLimitTest.php`, `PublicGraphControllerTest`, `SystemSetting`, `Illuminate\Database\Seeder`, `Illuminate\Console\Command`, `AlertRuleController`, `RawStreamConsumerTest`, `NewSensorReading`, `Lab`, `SensorReadingService`, `PublicGraphController.php`, `DashboardPreference`, `PublicGraphSeriesService`, `NewSensorReadingPayloadTest`?**
  _High betweenness centrality (0.033) - this node is a cross-community bridge._
- **Why does `vue` connect `vue` to `SensorMonitorBoard.test.js`, `NavBar.vue`, `SensorsView.vue`, `DevicesView.vue`, `vitest`, `SensorDetailView.test.js`, `ConfigView.vue`, `CatalogAdminView.vue`, `useAlertsRealtime.js`, `AlertRulesView.vue`, `client.js`, `SensorMonitorBoard.vue`, `useDeviceStatusRealtime.js`, `useSensorRealtime.js`, `getApiErrorMessage`, `LabShell.vue`, `index.js`, `AlertRuleForm.vue`, `ActiveAlertsCard.vue`, `useLabWorkspace.js`, `BaseModal.vue`, `AppLayout.test.js`, `DeviceDetailView.vue`, `front/package.json`?**
  _High betweenness centrality (0.033) - this node is a cross-community bridge._
- **Why does `User` connect `User` to `RegisterController`, `UserRoleController`, `DomainEventOutbox`, `SensorType`, `Sensor`, `Device`, `Controller`, `Illuminate\Http\JsonResponse`, `static`, `api.php`, `TestCase`, `DashboardPreference`, `AuthApiHeadlessTest.php`, `SystemSetting`, `Illuminate\Database\Seeder`, `Illuminate\Support\Facades\Log`, `Alert`?**
  _High betweenness centrality (0.023) - this node is a cross-community bridge._
- **What connects `$schema`, `name`, `type` to the rest of the system?**
  _806 weakly-connected nodes found - possible documentation gaps or missing edges._