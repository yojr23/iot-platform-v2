# Graph Report - iot-platform-v2  (2026-09-11)

## Corpus Check
- cluster-only mode — file stats not available

## Summary
- 2804 nodes · 5509 edges · 270 communities (124 shown, 48 thin omitted)
- Extraction: 99% EXTRACTED · 1% INFERRED · 0% AMBIGUOUS · INFERRED: 80 edges (avg confidence: 0.86)
- Token cost: 0 input · 0 output

## Graph Freshness
- Built from commit: `979e3414`
- Run `git rev-parse HEAD` and compare to check if the graph is stale.
- Run `graphify update .` after code changes (no API cost).

## Community Hubs (Navigation)
- DomainEventOutbox
- main.py
- Illuminate\Support\Facades\Log
- User
- Illuminate\Database\Eloquent\Factories\Factory
- EventPipelineMetricsService
- TestCase
- src/views/SensorDetailView.vue
- run.mjs
- Illuminate\Http\Request
- Api/AlertRuleController.php
- src/views/DevicesView.vue
- User.php
- Controller
- src/views/CatalogAdminView.vue
- index.js
- NewAlertTriggered
- SystemSetting
- SensorType
- Sensor
- Illuminate\Foundation\Testing\RefreshDatabase
- SensorMonitorBoard.vue
- ApiMetricsService
- script_datos.py
- Alert
- StoreAlertRuleRequest
- SensorReading
- PublicGraphControllerTest
- Device
- src/views/SensorsView.vue
- AlertRule
- useLabWorkspace
- useAlertsRealtime.js
- src/views/DeviceDetailView.vue
- src/views/AlertRulesView.vue
- RawSensorEvent
- event-injection.mjs
- SensorReadingChart.vue
- src/views/AlertsView.vue
- AlertService
- SendDangerAlertEmailJob
- dependencies
- verify-phase5.mjs
- echo.js
- DeviceType
- Lab
- SecurityRateLimitTest.php
- Illuminate\Database\Seeder
- devDependencies
- useDeviceStatusRealtime.test.js
- src/views/MetricsView.vue
- scripts
- client.js
- NavBar.vue
- DeviceService
- RawStreamConsumerTest
- devDependencies
- useDeviceStatusRealtime.js
- RawReadingNormalizer
- DashboardMetricsService
- scripts
- src/views/config/EmailConfigView.vue
- DashboardView.test.js
- NewSensorReading
- AlertResolved
- RuntimeException
- SensorReadingService
- EventServiceProvider.php
- Illuminate\Database\Migrations\Migration
- dependencies
- MigrationIntegrityTest
- AlertToast.vue
- graphZonesProjection.js
- ActiveAlertsCard.vue
- useSensorRealtime.js
- useAlertsRealtime.test.js
- AlertController
- composer.json
- Gate10PublicGraphBoundaryTest
- SensorDataControllerTest.php
- network-assertion-live.mjs
- verify-no-polling.mjs
- verify-phase3.mjs
- layout/AppLayout.vue
- src/views/config/AlertConfigView.vue
- src/views/ConfigView.vue
- DeviceController
- RawStreamConsumer
- Illuminate\Support\Facades\Schema
- Illuminate\Database\Schema\Blueprint
- ApiAuthTokenTest
- BroadcastChannelAuthorizationTest
- IngestionApiTest
- AlertItem.vue
- AppLayout.test.js
- chartTheme.js
- src/views/config/GeneralConfigView.vue
- require
- verify-phase7.mjs
- AlertRuleForm.vue
- LabShell.vue
- SensorMonitorBoard.test.js
- src/views/AlertDetailView.vue
- require-dev
- DatabaseSeeder
- Gate10NoPollingArchitectureTest
- ReadingTimeSemanticsTest
- SensorPublicMonitoringAdminTest
- BaseModal.vue
- channelRegistry.js
- useSensorRealtime.test.js
- graphSeriesQuery.js
- src/views/auth/EmailVerificationView.vue
- HasEventEnvelope
- RegisterController
- config
- AlertTransportAuthorizationTest
- ConfigGeneralUpdateTest
- EventPipelineMetricsServiceTest
- SecurityAccessControlTest
- front/package.json
- verify-phase4.mjs
- graphSeriesProjection.js
- src/views/auth/ResetPasswordView.vue
- SensorsView.test.js
- bootstrap/app.php
- back/package.json
- DeviceApiKeyVisibilityTest
- DeviceApiPaginationTest
- PublisherStreamPrefixTest
- SensorGraphZonesTest
- run-all.mjs
- graph.js
- src/views/auth/ForgotPasswordView.vue
- src/views/auth/LoginView.vue
- src/views/auth/RegisterView.vue
- src/views/config/DiagnosticsConfigView.vue
- ConfigView.test.js
- PublicGraphController
- psr-4
- logging.php
- bootstrap.js
- .makeDangerRuleSensor
- SecuritySqlInjectionTest.php
- MetricsCards.vue
- SensorChart.vue
- SensorReadingsChart.vue
- front/src/stores/alerts.js
- sensorReadings.js
- UserRoleController
- post-create-project-cmd
- AlertRuleNameTest.php
- .makeDangerAlert
- LabIcon.vue
- widgetState
- sound.js
- extra
- keywords
- app.blade.php
- console.php
- live-browser-check.mjs
- BaseInput.vue
- sensorChartViewModel.js
- deviceStatuses.js
- legacy.js
- Api/DashboardPreferenceController.php
- sanctum.php
- channels.php
- capture-lab-demo.mjs
- AlertFilters.vue
- 01-create-cdc-user.sh
- __init__.py

