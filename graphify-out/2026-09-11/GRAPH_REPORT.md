# Graph Report - iot-platform-v2  (2026-09-09)

## Corpus Check
- 448 files · ~0 words
- Verdict: corpus is large enough that graph structure adds value.

## Summary
- 2510 nodes · 4593 edges · 277 communities (127 shown, 58 thin omitted)
- Extraction: 96% EXTRACTED · 4% INFERRED · 0% AMBIGUOUS · INFERRED: 176 edges (avg confidence: 0.85)
- Token cost: 0 input · 0 output

## Community Hubs (Navigation)
- Backend Client
- Domain Events
- Device Models
- Sensor Monitor UI
- Alert Models
- Alert Events
- Alert Store
- AlertRule Controller
- Sensor Controller
- Sensor API
- Test Factories
- Health/Feed Controllers
- User Roles
- App Layout
- Auth API
- API Tests
- Metrics
- IoT Data Script
- Dashboard API
- Password Reset
- Community 20
- Community 21
- Community 22
- Community 23
- Community 24
- Community 25
- Community 26
- Community 27
- Community 28
- Community 29
- Community 30
- Community 31
- Community 32
- Community 33
- Community 34
- Community 35
- Community 36
- Community 37
- Community 38
- Community 39
- Community 40
- Community 41
- Community 42
- Community 43
- Community 44
- Community 45
- Community 46
- Community 47
- Community 48
- Community 49
- Community 50
- Community 51
- Community 52
- Community 53
- Community 54
- Community 55
- Community 56
- Community 57
- Community 58
- Community 59
- Community 60
- Community 61
- Community 62
- Community 63
- Community 64
- Community 65
- Community 66
- Community 68
- Community 69
- Community 70
- Community 71
- Community 72
- Community 73
- Community 74
- Community 75
- Community 76
- Community 77
- Community 78
- Community 79
- Community 80
- Community 81
- Community 82
- Community 83
- Community 84
- Community 85
- Community 86
- Community 89
- Community 90
- Community 91
- Community 92
- Community 93
- Community 94
- Community 95
- Community 96
- Community 97
- Community 100
- Community 101
- Community 102
- Community 104
- Community 105
- Community 106
- Community 107
- Community 108
- Community 109
- Community 110
- Community 111
- Community 112
- Community 113
- Community 116
- Community 117
- Community 118
- Community 119
- Community 120
- Community 121
- Community 122
- Community 123
- Community 124
- Community 125
- Community 126
- Community 127
- Community 128
- Community 129
- Community 130
- Community 131
- Community 132
- Community 133
- Community 134
- Community 135
- Community 136
- Community 137
- Community 138
- Community 139
- Community 140
- Community 141
- Community 142
- Community 143
- Community 144
- Community 145
- Community 147
- Community 148
- Community 149
- Community 150
- Community 151
- Community 152
- Community 153
- Community 154
- Community 155
- Community 156
- Community 157
- Community 158
- Community 159
- Community 160
- Community 161
- Community 162
- Community 163
- Community 185
- Community 186
- Community 187
- Community 188
- Community 189
- Community 190
- Community 191
- Community 192
- Community 193
- Community 194
- Community 195
- Community 196
- Community 197
- Community 199
- Community 200
- Community 201
- Community 202
- Community 203
- Community 204
- Community 266
- Community 267
- Community 268
- Community 269
- Community 270
- Community 271
- Community 272
- Community 273
- Community 274
- Community 275
- Community 276

## God Nodes (most connected - your core abstractions)
1. `Sensor` - 147 edges
2. `User` - 94 edges
3. `TestCase` - 63 edges
4. `SensorType` - 55 edges
5. `Device` - 54 edges
6. `SensorReading` - 54 edges
7. `Controller` - 53 edges
8. `SystemSetting` - 45 edges
9. `AlertRule` - 42 edges
10. `DomainEventOutbox` - 40 edges

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
- **Vue realtime/polling remediation targets (audit.md)** — code_front_src_components_layout_applayout, code_front_src_components_dashboard_activealertscard, code_front_src_components_dashboard_sensormonitorboard, code_front_src_realtime_echo, code_front_src_realtime_usealertsrealtime, code_front_src_realtime_usesensorrealtime [EXTRACTED 1.00]
- **ICONTEC/ISO normative compliance document set** — docs_icontec_compliance, docs_matriz_trazabilidad_icontec_iso, docs_evidencia_cumplimiento_codigo_estructura, docs_procedimiento_auditoria_normativa, docs_referencias_icontec, docs_plantilla_trabajo_icontec, documentacion_proyecto, analisis_proyecto [EXTRACTED 1.00]
- **Front/Back Migration Phase Roadmap (Fase 0 to Fase 8A)** — memory_05_migration_log_fase_0, memory_05_migration_log_fase_1, memory_05_migration_log_fase_2, memory_05_migration_log_fase_3, memory_05_migration_log_fase_4, memory_05_migration_log_fase_5, memory_05_migration_log_fase_6, memory_05_migration_log_fase_7, memory_05_migration_log_fase_7b, memory_05_migration_log_fase_8a [EXTRACTED 1.00]
- **SPA Auth & Security Boundary Design** — memory_01_architecture_decisions_adr_005_env_separation, memory_01_architecture_decisions_adr_006_api_only_communication, memory_01_architecture_decisions_adr_009_sanctum_bearer_auth, memory_15_security_review_sec_003_localstorage_bearer_token [INFERRED 0.85]

