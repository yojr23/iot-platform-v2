# Graph Report - iot-platform-v2  (2026-09-16)

## Corpus Check
- 71 files · ~314,637 words
- Verdict: corpus is large enough that graph structure adds value.

## Summary
- 3820 nodes · 7344 edges · 354 communities (169 shown, 77 thin omitted)
- Extraction: 96% EXTRACTED · 4% INFERRED · 0% AMBIGUOUS · INFERRED: 269 edges (avg confidence: 0.86)
- Token cost: 0 input · 0 output

## Community Hubs (Navigation)
- User Auth & Policy Tests
- API Security Tests
- Device Tests
- Python CLI Scripts
- Domain Events & Resources
- Alert Rule & Sensor Controllers
- CDC Domain Event Pipeline
- Project Documentation
- API Controllers
- Database Factories
- Frontend Shared Components
- Blade Auth State
- Blade Config State
- Domain Models & Events
- Laravel Controllers & Auth
- CDC Outbox Stream Consumer
- Auth Notifications
- Lab & Device Controllers
- Vue Auth Store
- API Metrics & Monitoring
- Event Pipeline Metrics
- Dashboard Widget State
- Metrics & Middleware
- Sensor & Public Graph
- Config Controllers
- Sensor Alert Tests
- Vue Sensor Management
- Database Seeders
- Chart Visualization
- Python IoT Sender Scripts
- Durable Event Spool
- Sensor Graph Zones Tests
- Vue Device Management
- Event Pipeline Metrics Test
- Alert Lifecycle
- Playwright E2E Tests
- Config System Tests
- Alert Rule Resources
- Lab Workspace Composable
- Realtime Alerts Channel
- Dashboard Metrics Charts
- Alerts View State
- Eloquent Base Models
- Device Status Logs
- Event Publisher Tests
- Ingestion Identity Builder
- Realtime Store Orchestration
- Alert Rule Modal State
- Device API Resources
- Queue Jobs
- Sensor Reading Alert Evaluation
- Vue Unit Test Stubs
- MQTT Simulation Scripts
- NPM Dependencies
- SensorType Controllers
- Ingestion Delivery Worker
- Frontend Imports
- Echo Configuration
- App Layout Sidebar
- DeviceType Controllers
- Lab Controllers
- Database Seeders Extended
- Backend NPM Scripts
- Gate 10 CI Scripts
- Community 64
- Navigation Sidebar Items
- Ingestion Pipeline Docs
- Graph Dashboard State
- Device Detail State
- Reading Projection
- Notification Mail Messages
- Alert Rules Migration
- Community 72
- Frontend Test Dependencies
- Device Status Realtime
- Form Request Validation
- Store Request Validation
- Composer Scripts
- Dead Letter Stream Tests
- Email Config View State
- Community 80
- Community 81
- Test Fixtures & Mocks
- Community 83
- Register View State
- Gate 10 Evidence Reports
- Artisan Console Commands
- Community 87
- Community 88
- Community 89
- PHPUnit Base Tests
- E2E Auth Transition
- Alert Detail State
- Graph Zone Boundaries
- Sensor Realtime Normalizer
- Community 96
- Composer Package Config
- Community 98
- Security Audit Scripts
- Network Assertion Scripts
- Dependency Audit Scripts
- Community 102
- Settings View State
- Config View Sections
- Ingestion Backend Client
- Payload Validation
- Blade Legacy State
- Realtime Transport Layer
- Community 109
- Community 110
- Permission Relations
- Event Service Provider
- App Service Providers
- Community 114
- Community 115
- Frontend Chart Dependencies
- Device Status List
- Community 118
- Severity Class Styling
- Chart Token Resolution
- Config Form State
- Community 122
- Backend Composer Dependencies
- Frontend Audit Scripts
- Community 126
- Community 127
- Community 128
- Sensor Form Emit
- Sensor Board State
- Community 134
- Community 135
- Community 136
- Community 137
- Community 138
- Community 139
- Base Modal Dialog
- Channel Registry Mock
- Echo Mock Connection
- Graph Series Query Store
- Email Verify View
- Migration Phase Docs
- Community 146
- Community 147
- Composer Plugin Config
- Community 149
- Community 150
- Community 151
- Ingestion Data Models
- Frontend Package Config
- Frontend Audit Checks
- Community 155
- Community 156
- Graph Series Service
- Graph Series Compose
- Device Status Snapshot
- Docker Architecture Docs
- Realtime Integration Docs
- Graph Catalog Endpoint
- Community 165
- Community 166
- Community 167
- Community 168
- Frontend Build Config
- Community 170
- Community 171
- Community 172
- Community 173
- Auth Transition Evidence
- Auth Install Script
- Forgot Password View
- Login Form State
- System Info View
- Config API State
- Device Detail View
- Devices List View
- Project Architecture ADRs
- Migration Hygiene Rules
- Frontend Entry Config
- Autoload Config
- Monolog Handlers
- Chart Line Series
- Community 188
- Community 189
- Community 190
- Frontend Functional State
- Frontend Framework ADR
- Metrics Cards Render
- Community 194
- Sensor Readings Normalizer
- Graph Window Validation
- Composer Post Scripts
- Community 199
- Alert Sound Effects
- Community 201
- Community 202
- Community 234
- Community 235
- Community 236
- Community 237
- User Model Notifications
- Community 239
- Community 240
- Community 241
- Community 242
- Community 243
- Community 244
- Environment Variable ADRs
- Backend Functional State
- Mobile Accessibility Tests
- Community 248
- Community 249
- Community 250
- Community 251
- Community 253
- Community 254
- Community 257
- Community 258
- Community 259
- Community 260
- Community 261
- Community 262
- Community 263
- Community 264
- Community 316
- Community 317
- Community 318
- Community 320
- Community 335
- Community 336
- Community 337
- Community 338
- Community 339
- Community 340
- Community 341
- Community 342
- Community 343
- Community 344
- Community 345
- Community 346
- Community 347
- Community 348
- Community 349
- Community 350
- Community 351
- Community 352
- Community 353

## God Nodes (most connected - your core abstractions)
1. `User` - 289 edges
2. `Device` - 227 edges
3. `TestCase` - 147 edges
4. `Alert` - 87 edges
5. `Sensor` - 86 edges
6. `Controller` - 44 edges
7. `AlertRule` - 43 edges
8. `SensorType` - 42 edges
9. `DurableEventSpool` - 38 edges
10. `EventPipelineMetricsService` - 34 edges

## Surprising Connections (you probably didn't know these)
- `chart.js` --references--> `Functional Inventory: Frontend SPA Table`  [INFERRED]
  front/package.json → memory/11-functional-inventory.md
- `useAlertsRealtime` --semantically_similar_to--> `channelRegistry`  [INFERRED] [semantically similar]
  docs/implementation/ownership.md → front_rebuild_plan/SINOA_Agentic_Dashboard_Implementation_Plan_v2.1_PUBLIC_REALTIME.md
- `Responsive Audit Matrix (33 Rows)` --semantically_similar_to--> `44px Minimum Touch Target Remediation`  [INFERRED] [semantically similar]
  task-10-report.md → task-10-remediation-report.md
- `Debezium CDC Pipeline` --conceptually_related_to--> `raw:consume Command`  [INFERRED]
  scripts/gate10/README.md → ingestion_service/README.md
- `Graph Semantic Zones Plan` --referenced_by--> `Project README (verified state)`  [INFERRED]
  docs/implementation/graph-semantic-zones-plan.md → README.md

## Import Cycles
- None detected.