## God Nodes (most connected - your core abstractions)
1. `Sensor` - 193 edges
2. `User` - 177 edges
3. `Device` - 163 edges
4. `TestCase` - 145 edges
5. `SensorReading` - 87 edges
6. `Alert` - 82 edges
7. `SensorType` - 70 edges
8. `Controller` - 69 edges
9. `AlertRule` - 68 edges
10. `DomainEventOutbox` - 56 edges

## Surprising Connections (you probably didn't know these)
- `DomainEventBroadcastConsumer` --mixes_in--> `UsesRawRedisCommands`  [EXTRACTED]
  back/app/Services/Ingestion/DomainEventBroadcastConsumer.php → back/app/Services/Ingestion/Concerns/UsesRawRedisCommands.php
- `DomainEventBroadcastConsumer` --references--> `PublicGraphVisibility`  [EXTRACTED]
  back/app/Services/Ingestion/DomainEventBroadcastConsumer.php → back/app/Services/Monitoring/PublicGraphVisibility.php
- `DeviceStatusChangeTransitionTest` --inherits--> `TestCase`  [EXTRACTED]
  back/tests/Feature/DeviceStatusChangeTransitionTest.php → back/tests/TestCase.php
- `DomainEventBroadcastConsumerTest` --inherits--> `TestCase`  [EXTRACTED]
  back/tests/Feature/DomainEventBroadcastConsumerTest.php → back/tests/TestCase.php
- `MQTTIngestionClient` --uses--> `BackendClient`  [INFERRED]
  ingestion_service/app/mqtt_client.py → ingestion_service/app/backend_client.py

## Import Cycles
- None detected.

## Communities (270 total, 48 thin omitted)

### Community 0 - "DomainEventOutbox"
Cohesion: 0.07
Nodes (11): DashboardPreference, DeviceStatusLog, DomainEventOutbox, RawEventOutbox, DomainEventBroadcastConsumer, DeviceStatusChangeTransitionTest, DomainEventBroadcastConsumerTest, Connection (+3 more)

### Community 1 - "main.py"
Cohesion: 0.07
Nodes (49): BaseModel, Client, BackendClient, BackendClientError, Any, Raised when backend ingestion endpoint cannot be reached or rejects payload., configure_logging(), main() (+41 more)

### Community 2 - "Illuminate\Support\Facades\Log"
Cohesion: 0.07
Nodes (20): ConsumeCdcOutboxes, UsesRawRedisCommands, DomainEventPublisher, RawSensorEventPublisher, CountingDomainPublisher, CountingRawPublisher, RedisManager, Carbon\Carbon (+12 more)

### Community 3 - "User"
Cohesion: 0.06
Nodes (9): User, AdminAccessTest, AlertRuleValidationTest, AuthApiHeadlessTest, DashboardPreferenceControllerTest, DeviceApiStatusUpdateTest, Phase2ApiEndpointsTest, SpaParityApiTest (+1 more)

### Community 4 - "Illuminate\Database\Eloquent\Factories\Factory"
Cohesion: 0.05
Nodes (16): AlertFactory, AlertRuleFactory, DeviceFactory, DeviceStatusLogFactory, DeviceTypeFactory, DomainEventOutboxFactory, LabFactory, RawEventOutboxFactory (+8 more)

### Community 5 - "EventPipelineMetricsService"
Cohesion: 0.11
Nodes (6): CdcOutboxStreamConsumer, EventPipelineMetricsService, Connection, CdcOutboxStreamConsumerTest, Connection, self

### Community 6 - "TestCase"
Cohesion: 0.05
Nodes (13): ConfigSystemInfoTest, DataIntegrityDuplicationTest, DeviceCreateTest, DeviceUpdateTest, ExampleTest, IotApiKeyAccessTest, Phase7DockerReadinessTest, PublicDashboardEntryPointTest (+5 more)

### Community 7 - "src/views/SensorDetailView.vue"
Cohesion: 0.05
Nodes (33): authStore, props, readingsStore, subscriptions, mountedApps, mountList(), onReadingBySensor, subscribeSensor (+25 more)

### Community 8 - "run.mjs"
Cohesion: 0.06
Nodes (30): alert(), countFor(), device(), deviceSensor(), installAuth(), json(), mockApi(), readings() (+22 more)