## Communities (277 total, 58 thin omitted)

### Community 0 - "Backend Client"
Cohesion: 0.06
Nodes (51): BackendClient, BaseModel, Client, BackendClient, BackendClientError, Any, Raised when backend ingestion endpoint cannot be reached or rejects payload., configure_logging() (+43 more)

### Community 1 - "Domain Events"
Cohesion: 0.07
Nodes (13): DeviceStatusLog, DomainEventOutbox, DomainEventPublisher, DomainOutboxRelay, AlertResolveTransitionTest, DeviceStatusChangeTransitionTest, DomainEventBroadcastConsumerTest, Connection (+5 more)

### Community 2 - "Device Models"
Cohesion: 0.05
Nodes (14): App\Events\DeviceCommunicationReceived, App\Models\DeviceType, App\Models\Lab, DeviceTypeController, LabController, DeviceController, Device, DeviceTypeController (+6 more)

### Community 3 - "Sensor Monitor UI"
Cohesion: 0.07
Nodes (43): addMonitor(), authStore, availableSensors(), chartViewModel(), currentLayout(), firstSelectableSensor(), graphSeriesQueryStore, handleDeviceChange() (+35 more)

### Community 4 - "Alert Models"
Cohesion: 0.15
Nodes (18): App\Mail\DangerAlertMail, App\Models\AlertRule, App\Models\Device, App\Models\DeviceStatusLog, App\Models\SensorReading, App\Models\SensorType, App\Models\SystemSetting, App\Models\User (+10 more)

### Community 5 - "Alert Events"
Cohesion: 0.09
Nodes (11): AlertResolved, HasEventEnvelope, VersionedDomainEvent, DeviceCommunicationReceived, NewAlertTriggered, Illuminate\Broadcasting\Channel, Illuminate\Broadcasting\InteractsWithSockets, Illuminate\Broadcasting\PresenceChannel (+3 more)

### Community 6 - "Alert Store"
Cohesion: 0.07
Nodes (28): alert(), countFor(), device(), deviceSensor(), installAuth(), json(), mockApi(), readings() (+20 more)

### Community 7 - "AlertRule Controller"
Cohesion: 0.08
Nodes (8): AlertRuleController, AlertRule, SensorReading, AlertRuleSeeder, AlertEmailTest, AlertRuleCascadeDeleteTest, SensorReadingAlertTest, SensorReadingTriggeredRulesTest

### Community 8 - "Sensor Controller"
Cohesion: 0.08
Nodes (6): Device, SensorController, Sensor, BroadcastChannelAuthorizationTest, IotApiKeyAccessTest, SensorApiControllerTest

### Community 9 - "Sensor API"
Cohesion: 0.11
Nodes (6): SensorApiController, SensorDataController, SensorTypeController, SensorResource, Illuminate\Http\Request, PHPUnit\Framework\Attributes\CoversClass

### Community 10 - "Test Factories"
Cohesion: 0.08
Nodes (12): AlertFactory, AlertRuleFactory, DeviceFactory, DeviceStatusLogFactory, DeviceTypeFactory, DomainEventOutboxFactory, LabFactory, RawSensorEventFactory (+4 more)

### Community 11 - "Health/Feed Controllers"
Cohesion: 0.09
Nodes (17): AlertFeedController, HealthController, ProfileController, ConfirmPasswordController, ForgotPasswordController, LoginController, ResetPasswordController, VerificationController (+9 more)

### Community 12 - "User Roles"
Cohesion: 0.09
Nodes (7): UserRoleController, User, AdminAccessTest, AuthApiHeadlessTest, DashboardPreferenceControllerTest, SpaParityApiTest, Illuminate\Foundation\Auth\User

### Community 13 - "App Layout"
Cohesion: 0.07
Nodes (25): alertsStore, authStore, refreshActiveAlerts(), startGlobalAlerts(), { subscribeAlerts, unsubscribeAlerts }, flush(), getActiveAlerts, getPublicConfig (+17 more)