## Hyperedges (group relationships)
- **CDC Pipeline: MySQL Outbox → Debezium → Redis → Application Stream → Raw Consumer** — scripts_gate10_readme_mysql_outbox, scripts_gate10_readme_debezium_cdc, scripts_gate10_readme_fault_scenarios, ingestion_service_readme_raw_process_v1, ingestion_service_readme_raw_consume [EXTRACTED 0.95]
- **Authenticated Graph Catalog: Endpoint → Controller → Zones Mapping → Frontend Client** — task_8_report_dashboard_graph_catalog, task_8_report_dashboard_graph_catalog_controller, task_8_report_rule_to_graph_zones, task_8_report_getauthenticatedgraphcatalog, task_8_report_sanctum_auth [EXTRACTED 0.95]
- **Graph Series Window Validation: Controllers → Service → HTTP 422** — task_9_report_publicgraph_controller, task_9_report_sensor_api_controller, task_9_report_publicgraphseries_service, task_9_report_window_validation [EXTRACTED 0.95]
- **Fase 6B Blade Cleanup Gating Decision** — memory_14_blade_cleanup_readiness_verdict_nogo, memory_13_manual_validation_checklist_checklist, memory_12_stable_blade_comparison_missing_features, memory_06_pending_risks_operational_risks, memory_08_refactor_checklist_fase_6b [EXTRACTED 1.00]
- **Sensor reading to alert notification pipeline** — code_back_app_http_controllers_api_sensorapicontroller, code_back_app_observers_sensorreadingobserver, code_back_app_models_sensorreading, code_back_app_services_alerts_alertservice, code_back_app_observers_alertobserver, code_back_app_services_notifications_notificationservice, code_back_app_events_newalerttriggered, code_back_app_mail_dangeralertmail [EXTRACTED 1.00]
- **ICONTEC/ISO normative compliance document set** — docs_icontec_compliance, docs_matriz_trazabilidad_icontec_iso, docs_evidencia_cumplimiento_codigo_estructura, docs_procedimiento_auditoria_normativa, docs_referencias_icontec, docs_plantilla_trabajo_icontec, analisis_proyecto [EXTRACTED 1.00]
- **Ingestion Data Pipeline** — docs_superpowers_plans_2026_09_08_gate_s5r_closure_md_sensor_reading_service, docs_superpowers_plans_2026_09_08_gate_s5r_closure_md_raw_reading_normalizer, docs_superpowers_plans_2026_09_08_gate_s5r_closure_md_domain_event_broadcast_consumer, docs_superpowers_plans_2026_09_08_gate_s5r_closure_md_new_sensor_reading, docs_implementation_ownership_md_raw_sensor_event_publisher [EXTRACTED 1.00]
- **Front/Back Migration Phase Roadmap (Fase 0 to Fase 8A)** — memory_05_migration_log_fase_0, memory_05_migration_log_fase_1, memory_05_migration_log_fase_2, memory_05_migration_log_fase_3, memory_05_migration_log_fase_4, memory_05_migration_log_fase_5, memory_05_migration_log_fase_6, memory_05_migration_log_fase_7, memory_05_migration_log_fase_7b, memory_05_migration_log_fase_8a [EXTRACTED 1.00]
- **Public Graph Visibility Boundary** — front_rebuild_plan_public_graph_visibility_agentic_execution_plan_md_public_graph_visibility_service, front_rebuild_plan_public_graph_visibility_agentic_execution_plan_md_public_graph_controller, front_rebuild_plan_public_graph_visibility_agentic_execution_plan_md_public_graph_series_service, docs_superpowers_plans_2026_09_08_gate_s5r_closure_md_domain_event_broadcast_consumer [EXTRACTED 1.00]
- **Role Access Control Boundary** — docs_superpowers_plans_2026_09_11_role_access_control_md_role_access_control, docs_superpowers_specs_2026_09_11_role_access_control_design_md_role_access_design, docs_superpowers_plans_2026_09_11_role_access_control_md_can_manage_dashboard, docs_security_security_hardening_plan_md_resource_access_service [INFERRED 0.85]
- **SPA Auth & Security Boundary Design** — memory_01_architecture_decisions_adr_005_env_separation, memory_01_architecture_decisions_adr_006_api_only_communication, memory_01_architecture_decisions_adr_009_sanctum_bearer_auth, memory_15_security_review_sec_003_localstorage_bearer_token [INFERRED 0.85]
- **Migration Gate Evidence Chain** — docs__implementation__gates-6-7-8-evidence.md, docs__implementation__gate-9-evidence.md, docs__implementation__gate-10-evidence.md, docs__implementation__gate10-fault-matrix-current.md [INFERRED]
- **CDC Pipeline Architecture** — docs__INGESTION_PIPELINE.md, docker-compose.yml, docs__implementation__adr-g1.md, docs__implementation__gate-10-evidence.md, docs__implementation__gate10-fault-matrix-current.md, back__github__workflows__gate10-quality.yml [INFERRED]
- **Agent Convention Definitions** — back__claude__agents__iot-back-implementador.md, back__claude__agents__iot-front-implementador.md, CLAUDE.md [INFERRED]
- **Gate 10 CI Verification Bundle** — plan_md_gate_10_reconciliation, docs_gate10_mac_verification_evidence_2026_09_16_md_gate10_evidence, plan_md_migration_000008, plan_md_migration_000003, plan_md_migration_000002, plan_md_cdc_outbox_stream_consumer [EXTRACTED 1.00]
- **Live Docker Stack Verification (M9–M12)** — docs_gate10_mac_verification_evidence_2026_09_16_md_m9_migrations, docs_gate10_mac_verification_evidence_2026_09_16_md_m10_stack_health, docs_gate10_mac_verification_evidence_2026_09_16_md_m11_vertical_slice, docs_gate10_mac_verification_evidence_2026_09_16_md_m12_fault_matrix, plan_md_adr_1_durable_publication, plan_md_sensor_mapping_service [EXTRACTED 1.00]
- **Responsive E2E Test Coverage Bundle** — front_audit_e2e_task10_matrix_txt_responsive_matrix, front_audit_e2e_task10_matrix_txt_35_test_cases, front_audit_e2e_task10_matrix_txt_viewport_coverage, front_audit_e2e_task10_matrix_txt_role_boundary, front_audit_e2e_task10_matrix_txt_modal_interaction, docs_gate10_mac_verification_evidence_2026_09_16_md_responsive_e2e_pass [EXTRACTED 1.00]

## Communities (354 total, 77 thin omitted)

### Community 0 - "User Auth & Policy Tests"
Cohesion: 0.03
Nodes (16): App\Models\DashboardPreference, Collection, User, DevicePolicy, ResourceAccessService, AdminAccessTest, AuthSecurityTest, EmailSecretStorageTest (+8 more)

### Community 1 - "API Security Tests"
Cohesion: 0.04
Nodes (21): AlertTransportAuthorizationTest, RealtimeAuthorizationRegressionTest, SensorExportSecurityTest, ApiAuthTokenTest, ConfigSystemInfoTest, DataIntegrityDuplicationTest, DeviceApiPaginationTest, DeviceCreateTest (+13 more)

### Community 2 - "Device Tests"
Cohesion: 0.04
Nodes (12): Device, DataLeakageSentinelTest, DeviceAuthorizationTest, ResourcePayloadTest, ApiRoutingRegressionTest, DashboardGraphCatalogControllerTest, DeviceApiKeyVisibilityTest, DeviceApiStatusUpdateTest (+4 more)