### Community 9 - "Illuminate\Http\Request"
Cohesion: 0.10
Nodes (5): AuthApiController, ProfileController, SensorApiController, SensorDataController, Illuminate\Http\Request

### Community 10 - "Api/AlertRuleController.php"
Cohesion: 0.08
Nodes (10): AlertRuleController, DeviceApiController, UpdateAlertRuleRequest, AlertRuleResource, DeviceResource, DeviceStatusSnapshotResource, SensorResource, Illuminate\Http\Resources\Json\JsonResource (+2 more)

### Community 11 - "src/views/DevicesView.vue"
Cohesion: 0.06
Nodes (36): applyDevicesPage(), authStore, closeForm(), defaultDeviceForm(), deleteSelectedDevice(), deviceForm, devicePayload(), devices (+28 more)

### Community 12 - "User.php"
Cohesion: 0.06
Nodes (18): MailMessage, ResetPasswordNotification, MailMessage, VerifyEmailNotification, PasswordResetTest, RegistrationEmailVerificationTest, SecurityPrivilegeEscalationTest, Illuminate\Auth\Access\AuthorizationException (+10 more)

### Community 13 - "Controller"
Cohesion: 0.07
Nodes (18): AlertFeedController, HealthController, ConfirmPasswordController, ForgotPasswordController, LoginController, ResetPasswordController, VerificationController, Controller (+10 more)

### Community 14 - "src/views/CatalogAdminView.vue"
Cohesion: 0.06
Nodes (30): apps, flush(), mount(), catalogConfigs, config, deletingId, editingId, error (+22 more)

### Community 15 - "index.js"
Cohesion: 0.06
Nodes (5): app, authStore, pinia, router, useAuthStore

### Community 16 - "NewAlertTriggered"
Cohesion: 0.11
Nodes (12): VersionedDomainEvent, DeviceCommunicationReceived, DeviceStatusUpdated, NewAlertTriggered, Illuminate\Broadcasting\Channel, Illuminate\Broadcasting\InteractsWithSockets, Illuminate\Broadcasting\PresenceChannel, Illuminate\Broadcasting\PrivateChannel (+4 more)

### Community 17 - "SystemSetting"
Cohesion: 0.10
Nodes (8): ConfigController, EmailConfigController, ConfigController, DashboardPreferenceController, EmailConfigController, SystemSetting, Illuminate\Http\JsonResponse, Illuminate\Support\Facades\Route

### Community 18 - "SensorType"
Cohesion: 0.09
Nodes (7): SensorTypeController, SensorTypeController, SensorType, DangerAlertEmailTest, AlertUniqueConstraintTest, EventEnvelopeTest, SensorReadingAlertTest

### Community 19 - "Sensor"
Cohesion: 0.09
Nodes (5): SensorController, Sensor, PublicGraphVisibility, Gate6PublicSurfaceTest, Illuminate\Database\Eloquent\Builder

### Community 20 - "Illuminate\Foundation\Testing\RefreshDatabase"
Cohesion: 0.09
Nodes (11): DangerAlertMail, NotificationService, AlertRuleCascadeDeleteTest, ApiRoutingRegressionTest, DeviceShowStatusLogsTest, DeviceStatusSnapshotTest, SensorReadingRangeIndexTest, Illuminate\Foundation\Testing\RefreshDatabase (+3 more)

### Community 21 - "SensorMonitorBoard.vue"
Cohesion: 0.06
Nodes (20): alerts, {
    auth,
    widgets,
    selectedId,
    selected,
    editing,
    saveState,
    message,
    removed,
    catalog,
    sensor,
    points,
    viewModel,
    widgetViewModel,
    widgetError,
    activeData,
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
}, critical, dialog, dragged, expanded, expandedWidgets, isMobile (+12 more)

### Community 22 - "ApiMetricsService"
Cohesion: 0.09
Nodes (12): InternalMetricsController, MetricsController, MetricsController, EnsureIngestionToken, EnsureUserIsAdmin, TrackApiPerformance, ApiMetricsService, Closure (+4 more)

### Community 23 - "script_datos.py"
Cohesion: 0.12
Nodes (20): build_qc_checksum(), build_sensor_payload(), device_sender_worker(), get_api_key(), get_location(), get_node_id(), get_sensor_key(), get_sensors() (+12 more)

### Community 24 - "Alert"
Cohesion: 0.11
Nodes (4): AlertController, Alert, AlertLifecycleService, AlertResolveTransitionTest

### Community 25 - "StoreAlertRuleRequest"
Cohesion: 0.10
Nodes (8): IngestionController, StoreAlertRuleRequest, StoreRawIngestionEventRequest, UpdateAlertConfigRequest, UpdateEmailConfigRequest, UpdateGeneralConfigRequest, Illuminate\Foundation\Http\FormRequest, Illuminate\Validation\Validator

### Community 26 - "SensorReading"
Cohesion: 0.10
Nodes (6): PurgeFutureSensorReadings, SensorReading, AlertSeeder, AlertEmailTest, AlertTriggerTransitionTest, SensorReadingTriggeredRulesTest