### Community 14 - "Auth API"
Cohesion: 0.12
Nodes (6): App\Http\Requests\Api\UpdateAlertConfigRequest, AuthApiController, ConfigController, EmailConfigController, DashboardPreferenceController, Illuminate\Http\JsonResponse

### Community 15 - "API Tests"
Cohesion: 0.08
Nodes (10): ApiAuthTokenTest, ApiRoutingRegressionTest, DataIntegrityDuplicationTest, DeviceCreateTest, DeviceUpdateTest, ExampleTest, Phase7DockerReadinessTest, UserRoleManagementTest (+2 more)

### Community 16 - "Metrics"
Cohesion: 0.10
Nodes (11): InternalMetricsController, MetricsController, MetricsController, EnsureIngestionToken, TrackApiPerformance, ApiMetricsService, Closure, Illuminate\Http\Exceptions\HttpResponseException (+3 more)

### Community 17 - "IoT Data Script"
Cohesion: 0.12
Nodes (20): build_qc_checksum(), build_sensor_payload(), device_sender_worker(), get_api_key(), get_location(), get_node_id(), get_sensor_key(), get_sensors() (+12 more)

### Community 18 - "Dashboard API"
Cohesion: 0.10
Nodes (5): DashboardController, Device, DashboardMetricsService, DashboardMetricsServiceTest, Illuminate\Support\Facades\Cache

### Community 19 - "Password Reset"
Cohesion: 0.09
Nodes (13): MailMessage, ResetPasswordNotification, MailMessage, VerifyEmailNotification, PasswordResetTest, RegistrationEmailVerificationTest, Illuminate\Auth\Notifications\ResetPassword, Illuminate\Auth\Notifications\VerifyEmail (+5 more)

### Community 20 - "Community 20"
Cohesion: 0.11
Nodes (9): Alert, EventServiceProvider, AlertService, DateTimeInterface, PublicGraphSeriesService, Illuminate\Auth\Events\Registered, Illuminate\Auth\Listeners\SendEmailVerificationNotification, Illuminate\Foundation\Support\Providers\EventServiceProvider (+1 more)

### Community 21 - "Community 21"
Cohesion: 0.09
Nodes (22): authStore, closeForm(), defaultSensorForm(), deleteSelectedSensor(), devices, editingSensorId, error, filteredSensors (+14 more)

### Community 22 - "Community 22"
Cohesion: 0.11
Nodes (21): authStore, closeForm(), defaultDeviceForm(), deleteSelectedDevice(), deviceForm, devicePayload(), devices, deviceTypes (+13 more)

### Community 23 - "Community 23"
Cohesion: 0.11
Nodes (23): POST /api/ingestion/events, graphify-html-export agent, iot-back-implementador agent, CLAUDE.md (repo guidance), IngestionController, RawSensorEventPublisher, NavBar.vue, Eloquent::update() bulk calls do not fire observers (+15 more)

### Community 24 - "Community 24"
Cohesion: 0.11
Nodes (11): App\Http\Controllers\API\AuthApiController, App\Http\Controllers\Api\HealthController, App\Http\Controllers\Api\InternalMetricsController, App\Http\Controllers\Controller, App\Http\Resources\DeviceResource, IngestionController, PublicGraphController, StoreRawIngestionEventRequest (+3 more)

### Community 25 - "Community 25"
Cohesion: 0.13
Nodes (6): StoreAlertRuleRequest, UpdateAlertConfigRequest, UpdateAlertRuleRequest, UpdateEmailConfigRequest, Illuminate\Foundation\Http\FormRequest, Illuminate\Validation\Validator

### Community 26 - "Community 26"
Cohesion: 0.09
Nodes (19): adminActions, alertErrors, alertForm, alertPayload(), authStore, emailErrors, emailForm, emailPayload() (+11 more)

### Community 27 - "Community 27"
Cohesion: 0.26
Nodes (16): ANALISIS_PROYECTO.md (analisis tecnico y de cumplimiento), EnsureUserIsAdmin middleware, TrackApiPerformance middleware, AppServiceProvider (rate limiters), DashboardMetricsService, DashboardMetricsServiceTest constructor non-conformity, ICONTEC/ISO alignment (not certification) declaration policy, NTC 1486 (presentacion de trabajos escritos) (+8 more)

### Community 28 - "Community 28"
Cohesion: 0.18
Nodes (8): UsesRawRedisCommands, Dotenv\Dotenv, Illuminate\Redis\Connections\Connection, Illuminate\Support\Facades\DB, Illuminate\Support\Facades\Log, Illuminate\Support\Facades\Redis, RuntimeException, Throwable

### Community 29 - "Community 29"
Cohesion: 0.12
Nodes (17): catalogConfigs, config, deletingId, editingId, error, errors, form, items (+9 more)