### Community 3 - "Python CLI Scripts"
Cohesion: 0.06
Nodes (31): current_sha(), main(), parse_args(), Any, Namespace, Path, Write a short human index derived exclusively from the evidence document., write_json() (+23 more)

### Community 4 - "Domain Events & Resources"
Cohesion: 0.05
Nodes (29): App\Events\AlertResolved, App\Http\Requests\Api\StoreRawIngestionEventRequest, App\Http\Resources\AlertResource, App\Http\Resources\DeviceStatusSnapshotResource, App\Http\Resources\SensorResource, App\Models\RawEventOutbox, App\Models\RawSensorEvent, App\Models\Role (+21 more)

### Community 5 - "Alert Rule & Sensor Controllers"
Cohesion: 0.06
Nodes (14): AlertRuleController, SensorController, AlertRule, Sensor, SensorType, AlertRuleCascadeDeleteTest, AlertRuleNameTest, AlertRuleValidationTest (+6 more)

### Community 6 - "CDC Domain Event Pipeline"
Cohesion: 0.08
Nodes (8): App\Models\DomainEventOutbox, App\Services\Monitoring\PublicGraphVisibility, DomainEventBroadcastConsumer, DatabaseSeeder, DomainEventBroadcastConsumerTest, Connection, DomainEventOutbox, RedisManager

### Community 7 - "Project Documentation"
Cohesion: 0.06
Nodes (49): Project Changelog, Project Instructions (CLAUDE.md), Formal Technical Documentation (superseded), Project README (verified state), RBAC/Security Remediation Summary, ANALISIS_PROYECTO.md (analisis tecnico y de cumplimiento), Architecture Audit and Implementation Plan, Backend Agent Definition (iot-back-implementador) (+41 more)

### Community 8 - "API Controllers"
Cohesion: 0.08
Nodes (8): AlertController, AuthApiController, UserRoleController, DashboardPreferenceController, SensorTypeController, AuditService, DashboardMetricsService, Illuminate\Http\Request

### Community 9 - "Database Factories"
Cohesion: 0.06
Nodes (15): AlertFactory, AlertRuleFactory, DeviceFactory, DeviceStatusLogFactory, DeviceTypeFactory, DomainEventOutboxFactory, LabFactory, RawEventOutboxFactory (+7 more)

### Community 10 - "Frontend Shared Components"
Cohesion: 0.06
Nodes (46): Lab Blue Resource Views, LabShell, AlertService, BaseModal, Echo JS, Reuse and Ownership Freeze Matrix, Polling Inventory, RawSensorEventPublisher (+38 more)

### Community 11 - "Blade Auth State"
Cohesion: 0.05
Nodes (34): authStore, props, readingsStore, subscriptions, mountedApps, mountList(), onReadingBySensor, subscribeSensor (+26 more)

### Community 12 - "Blade Config State"
Cohesion: 0.05
Nodes (33): apps, flush(), mount(), catalogConfigs, config, deletingId, editingId, error (+25 more)

### Community 13 - "Domain Models & Events"
Cohesion: 0.11
Nodes (22): App\Events\DeviceStatusUpdated, App\Events\NewAlertTriggered, App\Events\NewSensorReading, App\Jobs\EvaluateSensorReadingAlerts, App\Jobs\SendDangerAlertEmailJob, App\Mail\DangerAlertMail, App\Models\Alert, App\Models\AlertRule (+14 more)

### Community 14 - "Laravel Controllers & Auth"
Cohesion: 0.07
Nodes (17): App\Http\Controllers\Controller, DashboardGraphCatalogController, RoleController, ForgotPasswordController, RegisterController, ResetPasswordController, Collection, Role (+9 more)

### Community 15 - "CDC Outbox Stream Consumer"
Cohesion: 0.13
Nodes (7): App\Services\Ingestion\DomainEventPublisher, App\Services\Ingestion\RawSensorEventPublisher, CdcOutboxStreamConsumer, CdcOutboxStreamConsumerTest, Connection, DomainEventOutbox, RawSensorEvent

### Community 16 - "Auth Notifications"
Cohesion: 0.06
Nodes (15): App\Notifications\ResetPasswordNotification, App\Notifications\VerifyEmailNotification, RateLimitSecurityTest, AuthSecurityTest, PasswordResetTest, RegistrationEmailVerificationTest, Illuminate\Auth\Access\AuthorizationException, Illuminate\Contracts\Auth\MustVerifyEmail (+7 more)

### Community 17 - "Lab & Device Controllers"
Cohesion: 0.08
Nodes (6): LabController, DeviceController, LabController, Lab, DeviceService, DomainEventRecorder

### Community 18 - "Vue Auth Store"
Cohesion: 0.06
Nodes (5): app, authStore, pinia, router, useAuthStore

### Community 19 - "API Metrics & Monitoring"
Cohesion: 0.07
Nodes (16): AlertFeedController, HealthController, ProfileController, SensorDataController, ConfirmPasswordController, LoginController, VerificationController, Controller (+8 more)

### Community 20 - "Event Pipeline Metrics"
Cohesion: 0.10
Nodes (4): EventPipelineMetricsService, Connection, EventPipelineMetricsServiceTest, self

### Community 21 - "Dashboard Widget State"
Cohesion: 0.06
Nodes (24): alerts, {
    auth,
    canManageDashboard,
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
}, critical, dialog, dragged, expanded, expandedWidgets, isMobile (+16 more)

### Community 22 - "Metrics & Middleware"
Cohesion: 0.09
Nodes (13): InternalMetricsController, MetricsController, MetricsController, EnsureIngestionToken, EnsureUserHasPermission, EnsureUserIsAdmin, TrackApiPerformance, ApiMetricsService (+5 more)

### Community 23 - "Sensor & Public Graph"
Cohesion: 0.12
Nodes (7): App\Models\Sensor, App\Services\Ingestion\SensorReadingProjectionService, App\Services\Monitoring\RuleToGraphZones, Sensor, SensorApiController, SensorPolicy, Carbon

### Community 24 - "Config Controllers"
Cohesion: 0.11
Nodes (7): ConfigController, DashboardController, ConfigController, EmailConfigController, SystemSetting, SystemSettingTest, Illuminate\Http\JsonResponse

### Community 25 - "Sensor Alert Tests"
Cohesion: 0.12
Nodes (5): DangerAlertEmailTest, Sensor, PublicGraphControllerTest, NotificationServiceRateLimitTest, CarbonImmutable

### Community 26 - "Vue Sensor Management"
Cohesion: 0.07
Nodes (29): authStore, closeForm(), defaultSensorForm(), deleteSelectedSensor(), devices, editingSensorId, error, filteredSensors (+21 more)

### Community 27 - "Database Seeders"
Cohesion: 0.08
Nodes (12): AlertRuleSeeder, AlertSeeder, DeviceTypeSeeder, RolePermissionSeeder, SensorTypeSeeder, SystemSettingsSeeder, UserSeeder, RbacUserProvisioningTest (+4 more)

### Community 28 - "Chart Visualization"
Cohesion: 0.08
Nodes (22): accessibleSummary, chartContainer, chartData, chartOptions, hasData, props, renderChart(), sampleViewModel (+14 more)

### Community 29 - "Python IoT Sender Scripts"
Cohesion: 0.12
Nodes (20): build_qc_checksum(), build_sensor_payload(), device_sender_worker(), get_api_key(), get_location(), get_node_id(), get_sensor_key(), get_sensors() (+12 more)