### Community 27 - "PublicGraphControllerTest"
Cohesion: 0.15
Nodes (4): DateTimeInterface, PublicGraphSeriesService, PublicGraphControllerTest, CarbonImmutable

### Community 28 - "Device"
Cohesion: 0.11
Nodes (5): Device, DeviceSensorListLatestReadingTest, SensorApiControllerTest, DashboardMetricsServiceTest, Illuminate\Support\Carbon

### Community 29 - "src/views/SensorsView.vue"
Cohesion: 0.09
Nodes (23): authStore, closeForm(), defaultSensorForm(), deleteSelectedSensor(), devices, editingSensorId, error, filteredSensors (+15 more)

### Community 30 - "AlertRule"
Cohesion: 0.17
Nodes (3): AlertRuleController, AlertRule, RuleToGraphZonesTest

### Community 31 - "useLabWorkspace"
Cohesion: 0.16
Nodes (19): ranges, api, apps, mount(), useLabWorkspace(), add(), changeDevice(), changeRange() (+11 more)

### Community 32 - "useAlertsRealtime.js"
Cohesion: 0.15
Nodes (23): ALERTS_CHANNEL, ALERTS_EVENT, ALERTS_EVENT_CLASS, ALERTS_RESOLVED_EVENT, ALERTS_RESOLVED_EVENT_CLASS, bufferDuringRecovery(), CONNECTION_ERROR_MESSAGES, error (+15 more)

### Community 33 - "src/views/DeviceDetailView.vue"
Cohesion: 0.10
Nodes (17): apiKeyCopied, authStore, device, deviceStatuses, effectiveDevice, error, loading, props (+9 more)

### Community 34 - "src/views/AlertRulesView.vue"
Cohesion: 0.12
Nodes (20): closeModal(), deleteRule(), deletingId, error, formError, load(), loading, loadMetadata() (+12 more)

### Community 35 - "RawSensorEvent"
Cohesion: 0.20
Nodes (3): RawSensorEvent, RawReadingNormalizerTest, RawSensorEventIdempotencyTest

### Community 36 - "event-injection.mjs"
Cohesion: 0.13
Nodes (13): __dirname, fakeEchoPlugin(), main(), record(), results, root, __dirname, fakeEchoPlugin() (+5 more)

### Community 37 - "SensorReadingChart.vue"
Cohesion: 0.12
Nodes (16): accessibleSummary, chartContainer, chartData, chartOptions, hasData, props, renderChart(), sampleViewModel (+8 more)

### Community 38 - "src/views/AlertsView.vue"
Cohesion: 0.12
Nodes (17): alerts, alertsStore, error, filter, handleResolve(), handleResolveAll(), load(), loading (+9 more)

### Community 39 - "AlertService"
Cohesion: 0.19
Nodes (3): AlertService, RuleToGraphZones, Illuminate\Support\Collection

### Community 40 - "SendDangerAlertEmailJob"
Cohesion: 0.19
Nodes (9): Throwable, QueueSmokeJob, Throwable, SendDangerAlertEmailJob, UpdateDeviceLastCommunication, Illuminate\Bus\Queueable, Illuminate\Contracts\Queue\ShouldQueue, Illuminate\Foundation\Bus\Dispatchable (+1 more)

### Community 41 - "dependencies"
Cohesion: 0.11
Nodes (18): bootstrap, bootstrap, @fontsource/inter, @fontsource/jetbrains-mono, dependencies, axios, bootstrap, @fontsource/inter (+10 more)

### Community 42 - "verify-phase5.mjs"
Cohesion: 0.11
Nodes (15): activeAlertsCard, alertsRealtime, alertsStore, alertsView, alertToast, appLayout, echo, envExample (+7 more)

### Community 43 - "echo.js"
Cohesion: 0.18
Nodes (15): booleanEnv(), createEcho(), disconnectEcho(), getEcho(), getEchoConfig(), handleAuthChange(), ADR-0002, notify() (+7 more)

### Community 44 - "DeviceType"
Cohesion: 0.21
Nodes (3): DeviceTypeController, DeviceTypeController, DeviceType

### Community 45 - "Lab"
Cohesion: 0.20
Nodes (3): LabController, LabController, Lab

### Community 46 - "SecurityRateLimitTest.php"
Cohesion: 0.15
Nodes (6): AppServiceProvider, ViewServiceProvider, SecurityRateLimitTest, Illuminate\Cache\RateLimiting\Limit, Illuminate\Support\Facades\RateLimiter, Illuminate\Support\ServiceProvider

### Community 47 - "Illuminate\Database\Seeder"
Cohesion: 0.17
Nodes (7): AlertRuleSeeder, DeviceTypeSeeder, SensorTypeSeeder, SystemSettingsSeeder, UserSeeder, Illuminate\Database\Console\Seeds\WithoutModelEvents, Illuminate\Database\Seeder

