# Graph Report - iot-platform-v2  (2026-09-07)

## Corpus Check
- 368 files · ~117,145 words
- Verdict: corpus is large enough that graph structure adds value.

## Summary
- 1925 nodes · 3341 edges · 228 communities (98 shown, 41 thin omitted)
- Extraction: 98% EXTRACTED · 2% INFERRED · 0% AMBIGUOUS · INFERRED: 80 edges (avg confidence: 0.85)
- Token cost: 310,461 input · 0 output

## Community Hubs (Navigation)
- Sensor Reading API & Cleanup
- Ingestion Backend Client (Python)
- Device Type & Lab Admin
- User Model & Admin Auth Tests
- Sensor Monitor Dashboard (Vue)
- Realtime Broadcast Events
- Frontend Dependencies (package.json)
- Frontend Dev Dependencies
- Auth Email Notifications
- REST API Controllers Overview
- Laravel Echo Realtime Wiring
- Device Relations & Routing Tests
- API Auth & Device Tests
- Auth API Controller
- Test Model Factories
- Sensor Data Simulator Script
- Alert Controller & Model
- Internal Metrics Controllers
- Sensors Admin View (Vue)
- Sensor & Password Controllers
- Sensor Reading Observer Pipeline
- Devices Admin View (Vue)
- Ingestion & Realtime Architecture Notes
- Config Admin View (Vue)
- Alert Rule Validation Tests
- ICONTEC Compliance Documentation
- Dashboard Controller
- Catalog Admin View (Vue)
- Alert Rule Controller & Model
- Config Controller
- Alert Notification Pipeline
- Alert Rules Admin View (Vue)
- Sensor Type Controller
- Device API Controller
- Database Seeders
- Phase 5 Realtime Verification Script
- Core Eloquent Models
- Service Providers & Rate Limiting
- Frontend API Client
- Sensor Detail View (Vue)
- Alert Rule Controller (Variant)
- User Role Controller & Migrations
- App Layout & Navigation (Vue)
- Alert Realtime Cross-Cutting Flow
- Alert Controller (Resolve Flow)
- API Form Request Validation
- Build & Dev Scripts
- IoT Sensor API & Docs
- Alert Feed & Service
- App Config & Cache Setup
- Alerts Admin View (Vue)
- Alert Toast Notification (Vue)
- Composer Package Manifest
- Architecture Decisions & Migration Phases
- Phase 3 Verification Script
- Registration & Auth Events
- Core DB Migrations
- Cache & Jobs Migrations
- DangerAlertMail
- 2025 06 03 000001 update alert rules table
- Verify Phase7
- Main
- AlertRuleForm
- ActiveAlertsCard
- AlertDetailView
- Requirements
- EmailConfigController
- EnsureIngestionToken
- StoreAlertRuleRequest
- Composer
- Composer
- DatabaseSeeder
- DeviceStatusList
- EmailVerificationView
- DashboardView
- UserRolesView
- 05 Migration Log
- Composer
- Verify Phase4
- ResetPasswordView
- DeviceDetailView
- MetricsView
- 12 Stable Blade Comparison
- 04 Api Contract
- 04 Api Contract
- EmailConfigController
- HomeController
- DeviceApiPaginationTest
- ForgotPasswordView
- LoginView
- RegisterView
- README
- 00 Project Context
- Composer
- Logging
- Bootstrap
- dashboard.blade
- SecuritySqlInjectionTest
- SensorChart
- SensorReadingsChart
- ProfileView
- 01 Architecture Decisions
- UserRoleController
- Composer
- AlertRuleNameTest
- ExampleTest
- AlertItem
- Alerts
- Sound
- 02 Current Audit
- Composer
- Composer
- app.blade
- Console
- INFORME ALERTAS NOTIFICACIONES
- BaseInput
- MetricsCards
- DeviceList
- Legacy
- 01 Architecture Decisions
- 10 Functional State
- DashboardPreferenceController
- Sanctum
- AlertFilters
- SensorList
-   init  
- 02 Current Audit
- 04 Api Contract
- 04 Api Contract
- 03 Refactor Rules
- 03 Refactor Rules
- 03 Refactor Rules
- 03 Refactor Rules
- 06 Pending Risks
- 10 Functional State
- 10 Functional State
- 10 Functional State
- 10 Functional State
- 15 Security Review