### Community 30 - "Durable Event Spool"
Cohesion: 0.13
Nodes (19): DurableEventSpool, PendingEvent, Return all dead-lettered events for inspection., The durable, local owner of MQTT events awaiting backend delivery., Return the spool's current time source for coordinated components., event(), test_claim_is_exclusive_until_its_lease_expires(), test_creates_missing_spool_parent_directory() (+11 more)

### Community 31 - "Sensor Graph Zones Tests"
Cohesion: 0.11
Nodes (7): StoreAlertRuleRequest, StoreRawIngestionEventRequest, UpdateAlertConfigRequest, UpdateEmailConfigRequest, UpdateGeneralConfigRequest, Illuminate\Foundation\Http\FormRequest, Illuminate\Validation\Validator

### Community 32 - "Vue Device Management"
Cohesion: 0.08
Nodes (24): authStore, defaultDeviceForm(), deviceForm, devices, deviceStatuses, deviceTypes, editingDeviceId, effectiveDevice() (+16 more)

### Community 33 - "Event Pipeline Metrics Test"
Cohesion: 0.08
Nodes (16): consoleErrors, diagnoseOverflow(), __dirname, failedRequests, interact, mode, name, pageErrors (+8 more)

### Community 34 - "Alert Lifecycle"
Cohesion: 0.14
Nodes (5): App\Services\Ingestion\DomainEventRecorder, AlertService, SensorReadingProjectionService, RuleToGraphZones, Illuminate\Support\Collection

### Community 35 - "Playwright E2E Tests"
Cohesion: 0.14
Nodes (7): AuditLog, DashboardPreference, RawEventOutbox, RawSensorEvent, RawSensorEventIdempotencyTest, Illuminate\Database\Eloquent\Factories\HasFactory, Illuminate\Database\Eloquent\Model

### Community 36 - "Config System Tests"
Cohesion: 0.14
Nodes (9): App\Events\Concerns\HasEventEnvelope, App\Events\Contracts\VersionedDomainEvent, AlertResolved, DeviceCommunicationReceived, NewAlertTriggered, Illuminate\Broadcasting\InteractsWithSockets, Illuminate\Broadcasting\PrivateChannel, Illuminate\Contracts\Broadcasting\ShouldBroadcastNow (+1 more)

### Community 37 - "Alert Rule Resources"
Cohesion: 0.10
Nodes (5): PurgeFutureSensorReadings, SensorReading, DeviceSensorListLatestReadingTest, Gate6PublicSurfaceTest, NewSensorReadingPayloadTest

### Community 38 - "Lab Workspace Composable"
Cohesion: 0.11
Nodes (7): AlertRuleController, UpdateAlertRuleRequest, AlertResource, AlertRuleResource, DeviceStatusSnapshotResource, SensorResource, Illuminate\Http\Resources\Json\JsonResource

### Community 39 - "Realtime Alerts Channel"
Cohesion: 0.09
Nodes (5): IoTCredentialSecurityTest, IotApiKeyAccessTest, PublicSurfaceClassificationTest, SensorReadingTriggeredRulesTest, Tests\TestCase

### Community 40 - "Dashboard Metrics Charts"
Cohesion: 0.12
Nodes (10): App\Http\Controllers\Api\AuthApiController, App\Http\Controllers\Api\DashboardGraphCatalogController, App\Http\Controllers\Api\HealthController, App\Http\Controllers\Api\IngestionController, App\Http\Controllers\Api\InternalMetricsController, App\Http\Controllers\Api\PublicGraphController, App\Services\DeviceService, DeviceApiController (+2 more)

### Community 41 - "Alerts View State"
Cohesion: 0.09
Nodes (17): alertsStore, authStore, canViewAlerts, canViewDevices, deviceStatusesStore, { subscribeAlerts, unsubscribeAlerts }, { subscribeDeviceStatus, unsubscribeDeviceStatus }, flush() (+9 more)

### Community 42 - "Eloquent Base Models"
Cohesion: 0.16
Nodes (19): ranges, api, apps, mount(), useLabWorkspace(), add(), changeDevice(), changeRange() (+11 more)

### Community 43 - "Device Status Logs"
Cohesion: 0.15
Nodes (23): ALERTS_CHANNEL, ALERTS_EVENT, ALERTS_EVENT_CLASS, ALERTS_RESOLVED_EVENT, ALERTS_RESOLVED_EVENT_CLASS, bufferDuringRecovery(), CONNECTION_ERROR_MESSAGES, error (+15 more)

### Community 44 - "Event Publisher Tests"
Cohesion: 0.09
Nodes (21): apiMetrics, apiRequestsChart, barOptions, comparisonChart, devicesChart, doughnutOptions, error, gaugeOptions (+13 more)

### Community 45 - "Ingestion Identity Builder"
Cohesion: 0.27
Nodes (3): Sensor, RawReadingNormalizerTest, RawSensorEvent

### Community 46 - "Realtime Store Orchestration"
Cohesion: 0.11
Nodes (19): alerts, alertsStore, error, filter, handleResolve(), handleResolveAll(), load(), loading (+11 more)

### Community 47 - "Alert Rule Modal State"
Cohesion: 0.19
Nodes (5): DeadLetterStreamService, DeadLetterStreamServiceTest, EventPublisherXaddShapeTest, SensorDataControllerTest, Mockery

### Community 48 - "Device API Resources"
Cohesion: 0.19
Nodes (3): IngestionApiTest, PDOException, RawEventOutbox

### Community 49 - "Queue Jobs"
Cohesion: 0.17
Nodes (16): Client, Any, build_raw_event(), derive_source_event_id(), _identity_part(), _iso_now(), Any, Build a retry-stable identity from device-domain fields, never MQTT packet IDs. (+8 more)

### Community 50 - "Sensor Reading Alert Evaluation"
Cohesion: 0.12
Nodes (20): closeModal(), deleteRule(), deletingId, error, formError, load(), loading, loadMetadata() (+12 more)

### Community 51 - "Vue Unit Test Stubs"
Cohesion: 0.20
Nodes (6): Attribute, DeviceSensorMapping, SensorMappingService, SensorMappingServiceTest, DateTimeInterface, Illuminate\Database\Eloquent\Casts\Attribute

### Community 52 - "MQTT Simulation Scripts"
Cohesion: 0.18
Nodes (5): DeviceStatusLog, DomainEventOutbox, DeviceShowStatusLogsTest, DeviceStatusChangeTransitionTest, DeviceStatusSnapshotTest

### Community 53 - "NPM Dependencies"
Cohesion: 0.13
Nodes (13): __dirname, fakeEchoPlugin(), main(), record(), results, root, __dirname, fakeEchoPlugin() (+5 more)

### Community 54 - "SensorType Controllers"
Cohesion: 0.20
Nodes (12): BackendClient, main(), parse_args(), Any, Namespace, run_mqtt(), run_simulation(), _sample_payload() (+4 more)

### Community 55 - "Ingestion Delivery Worker"
Cohesion: 0.18
Nodes (15): alert(), countFor(), device(), deviceSensor(), installAuth(), json(), mockApi(), readings() (+7 more)

### Community 56 - "Frontend Imports"
Cohesion: 0.11
Nodes (18): axios, bootstrap, bootstrap, @fontsource/inter, @fontsource/jetbrains-mono, dependencies, axios, bootstrap (+10 more)

### Community 57 - "Echo Configuration"
Cohesion: 0.19
Nodes (3): DeviceTypeController, DeviceTypeController, DeviceType