### Community 48 - "devDependencies"
Cohesion: 0.12
Nodes (17): devDependencies, axios, concurrently, laravel-vite-plugin, @popperjs/core, sass, tailwindcss, @tailwindcss/vite (+9 more)

### Community 49 - "useDeviceStatusRealtime.test.js"
Cohesion: 0.12
Nodes (11): authStore, deviceStatuses, props, getDevices, mountDeviceStatusList(), mountedApps, visibleDevices, connectionWatchers (+3 more)

### Community 50 - "src/views/MetricsView.vue"
Cohesion: 0.12
Nodes (15): barOptions, comparisonChart, devicesChart, doughnutOptions, error, gaugeOptions, ICONS, loading (+7 more)

### Community 51 - "scripts"
Cohesion: 0.12
Nodes (16): scripts, audit:baseline, audit:events, audit:network, audit:network:live, audit:no-polling:source, build, dev (+8 more)

### Community 53 - "NavBar.vue"
Cohesion: 0.14
Nodes (12): adminItems, alertsStore, authStore, laboratoryItems, navItems, realtimeStatusClass, realtimeStatusLabel, router (+4 more)

### Community 54 - "DeviceService"
Cohesion: 0.19
Nodes (3): DeviceService, DomainEventRecorder, DeviceServiceTest

### Community 55 - "RawStreamConsumerTest"
Cohesion: 0.30
Nodes (4): Connection, RedisManager, RawStreamConsumerTest, Illuminate\Redis\RedisManager

### Community 56 - "devDependencies"
Cohesion: 0.13
Nodes (15): devDependencies, jsdom, @playwright/test, sass, vite, @vitejs/plugin-vue, vitest, ws (+7 more)

### Community 57 - "useDeviceStatusRealtime.js"
Cohesion: 0.21
Nodes (14): onConnectionStateChange(), DEVICE_STATUS_CHANNEL, DEVICE_STATUS_EVENT, DEVICE_STATUS_EVENT_CLASS, error, eventPayload(), fetchStatusSnapshot(), isConnected (+6 more)

### Community 58 - "RawReadingNormalizer"
Cohesion: 0.20
Nodes (5): ConsumeDomainEvents, ConsumeRawEvents, InspectReadingTimeSemantics, RawReadingNormalizer, Illuminate\Console\Command

### Community 60 - "scripts"
Cohesion: 0.14
Nodes (14): scripts, dev, post-autoload-dump, post-root-package-install, post-update-cmd, test, Composer\\Config::disableProcessTimeout, Illuminate\\Foundation\\ComposerScripts::postAutoloadDump (+6 more)

### Community 61 - "src/views/config/EmailConfigView.vue"
Cohesion: 0.15
Nodes (10): email, error, errors, loading, payload(), save(), saving, success (+2 more)

### Community 62 - "DashboardView.test.js"
Cohesion: 0.16
Nodes (11): auth, error, graphDevices, loading, flush(), getActiveAlerts, getDashboardMetrics, getDevices (+3 more)

### Community 66 - "RuntimeException"
Cohesion: 0.18
Nodes (3): DebeziumChange, Illuminate\Support\Str, RuntimeException

### Community 67 - "SensorReadingService"
Cohesion: 0.30
Nodes (3): SensorReadingProjectionService, SensorReadingService, DateTimeInterface

### Community 68 - "EventServiceProvider.php"
Cohesion: 0.20
Nodes (6): AlertObserver, SensorReadingObserver, EventServiceProvider, Illuminate\Auth\Events\Registered, Illuminate\Auth\Listeners\SendEmailVerificationNotification, Illuminate\Foundation\Support\Providers\EventServiceProvider

### Community 69 - "Illuminate\Database\Migrations\Migration"
Cohesion: 0.20
Nodes (3): UpdateAlertRulesTable, UpdateAlertRulesSeverity, Illuminate\Database\Migrations\Migration

### Community 70 - "dependencies"
Cohesion: 0.17
Nodes (12): dependencies, chart.js, laravel-echo, lightweight-charts, pusher-js, chart.js, laravel-echo, pusher-js (+4 more)

### Community 71 - "MigrationIntegrityTest"
Cohesion: 0.26
Nodes (3): ExampleTest, MigrationIntegrityTest, PHPUnit\Framework\TestCase

### Community 72 - "AlertToast.vue"
Cohesion: 0.17
Nodes (10): alertsStore, currentAlert, currentDevice, currentMessage, currentSeverity, currentTime, currentTitle, currentValue (+2 more)

### Community 73 - "graphZonesProjection.js"
Cohesion: 0.30
Nodes (10): boundariesFromRegions(), buildZonesViewModel(), KNOWN_SEVERITIES, moreSevere(), normalizeBoundaries(), normalizeRegions(), normalizeSeverity(), PRECEDENCE (+2 more)

### Community 74 - "ActiveAlertsCard.vue"
Cohesion: 0.20
Nodes (9): alerts, alertsStore, count, error, loading, flush(), getActiveAlerts, mountActiveAlertsCard() (+1 more)