## God Nodes (most connected - your core abstractions)
1. `User` - 107 edges
2. `Sensor` - 100 edges
3. `Device` - 98 edges
4. `TestCase` - 73 edges
5. `Controller` - 68 edges
6. `Alert` - 53 edges
7. `SensorType` - 47 edges
8. `SensorReading` - 46 edges
9. `SystemSetting` - 43 edges
10. `AlertRule` - 41 edges

## Surprising Connections (you probably didn't know these)
- `graphify-html-export agent` --semantically_similar_to--> `CLAUDE.md (repo guidance)`  [AMBIGUOUS] [semantically similar]
  .claude/agents/graphify-html-export.md → CLAUDE.md
- `audit.md — architecture audit and implementation plan` --semantically_similar_to--> `INGESTION_PIPELINE.md (Raw-First)`  [INFERRED] [semantically similar]
  audit.md → docs/INGESTION_PIPELINE.md
- `DOCUMENTACION_PROYECTO.md (documentacion tecnica formal)` --semantically_similar_to--> `ANALISIS_PROYECTO.md (analisis tecnico y de cumplimiento)`  [INFERRED] [semantically similar]
  DOCUMENTACION_PROYECTO.md → ANALISIS_PROYECTO.md
- `Two parallel ingestion paths (legacy + raw)` --semantically_similar_to--> `Raw-first ingestion pipeline architecture`  [INFERRED] [semantically similar]
  CLAUDE.md → docs/INGESTION_PIPELINE.md
- `Ingestion Service (Python)` --semantically_similar_to--> `script_datos.py (Python IoT Simulator)`  [INFERRED] [semantically similar]
  ingestion_service/README.md → memory/02-current-audit.md

## Import Cycles
- None detected.

## Hyperedges (group relationships)
- **Sensor reading to alert notification pipeline** — code_back_app_http_controllers_api_sensorapicontroller, code_back_app_observers_sensorreadingobserver, code_back_app_models_sensorreading, code_back_app_services_alerts_alertservice, code_back_app_observers_alertobserver, code_back_app_services_notifications_notificationservice, code_back_app_events_newalerttriggered, code_back_app_mail_dangeralertmail [EXTRACTED 1.00]
- **ICONTEC/ISO normative compliance document set** — docs_icontec_compliance, docs_matriz_trazabilidad_icontec_iso, docs_evidencia_cumplimiento_codigo_estructura, docs_procedimiento_auditoria_normativa, docs_referencias_icontec, docs_plantilla_trabajo_icontec, documentacion_proyecto, analisis_proyecto [EXTRACTED 1.00]
- **Vue realtime/polling remediation targets (audit.md)** — code_front_src_components_layout_applayout, code_front_src_components_dashboard_activealertscard, code_front_src_components_dashboard_sensormonitorboard, code_front_src_realtime_echo, code_front_src_realtime_usealertsrealtime, code_front_src_realtime_usesensorrealtime [EXTRACTED 1.00]
- **SPA Auth & Security Boundary Design** — memory_01_architecture_decisions_adr_005_env_separation, memory_01_architecture_decisions_adr_006_api_only_communication, memory_01_architecture_decisions_adr_009_sanctum_bearer_auth, memory_15_security_review_sec_003_localstorage_bearer_token [INFERRED 0.85]
- **Front/Back Migration Phase Roadmap (Fase 0 to Fase 8A)** — memory_05_migration_log_fase_0, memory_05_migration_log_fase_1, memory_05_migration_log_fase_2, memory_05_migration_log_fase_3, memory_05_migration_log_fase_4, memory_05_migration_log_fase_5, memory_05_migration_log_fase_6, memory_05_migration_log_fase_7, memory_05_migration_log_fase_7b, memory_05_migration_log_fase_8a [EXTRACTED 1.00]
- **Fase 6B Blade Cleanup Gating Decision** — memory_14_blade_cleanup_readiness_verdict_nogo, memory_13_manual_validation_checklist_checklist, memory_12_stable_blade_comparison_missing_features, memory_06_pending_risks_operational_risks, memory_08_refactor_checklist_fase_6b [EXTRACTED 1.00]

## Communities (228 total, 41 thin omitted)

### Community 0 - "Sensor Reading API & Cleanup"
Cohesion: 0.06
Nodes (11): PurgeFutureSensorReadings, SensorApiController, SensorDataController, SensorController, Sensor, IngestionApiTest, SensorDataControllerTest, Carbon\Carbon (+3 more)

### Community 1 - "Ingestion Backend Client (Python)"
Cohesion: 0.07
Nodes (41): BaseModel, Client, BackendClient, BackendClientError, Any, Raised when backend ingestion endpoint cannot be reached or rejects payload., configure_logging(), main() (+33 more)