### Community 58 - "App Layout Sidebar"
Cohesion: 0.24
Nodes (9): Throwable, QueueSmokeJob, Throwable, SendDangerAlertEmailJob, Illuminate\Bus\Queueable, Illuminate\Contracts\Queue\ShouldQueue, Illuminate\Foundation\Bus\Dispatchable, Illuminate\Queue\InteractsWithQueue (+1 more)

### Community 59 - "DeviceType Controllers"
Cohesion: 0.16
Nodes (3): Alert, NotificationService, AlertResolveTransitionTest

### Community 60 - "Lab Controllers"
Cohesion: 0.12
Nodes (4): UpdateAlertRulesTable, UpdateAlertRulesSeverity, up(), Illuminate\Database\Migrations\Migration

### Community 61 - "Database Seeders Extended"
Cohesion: 0.22
Nodes (10): Exception, DeliveryWorker, retry_delay(), event(), RecordingBackend, test_reopened_spool_is_delivered_by_worker(), test_retry_delay_is_capped_exponential_backoff(), test_run_once_deletes_successful_event() (+2 more)

### Community 62 - "Backend NPM Scripts"
Cohesion: 0.11
Nodes (15): activeAlertsCard, alertsRealtime, alertsStore, alertsView, alertToast, appLayout, echo, envExample (+7 more)

### Community 63 - "Gate 10 CI Scripts"
Cohesion: 0.18
Nodes (15): booleanEnv(), createEcho(), disconnectEcho(), getEcho(), getEchoConfig(), handleAuthChange(), ADR-0002, notify() (+7 more)

### Community 64 - "Community 64"
Cohesion: 0.13
Nodes (13): icons, paths, props, alerts, auth, links, logout(), open (+5 more)

### Community 65 - "Navigation Sidebar Items"
Cohesion: 0.15
Nodes (6): AppServiceProvider, ViewServiceProvider, SecurityRateLimitTest, Illuminate\Cache\RateLimiting\Limit, Illuminate\Support\Facades\RateLimiter, Illuminate\Support\ServiceProvider

### Community 66 - "Ingestion Pipeline Docs"
Cohesion: 0.12
Nodes (17): devDependencies, axios, concurrently, laravel-vite-plugin, @popperjs/core, sass, tailwindcss, @tailwindcss/vite (+9 more)

### Community 67 - "Graph Dashboard State"
Cohesion: 0.12
Nodes (17): scripts, audit:baseline, audit:events, audit:gate9, audit:network, audit:network:live, audit:no-polling:source, build (+9 more)

### Community 69 - "Reading Projection"
Cohesion: 0.13
Nodes (17): Dead Letter Queue (DLQ), Exponential Backoff Delivery, Idempotency Ledger via status Column, MQTT Ingestion, POST /api/ingestion/events, QC Validation Gate, raw:consume Command, raw-process-v1 Redis Consumer Group (+9 more)

### Community 70 - "Notification Mail Messages"
Cohesion: 0.23
Nodes (6): App\Services\Ingestion\DeadLetterStreamService, DeadLetterCommandsTest, Connection, RedisManager, DeadLetterStreamService, Illuminate\Redis\RedisManager

### Community 71 - "Alert Rules Migration"
Cohesion: 0.17
Nodes (6): ConsumeDomainEvents, ConsumeRawEvents, InspectDeadLetters, InspectReadingTimeSemantics, ReplayDeadLetter, Illuminate\Console\Command

### Community 72 - "Community 72"
Cohesion: 0.13
Nodes (4): VersionedDomainEvent, NewSensorReading, Illuminate\Broadcasting\Channel, Illuminate\Broadcasting\PresenceChannel

### Community 73 - "Frontend Test Dependencies"
Cohesion: 0.31
Nodes (4): Connection, RedisManager, Sensor, RawStreamConsumerTest

### Community 74 - "Device Status Realtime"
Cohesion: 0.15
Nodes (16): 476 Backend Tests — Green, Gate 10 Mac Verification Evidence Ledger, M11 — End-to-End IoT Vertical Slice, M12 — Event Fault Matrix, M9 — MySQL Migration Verification, Responsive E2E 35/35 — Pass, 35 Responsive Test Cases, Task 10 Responsive E2E Matrix (+8 more)

### Community 75 - "Form Request Validation"
Cohesion: 0.15
Nodes (13): auth, error, graphDevices, loading, boardDevices, flush(), getActiveAlerts, getAuthenticatedGraphCatalog (+5 more)

### Community 76 - "Store Request Validation"
Cohesion: 0.12
Nodes (12): apiKeyCopied, authStore, device, deviceStatuses, effectiveDevice, error, loading, props (+4 more)

### Community 77 - "Composer Scripts"
Cohesion: 0.15
Nodes (9): MailMessage, ResetPasswordNotification, MailMessage, VerifyEmailNotification, Illuminate\Auth\Notifications\ResetPassword, Illuminate\Auth\Notifications\VerifyEmail, Illuminate\Notifications\Messages\MailMessage, Illuminate\Support\Facades\Config (+1 more)

### Community 78 - "Dead Letter Stream Tests"
Cohesion: 0.16
Nodes (4): DebeziumChange, Illuminate\Redis\Connections\Connection, InvalidArgumentException, RuntimeException

### Community 80 - "Community 80"
Cohesion: 0.13
Nodes (15): devDependencies, jsdom, @playwright/test, sass, vite, @vitejs/plugin-vue, vitest, ws (+7 more)

### Community 81 - "Community 81"
Cohesion: 0.15
Nodes (12): alertsStore, currentAlert, currentDevice, currentMessage, currentSeverity, currentTime, currentTitle, currentValue (+4 more)

### Community 82 - "Test Fixtures & Mocks"
Cohesion: 0.21
Nodes (14): onConnectionStateChange(), DEVICE_STATUS_CHANNEL, DEVICE_STATUS_EVENT, DEVICE_STATUS_EVENT_CLASS, error, eventPayload(), fetchStatusSnapshot(), isConnected (+6 more)

### Community 83 - "Community 83"
Cohesion: 0.14
Nodes (5): props, severityClass, apps, authStore, mountedApps

### Community 84 - "Register View State"
Cohesion: 0.14
Nodes (11): App\Http\Controllers\AlertRuleController, App\Http\Controllers\ConfigController, App\Http\Controllers\DeviceController, App\Http\Controllers\DeviceTypeController, App\Http\Controllers\EmailConfigController, App\Http\Controllers\LabController, App\Http\Controllers\MetricsController, App\Http\Controllers\SensorController (+3 more)

### Community 85 - "Gate 10 Evidence Reports"
Cohesion: 0.23
Nodes (5): App\Services\ReadingProvenanceService, RawReadingNormalizer, DomainEventRecorder, SensorReadingProjectionService, SensorReadingService

### Community 86 - "Artisan Console Commands"
Cohesion: 0.21
Nodes (3): EvaluateSensorReadingAlerts, SensorReadingObserver, SensorReadingAlertEvaluationRecoveryTest

### Community 87 - "Community 87"
Cohesion: 0.14
Nodes (14): scripts, dev, post-autoload-dump, post-root-package-install, post-update-cmd, test, Composer\\Config::disableProcessTimeout, Illuminate\\Foundation\\ComposerScripts::postAutoloadDump (+6 more)

### Community 88 - "Community 88"
Cohesion: 0.15
Nodes (10): email, error, errors, loading, payload(), save(), saving, success (+2 more)

### Community 90 - "PHPUnit Base Tests"
Cohesion: 0.23
Nodes (4): PublicGraphController, Sensor, PublicGraphVisibility, Illuminate\Database\Eloquent\Builder