### Community 75 - "useSensorRealtime.js"
Cohesion: 0.26
Nodes (11): onResync(), ADR-0002, normalizeReading(), RECOVERY_WINDOW_MS, resolveSensorId(), SENSOR_EVENT, SENSOR_EVENT_CLASS, useSensorRealtime() (+3 more)

### Community 76 - "useAlertsRealtime.test.js"
Cohesion: 0.18
Nodes (7): getActiveAlerts, transport, channelCallbacks, connectionWatchers, fetchActiveAlerts, listenOnChannel, resyncWatchers

### Community 78 - "composer.json"
Cohesion: 0.18
Nodes (10): autoload-dev, psr-4, description, license, minimum-stability, name, prefer-stable, Tests\\ (+2 more)

### Community 80 - "SensorDataControllerTest.php"
Cohesion: 0.24
Nodes (4): EventPublisherXaddShapeTest, SensorDataControllerTest, Mockery, PHPUnit\Framework\Attributes\CoversClass

### Community 81 - "network-assertion-live.mjs"
Cohesion: 0.31
Nodes (10): ALLOWED_ANON, apiLogin(), authenticatedScenario(), __dirname, guestScenario(), LEAK_ENDPOINTS, main(), OBSERVE_MS (+2 more)

### Community 82 - "verify-no-polling.mjs"
Cohesion: 0.18
Nodes (8): backendRoots, compose, __dirname, FRONT, RELAY_TOKENS, REPO, RETIRED_ENDPOINTS, violations

### Community 83 - "verify-phase3.mjs"
Cohesion: 0.18
Nodes (10): dependencies, emailVerification, envExample, missing, missingDependencies, packageJson, requiredDependencies, requiredFiles (+2 more)

### Community 84 - "layout/AppLayout.vue"
Cohesion: 0.18
Nodes (7): alertsStore, authStore, deviceStatusesStore, route, { subscribeAlerts, unsubscribeAlerts }, { subscribeDeviceStatus, unsubscribeDeviceStatus }, usesLabShell

### Community 85 - "src/views/config/AlertConfigView.vue"
Cohesion: 0.20
Nodes (8): error, errors, form, loading, payload(), save(), saving, success

### Community 86 - "src/views/ConfigView.vue"
Cohesion: 0.18
Nodes (9): adminActions, alerts, diagnostics, email, general, loading, loadNotice, sections (+1 more)

### Community 95 - "AlertItem.vue"
Cohesion: 0.20
Nodes (4): props, severityClass, authStore, mountedApps

### Community 96 - "AppLayout.test.js"
Cohesion: 0.22
Nodes (8): flush(), getActiveAlerts, getPublicConfig, getRuntimeConfig, mountAppLayout(), mountedApps, subscribeAlerts, unsubscribeAlerts

### Community 97 - "chartTheme.js"
Cohesion: 0.33
Nodes (8): FALLBACK_TOKENS, hexToRgba(), readCssVar(), resolveChartTokens(), resolveZoneTokens(), ZONE_CSS_VARS, ZONE_FALLBACKS, ZONE_FILL_ALPHA

### Community 98 - "src/views/config/GeneralConfigView.vue"
Cohesion: 0.20
Nodes (6): error, errors, form, loading, saving, success

### Community 99 - "require"
Cohesion: 0.22
Nodes (9): require, laravel/framework, laravel/sanctum, laravel/tinker, laravel/ui, php, predis/predis, pusher/pusher-php-server (+1 more)

### Community 100 - "verify-phase7.mjs"
Cohesion: 0.22
Nodes (8): __dirname, envExample, __filename, forbiddenKeys, frontDockerfile, frontRoot, repoRoot, requiredFiles

### Community 104 - "AlertRuleForm.vue"
Cohesion: 0.28
Nodes (6): emit, filteredSensors, form, nullableNumber(), props, submit()

### Community 105 - "LabShell.vue"
Cohesion: 0.25
Nodes (8): alerts, auth, links, logout(), open, route, router, switchDemo()

### Community 106 - "SensorMonitorBoard.test.js"
Cohesion: 0.25
Nodes (8): devices, fetchWindow, flush(), mountBoard(), mountedApps, resultForQuery, subscribeSensor, unsubscribeSensor

### Community 107 - "src/views/AlertDetailView.vue"
Cohesion: 0.22
Nodes (6): alert, error, loading, props, resolving, success

### Community 108 - "require-dev"
Cohesion: 0.25
Nodes (8): require-dev, fakerphp/faker, laravel/pail, laravel/pint, laravel/sail, mockery/mockery, nunomaduro/collision, phpunit/phpunit

### Community 113 - "BaseModal.vue"
Cohesion: 0.36
Nodes (7): close(), dialogEl, emit, focusableElements(), onKeydown(), props, titleId

### Community 114 - "channelRegistry.js"
Cohesion: 0.36
Nodes (6): channelKey(), getChannelRefCount(), leaveChannelName(), listenOnChannel(), refCounts, echoMock