### Community 30 - "Community 30"
Cohesion: 0.13
Nodes (6): AlertController, Alert, HomeController, AlertLifecycleService, Alert, Controller

### Community 31 - "Community 31"
Cohesion: 0.17
Nodes (5): DomainEventRecorder, SensorReadingProjectionService, SensorReadingService, Carbon\Carbon, DateTimeInterface

### Community 32 - "Community 32"
Cohesion: 0.12
Nodes (18): DeviceStatusUpdated event, NewAlertTriggered event, NewSensorReading event, EmailConfigController, SensorController, DangerAlertMail, Alert model, AlertObserver (+10 more)

### Community 33 - "Community 33"
Cohesion: 0.18
Nodes (19): ALERTS_CHANNEL, ALERTS_EVENT, ALERTS_EVENT_CLASS, CONNECTION_ERROR_MESSAGES, error, getEventPayload(), isConnected, isRealtimeEnabled (+11 more)

### Community 34 - "Community 34"
Cohesion: 0.13
Nodes (19): closeModal(), deleteRule(), deletingId, error, formError, load(), loading, loadMetadata() (+11 more)

### Community 35 - "Community 35"
Cohesion: 0.12
Nodes (7): RegisterController, Illuminate\Auth\Events\PasswordReset, Illuminate\Auth\Events\Verified, Illuminate\Foundation\Auth\RegistersUsers, Illuminate\Support\Facades\Hash, Illuminate\Support\Facades\Validator, Illuminate\Support\Str

### Community 36 - "Community 36"
Cohesion: 0.30
Nodes (10): QueueSmokeJob, RelayDomainOutboxJob, RelayRawOutboxJob, SendDangerAlertEmailJob, UpdateDeviceLastCommunication, Illuminate\Bus\Queueable, Illuminate\Contracts\Queue\ShouldQueue, Illuminate\Foundation\Bus\Dispatchable (+2 more)

### Community 37 - "Community 37"
Cohesion: 0.20
Nodes (4): DashboardPreference, RawEventOutbox, Illuminate\Database\Eloquent\Factories\HasFactory, Illuminate\Database\Eloquent\Model

### Community 38 - "Community 38"
Cohesion: 0.16
Nodes (5): SensorTypeController, SensorType, AlertRuleNameTest, Illuminate\Foundation\Http\Middleware\VerifyCsrfToken, Illuminate\Support\Carbon

### Community 39 - "Community 39"
Cohesion: 0.11
Nodes (15): activeAlertsCard, alertsRealtime, alertsStore, alertsView, alertToast, appLayout, echo, envExample (+7 more)

### Community 40 - "Community 40"
Cohesion: 0.18
Nodes (15): booleanEnv(), createEcho(), disconnectEcho(), getEcho(), getEchoConfig(), handleAuthChange(), ADR-0002, notify() (+7 more)

### Community 41 - "Community 41"
Cohesion: 0.23
Nodes (5): RawSensorEvent, RawEventOutboxFactory, RawReadingNormalizerTest, RawSensorEventIdempotencyTest, Illuminate\Database\QueryException

### Community 42 - "Community 42"
Cohesion: 0.15
Nodes (6): AppServiceProvider, ViewServiceProvider, SecurityRateLimitTest, Illuminate\Cache\RateLimiting\Limit, Illuminate\Support\Facades\RateLimiter, Illuminate\Support\ServiceProvider

### Community 43 - "Community 43"
Cohesion: 0.12
Nodes (17): devDependencies, axios, concurrently, laravel-vite-plugin, @popperjs/core, sass, tailwindcss, @tailwindcss/vite (+9 more)

### Community 45 - "Community 45"
Cohesion: 0.15
Nodes (12): __dirname, fakeEchoPlugin(), main(), record(), results, root, __dirname, fakeEchoPlugin() (+4 more)

### Community 46 - "Community 46"
Cohesion: 0.23
Nodes (5): App\Http\Resources\AlertResource, App\Services\Alerts\AlertService, AlertController, Alert, AlertResource

### Community 47 - "Community 47"
Cohesion: 0.23
Nodes (3): ConsumeRawEvents, RawReadingNormalizer, RawStreamConsumer

### Community 49 - "Community 49"
Cohesion: 0.14
Nodes (13): authStore, devices, error, graphDevices, latestReadings, loading, summary, flush() (+5 more)

### Community 50 - "Community 50"
Cohesion: 0.15
Nodes (14): error, exporting, exportReadings(), filtering, filterReadings(), filters, load(), loading (+6 more)

### Community 52 - "Community 52"
Cohesion: 0.23
Nodes (14): GET /api/alerts/active, audit.md — architecture audit and implementation plan, iot-front-implementador agent, AlertController (API), ActiveAlertsCard.vue, SensorMonitorBoard.vue, AppLayout.vue, echo.js (Echo singleton) (+6 more)