### Community 91 - "E2E Auth Transition"
Cohesion: 0.23
Nodes (3): ReadingProjection, ReadingProvenanceService, Illuminate\Database\Eloquent\Relations\BelongsTo

### Community 95 - "Sensor Realtime Normalizer"
Cohesion: 0.17
Nodes (9): error, form, loading, router, success, authApi, mountedApps, mountRegisterView() (+1 more)

### Community 96 - "Community 96"
Cohesion: 0.18
Nodes (13): Task 10 Remediation Report, Audit Lifecycle Fixture Fix, Browser Fixture Graph Bootstrap, 44px Minimum Touch Target Remediation, useLabWorkspace Authorization Fix, Task 10 Report — Gate 9/10 Evidence, gate10-quality.yml CI Pipeline, Exact-SHA Browser Evidence (+5 more)

### Community 97 - "Composer Package Config"
Cohesion: 0.26
Nodes (3): App\Services\Ingestion\SensorReadingService, PublicGraphSeriesService, Carbon\CarbonImmutable

### Community 100 - "Network Assertion Scripts"
Cohesion: 0.26
Nodes (3): ExampleTest, MigrationIntegrityTest, PHPUnit\Framework\TestCase

### Community 101 - "Dependency Audit Scripts"
Cohesion: 0.26
Nodes (9): buildMatrixSummaryRecord(), isPassingMatrixRecord(), parseMatrixRow(), parsedRun, clean, __dirname, filter, lines (+1 more)

### Community 102 - "Community 102"
Cohesion: 0.30
Nodes (10): boundariesFromRegions(), buildZonesViewModel(), KNOWN_SEVERITIES, moreSevere(), normalizeBoundaries(), normalizeRegions(), normalizeSeverity(), PRECEDENCE (+2 more)

### Community 103 - "Settings View State"
Cohesion: 0.20
Nodes (9): alerts, alertsStore, count, error, loading, flush(), getActiveAlerts, mountActiveAlertsCard() (+1 more)

### Community 104 - "Config View Sections"
Cohesion: 0.26
Nodes (11): onResync(), ADR-0002, normalizeReading(), RECOVERY_WINDOW_MS, resolveSensorId(), SENSOR_EVENT, SENSOR_EVENT_CLASS, useSensorRealtime() (+3 more)

### Community 105 - "Ingestion Backend Client"
Cohesion: 0.18
Nodes (10): autoload-dev, psr-4, description, license, minimum-stability, name, prefer-stable, Tests\\ (+2 more)

### Community 106 - "Payload Validation"
Cohesion: 0.31
Nodes (10): ALLOWED_ANON, apiLogin(), authenticatedScenario(), __dirname, guestScenario(), LEAK_ENDPOINTS, main(), OBSERVE_MS (+2 more)

### Community 107 - "Blade Legacy State"
Cohesion: 0.18
Nodes (8): backendRoots, compose, __dirname, FRONT, RELAY_TOKENS, REPO, RETIRED_ENDPOINTS, violations

### Community 108 - "Realtime Transport Layer"
Cohesion: 0.18
Nodes (10): dependencies, emailVerification, envExample, missing, missingDependencies, packageJson, requiredDependencies, requiredFiles (+2 more)

### Community 109 - "Community 109"
Cohesion: 0.27
Nodes (9): close(), dialogEl, emit, focusableElements(), onKeydown(), props, flush(), mountBaseModal() (+1 more)

### Community 111 - "Permission Relations"
Cohesion: 0.20
Nodes (8): error, errors, form, loading, payload(), save(), saving, success

### Community 112 - "Event Service Provider"
Cohesion: 0.18
Nodes (9): adminActions, alerts, diagnostics, email, general, loading, loadNotice, sections (+1 more)

### Community 113 - "App Service Providers"
Cohesion: 0.24
Nodes (7): BackendClient, BackendClientError, Any, Raised when backend ingestion endpoint cannot be reached or rejects payload., test_backend_client_sends_token_header_and_payload(), RuntimeError, Session

### Community 114 - "Community 114"
Cohesion: 0.38
Nodes (9): PayloadValidationError, Any, Raised when a payload does not satisfy minimum ingestion requirements., validate_payload(), test_validate_payload_accepts_valid_payload(), test_validate_payload_rejects_missing_sensors(), test_validate_payload_rejects_sensor_without_value(), valid_payload() (+1 more)

### Community 115 - "Community 115"
Cohesion: 0.18
Nodes (11): ADR-003: Do Not Move Blade Directly To /front, Blade Views (Legacy UI), script_datos.py (Python IoT Simulator), Operational Risks, Functional State: Blade Legacy, Legacy Views Still Dependent on Blade, Lost or Not-Found Features, Manual Validation Checklist (Not Yet Executed) (+3 more)

### Community 116 - "Frontend Chart Dependencies"
Cohesion: 0.20
Nodes (7): getActiveAlerts, transport, channelCallbacks, connectionWatchers, fetchActiveAlerts, listenOnChannel, resyncWatchers

### Community 118 - "Community 118"
Cohesion: 0.24
Nodes (5): AlertObserver, EventServiceProvider, Illuminate\Auth\Events\Registered, Illuminate\Auth\Listeners\SendEmailVerificationNotification, Illuminate\Foundation\Support\Providers\EventServiceProvider

### Community 120 - "Chart Token Resolution"
Cohesion: 0.20
Nodes (10): dependencies, chart.js, laravel-echo, lightweight-charts, pusher-js, laravel-echo, pusher-js, laravel-echo (+2 more)

### Community 122 - "Community 122"
Cohesion: 0.22
Nodes (7): authStore, deviceStatuses, props, getDevices, mountDeviceStatusList(), mountedApps, visibleDevices

### Community 124 - "Backend Composer Dependencies"
Cohesion: 0.33
Nodes (8): FALLBACK_TOKENS, hexToRgba(), readCssVar(), resolveChartTokens(), resolveZoneTokens(), ZONE_CSS_VARS, ZONE_FALLBACKS, ZONE_FILL_ALPHA

### Community 125 - "Frontend Audit Scripts"
Cohesion: 0.20
Nodes (6): error, errors, form, loading, saving, success

### Community 126 - "Community 126"
Cohesion: 0.22
Nodes (9): Gate 10 Quality CI Workflow, Docker Compose Stack Definition, Raw-First Ingestion Pipeline Documentation, Pending Optimizations Backlog, Architecture Decision Records (ADR G1), Gate 10 Evidence (CDC Pipeline Verified), Gate 9 Evidence (Source Stabilization), Gate 10 Fault Matrix (Target) (+1 more)

### Community 128 - "Community 128"
Cohesion: 0.22
Nodes (9): require, laravel/framework, laravel/sanctum, laravel/tinker, laravel/ui, php, predis/predis, pusher/pusher-php-server (+1 more)

### Community 130 - "Sensor Board State"
Cohesion: 0.22
Nodes (8): __dirname, envExample, __filename, forbiddenKeys, frontDockerfile, frontRoot, repoRoot, requiredFiles

### Community 134 - "Community 134"
Cohesion: 0.28
Nodes (6): emit, filteredSensors, form, nullableNumber(), props, submit()

### Community 135 - "Community 135"
Cohesion: 0.25
Nodes (8): devices, fetchWindow, flush(), mountBoard(), mountedApps, resultForQuery, subscribeSensor, unsubscribeSensor

### Community 136 - "Community 136"
Cohesion: 0.22
Nodes (6): alert, error, loading, props, resolving, success