### Community 115 - "useSensorRealtime.test.js"
Cohesion: 0.25
Nodes (3): connectionWatchers, echoMock, resyncWatchers

### Community 116 - "graphSeriesQuery.js"
Cohesion: 0.36
Nodes (3): activeControllersByConsumer, buildGraphQueryKey(), useGraphSeriesQueryStore

### Community 118 - "src/views/auth/EmailVerificationView.vue"
Cohesion: 0.25
Nodes (6): authStore, error, loading, resending, route, success

### Community 121 - "config"
Cohesion: 0.29
Nodes (7): pestphp/pest-plugin, php-http/discovery, config, allow-plugins, optimize-autoloader, preferred-install, sort-packages

### Community 126 - "front/package.json"
Cohesion: 0.29
Nodes (6): allowScripts, esbuild@0.21.5, name, private, type, version

### Community 127 - "verify-phase4.mjs"
Cohesion: 0.29
Nodes (6): missing, navbar, requiredFiles, root, router, srcFilesToCheck

### Community 130 - "graphSeriesProjection.js"
Cohesion: 0.52
Nodes (4): composeGraphSeries(), computeStats(), idCompare(), toPoint()

### Community 131 - "src/views/auth/ResetPasswordView.vue"
Cohesion: 0.29
Nodes (5): error, form, loading, route, success

### Community 132 - "SensorsView.test.js"
Cohesion: 0.33
Nodes (6): currentQuery, flush(), getDevices, getSensors, mountedApps, mountView()

### Community 133 - "bootstrap/app.php"
Cohesion: 0.33
Nodes (4): Illuminate\Foundation\Application, Illuminate\Foundation\Configuration\Exceptions, Illuminate\Foundation\Configuration\Middleware, Symfony\Component\HttpKernel\Exception\BadRequestHttpException

### Community 134 - "back/package.json"
Cohesion: 0.33
Nodes (5): private, scripts, build, dev, type

### Community 139 - "run-all.mjs"
Cohesion: 0.33
Nodes (5): clean, __dirname, filter, lines, summary

### Community 140 - "graph.js"
Cohesion: 0.47
Nodes (3): getGraphSeries(), getPrivateGraphSeries(), toWindowParam()

### Community 141 - "src/views/auth/ForgotPasswordView.vue"
Cohesion: 0.33
Nodes (4): email, error, loading, success

### Community 142 - "src/views/auth/LoginView.vue"
Cohesion: 0.33
Nodes (4): authStore, form, route, router

### Community 143 - "src/views/auth/RegisterView.vue"
Cohesion: 0.33
Nodes (4): error, form, loading, success

### Community 144 - "src/views/config/DiagnosticsConfigView.vue"
Cohesion: 0.33
Nodes (4): error, items, loading, system

### Community 145 - "ConfigView.test.js"
Cohesion: 0.47
Nodes (5): configApi, flush(), mountView(), response(), setResponses()

### Community 147 - "psr-4"
Cohesion: 0.40
Nodes (5): autoload, psr-4, App\\, Database\\Factories\\, Database\\Seeders\\

### Community 148 - "logging.php"
Cohesion: 0.40
Nodes (4): Monolog\Handler\NullHandler, Monolog\Handler\StreamHandler, Monolog\Handler\SyslogUdpHandler, Monolog\Processor\PsrLogMessageProcessor

### Community 149 - "bootstrap.js"
Cohesion: 0.40
Nodes (3): chart, lineSeries, lineSeriesDef

### Community 152 - "MetricsCards.vue"
Cohesion: 0.50
Nodes (3): metrics, props, renderMetricsCards()

### Community 153 - "SensorChart.vue"
Cohesion: 0.50
Nodes (3): props, renderChart(), viewModel

### Community 154 - "SensorReadingsChart.vue"
Cohesion: 0.50
Nodes (3): props, renderChart(), viewModel

### Community 156 - "sensorReadings.js"
Cohesion: 0.60
Nodes (3): normalizeReading(), normalizeReadings(), useSensorReadingsStore

### Community 158 - "post-create-project-cmd"
Cohesion: 0.50
Nodes (4): post-create-project-cmd, @php artisan key:generate --ansi, @php artisan migrate --graceful --ansi, @php -r \"file_exists('database/database.sqlite') || touch('database/database.sqlite');\

### Community 162 - "LabIcon.vue"
Cohesion: 0.50
Nodes (3): icons, paths, props

### Community 163 - "widgetState"
Cohesion: 0.50
Nodes (4): selectedSensor, widgetLatest(), widgetState(), sensor

### Community 164 - "sound.js"
Cohesion: 0.83
Nodes (3): getAudioContext(), playAlertSound(), unlockAlertSound()

### Community 165 - "extra"
Cohesion: 0.67
Nodes (3): extra, laravel, dont-discover

### Community 166 - "keywords"
Cohesion: 0.67
Nodes (3): keywords, framework, laravel