### Community 2 - "Device Type & Lab Admin"
Cohesion: 0.06
Nodes (9): DeviceTypeController, LabController, DeviceController, DeviceTypeController, LabController, DeviceType, Lab, DeviceService (+1 more)

### Community 3 - "User Model & Admin Auth Tests"
Cohesion: 0.07
Nodes (11): User, AdminAccessTest, AuthApiHeadlessTest, Phase2ApiEndpointsTest, SecurityPrivilegeEscalationTest, SpaParityApiTest, Illuminate\Auth\Access\AuthorizationException, Illuminate\Contracts\Auth\MustVerifyEmail (+3 more)

### Community 4 - "Sensor Monitor Dashboard (Vue)"
Cohesion: 0.09
Nodes (40): addMonitor(), authStore, availableSensors(), chartData(), chartOptions, currentLayout(), firstSelectableSensor(), handleDeviceChange() (+32 more)

### Community 5 - "Realtime Broadcast Events"
Cohesion: 0.10
Nodes (18): DeviceCommunicationReceived, DeviceStatusUpdated, NewAlertTriggered, NewSensorReading, QueueSmokeJob, UpdateDeviceLastCommunication, Illuminate\Broadcasting\Channel, Illuminate\Broadcasting\InteractsWithSockets (+10 more)

### Community 6 - "Frontend Dependencies (package.json)"
Cohesion: 0.05
Nodes (38): dependencies, axios, bootstrap, chart.js, laravel-echo, pinia, pusher-js, vue (+30 more)

### Community 7 - "Frontend Dev Dependencies"
Cohesion: 0.06
Nodes (33): dependencies, chart.js, laravel-echo, lightweight-charts, pusher-js, devDependencies, axios, bootstrap (+25 more)

### Community 8 - "Auth Email Notifications"
Cohesion: 0.08
Nodes (14): MailMessage, ResetPasswordNotification, MailMessage, VerifyEmailNotification, PasswordResetTest, RegistrationEmailVerificationTest, Illuminate\Auth\Notifications\ResetPassword, Illuminate\Auth\Notifications\VerifyEmail (+6 more)

### Community 9 - "REST API Controllers Overview"
Cohesion: 0.11
Nodes (15): IngestionController, RawSensorEvent, RawSensorEventPublisher, Dotenv\Dotenv, Illuminate\Database\QueryException, Illuminate\Foundation\Application, Illuminate\Foundation\Configuration\Exceptions, Illuminate\Foundation\Configuration\Middleware (+7 more)

### Community 10 - "Laravel Echo Realtime Wiring"
Cohesion: 0.11
Nodes (26): booleanEnv(), createEcho(), disconnectEcho(), getEcho(), getEchoConfig(), numberEnv(), ALERTS_CHANNEL, ALERTS_EVENT (+18 more)

### Community 11 - "Device Relations & Routing Tests"
Cohesion: 0.10
Nodes (5): Device, ApiRoutingRegressionTest, DeviceApiStatusUpdateTest, SensorApiControllerTest, DashboardMetricsServiceTest

### Community 12 - "API Auth & Device Tests"
Cohesion: 0.09
Nodes (9): ApiAuthTokenTest, DeviceCreateTest, DeviceUpdateTest, ExampleTest, IotApiKeyAccessTest, Phase7DockerReadinessTest, UserRoleManagementTest, TestCase (+1 more)

### Community 13 - "Auth API Controller"
Cohesion: 0.15
Nodes (6): AuthApiController, HealthController, ProfileController, DashboardPreferenceController, Illuminate\Http\JsonResponse, Illuminate\Http\Request

### Community 14 - "Test Model Factories"
Cohesion: 0.10
Nodes (10): AlertFactory, AlertRuleFactory, DeviceFactory, DeviceStatusLogFactory, DeviceTypeFactory, LabFactory, SensorFactory, SensorReadingFactory (+2 more)

### Community 15 - "Sensor Data Simulator Script"
Cohesion: 0.14
Nodes (18): build_qc_checksum(), build_sensor_payload(), device_sender_worker(), get_api_key(), get_location(), get_node_id(), get_sensor_key(), get_sensors() (+10 more)

### Community 16 - "Alert Controller & Model"
Cohesion: 0.12
Nodes (5): AlertController, Alert, AlertObserver, NotificationService, Illuminate\Support\Facades\Cache