### Community 137 - "Community 137"
Cohesion: 0.29
Nodes (3): Permission, Illuminate\Database\Eloquent\Relations\BelongsToMany, Illuminate\Database\Eloquent\Relations\HasMany

### Community 138 - "Community 138"
Cohesion: 0.25
Nodes (8): require-dev, fakerphp/faker, laravel/pail, laravel/pint, laravel/sail, mockery/mockery, nunomaduro/collision, phpunit/phpunit

### Community 140 - "Base Modal Dialog"
Cohesion: 0.46
Nodes (3): AlertTriggerTransitionTest, Sensor, DomainEventOutbox

### Community 146 - "Community 146"
Cohesion: 0.29
Nodes (8): M10 — Docker Stack Health, Modal Focus Trap / Escape Tests, ADR-1: Durable Publication Topology, Golden Rules — No Polling, G0D — Reuse & Ownership Freeze, SEC-1 — Rotate SMTP Credentials, Stage Sequencing DAG, Migration Stages 0–10

### Community 147 - "Community 147"
Cohesion: 0.36
Nodes (6): channelKey(), getChannelRefCount(), leaveChannelName(), listenOnChannel(), refCounts, echoMock

### Community 148 - "Composer Plugin Config"
Cohesion: 0.25
Nodes (3): connectionWatchers, echoMock, resyncWatchers

### Community 149 - "Community 149"
Cohesion: 0.36
Nodes (3): activeControllersByConsumer, buildGraphQueryKey(), useGraphSeriesQueryStore

### Community 150 - "Community 150"
Cohesion: 0.25
Nodes (6): authStore, error, loading, resending, route, success

### Community 151 - "Community 151"
Cohesion: 0.29
Nodes (8): Phase 8A API Additions (CRUD/Admin), Fase 7B: Operational Validation Before Blade Cleanup, Fase 8A: Admin CRUD/Catalog SPA Migration, QueueSmokeJob (queue infrastructure smoke test), High Risks, Fase 6B: Blade Legacy Cleanup and Production SPA Adjustment (Pending), Fase 8: Final Cleanup (Pending), Session Progress: Fase 8A Work Log

### Community 154 - "Frontend Audit Checks"
Cohesion: 0.29
Nodes (7): pestphp/pest-plugin, php-http/discovery, config, allow-plugins, optimize-autoloader, preferred-install, sort-packages

### Community 156 - "Community 156"
Cohesion: 0.48
Nodes (6): BaseModel, DevicePayload, MqttPayload, QCPayload, RawIngestionEvent, SensorValue

### Community 157 - "Graph Series Service"
Cohesion: 0.57
Nodes (5): hasInteractionFailure(), isCleanResult(), isPageCorrect(), isPassingAuditResult(), healthyRun

### Community 158 - "Graph Series Compose"
Cohesion: 0.29
Nodes (6): allowScripts, esbuild@0.21.5, name, private, type, version

### Community 159 - "Device Status Snapshot"
Cohesion: 0.29
Nodes (6): missing, navbar, requiredFiles, root, router, srcFilesToCheck

### Community 162 - "Docker Architecture Docs"
Cohesion: 0.38
Nodes (3): getGraphSeries(), getPrivateGraphSeries(), toWindowParam()

### Community 163 - "Realtime Integration Docs"
Cohesion: 0.52
Nodes (4): composeGraphSeries(), computeStats(), idCompare(), toPoint()

### Community 164 - "Graph Catalog Endpoint"
Cohesion: 0.29
Nodes (4): connectionWatchers, echoMock, getDeviceStatusSnapshot, resyncWatchers

### Community 165 - "Community 165"
Cohesion: 0.29
Nodes (5): error, form, loading, route, success

### Community 166 - "Community 166"
Cohesion: 0.29
Nodes (7): applyDevicesPage(), closeForm(), deleteSelectedDevice(), devicePayload(), load(), loadMore(), saveDevice()

### Community 167 - "Community 167"
Cohesion: 0.33
Nodes (7): ADR-007: Docker Per Service, Docker/Docker Compose (absent at audit time), Phase 6 Physical Separation Notes, Phase 7 Docker Consumer Notes, Fase 6: Physical Separation Into /back, Fase 7: Docker and Compose, Functional State: Docker

### Community 168 - "Community 168"
Cohesion: 0.29
Nodes (7): Pusher / Laravel Echo Realtime, Broadcast Event: NewAlertTriggered (channel alerts), Broadcast Event: NewSensorReading (channel sensor.{id}), Phase 4 Frontend Consumer Notes, Phase 5 Realtime Contract Notes, Fase 4: Progressive Screen Migration, Fase 5: Realtime (Echo/Pusher) Integration

### Community 169 - "Frontend Build Config"
Cohesion: 0.52
Nodes (7): Task 8 Report — Authenticated Graph Catalog, GET /api/dashboard/graph-catalog Endpoint, DashboardGraphCatalogController, getAuthenticatedGraphCatalog() Frontend Client, Public Bootstrap + Authenticated Catalog Merge, RuleToGraphZones Mapping, Sanctum API Authentication Group

### Community 172 - "Community 172"
Cohesion: 0.33
Nodes (4): Illuminate\Foundation\Application, Illuminate\Foundation\Configuration\Exceptions, Illuminate\Foundation\Configuration\Middleware, Symfony\Component\HttpKernel\Exception\BadRequestHttpException

### Community 173 - "Community 173"
Cohesion: 0.33
Nodes (5): private, scripts, build, dev, type

### Community 175 - "Auth Install Script"
Cohesion: 0.33
Nodes (4): email, error, loading, success

### Community 176 - "Forgot Password View"
Cohesion: 0.33
Nodes (4): authStore, form, route, router

### Community 177 - "Login Form State"
Cohesion: 0.33
Nodes (4): error, items, loading, system

### Community 178 - "System Info View"
Cohesion: 0.47
Nodes (5): configApi, flush(), mountView(), response(), setResponses()

### Community 179 - "Config API State"
Cohesion: 0.40
Nodes (5): flush(), getDevice, getDeviceSensors, mountDeviceDetailView(), mountedApps

### Community 180 - "Device Detail View"
Cohesion: 0.40
Nodes (5): flush(), getDevices, mountDevicesView(), mountedApps, updateDeviceStatus

### Community 181 - "Devices List View"
Cohesion: 0.33
Nodes (6): iot-platform-v2 Project, Incremental Migration Principle, Front/Back Separation Goal, ADR-002: Backend As REST API + Broadcasting, ADR-004: Incremental Migration, Laravel 12 / PHP 8.2+ Backend

### Community 182 - "Project Architecture ADRs"
Cohesion: 0.33
Nodes (6): ADR-001: Keep A Monorepo Initially, Migration Hygiene Rules, Fase 0: Read-only Audit and Real Contract, Fase 1: Headless Auth (Sanctum Bearer), Fase 2: API Normalization Replacing Blade, Codex Working Notes Operational Rules

### Community 183 - "Migration Hygiene Rules"
Cohesion: 0.40
Nodes (5): autoload, psr-4, App\\, Database\\Factories\\, Database\\Seeders\\

### Community 184 - "Frontend Entry Config"
Cohesion: 0.40
Nodes (4): Monolog\Handler\NullHandler, Monolog\Handler\StreamHandler, Monolog\Handler\SyslogUdpHandler, Monolog\Processor\PsrLogMessageProcessor

### Community 185 - "Autoload Config"
Cohesion: 0.40
Nodes (3): chart, lineSeries, lineSeriesDef