### Community 53 - "Community 53"
Cohesion: 0.14
Nodes (11): App\Http\Controllers\AlertRuleController, App\Http\Controllers\ConfigController, App\Http\Controllers\DashboardPreferenceController, App\Http\Controllers\DeviceTypeController, App\Http\Controllers\EmailConfigController, App\Http\Controllers\LabController, App\Http\Controllers\MetricsController, App\Http\Controllers\SensorTypeController (+3 more)

### Community 54 - "Community 54"
Cohesion: 0.14
Nodes (14): axios, bootstrap, bootstrap, dependencies, axios, bootstrap, pinia, vue (+6 more)

### Community 55 - "Community 55"
Cohesion: 0.24
Nodes (3): DeviceApiController, Device, DeviceResource

### Community 56 - "Community 56"
Cohesion: 0.21
Nodes (3): EmailConfigController, SystemSetting, SystemSettingTest

### Community 58 - "Community 58"
Cohesion: 0.14
Nodes (14): scripts, dev, post-autoload-dump, post-root-package-install, post-update-cmd, test, Composer\\Config::disableProcessTimeout, Illuminate\\Foundation\\ComposerScripts::postAutoloadDump (+6 more)

### Community 59 - "Community 59"
Cohesion: 0.20
Nodes (6): AlertSeeder, DeviceTypeSeeder, SensorTypeSeeder, SystemSettingsSeeder, Illuminate\Database\Console\Seeds\WithoutModelEvents, Illuminate\Database\Seeder

### Community 60 - "Community 60"
Cohesion: 0.21
Nodes (12): GET /api/iot/sensors, POST /api/sensors/{sensor}/readings, PurgeFutureSensorReadings command, DeviceApiController, SensorApiController, SensorReading model, SensorReadingObserver, docs/api/openapi.yaml (IoT Platform API spec) (+4 more)

### Community 61 - "Community 61"
Cohesion: 0.27
Nodes (4): App\Models\Alert, AlertObserver, NotificationService, Alert

### Community 62 - "Community 62"
Cohesion: 0.21
Nodes (5): ConsumeDomainEvents, InspectReadingTimeSemantics, PurgeFutureSensorReadings, RelayDomainOutbox, Illuminate\Console\Command

### Community 63 - "Community 63"
Cohesion: 0.19
Nodes (3): AlertRuleController, AlertRuleResource, Illuminate\Http\Resources\Json\JsonResource

### Community 64 - "Community 64"
Cohesion: 0.37
Nodes (3): Connection, RedisManager, RawStreamConsumerTest

### Community 65 - "Community 65"
Cohesion: 0.15
Nodes (13): devDependencies, jsdom, @playwright/test, sass, vite, @vitejs/plugin-vue, vitest, jsdom (+5 more)

### Community 66 - "Community 66"
Cohesion: 0.15
Nodes (13): scripts, audit:baseline, audit:events, audit:network, build, dev, preview, test:phase4 (+5 more)

### Community 68 - "Community 68"
Cohesion: 0.23
Nodes (12): onConnectionStateChange(), onResync(), ADR-0002, normalizeReading(), RECOVERY_WINDOW_MS, resolveSensorId(), SENSOR_EVENT, SENSOR_EVENT_CLASS (+4 more)

### Community 69 - "Community 69"
Cohesion: 0.18
Nodes (11): alerts, alertsStore, error, filter, handleResolve(), handleResolveAll(), load(), loading (+3 more)

### Community 71 - "Community 71"
Cohesion: 0.20
Nodes (3): UpdateAlertRulesTable, UpdateAlertRulesSeverity, Illuminate\Database\Migrations\Migration

### Community 72 - "Community 72"
Cohesion: 0.17
Nodes (10): alertsStore, currentAlert, currentDevice, currentMessage, currentSeverity, currentTime, currentTitle, currentValue (+2 more)

### Community 73 - "Community 73"
Cohesion: 0.25
Nodes (3): RelayRawOutbox, RawOutboxRelay, RawSensorEventPublisher

### Community 74 - "Community 74"
Cohesion: 0.18
Nodes (5): SecurityPrivilegeEscalationTest, Illuminate\Auth\Access\AuthorizationException, Illuminate\Contracts\Auth\MustVerifyEmail, Illuminate\Notifications\Notifiable, Laravel\Sanctum\HasApiTokens

### Community 75 - "Community 75"
Cohesion: 0.18
Nodes (10): autoload-dev, psr-4, description, license, minimum-stability, name, prefer-stable, Tests\\ (+2 more)