### Community 17 - "Internal Metrics Controllers"
Cohesion: 0.11
Nodes (9): InternalMetricsController, MetricsController, MetricsController, TrackApiPerformance, ApiMetricsService, Illuminate\Http\Exceptions\HttpResponseException, Illuminate\Http\Response, Illuminate\View\View (+1 more)

### Community 18 - "Sensors Admin View (Vue)"
Cohesion: 0.09
Nodes (22): authStore, closeForm(), defaultSensorForm(), deleteSelectedSensor(), devices, editingSensorId, error, filteredSensors (+14 more)

### Community 19 - "Sensor & Password Controllers"
Cohesion: 0.12
Nodes (14): ConfirmPasswordController, ForgotPasswordController, LoginController, ResetPasswordController, VerificationController, Controller, Illuminate\Foundation\Auth\Access\AuthorizesRequests, Illuminate\Foundation\Auth\AuthenticatesUsers (+6 more)

### Community 20 - "Sensor Reading Observer Pipeline"
Cohesion: 0.12
Nodes (8): SensorReading, SensorReadingObserver, EventServiceProvider, AlertEmailTest, SensorReadingAlertTest, Illuminate\Auth\Events\Registered, Illuminate\Auth\Listeners\SendEmailVerificationNotification, Illuminate\Foundation\Support\Providers\EventServiceProvider

### Community 21 - "Devices Admin View (Vue)"
Cohesion: 0.11
Nodes (21): authStore, closeForm(), defaultDeviceForm(), deleteSelectedDevice(), deviceForm, devicePayload(), devices, deviceTypes (+13 more)

### Community 22 - "Ingestion & Realtime Architecture Notes"
Cohesion: 0.11
Nodes (23): POST /api/ingestion/events, graphify-html-export agent, iot-back-implementador agent, CLAUDE.md (repo guidance), IngestionController, RawSensorEventPublisher, NavBar.vue, Eloquent::update() bulk calls do not fire observers (+15 more)

### Community 23 - "Config Admin View (Vue)"
Cohesion: 0.09
Nodes (19): adminActions, alertErrors, alertForm, alertPayload(), authStore, emailErrors, emailForm, emailPayload() (+11 more)

### Community 24 - "Alert Rule Validation Tests"
Cohesion: 0.11
Nodes (6): AlertRuleCascadeDeleteTest, AlertRuleValidationTest, DashboardPreferenceControllerTest, DataIntegrityDuplicationTest, SecurityAccessControlTest, Illuminate\Foundation\Testing\RefreshDatabase

### Community 25 - "ICONTEC Compliance Documentation"
Cohesion: 0.26
Nodes (16): ANALISIS_PROYECTO.md (analisis tecnico y de cumplimiento), EnsureUserIsAdmin middleware, TrackApiPerformance middleware, AppServiceProvider (rate limiters), DashboardMetricsService, DashboardMetricsServiceTest constructor non-conformity, ICONTEC/ISO alignment (not certification) declaration policy, NTC 1486 (presentacion de trabajos escritos) (+8 more)

### Community 26 - "Dashboard Controller"
Cohesion: 0.13
Nodes (3): DashboardController, DashboardController, DashboardMetricsService

### Community 27 - "Catalog Admin View (Vue)"
Cohesion: 0.12
Nodes (17): catalogConfigs, config, deletingId, editingId, error, errors, form, items (+9 more)

### Community 28 - "Alert Rule Controller & Model"
Cohesion: 0.14
Nodes (4): AlertRuleController, AlertRule, DangerAlertEmailTest, SensorReadingTriggeredRulesTest

### Community 29 - "Config Controller"
Cohesion: 0.15
Nodes (4): ConfigController, ConfigController, SystemSetting, SystemSettingTest

### Community 30 - "Alert Notification Pipeline"
Cohesion: 0.12
Nodes (18): DeviceStatusUpdated event, NewAlertTriggered event, NewSensorReading event, EmailConfigController, SensorController, DangerAlertMail, Alert model, AlertObserver (+10 more)

### Community 31 - "Alert Rules Admin View (Vue)"
Cohesion: 0.13
Nodes (19): closeModal(), deleteRule(), deletingId, error, formError, load(), loading, loadMetadata() (+11 more)

### Community 32 - "Sensor Type Controller"
Cohesion: 0.17
Nodes (3): SensorTypeController, SensorTypeController, SensorType

### Community 33 - "Device API Controller"
Cohesion: 0.20
Nodes (4): DeviceApiController, DeviceResource, SensorResource, Illuminate\Http\Resources\Json\JsonResource