### Community 187 - "Chart Line Series"
Cohesion: 0.40
Nodes (5): chart.js, chart.js, Functional State: Frontend, Functional Inventory: Frontend SPA Table, Partially Migrated Features

### Community 188 - "Community 188"
Cohesion: 0.40
Nodes (5): Front SPA HTML Entrypoint, ADR-008: Frontend Framework (Vue 3 + Bootstrap 5, proposed), Vite (laravel-vite-plugin), Phase 3 Frontend Consumer Notes, Fase 3: Vue 3 SPA Initialization

### Community 189 - "Community 189"
Cohesion: 0.50
Nodes (3): metrics, props, renderMetricsCards()

### Community 191 - "Frontend Functional State"
Cohesion: 0.60
Nodes (3): normalizeReading(), normalizeReadings(), useSensorReadingsStore

### Community 192 - "Frontend Framework ADR"
Cohesion: 0.50
Nodes (5): ADR-009: SPA Auth Uses Sanctum Bearer Tokens Initially, Laravel Sanctum, Authentication Strategy: Sanctum Bearer Tokens, Bearer Token in localStorage Risk, SEC-003: Bearer Token in localStorage

### Community 193 - "Metrics Cards Render"
Cohesion: 0.90
Nodes (5): Task 9 Report — Oversized Graph Window Validation, PublicGraphController, PublicGraphSeriesService, SensorApiController, 24-Hour Window Validation (HTTP 422)

### Community 194 - "Community 194"
Cohesion: 0.50
Nodes (4): post-create-project-cmd, @php artisan key:generate --ansi, @php artisan migrate --graceful --ansi, @php -r \"file_exists('database/database.sqlite') || touch('database/database.sqlite');\

### Community 197 - "Graph Window Validation"
Cohesion: 0.83
Nodes (3): getAudioContext(), playAlertSound(), unlockAlertSound()

### Community 201 - "Community 201"
Cohesion: 0.67
Nodes (3): extra, laravel, dont-discover

### Community 202 - "Community 202"
Cohesion: 0.67
Nodes (3): keywords, framework, laravel

### Community 236 - "Community 236"
Cohesion: 0.67
Nodes (3): User model, ResetPasswordNotification, VerifyEmailNotification

### Community 238 - "User Model Notifications"
Cohesion: 0.67
Nodes (3): Role-Based Access Boundary Tests, Fail-Closed Policy, PublicGraphVisibility

### Community 244 - "Community 244"
Cohesion: 0.67
Nodes (3): ADR-005: Separate Environment Variables, ADR-006: API-Only Frontend Communication, FIX-001: front/.env Configured With Only Public VITE_* Vars

### Community 245 - "Environment Variable ADRs"
Cohesion: 0.67
Nodes (3): Functional State: Backend, Functional Inventory: Backend API Table, Conserved Features (Blade to Vue Parity)

### Community 246 - "Backend Functional State"
Cohesion: 0.67
Nodes (3): Task 11 Report — Mobile/Accessibility, AppLayout Route Stub, Dependency Audit (npm audit --offline)

## Ambiguous Edges - Review These
- `NewSensorReading event` → `AlertService`  [AMBIGUOUS]
  INFORME_ALERTAS_NOTIFICACIONES.md · relation: calls

## Knowledge Gaps
- **687 isolated node(s):** `DashboardPreferenceController`, `backendRoots`, `compose`, `__dirname`, `FRONT` (+682 more)
  These have ≤1 connection - possible missing edges or undocumented components. (Counts symbols only; 1427 node(s) total have ≤1 connection when file, concept and rationale nodes are included.)
- **77 thin communities (<3 nodes) omitted from report** — run `graphify query` to explore isolated nodes.

## Suggested Questions
_Questions this graph is uniquely positioned to answer:_

- **What is the exact relationship between `NewSensorReading event` and `AlertService`?**
  _Edge tagged AMBIGUOUS (relation: calls) - confidence is low._
- **Why does `User` connect `User Auth & Policy Tests` to `API Security Tests`, `Device Tests`, `Domain Events & Resources`, `Alert Rule & Sensor Controllers`, `CDC Domain Event Pipeline`, `API Controllers`, `Channel Registry Mock`, `Laravel Controllers & Auth`, `Domain Models & Events`, `Auth Notifications`, `Migration Phase Docs`, `API Metrics & Monitoring`, `Event Pipeline Metrics`, `Sensor & Public Graph`, `Community 155`, `Database Seeders`, `Playwright E2E Tests`, `Alert Rule Resources`, `Realtime Alerts Channel`, `Community 171`, `MQTT Simulation Scripts`, `Monolog Handlers`, `DeviceType Controllers`, `Email Config View State`, `Register View State`, `Community 98`, `Security Audit Scripts`, `Config Form State`?**
  _High betweenness centrality (0.055) - this node is a cross-community bridge._
- **Why does `Device` connect `Device Tests` to `User Auth & Policy Tests`, `API Security Tests`, `Sensor Form Emit`, `Domain Events & Resources`, `Alert Rule & Sensor Controllers`, `CDC Domain Event Pipeline`, `API Controllers`, `Base Modal Dialog`, `Domain Models & Events`, `Laravel Controllers & Auth`, `Echo Mock Connection`, `Auth Notifications`, `Lab & Device Controllers`, `CDC Outbox Stream Consumer`, `Migration Phase Docs`, `Sensor & Public Graph`, `Config Controllers`, `Sensor Alert Tests`, `Community 155`, `Playwright E2E Tests`, `Config System Tests`, `Alert Rule Resources`, `Lab Workspace Composable`, `Realtime Alerts Channel`, `Dashboard Metrics Charts`, `Ingestion Identity Builder`, `Auth Transition Evidence`, `Vue Unit Test Stubs`, `MQTT Simulation Scripts`, `Frontend Test Dependencies`, `Gate 10 Evidence Reports`, `Composer Package Config`, `Security Audit Scripts`?**
  _High betweenness centrality (0.044) - this node is a cross-community bridge._
- **Why does `TestCase` connect `API Security Tests` to `User Auth & Policy Tests`, `Device Tests`, `Domain Events & Resources`, `Alert Rule & Sensor Controllers`, `CDC Domain Event Pipeline`, `Community 139`, `Domain Models & Events`, `Channel Registry Mock`, `Echo Mock Connection`, `Auth Notifications`, `CDC Outbox Stream Consumer`, `Graph Series Query Store`, `Email Verify View`, `Event Pipeline Metrics`, `Migration Phase Docs`, `API Metrics & Monitoring`, `Config Controllers`, `Sensor Alert Tests`, `Database Seeders`, `Community 155`, `Playwright E2E Tests`, `Alert Rule Resources`, `Alert Rule Modal State`, `Device API Resources`, `MQTT Simulation Scripts`, `Monolog Handlers`, `DeviceType Controllers`, `Email Config View State`, `Artisan Console Commands`, `Composer Package Config`, `Community 98`, `Config Form State`?**
  _High betweenness centrality (0.028) - this node is a cross-community bridge._
- **What connects `DashboardPreferenceController`, `backendRoots`, `compose` to the rest of the system?**
  _687 weakly-connected nodes found - possible documentation gaps or missing edges._
- **Should `User Auth & Policy Tests` be split into smaller, more focused modules?**
  _Cohesion score 0.030692243536280233 - nodes in this community are weakly interconnected._
- **Should `API Security Tests` be split into smaller, more focused modules?**
  _Cohesion score 0.03901319563970167 - nodes in this community are weakly interconnected._