### Community 76 - "Community 76"
Cohesion: 0.18
Nodes (11): Front SPA HTML Entrypoint, ADR-001: Keep A Monorepo Initially, ADR-008: Frontend Framework (Vue 3 + Bootstrap 5, proposed), Vite (laravel-vite-plugin), Migration Hygiene Rules, Phase 3 Frontend Consumer Notes, Fase 0: Read-only Audit and Real Contract, Fase 1: Headless Auth (Sanctum Bearer) (+3 more)

### Community 77 - "Community 77"
Cohesion: 0.18
Nodes (10): dependencies, emailVerification, envExample, missing, missingDependencies, packageJson, requiredDependencies, requiredFiles (+2 more)

### Community 81 - "Community 81"
Cohesion: 0.22
Nodes (8): accessibleSummary, chartData, chartOptions, hasData, props, renderChart(), sampleViewModel, tokens

### Community 83 - "Community 83"
Cohesion: 0.22
Nodes (9): require, laravel/framework, laravel/sanctum, laravel/tinker, laravel/ui, php, predis/predis, pusher/pusher-php-server (+1 more)

### Community 84 - "Community 84"
Cohesion: 0.22
Nodes (9): dependencies, laravel-echo, lightweight-charts, pusher-js, laravel-echo, pusher-js, laravel-echo, lightweight-charts (+1 more)

### Community 85 - "Community 85"
Cohesion: 0.44
Nodes (3): Device, SensorPublicMonitoringAdminTest, User

### Community 86 - "Community 86"
Cohesion: 0.22
Nodes (8): __dirname, envExample, __filename, forbiddenKeys, frontDockerfile, frontRoot, repoRoot, requiredFiles

### Community 89 - "Community 89"
Cohesion: 0.28
Nodes (5): app, authStore, pinia, router, useAuthStore

### Community 90 - "Community 90"
Cohesion: 0.28
Nodes (6): emit, filteredSensors, form, nullableNumber(), props, submit()

### Community 91 - "Community 91"
Cohesion: 0.22
Nodes (6): alerts, alertsStore, count, error, loading, updateMode

### Community 92 - "Community 92"
Cohesion: 0.28
Nodes (4): activeControllers, adaptLatestReadings(), computeStats(), useGraphSeriesQueryStore

### Community 93 - "Community 93"
Cohesion: 0.22
Nodes (6): alert, error, loading, props, resolving, success

### Community 94 - "Community 94"
Cohesion: 0.22
Nodes (9): Ingestion Service (Python), pydantic (Python library), pytest (Python library), python-dotenv (Python library), requests (Python library), script_datos.py (Python IoT Simulator), Operational Risks, SEC-002: Global Legacy IoT API Key (+1 more)

### Community 95 - "Community 95"
Cohesion: 0.25
Nodes (8): require-dev, fakerphp/faker, laravel/pail, laravel/pint, laravel/sail, mockery/mockery, nunomaduro/collision, phpunit/phpunit

### Community 100 - "Community 100"
Cohesion: 0.36
Nodes (7): close(), dialogEl, emit, focusableElements(), onKeydown(), props, titleId

### Community 101 - "Community 101"
Cohesion: 0.25
Nodes (6): authStore, devices, error, loading, props, visibleDevices

### Community 102 - "Community 102"
Cohesion: 0.36
Nodes (6): channelKey(), getChannelRefCount(), leaveChannelName(), listenOnChannel(), refCounts, echoMock

### Community 104 - "Community 104"
Cohesion: 0.25
Nodes (6): authStore, error, loading, resending, route, success

### Community 105 - "Community 105"
Cohesion: 0.29
Nodes (7): error, load(), loading, savingId, success, toggleRole(), users

### Community 106 - "Community 106"
Cohesion: 0.29
Nodes (8): Phase 8A API Additions (CRUD/Admin), Fase 7B: Operational Validation Before Blade Cleanup, Fase 8A: Admin CRUD/Catalog SPA Migration, QueueSmokeJob (queue infrastructure smoke test), High Risks, Fase 6B: Blade Legacy Cleanup and Production SPA Adjustment (Pending), Fase 8: Final Cleanup (Pending), Session Progress: Fase 8A Work Log

### Community 109 - "Community 109"
Cohesion: 0.29
Nodes (7): pestphp/pest-plugin, php-http/discovery, config, allow-plugins, optimize-autoloader, preferred-install, sort-packages

### Community 112 - "Community 112"
Cohesion: 0.29
Nodes (6): allowScripts, esbuild@0.21.5, name, private, type, version

### Community 113 - "Community 113"
Cohesion: 0.29
Nodes (6): missing, navbar, requiredFiles, root, router, srcFilesToCheck

### Community 116 - "Community 116"
Cohesion: 0.29
Nodes (5): error, form, loading, route, success