### Community 34 - "Database Seeders"
Cohesion: 0.16
Nodes (7): AlertRuleSeeder, AlertSeeder, DeviceTypeSeeder, SensorTypeSeeder, SystemSettingsSeeder, Illuminate\Database\Console\Seeds\WithoutModelEvents, Illuminate\Database\Seeder

### Community 35 - "Phase 5 Realtime Verification Script"
Cohesion: 0.11
Nodes (15): activeAlertsCard, alertsRealtime, alertsStore, alertsView, alertToast, appLayout, echo, envExample (+7 more)

### Community 36 - "Core Eloquent Models"
Cohesion: 0.22
Nodes (4): DashboardPreference, DeviceStatusLog, Illuminate\Database\Eloquent\Factories\HasFactory, Illuminate\Database\Eloquent\Model

### Community 37 - "Service Providers & Rate Limiting"
Cohesion: 0.15
Nodes (6): AppServiceProvider, ViewServiceProvider, SecurityRateLimitTest, Illuminate\Cache\RateLimiting\Limit, Illuminate\Support\Facades\RateLimiter, Illuminate\Support\ServiceProvider

### Community 39 - "Sensor Detail View (Vue)"
Cohesion: 0.15
Nodes (14): error, exporting, exportReadings(), filtering, filterReadings(), filters, load(), loading (+6 more)

### Community 40 - "Alert Rule Controller (Variant)"
Cohesion: 0.21
Nodes (3): AlertRuleController, UpdateAlertRuleRequest, AlertRuleResource

### Community 42 - "App Layout & Navigation (Vue)"
Cohesion: 0.13
Nodes (10): alertsStore, { subscribeAlerts, unsubscribeAlerts }, adminItems, alertsStore, authStore, laboratoryItems, navItems, realtimeStatusClass (+2 more)

### Community 43 - "Alert Realtime Cross-Cutting Flow"
Cohesion: 0.23
Nodes (14): GET /api/alerts/active, audit.md — architecture audit and implementation plan, iot-front-implementador agent, AlertController (API), ActiveAlertsCard.vue, SensorMonitorBoard.vue, AppLayout.vue, echo.js (Echo singleton) (+6 more)

### Community 45 - "API Form Request Validation"
Cohesion: 0.18
Nodes (4): StoreRawIngestionEventRequest, UpdateAlertConfigRequest, UpdateEmailConfigRequest, Illuminate\Foundation\Http\FormRequest

### Community 46 - "Build & Dev Scripts"
Cohesion: 0.14
Nodes (14): scripts, dev, post-autoload-dump, post-root-package-install, post-update-cmd, test, Composer\\Config::disableProcessTimeout, Illuminate\\Foundation\\ComposerScripts::postAutoloadDump (+6 more)

### Community 47 - "IoT Sensor API & Docs"
Cohesion: 0.21
Nodes (12): GET /api/iot/sensors, POST /api/sensors/{sensor}/readings, PurgeFutureSensorReadings command, DeviceApiController, SensorApiController, SensorReading model, SensorReadingObserver, docs/api/openapi.yaml (IoT Platform API spec) (+4 more)

### Community 48 - "Alert Feed & Service"
Cohesion: 0.23
Nodes (3): AlertFeedController, AlertService, Illuminate\Support\Collection

### Community 49 - "App Config & Cache Setup"
Cohesion: 0.17
Nodes (3): UserFactory, Illuminate\Support\Str, static

### Community 51 - "Alerts Admin View (Vue)"
Cohesion: 0.18
Nodes (11): alerts, alertsStore, error, filter, handleResolve(), handleResolveAll(), load(), loading (+3 more)

### Community 52 - "Alert Toast Notification (Vue)"
Cohesion: 0.17
Nodes (10): alertsStore, currentAlert, currentDevice, currentMessage, currentSeverity, currentTime, currentTitle, currentValue (+2 more)

### Community 53 - "Composer Package Manifest"
Cohesion: 0.18
Nodes (10): description, extra, laravel, dont-discover, license, minimum-stability, name, prefer-stable (+2 more)

### Community 54 - "Architecture Decisions & Migration Phases"
Cohesion: 0.18
Nodes (11): Front SPA HTML Entrypoint, ADR-001: Keep A Monorepo Initially, ADR-008: Frontend Framework (Vue 3 + Bootstrap 5, proposed), Vite (laravel-vite-plugin), Migration Hygiene Rules, Phase 3 Frontend Consumer Notes, Fase 0: Read-only Audit and Real Contract, Fase 1: Headless Auth (Sanctum Bearer) (+3 more)