## Knowledge Gaps
- **568 isolated node(s):** `DashboardPreferenceController`, `__dirname`, `envExample`, `__filename`, `forbiddenKeys` (+563 more)
  These have ≤1 connection - possible missing edges or undocumented components. (Counts symbols only; 1125 node(s) total have ≤1 connection when file, concept and rationale nodes are included.)
- **48 thin communities (<3 nodes) omitted from report** — run `graphify query` to explore isolated nodes.

## Suggested Questions
_Questions this graph is uniquely positioned to answer:_

- **Why does `Sensor` connect `Sensor` to `DomainEventOutbox`, `Illuminate\Support\Facades\Log`, `User`, `TestCase`, `Illuminate\Http\Request`, `Api/AlertRuleController.php`, `SensorGraphZonesTest`, `NewAlertTriggered`, `PublicGraphController`, `SensorType`, `Illuminate\Foundation\Testing\RefreshDatabase`, `.makeDangerRuleSensor`, `SecuritySqlInjectionTest.php`, `StoreAlertRuleRequest`, `SensorReading`, `PublicGraphControllerTest`, `Device`, `AlertRule`, `AlertRuleNameTest.php`, `.makeDangerAlert`, `RawSensorEvent`, `AlertService`, `SecurityRateLimitTest.php`, `Illuminate\Database\Seeder`, `RawStreamConsumerTest`, `DashboardMetricsService`, `NewSensorReading`, `RuntimeException`, `SensorReadingService`, `Gate10PublicGraphBoundaryTest`, `SensorDataControllerTest.php`, `ApiAuthTokenTest`, `BroadcastChannelAuthorizationTest`, `DatabaseSeeder`, `ReadingTimeSemanticsTest`, `SensorPublicMonitoringAdminTest`?**
  _High betweenness centrality (0.054) - this node is a cross-community bridge._
- **Why does `TestCase` connect `TestCase` to `DomainEventOutbox`, `Illuminate\Support\Facades\Log`, `User`, `EventPipelineMetricsService`, `DeviceApiKeyVisibilityTest`, `DeviceApiPaginationTest`, `PublisherStreamPrefixTest`, `SensorGraphZonesTest`, `User.php`, `NewAlertTriggered`, `SensorType`, `Sensor`, `Illuminate\Foundation\Testing\RefreshDatabase`, `.makeDangerRuleSensor`, `SecuritySqlInjectionTest.php`, `Alert`, `SensorReading`, `PublicGraphControllerTest`, `Device`, `AlertRule`, `AlertRuleNameTest.php`, `.makeDangerAlert`, `RawSensorEvent`, `SecurityRateLimitTest.php`, `DeviceService`, `RawStreamConsumerTest`, `NewSensorReading`, `Gate10PublicGraphBoundaryTest`, `SensorDataControllerTest.php`, `ApiAuthTokenTest`, `BroadcastChannelAuthorizationTest`, `IngestionApiTest`, `Gate10NoPollingArchitectureTest`, `ReadingTimeSemanticsTest`, `SensorPublicMonitoringAdminTest`, `AlertTransportAuthorizationTest`, `ConfigGeneralUpdateTest`, `EventPipelineMetricsServiceTest`, `SecurityAccessControlTest`?**
  _High betweenness centrality (0.036) - this node is a cross-community bridge._
- **Why does `User` connect `User` to `DomainEventOutbox`, `Illuminate\Support\Facades\Log`, `Illuminate\Database\Eloquent\Factories\Factory`, `TestCase`, `DeviceApiKeyVisibilityTest`, `DeviceApiPaginationTest`, `Illuminate\Http\Request`, `SensorGraphZonesTest`, `User.php`, `Controller`, `Sensor`, `Illuminate\Foundation\Testing\RefreshDatabase`, `SecuritySqlInjectionTest.php`, `Alert`, `SensorReading`, `Device`, `UserRoleController`, `AlertRuleNameTest.php`, `Illuminate\Database\Seeder`, `Gate10PublicGraphBoundaryTest`, `ApiAuthTokenTest`, `BroadcastChannelAuthorizationTest`, `SensorPublicMonitoringAdminTest`, `RegisterController`, `AlertTransportAuthorizationTest`, `ConfigGeneralUpdateTest`, `EventPipelineMetricsServiceTest`, `SecurityAccessControlTest`?**
  _High betweenness centrality (0.030) - this node is a cross-community bridge._
- **What connects `DashboardPreferenceController`, `__dirname`, `envExample` to the rest of the system?**
  _568 weakly-connected nodes found - possible documentation gaps or missing edges._
- **Should `DomainEventOutbox` be split into smaller, more focused modules?**
  _Cohesion score 0.06572769953051644 - nodes in this community are weakly interconnected._
- **Should `main.py` be split into smaller, more focused modules?**
  _Cohesion score 0.06648575305291723 - nodes in this community are weakly interconnected._
- **Should `Illuminate\Support\Facades\Log` be split into smaller, more focused modules?**
  _Cohesion score 0.07244843997884717 - nodes in this community are weakly interconnected._