### Community 117 - "Community 117"
Cohesion: 0.29
Nodes (5): device, error, loading, props, sensors

### Community 118 - "Community 118"
Cohesion: 0.29
Nodes (5): error, generatedAt, loading, metricCards, snapshot

### Community 119 - "Community 119"
Cohesion: 0.29
Nodes (7): ADR-003: Do Not Move Blade Directly To /front, Blade Views (Legacy UI), Functional State: Blade Legacy, Legacy Views Still Dependent on Blade, Lost or Not-Found Features, Manual Validation Checklist (Not Yet Executed), Blade Cleanup Readiness Verdict: NO-GO

### Community 120 - "Community 120"
Cohesion: 0.33
Nodes (7): ADR-007: Docker Per Service, Docker/Docker Compose (absent at audit time), Phase 6 Physical Separation Notes, Phase 7 Docker Consumer Notes, Fase 6: Physical Separation Into /back, Fase 7: Docker and Compose, Functional State: Docker

### Community 121 - "Community 121"
Cohesion: 0.29
Nodes (7): Pusher / Laravel Echo Realtime, Broadcast Event: NewAlertTriggered (channel alerts), Broadcast Event: NewSensorReading (channel sensor.{id}), Phase 4 Frontend Consumer Notes, Phase 5 Realtime Contract Notes, Fase 4: Progressive Screen Migration, Fase 5: Realtime (Echo/Pusher) Integration

### Community 122 - "Community 122"
Cohesion: 0.33
Nodes (4): Illuminate\Foundation\Application, Illuminate\Foundation\Configuration\Exceptions, Illuminate\Foundation\Configuration\Middleware, Symfony\Component\HttpKernel\Exception\BadRequestHttpException

### Community 123 - "Community 123"
Cohesion: 0.33
Nodes (5): private, scripts, build, dev, type

### Community 124 - "Community 124"
Cohesion: 0.33
Nodes (6): chart.js, chart.js, chart.js, Functional State: Frontend, Functional Inventory: Frontend SPA Table, Partially Migrated Features

### Community 128 - "Community 128"
Cohesion: 0.33
Nodes (5): clean, __dirname, filter, lines, summary

### Community 129 - "Community 129"
Cohesion: 0.33
Nodes (3): connectionWatchers, echoMock, resyncWatchers

### Community 131 - "Community 131"
Cohesion: 0.53
Nodes (4): FALLBACK_TOKENS, hexToRgba(), readCssVar(), resolveChartTokens()

### Community 132 - "Community 132"
Cohesion: 0.33
Nodes (4): email, error, loading, success

### Community 133 - "Community 133"
Cohesion: 0.33
Nodes (4): authStore, form, route, router

### Community 134 - "Community 134"
Cohesion: 0.33
Nodes (4): error, form, loading, success

### Community 135 - "Community 135"
Cohesion: 0.33
Nodes (6): Backend Ingestion Contract (POST /api/ingestion/events), data_jobs_service (future consumer, not implemented), MQTT Mode (mqtt_client.py), raw_sensor_events Processing Flow, Simulate Mode (python -m app.main --simulate), paho-mqtt (Python library)

### Community 136 - "Community 136"
Cohesion: 0.33
Nodes (6): iot-platform-v2 Project, Incremental Migration Principle, Front/Back Separation Goal, ADR-002: Backend As REST API + Broadcasting, ADR-004: Incremental Migration, Laravel 12 / PHP 8.2+ Backend

### Community 139 - "Community 139"
Cohesion: 0.40
Nodes (5): autoload, psr-4, App\\, Database\\Factories\\, Database\\Seeders\\

### Community 140 - "Community 140"
Cohesion: 0.40
Nodes (4): Monolog\Handler\NullHandler, Monolog\Handler\StreamHandler, Monolog\Handler\SyslogUdpHandler, Monolog\Processor\PsrLogMessageProcessor

### Community 141 - "Community 141"
Cohesion: 0.40
Nodes (3): chart, lineSeries, lineSeriesDef

### Community 148 - "Community 148"
Cohesion: 0.50
Nodes (3): metrics, props, renderMetricsCards()

### Community 149 - "Community 149"
Cohesion: 0.50
Nodes (3): props, renderChart(), viewModel

### Community 150 - "Community 150"
Cohesion: 0.50
Nodes (3): props, renderChart(), viewModel

### Community 151 - "Community 151"
Cohesion: 0.40
Nodes (4): connectionWatchers, fetchActiveAlerts, listenOnChannel, resyncWatchers

### Community 152 - "Community 152"
Cohesion: 0.60
Nodes (3): normalizeReading(), normalizeReadings(), useSensorReadingsStore

### Community 153 - "Community 153"
Cohesion: 0.40
Nodes (3): error, loading, profile