### Community 55 - "Phase 3 Verification Script"
Cohesion: 0.18
Nodes (10): dependencies, emailVerification, envExample, missing, missingDependencies, packageJson, requiredDependencies, requiredFiles (+2 more)

### Community 56 - "Registration & Auth Events"
Cohesion: 0.24
Nodes (5): RegisterController, Illuminate\Auth\Events\PasswordReset, Illuminate\Auth\Events\Verified, Illuminate\Foundation\Auth\RegistersUsers, Illuminate\Support\Facades\Hash

### Community 59 - "DangerAlertMail"
Cohesion: 0.28
Nodes (3): DangerAlertMail, Illuminate\Mail\Mailable, Illuminate\Support\Facades\Mail

### Community 60 - "2025 06 03 000001 update alert rules table"
Cohesion: 0.28
Nodes (3): UpdateAlertRulesTable, UpdateAlertRulesSeverity, Illuminate\Database\Migrations\Migration

### Community 61 - "Verify Phase7"
Cohesion: 0.22
Nodes (8): __dirname, envExample, __filename, forbiddenKeys, frontDockerfile, frontRoot, repoRoot, requiredFiles

### Community 64 - "Main"
Cohesion: 0.28
Nodes (5): app, authStore, pinia, router, useAuthStore

### Community 65 - "AlertRuleForm"
Cohesion: 0.28
Nodes (6): emit, filteredSensors, form, nullableNumber(), props, submit()

### Community 66 - "ActiveAlertsCard"
Cohesion: 0.22
Nodes (6): alerts, alertsStore, count, error, loading, updateMode

### Community 67 - "AlertDetailView"
Cohesion: 0.22
Nodes (6): alert, error, loading, props, resolving, success

### Community 68 - "Requirements"
Cohesion: 0.22
Nodes (9): Ingestion Service (Python), pydantic (Python library), pytest (Python library), python-dotenv (Python library), requests (Python library), script_datos.py (Python IoT Simulator), Operational Risks, SEC-002: Global Legacy IoT API Key (+1 more)

### Community 70 - "EnsureIngestionToken"
Cohesion: 0.32
Nodes (4): EnsureIngestionToken, EnsureUserIsAdmin, Closure, Illuminate\Support\Facades\Auth

### Community 72 - "Composer"
Cohesion: 0.25
Nodes (8): require, laravel/framework, laravel/sanctum, laravel/tinker, laravel/ui, php, pusher/pusher-php-server, vlucas/phpdotenv

### Community 73 - "Composer"
Cohesion: 0.25
Nodes (8): require-dev, fakerphp/faker, laravel/pail, laravel/pint, laravel/sail, mockery/mockery, nunomaduro/collision, phpunit/phpunit

### Community 76 - "DeviceStatusList"
Cohesion: 0.25
Nodes (6): authStore, devices, error, loading, props, visibleDevices

### Community 78 - "EmailVerificationView"
Cohesion: 0.25
Nodes (6): authStore, error, loading, resending, route, success

### Community 79 - "DashboardView"
Cohesion: 0.25
Nodes (6): devices, error, latestReadings, loading, pollInterval, summary

### Community 80 - "UserRolesView"
Cohesion: 0.29
Nodes (7): error, load(), loading, savingId, success, toggleRole(), users

### Community 81 - "05 Migration Log"
Cohesion: 0.29
Nodes (8): Phase 8A API Additions (CRUD/Admin), Fase 7B: Operational Validation Before Blade Cleanup, Fase 8A: Admin CRUD/Catalog SPA Migration, QueueSmokeJob (queue infrastructure smoke test), High Risks, Fase 6B: Blade Legacy Cleanup and Production SPA Adjustment (Pending), Fase 8: Final Cleanup (Pending), Session Progress: Fase 8A Work Log

### Community 82 - "Composer"
Cohesion: 0.29
Nodes (7): pestphp/pest-plugin, php-http/discovery, config, allow-plugins, optimize-autoloader, preferred-install, sort-packages

### Community 83 - "Verify Phase4"
Cohesion: 0.29
Nodes (6): missing, navbar, requiredFiles, root, router, srcFilesToCheck

### Community 87 - "ResetPasswordView"
Cohesion: 0.29
Nodes (5): error, form, loading, route, success

### Community 88 - "DeviceDetailView"
Cohesion: 0.29
Nodes (5): device, error, loading, props, sensors

### Community 89 - "MetricsView"
Cohesion: 0.29
Nodes (5): error, generatedAt, loading, metricCards, snapshot

### Community 90 - "12 Stable Blade Comparison"
Cohesion: 0.29
Nodes (7): ADR-003: Do Not Move Blade Directly To /front, Blade Views (Legacy UI), Functional State: Blade Legacy, Legacy Views Still Dependent on Blade, Lost or Not-Found Features, Manual Validation Checklist (Not Yet Executed), Blade Cleanup Readiness Verdict: NO-GO

### Community 91 - "04 Api Contract"
Cohesion: 0.33
Nodes (7): ADR-007: Docker Per Service, Docker/Docker Compose (absent at audit time), Phase 6 Physical Separation Notes, Phase 7 Docker Consumer Notes, Fase 6: Physical Separation Into /back, Fase 7: Docker and Compose, Functional State: Docker

### Community 92 - "04 Api Contract"
Cohesion: 0.29
Nodes (7): Pusher / Laravel Echo Realtime, Broadcast Event: NewAlertTriggered (channel alerts), Broadcast Event: NewSensorReading (channel sensor.{id}), Phase 4 Frontend Consumer Notes, Phase 5 Realtime Contract Notes, Fase 4: Progressive Screen Migration, Fase 5: Realtime (Echo/Pusher) Integration

### Community 96 - "ForgotPasswordView"
Cohesion: 0.33
Nodes (4): email, error, loading, success

### Community 97 - "LoginView"
Cohesion: 0.33
Nodes (4): authStore, form, route, router

### Community 98 - "RegisterView"
Cohesion: 0.33
Nodes (4): error, form, loading, success

### Community 99 - "README"
Cohesion: 0.33
Nodes (6): Backend Ingestion Contract (POST /api/ingestion/events), data_jobs_service (future consumer, not implemented), MQTT Mode (mqtt_client.py), raw_sensor_events Processing Flow, Simulate Mode (python -m app.main --simulate), paho-mqtt (Python library)

### Community 100 - "00 Project Context"
Cohesion: 0.33
Nodes (6): iot-platform-v2 Project, Incremental Migration Principle, Front/Back Separation Goal, ADR-002: Backend As REST API + Broadcasting, ADR-004: Incremental Migration, Laravel 12 / PHP 8.2+ Backend

### Community 101 - "Composer"
Cohesion: 0.40
Nodes (5): autoload, psr-4, App\\, Database\\Factories\\, Database\\Seeders\\

### Community 102 - "Logging"
Cohesion: 0.40
Nodes (4): Monolog\Handler\NullHandler, Monolog\Handler\StreamHandler, Monolog\Handler\SyslogUdpHandler, Monolog\Processor\PsrLogMessageProcessor

### Community 103 - "Bootstrap"
Cohesion: 0.40
Nodes (3): chart, lineSeries, lineSeriesDef

### Community 104 - "dashboard.blade"
Cohesion: 0.40
Nodes (4): dashboard.partials.alerts-card, dashboard.partials.monitors, dashboard.partials.realtime-monitor, dashboard.partials.summary-cards

### Community 107 - "SensorChart"
Cohesion: 0.40
Nodes (4): chartData, chartOptions, chartReadings, props

### Community 108 - "SensorReadingsChart"
Cohesion: 0.40
Nodes (4): chartData, chartOptions, chartReadings, props

### Community 109 - "ProfileView"
Cohesion: 0.40
Nodes (3): error, loading, profile

### Community 110 - "01 Architecture Decisions"
Cohesion: 0.50
Nodes (5): ADR-009: SPA Auth Uses Sanctum Bearer Tokens Initially, Laravel Sanctum, Authentication Strategy: Sanctum Bearer Tokens, Bearer Token in localStorage Risk, SEC-003: Bearer Token in localStorage

### Community 112 - "Composer"
Cohesion: 0.50
Nodes (4): post-create-project-cmd, @php artisan key:generate --ansi, @php artisan migrate --graceful --ansi, @php -r \"file_exists('database/database.sqlite') || touch('database/database.sqlite');\

### Community 117 - "Sound"
Cohesion: 0.83
Nodes (3): getAudioContext(), playAlertSound(), unlockAlertSound()

### Community 118 - "02 Current Audit"
Cohesion: 0.50
Nodes (4): Chart.js, Functional State: Frontend, Functional Inventory: Frontend SPA Table, Partially Migrated Features

### Community 119 - "Composer"
Cohesion: 0.67
Nodes (3): autoload-dev, psr-4, Tests\\

### Community 120 - "Composer"
Cohesion: 0.67
Nodes (3): keywords, framework, laravel