### Community 154 - "Community 154"
Cohesion: 0.50
Nodes (5): ADR-009: SPA Auth Uses Sanctum Bearer Tokens Initially, Laravel Sanctum, Authentication Strategy: Sanctum Bearer Tokens, Bearer Token in localStorage Risk, SEC-003: Bearer Token in localStorage

### Community 155 - "Community 155"
Cohesion: 0.50
Nodes (4): post-create-project-cmd, @php artisan key:generate --ansi, @php artisan migrate --graceful --ansi, @php -r \"file_exists('database/database.sqlite') || touch('database/database.sqlite');\

### Community 160 - "Community 160"
Cohesion: 0.83
Nodes (3): getAudioContext(), playAlertSound(), unlockAlertSound()

### Community 162 - "Community 162"
Cohesion: 0.67
Nodes (3): extra, laravel, dont-discover

### Community 163 - "Community 163"
Cohesion: 0.67
Nodes (3): keywords, framework, laravel

### Community 187 - "Community 187"
Cohesion: 0.67
Nodes (3): User model, ResetPasswordNotification, VerifyEmailNotification

### Community 193 - "Community 193"
Cohesion: 0.67
Nodes (3): ADR-005: Separate Environment Variables, ADR-006: API-Only Frontend Communication, FIX-001: front/.env Configured With Only Public VITE_* Vars

### Community 194 - "Community 194"
Cohesion: 0.67
Nodes (3): Functional State: Backend, Functional Inventory: Backend API Table, Conserved Features (Blade to Vue Parity)

## Ambiguous Edges - Review These
- `NavBar.vue` → `State ownership target: WS -> Pinia -> components`  [AMBIGUOUS]
  audit.md · relation: conceptually_related_to
- `graphify-html-export agent` → `CLAUDE.md (repo guidance)`  [AMBIGUOUS]
  .claude/agents/graphify-html-export.md · relation: semantically_similar_to
- `NewSensorReading event` → `AlertService`  [AMBIGUOUS]
  INFORME_ALERTAS_NOTIFICACIONES.md · relation: calls

## Knowledge Gaps
- **479 isolated node(s):** `DashboardPreferenceController`, `dialogEl`, `props`, `titleId`, `authStore` (+474 more)
  These have ≤1 connection - possible missing edges or undocumented components. (Counts symbols only; 1000 node(s) total have ≤1 connection when file, concept and rationale nodes are included.)
- **58 thin communities (<3 nodes) omitted from report** — run `graphify query` to explore isolated nodes.

## Suggested Questions
_Questions this graph is uniquely positioned to answer:_

- **What is the exact relationship between `NavBar.vue` and `State ownership target: WS -> Pinia -> components`?**
  _Edge tagged AMBIGUOUS (relation: conceptually_related_to) - confidence is low._
- **What is the exact relationship between `graphify-html-export agent` and `CLAUDE.md (repo guidance)`?**
  _Edge tagged AMBIGUOUS (relation: semantically_similar_to) - confidence is low._
- **What is the exact relationship between `NewSensorReading event` and `AlertService`?**
  _Edge tagged AMBIGUOUS (relation: calls) - confidence is low._
- **Why does `Sensor` connect `Sensor Controller` to `Domain Events`, `Alert Models`, `AlertRule Controller`, `Sensor API`, `User Roles`, `Community 142`, `Community 143`, `API Tests`, `Community 144`, `Dashboard API`, `Community 145`, `Community 20`, `Community 24`, `Community 25`, `Community 28`, `Community 156`, `Community 31`, `Community 37`, `Community 38`, `Community 41`, `Community 42`, `Community 44`, `Community 47`, `Community 51`, `Community 56`, `Community 62`, `Community 63`, `Community 64`, `Community 70`, `Community 82`, `Community 85`, `Community 96`, `Community 97`, `Community 108`, `Community 127`?**
  _High betweenness centrality (0.071) - this node is a cross-community bridge._
- **Why does `User` connect `User Roles` to `Community 35`, `Community 37`, `Community 38`, `Community 137`, `Community 74`, `Health/Feed Controllers`, `Auth API`, `Community 78`, `Community 143`, `API Tests`, `Community 110`, `Password Reset`, `Community 111`, `Community 144`, `Community 126`?**
  _High betweenness centrality (0.025) - this node is a cross-community bridge._
- **Why does `DomainEventOutbox` connect `Domain Events` to `Alert Models`, `Community 37`, `Sensor Controller`, `Test Factories`, `Community 57`, `Community 28`, `Community 31`?**
  _High betweenness centrality (0.022) - this node is a cross-community bridge._
- **What connects `DashboardPreferenceController`, `dialogEl`, `props` to the rest of the system?**
  _479 weakly-connected nodes found - possible documentation gaps or missing edges._