### Community 136 - "INFORME ALERTAS NOTIFICACIONES"
Cohesion: 0.67
Nodes (3): User model, ResetPasswordNotification, VerifyEmailNotification

### Community 141 - "01 Architecture Decisions"
Cohesion: 0.67
Nodes (3): ADR-005: Separate Environment Variables, ADR-006: API-Only Frontend Communication, FIX-001: front/.env Configured With Only Public VITE_* Vars

### Community 142 - "10 Functional State"
Cohesion: 0.67
Nodes (3): Functional State: Backend, Functional Inventory: Backend API Table, Conserved Features (Blade to Vue Parity)

## Ambiguous Edges - Review These
- `graphify-html-export agent` → `CLAUDE.md (repo guidance)`  [AMBIGUOUS]
  .claude/agents/graphify-html-export.md · relation: semantically_similar_to
- `State ownership target: WS -> Pinia -> components` → `NavBar.vue`  [AMBIGUOUS]
  audit.md · relation: conceptually_related_to
- `AlertService` → `NewSensorReading event`  [AMBIGUOUS]
  INFORME_ALERTAS_NOTIFICACIONES.md · relation: calls

## Knowledge Gaps
- **405 isolated node(s):** `$schema`, `name`, `type`, `description`, `laravel` (+400 more)
  These have ≤1 connection - possible missing edges or undocumented components. (Counts symbols only; 816 node(s) total have ≤1 connection when file, concept and rationale nodes are included.)
- **41 thin communities (<3 nodes) omitted from report** — run `graphify query` to explore isolated nodes.

## Suggested Questions
_Questions this graph is uniquely positioned to answer:_

- **What is the exact relationship between `graphify-html-export agent` and `CLAUDE.md (repo guidance)`?**
  _Edge tagged AMBIGUOUS (relation: semantically_similar_to) - confidence is low._
- **What is the exact relationship between `State ownership target: WS -> Pinia -> components` and `NavBar.vue`?**
  _Edge tagged AMBIGUOUS (relation: conceptually_related_to) - confidence is low._
- **What is the exact relationship between `AlertService` and `NewSensorReading event`?**
  _Edge tagged AMBIGUOUS (relation: calls) - confidence is low._
- **Why does `Controller` connect `Sensor & Password Controllers` to `Sensor Reading API & Cleanup`, `Device Type & Lab Admin`, `REST API Controllers Overview`, `Auth API Controller`, `Alert Controller & Model`, `Internal Metrics Controllers`, `Dashboard Controller`, `Alert Rule Controller & Model`, `Config Controller`, `Sensor Type Controller`, `Device API Controller`, `Alert Rule Controller (Variant)`, `User Role Controller & Migrations`, `Alert Controller (Resolve Flow)`, `Alert Feed & Service`, `Registration & Auth Events`, `EmailConfigController`, `EmailConfigController`, `HomeController`, `UserRoleController`?**
  _High betweenness centrality (0.049) - this node is a cross-community bridge._
- **Why does `User` connect `User Model & Admin Auth Tests` to `Core Eloquent Models`, `Auth Email Notifications`, `REST API Controllers Overview`, `User Role Controller & Migrations`, `Device Relations & Routing Tests`, `API Auth & Device Tests`, `Auth API Controller`, `SecuritySqlInjectionTest`, `UserRoleController`, `App Config & Cache Setup`, `AlertRuleNameTest`, `Registration & Auth Events`, `Alert Rule Validation Tests`, `DeviceApiPaginationTest`?**
  _High betweenness centrality (0.027) - this node is a cross-community bridge._
- **Why does `Sensor` connect `Sensor Reading API & Cleanup` to `User Model & Admin Auth Tests`, `Auth Email Notifications`, `REST API Controllers Overview`, `Device Relations & Routing Tests`, `API Auth & Device Tests`, `Auth API Controller`, `Alert Controller & Model`, `Sensor & Password Controllers`, `Sensor Reading Observer Pipeline`, `Alert Rule Validation Tests`, `Dashboard Controller`, `Alert Rule Controller & Model`, `Device API Controller`, `Database Seeders`, `Core Eloquent Models`, `Service Providers & Rate Limiting`, `Alert Rule Controller (Variant)`, `DangerAlertMail`, `StoreAlertRuleRequest`, `DatabaseSeeder`, `SecuritySqlInjectionTest`, `AlertRuleNameTest`?**
  _High betweenness centrality (0.026) - this node is a cross-community bridge._
- **What connects `$schema`, `name`, `type` to the rest of the system?**
  _405 weakly-connected nodes found - possible documentation gaps or missing edges._