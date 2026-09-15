# Graph Report - iot-platform-v2  (2026-09-15)

## Corpus Check
- 277 files · ~306,564 words
- Verdict: corpus is large enough that graph structure adds value.

## Summary
- 3717 nodes · 7215 edges · 352 communities (158 shown, 87 thin omitted)
- Extraction: 96% EXTRACTED · 4% INFERRED · 0% AMBIGUOUS · INFERRED: 286 edges (avg confidence: 0.86)
- Token cost: 0 input · 0 output

## Community Hubs (Navigation)
- User Auth & Policy Tests
- Alert Rule Tests
- Python CLI Scripts
- Device Tests
- CDC Domain Event Pipeline
- Project Documentation
- Laravel Controllers & Auth
- CDC Outbox Stream Consumer
- Domain Models & Events
- Ingestion Services
- API Controllers
- Sensor & Public Graph
- Blade Controllers
- Frontend Shared Components
- Blade Auth State
- Blade Config State
- Auth Notifications
- Vue Auth Store
- Dashboard Widget State
- Database Factories
- Raw Event Ingestion
- Blade Request Handlers
- Vue Sensor Management
- Alert Rule Backend
- Email & Role Controllers
- Sensor Data Projection
- Chart Visualization
- Python IoT Sender Scripts
- Event Broadcasting
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
- Community 67
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
- Community 84
- Community 85
- Community 86
- Community 87
- Community 88
- Community 89
- Community 90
- Community 91
- Community 92
- Community 93
- Community 94
- Community 95
- Community 96
- Community 97
- Community 98
- Community 99
- Community 100
- Community 101
- Community 102
- Community 103
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
- Community 114
- Community 115
- Community 116
- Community 117
- Community 119
- Community 120
- Community 121
- Community 122
- Community 123
- Community 124
- Community 125
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
- Community 146
- Community 147
- Community 148
- Community 149
- Community 150
- Community 151
- Community 152
- Community 153
- Community 154
- Community 157
- Community 158
- Community 159
- Community 160
- Community 161
- Community 162
- Community 163
- Community 164
- Community 165
- Community 166
- Community 167
- Community 168
- Community 169
- Community 170
- Community 171
- Community 172
- Community 173
- Community 174
- Community 175
- Community 176
- Community 177
- Community 178
- Community 179
- Community 180
- Community 181
- Community 182
- Community 183
- Community 184
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
- Community 198
- Community 200
- Community 201
- Community 202
- Community 203
- Community 204
- Community 205
- Community 236
- Community 237
- Community 238
- Community 239
- Community 240
- Community 241
- Community 242
- Community 243
- Community 244
- Community 245
- Community 246
- Community 247
- Community 248
- Community 249
- Community 250
- Community 251
- Community 252
- Community 253
- Community 255
- Community 256
- Community 259
- Community 260
- Community 261
- Community 262
- Community 263
- Community 264
- Community 316
- Community 317
- Community 333
- Community 334
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

## God Nodes (most connected - your core abstractions)
1. `User` - 288 edges
2. `Device` - 211 edges
3. `TestCase` - 187 edges
4. `Sensor` - 106 edges
5. `Alert` - 101 edges
6. `SensorType` - 48 edges
7. `AlertRule` - 47 edges
8. `SensorReading` - 45 edges
9. `Controller` - 44 edges
10. `EventPipelineMetricsService` - 42 edges

## Surprising Connections (you probably didn't know these)
- `chart.js` --references--> `Functional Inventory: Frontend SPA Table`  [INFERRED]
  front/package.json → memory/11-functional-inventory.md
- `useAlertsRealtime` --semantically_similar_to--> `channelRegistry`  [INFERRED] [semantically similar]
  docs/implementation/ownership.md → front_rebuild_plan/SINOA_Agentic_Dashboard_Implementation_Plan_v2.1_PUBLIC_REALTIME.md
- `Responsive Audit Matrix (33 Rows)` --semantically_similar_to--> `44px Minimum Touch Target Remediation`  [INFERRED] [semantically similar]
  task-10-report.md → task-10-remediation-report.md
- `Backend Agent Definition (iot-back-implementador)` --references--> `Full Migration Plan (refraccion)`  [INFERRED]
  .claude/agents/iot-back-implementador.md → PLAN.md
- `Backend Agent Definition (iot-back-implementador)` --references--> `Architecture Audit and Implementation Plan`  [INFERRED]
  .claude/agents/iot-back-implementador.md → audit.md

## Import Cycles
- None detected.

## Hyperedges (group relationships)
- **Sensor reading to alert notification pipeline** — code_back_app_http_controllers_api_sensorapicontroller, code_back_app_observers_sensorreadingobserver, code_back_app_models_sensorreading, code_back_app_services_alerts_alertservice, code_back_app_observers_alertobserver, code_back_app_services_notifications_notificationservice, code_back_app_events_newalerttriggered, code_back_app_mail_dangeralertmail [EXTRACTED 1.00]
- **ICONTEC/ISO normative compliance document set** — docs_icontec_compliance, docs_matriz_trazabilidad_icontec_iso, docs_evidencia_cumplimiento_codigo_estructura, docs_procedimiento_auditoria_normativa, docs_referencias_icontec, docs_plantilla_trabajo_icontec, analisis_proyecto [EXTRACTED 1.00]
- **SPA Auth & Security Boundary Design** — memory_01_architecture_decisions_adr_005_env_separation, memory_01_architecture_decisions_adr_006_api_only_communication, memory_01_architecture_decisions_adr_009_sanctum_bearer_auth, memory_15_security_review_sec_003_localstorage_bearer_token [INFERRED 0.85]
- **Front/Back Migration Phase Roadmap (Fase 0 to Fase 8A)** — memory_05_migration_log_fase_0, memory_05_migration_log_fase_1, memory_05_migration_log_fase_2, memory_05_migration_log_fase_3, memory_05_migration_log_fase_4, memory_05_migration_log_fase_5, memory_05_migration_log_fase_6, memory_05_migration_log_fase_7, memory_05_migration_log_fase_7b, memory_05_migration_log_fase_8a [EXTRACTED 1.00]
- **Fase 6B Blade Cleanup Gating Decision** — memory_14_blade_cleanup_readiness_verdict_nogo, memory_13_manual_validation_checklist_checklist, memory_12_stable_blade_comparison_missing_features, memory_06_pending_risks_operational_risks, memory_08_refactor_checklist_fase_6b [EXTRACTED 1.00]
- **CDC Pipeline Architecture** — docs__INGESTION_PIPELINE.md, docker-compose.yml, docs__implementation__adr-g1.md, docs__implementation__gate-10-evidence.md, docs__implementation__gate10-fault-matrix-current.md, back__github__workflows__gate10-quality.yml [INFERRED]
- **Migration Gate Evidence Chain** — PLAN.md, docs__implementation__gates-6-7-8-evidence.md, docs__implementation__gate-9-evidence.md, docs__implementation__gate-10-evidence.md, docs__implementation__gate10-fault-matrix-current.md [INFERRED]
- **Agent Convention Definitions** — back__claude__agents__iot-back-implementador.md, back__claude__agents__iot-front-implementador.md, CLAUDE.md, PLAN.md [INFERRED]
- **Ingestion Data Pipeline** — docs_superpowers_plans_2026_09_08_gate_s5r_closure_md_sensor_reading_service, docs_superpowers_plans_2026_09_08_gate_s5r_closure_md_raw_reading_normalizer, docs_superpowers_plans_2026_09_08_gate_s5r_closure_md_domain_event_broadcast_consumer, docs_superpowers_plans_2026_09_08_gate_s5r_closure_md_new_sensor_reading, docs_implementation_ownership_md_raw_sensor_event_publisher [EXTRACTED 1.00]
- **Public Graph Visibility Boundary** — front_rebuild_plan_public_graph_visibility_agentic_execution_plan_md_public_graph_visibility_service, front_rebuild_plan_public_graph_visibility_agentic_execution_plan_md_public_graph_controller, front_rebuild_plan_public_graph_visibility_agentic_execution_plan_md_public_graph_series_service, docs_superpowers_plans_2026_09_08_gate_s5r_closure_md_domain_event_broadcast_consumer [EXTRACTED 1.00]
- **Role Access Control Boundary** — docs_superpowers_plans_2026_09_11_role_access_control_md_role_access_control, docs_superpowers_specs_2026_09_11_role_access_control_design_md_role_access_design, docs_superpowers_plans_2026_09_11_role_access_control_md_can_manage_dashboard, docs_security_security_hardening_plan_md_resource_access_service [INFERRED 0.85]
- **CDC Pipeline: MySQL Outbox → Debezium → Redis → Application Stream → Raw Consumer** — scripts_gate10_readme_mysql_outbox, scripts_gate10_readme_debezium_cdc, scripts_gate10_readme_fault_scenarios, ingestion_service_readme_raw_process_v1, ingestion_service_readme_raw_consume [EXTRACTED 0.95]
- **Authenticated Graph Catalog: Endpoint → Controller → Zones Mapping → Frontend Client** — task_8_report_dashboard_graph_catalog, task_8_report_dashboard_graph_catalog_controller, task_8_report_rule_to_graph_zones, task_8_report_getauthenticatedgraphcatalog, task_8_report_sanctum_auth [EXTRACTED 0.95]
- **Graph Series Window Validation: Controllers → Service → HTTP 422** — task_9_report_publicgraph_controller, task_9_report_sensor_api_controller, task_9_report_publicgraphseries_service, task_9_report_window_validation [EXTRACTED 0.95]

## Communities (352 total, 87 thin omitted)

### Community 0 - "User Auth & Policy Tests"
Cohesion: 0.03
Nodes (14): User, DevicePolicy, AdminAccessTest, EmailSecretStorageTest, SensorAuthorizationTest, BroadcastChannelAuthorizationTest, ConfigGeneralUpdateTest, Phase2ApiEndpointsTest (+6 more)

### Community 1 - "Alert Rule Tests"
Cohesion: 0.03
Nodes (26): AlertRuleCascadeDeleteTest, AlertRuleNameTest, AlertRuleValidationTest, AlertTransportAuthorizationTest, IoTCredentialSecurityTest, RateLimitSecurityTest, ApiAuthTokenTest, ConfigSystemInfoTest (+18 more)

### Community 2 - "Python CLI Scripts"
Cohesion: 0.06
Nodes (31): current_sha(), main(), parse_args(), Any, Namespace, Path, Write a short human index derived exclusively from the evidence document., write_json() (+23 more)

### Community 3 - "Device Tests"
Cohesion: 0.04
Nodes (12): Device, DataLeakageSentinelTest, DeviceAuthorizationTest, ResourcePayloadTest, ApiRoutingRegressionTest, DangerAlertEmailTest, DashboardGraphCatalogControllerTest, DeviceApiKeyVisibilityTest (+4 more)

### Community 4 - "CDC Domain Event Pipeline"
Cohesion: 0.06
Nodes (20): App\Models\DomainEventOutbox, App\Models\RawSensorEvent, ConsumeCdcOutboxes, ConsumeDomainEvents, DebeziumChange, UsesRawRedisCommands, DomainEventBroadcastConsumer, DomainEventOutbox (+12 more)

### Community 5 - "Project Documentation"
Cohesion: 0.05
Nodes (59): Project Changelog, Project Instructions (CLAUDE.md), Formal Technical Documentation (superseded), Full Migration Plan (refraccion), Project README (verified state), RBAC/Security Remediation Summary, ANALISIS_PROYECTO.md (analisis tecnico y de cumplimiento), Architecture Audit and Implementation Plan (+51 more)

### Community 6 - "Laravel Controllers & Auth"
Cohesion: 0.06
Nodes (25): App\Http\Resources\AlertResource, HealthController, ProfileController, ConfirmPasswordController, LoginController, VerificationController, Controller, Carbon\Carbon (+17 more)

### Community 7 - "CDC Outbox Stream Consumer"
Cohesion: 0.08
Nodes (8): CdcOutboxStreamConsumer, CdcOutboxStreamConsumerTest, Connection, DomainEventOutbox, RawSensorEvent, IngestionApiTest, PDOException, RawEventOutbox

### Community 8 - "Domain Models & Events"
Cohesion: 0.08
Nodes (24): App\Events\DeviceStatusUpdated, App\Events\NewSensorReading, App\Jobs\SendDangerAlertEmailJob, App\Mail\DangerAlertMail, App\Models\AlertRule, App\Models\DeviceStatusLog, App\Models\DeviceType, App\Models\Lab (+16 more)

### Community 9 - "Ingestion Services"
Cohesion: 0.06
Nodes (11): App\Services\Ingestion\DomainEventRecorder, AlertFeedController, AlertService, DeviceService, DomainEventRecorder, SensorReadingProjectionService, SensorReadingService, PublicGraphSeriesService (+3 more)

### Community 10 - "API Controllers"
Cohesion: 0.08
Nodes (17): App\Http\Controllers\Api\HealthController, App\Http\Controllers\Api\InternalMetricsController, AuthApiController, PublicGraphController, RoleController, DashboardPreferenceController, UserRoleController, Collection (+9 more)

### Community 11 - "Sensor & Public Graph"
Cohesion: 0.09
Nodes (6): Sensor, PublicGraphControllerTest, Sensor, RawReadingNormalizerTest, NotificationServiceRateLimitTest, CarbonImmutable

### Community 12 - "Blade Controllers"
Cohesion: 0.06
Nodes (22): App\Http\Controllers\AlertRuleController, App\Http\Controllers\ConfigController, App\Http\Controllers\DeviceController, App\Http\Controllers\DeviceTypeController, App\Http\Controllers\EmailConfigController, App\Http\Controllers\LabController, App\Http\Controllers\MetricsController, App\Http\Controllers\SensorController (+14 more)

### Community 13 - "Frontend Shared Components"
Cohesion: 0.06
Nodes (46): Lab Blue Resource Views, LabShell, AlertService, BaseModal, Echo JS, Reuse and Ownership Freeze Matrix, Polling Inventory, RawSensorEventPublisher (+38 more)

### Community 14 - "Blade Auth State"
Cohesion: 0.05
Nodes (34): authStore, props, readingsStore, subscriptions, mountedApps, mountList(), onReadingBySensor, subscribeSensor (+26 more)

### Community 15 - "Blade Config State"
Cohesion: 0.05
Nodes (33): apps, flush(), mount(), catalogConfigs, config, deletingId, editingId, error (+25 more)

### Community 16 - "Auth Notifications"
Cohesion: 0.06
Nodes (15): App\Notifications\ResetPasswordNotification, App\Notifications\VerifyEmailNotification, UserSeeder, AuthSecurityTest, AuthSecurityTest, PasswordResetTest, RegistrationEmailVerificationTest, Illuminate\Contracts\Auth\MustVerifyEmail (+7 more)

### Community 17 - "Vue Auth Store"
Cohesion: 0.06
Nodes (5): app, authStore, pinia, router, useAuthStore

### Community 18 - "Dashboard Widget State"
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

### Community 19 - "Database Factories"
Cohesion: 0.07
Nodes (13): AlertFactory, AlertRuleFactory, DeviceFactory, DeviceStatusLogFactory, DeviceTypeFactory, DomainEventOutboxFactory, LabFactory, RawEventOutboxFactory (+5 more)

### Community 20 - "Raw Event Ingestion"
Cohesion: 0.12
Nodes (9): ConsumeRawEvents, SensorReadingService, RawReadingNormalizer, RawStreamConsumer, Connection, RawSensorEvent, RedisManager, Sensor (+1 more)

### Community 21 - "Blade Request Handlers"
Cohesion: 0.09
Nodes (15): App\Http\Controllers\Controller, App\Http\Requests\Api\StoreRawIngestionEventRequest, App\Models\RawEventOutbox, DashboardGraphCatalogController, IngestionController, ForgotPasswordController, RegisterController, ResetPasswordController (+7 more)

### Community 22 - "Vue Sensor Management"
Cohesion: 0.07
Nodes (29): authStore, closeForm(), defaultSensorForm(), deleteSelectedSensor(), devices, editingSensorId, error, filteredSensors (+21 more)

### Community 23 - "Alert Rule Backend"
Cohesion: 0.10
Nodes (7): AlertRuleController, AlertRule, SensorReading, AlertSeeder, AlertUniqueConstraintTest, SensorReadingAlertTest, SensorReadingTriggeredRulesTest

### Community 24 - "Email & Role Controllers"
Cohesion: 0.11
Nodes (5): AlertController, EmailConfigController, UserRoleController, AuditService, DashboardMetricsService

### Community 25 - "Sensor Data Projection"
Cohesion: 0.11
Nodes (5): App\Services\Ingestion\SensorReadingProjectionService, Sensor, SensorApiController, SensorDataController, SensorResource

### Community 26 - "Chart Visualization"
Cohesion: 0.08
Nodes (22): accessibleSummary, chartContainer, chartData, chartOptions, hasData, props, renderChart(), sampleViewModel (+14 more)

### Community 27 - "Python IoT Sender Scripts"
Cohesion: 0.12
Nodes (20): build_qc_checksum(), build_sensor_payload(), device_sender_worker(), get_api_key(), get_location(), get_node_id(), get_sensor_key(), get_sensors() (+12 more)

### Community 28 - "Event Broadcasting"
Cohesion: 0.13
Nodes (11): App\Events\Concerns\HasEventEnvelope, App\Events\Contracts\VersionedDomainEvent, AlertResolved, DeviceCommunicationReceived, NewAlertTriggered, Illuminate\Broadcasting\Channel, Illuminate\Broadcasting\InteractsWithSockets, Illuminate\Broadcasting\PresenceChannel (+3 more)

### Community 29 - "Community 29"
Cohesion: 0.21
Nodes (3): DomainEventBroadcastConsumerTest, Connection, DomainEventOutbox

### Community 30 - "Community 30"
Cohesion: 0.13
Nodes (19): DurableEventSpool, PendingEvent, Return all dead-lettered events for inspection., The durable, local owner of MQTT events awaiting backend delivery., Return the spool's current time source for coordinated components., event(), test_claim_is_exclusive_until_its_lease_expires(), test_creates_missing_spool_parent_directory() (+11 more)

### Community 31 - "Community 31"
Cohesion: 0.15
Nodes (3): Sensor, SensorGraphZonesTest, RuleToGraphZonesTest

### Community 32 - "Community 32"
Cohesion: 0.08
Nodes (24): authStore, defaultDeviceForm(), deviceForm, devices, deviceStatuses, deviceTypes, editingDeviceId, effectiveDevice() (+16 more)

### Community 33 - "Community 33"
Cohesion: 0.15
Nodes (3): EventPipelineMetricsService, Connection, self

### Community 34 - "Community 34"
Cohesion: 0.12
Nodes (4): Alert, NotificationService, AlertResolveTransitionTest, AlertAuthorizationTest

### Community 35 - "Community 35"
Cohesion: 0.08
Nodes (14): consoleErrors, diagnoseOverflow(), __dirname, failedRequests, interact, mode, name, pageErrors (+6 more)

### Community 36 - "Community 36"
Cohesion: 0.12
Nodes (5): ConfigController, ConfigController, EmailConfigController, SystemSetting, SystemSettingTest

### Community 37 - "Community 37"
Cohesion: 0.12
Nodes (6): AlertRuleController, UpdateAlertRuleRequest, AlertResource, AlertRuleResource, DeviceStatusSnapshotResource, Illuminate\Http\Resources\Json\JsonResource

### Community 38 - "Community 38"
Cohesion: 0.16
Nodes (19): ranges, api, apps, mount(), useLabWorkspace(), add(), changeDevice(), changeRange() (+11 more)

### Community 39 - "Community 39"
Cohesion: 0.15
Nodes (23): ALERTS_CHANNEL, ALERTS_EVENT, ALERTS_EVENT_CLASS, ALERTS_RESOLVED_EVENT, ALERTS_RESOLVED_EVENT_CLASS, bufferDuringRecovery(), CONNECTION_ERROR_MESSAGES, error (+15 more)

### Community 40 - "Community 40"
Cohesion: 0.09
Nodes (21): apiMetrics, apiRequestsChart, barOptions, comparisonChart, devicesChart, doughnutOptions, error, gaugeOptions (+13 more)

### Community 41 - "Community 41"
Cohesion: 0.11
Nodes (19): alerts, alertsStore, error, filter, handleResolve(), handleResolveAll(), load(), loading (+11 more)

### Community 42 - "Community 42"
Cohesion: 0.18
Nodes (5): AuditLog, DashboardPreference, RawEventOutbox, Illuminate\Database\Eloquent\Factories\HasFactory, Illuminate\Database\Eloquent\Model

### Community 43 - "Community 43"
Cohesion: 0.17
Nodes (5): DeviceStatusLog, DomainEventOutbox, DeviceShowStatusLogsTest, DeviceStatusChangeTransitionTest, DeviceStatusSnapshotTest

### Community 44 - "Community 44"
Cohesion: 0.15
Nodes (4): EventPipelineMetricsServiceTest, EventPublisherXaddShapeTest, SensorDataControllerTest, Mockery

### Community 45 - "Community 45"
Cohesion: 0.17
Nodes (16): Client, Any, build_raw_event(), derive_source_event_id(), _identity_part(), _iso_now(), Any, Build a retry-stable identity from device-domain fields, never MQTT packet IDs. (+8 more)

### Community 46 - "Community 46"
Cohesion: 0.10
Nodes (15): alertsStore, authStore, deviceStatusesStore, route, { subscribeAlerts, unsubscribeAlerts }, { subscribeDeviceStatus, unsubscribeDeviceStatus }, flush(), getActiveAlerts (+7 more)

### Community 47 - "Community 47"
Cohesion: 0.12
Nodes (20): closeModal(), deleteRule(), deletingId, error, formError, load(), loading, loadMetadata() (+12 more)

### Community 48 - "Community 48"
Cohesion: 0.16
Nodes (5): App\Http\Resources\DeviceStatusSnapshotResource, App\Services\DeviceService, DeviceApiController, DeviceResource, Illuminate\Validation\Rule

### Community 49 - "Community 49"
Cohesion: 0.20
Nodes (10): Throwable, QueueSmokeJob, Throwable, SendDangerAlertEmailJob, UpdateDeviceLastCommunication, Illuminate\Bus\Queueable, Illuminate\Contracts\Queue\ShouldQueue, Illuminate\Foundation\Bus\Dispatchable (+2 more)

### Community 50 - "Community 50"
Cohesion: 0.16
Nodes (5): EvaluateSensorReadingAlerts, SensorReadingObserver, AlertEmailAsyncDeliveryTest, Sensor, SensorReadingAlertEvaluationRecoveryTest

### Community 51 - "Community 51"
Cohesion: 0.13
Nodes (13): __dirname, fakeEchoPlugin(), main(), record(), results, root, __dirname, fakeEchoPlugin() (+5 more)

### Community 52 - "Community 52"
Cohesion: 0.20
Nodes (12): BackendClient, main(), parse_args(), Any, Namespace, run_mqtt(), run_simulation(), _sample_payload() (+4 more)

### Community 53 - "Community 53"
Cohesion: 0.11
Nodes (18): axios, bootstrap, bootstrap, @fontsource/inter, @fontsource/jetbrains-mono, dependencies, axios, bootstrap (+10 more)

### Community 54 - "Community 54"
Cohesion: 0.18
Nodes (3): SensorTypeController, SensorTypeController, SensorType

### Community 55 - "Community 55"
Cohesion: 0.22
Nodes (10): Exception, DeliveryWorker, retry_delay(), event(), RecordingBackend, test_reopened_spool_is_delivered_by_worker(), test_retry_delay_is_capped_exponential_backoff(), test_run_once_deletes_successful_event() (+2 more)

### Community 56 - "Community 56"
Cohesion: 0.11
Nodes (15): activeAlertsCard, alertsRealtime, alertsStore, alertsView, alertToast, appLayout, echo, envExample (+7 more)

### Community 57 - "Community 57"
Cohesion: 0.18
Nodes (15): booleanEnv(), createEcho(), disconnectEcho(), getEcho(), getEchoConfig(), handleAuthChange(), ADR-0002, notify() (+7 more)

### Community 58 - "Community 58"
Cohesion: 0.13
Nodes (13): icons, paths, props, alerts, auth, links, logout(), open (+5 more)

### Community 59 - "Community 59"
Cohesion: 0.21
Nodes (3): DeviceTypeController, DeviceTypeController, DeviceType

### Community 60 - "Community 60"
Cohesion: 0.20
Nodes (3): LabController, LabController, Lab

### Community 61 - "Community 61"
Cohesion: 0.17
Nodes (7): AlertRuleSeeder, DeviceTypeSeeder, RolePermissionSeeder, SensorTypeSeeder, SystemSettingsSeeder, Illuminate\Database\Console\Seeds\WithoutModelEvents, Illuminate\Database\Seeder

### Community 62 - "Community 62"
Cohesion: 0.12
Nodes (17): devDependencies, axios, concurrently, laravel-vite-plugin, @popperjs/core, sass, tailwindcss, @tailwindcss/vite (+9 more)

### Community 63 - "Community 63"
Cohesion: 0.12
Nodes (17): scripts, audit:baseline, audit:events, audit:gate9, audit:network, audit:network:live, audit:no-polling:source, build (+9 more)

### Community 65 - "Community 65"
Cohesion: 0.13
Nodes (13): adminItems, alertsStore, authenticatedItems, authStore, laboratoryItems, navItems, realtimeStatusClass, realtimeStatusLabel (+5 more)

### Community 66 - "Community 66"
Cohesion: 0.13
Nodes (17): Dead Letter Queue (DLQ), Exponential Backoff Delivery, Idempotency Ledger via status Column, MQTT Ingestion, POST /api/ingestion/events, QC Validation Gate, raw:consume Command, raw-process-v1 Redis Consumer Group (+9 more)

### Community 67 - "Community 67"
Cohesion: 0.15
Nodes (13): auth, error, graphDevices, loading, boardDevices, flush(), getActiveAlerts, getAuthenticatedGraphCatalog (+5 more)

### Community 68 - "Community 68"
Cohesion: 0.12
Nodes (12): apiKeyCopied, authStore, device, deviceStatuses, effectiveDevice, error, loading, props (+4 more)

### Community 69 - "Community 69"
Cohesion: 0.19
Nodes (3): ReadingProjection, ReadingProvenanceService, Illuminate\Database\Eloquent\Relations\BelongsTo

### Community 70 - "Community 70"
Cohesion: 0.15
Nodes (9): MailMessage, ResetPasswordNotification, MailMessage, VerifyEmailNotification, Illuminate\Auth\Notifications\ResetPassword, Illuminate\Auth\Notifications\VerifyEmail, Illuminate\Notifications\Messages\MailMessage, Illuminate\Support\Facades\Config (+1 more)

### Community 71 - "Community 71"
Cohesion: 0.15
Nodes (3): UpdateAlertRulesTable, UpdateAlertRulesSeverity, Illuminate\Database\Migrations\Migration

### Community 73 - "Community 73"
Cohesion: 0.13
Nodes (15): devDependencies, jsdom, @playwright/test, sass, vite, @vitejs/plugin-vue, vitest, ws (+7 more)

### Community 74 - "Community 74"
Cohesion: 0.21
Nodes (14): onConnectionStateChange(), DEVICE_STATUS_CHANNEL, DEVICE_STATUS_EVENT, DEVICE_STATUS_EVENT_CLASS, error, eventPayload(), fetchStatusSnapshot(), isConnected (+6 more)

### Community 75 - "Community 75"
Cohesion: 0.18
Nodes (4): StoreRawIngestionEventRequest, UpdateAlertConfigRequest, UpdateGeneralConfigRequest, Illuminate\Foundation\Http\FormRequest

### Community 76 - "Community 76"
Cohesion: 0.22
Nodes (3): StoreAlertRuleRequest, UpdateEmailConfigRequest, Illuminate\Validation\Validator

### Community 77 - "Community 77"
Cohesion: 0.14
Nodes (14): scripts, dev, post-autoload-dump, post-root-package-install, post-update-cmd, test, Composer\\Config::disableProcessTimeout, Illuminate\\Foundation\\ComposerScripts::postAutoloadDump (+6 more)

### Community 78 - "Community 78"
Cohesion: 0.26
Nodes (4): DeadLetterCommandsTest, Connection, RedisManager, Illuminate\Redis\RedisManager

### Community 79 - "Community 79"
Cohesion: 0.15
Nodes (10): email, error, errors, loading, payload(), save(), saving, success (+2 more)

### Community 82 - "Community 82"
Cohesion: 0.24
Nodes (10): alert(), countFor(), device(), deviceSensor(), json(), mockApi(), readings(), USERS (+2 more)

### Community 84 - "Community 84"
Cohesion: 0.17
Nodes (9): error, form, loading, router, success, authApi, mountedApps, mountRegisterView() (+1 more)

### Community 85 - "Community 85"
Cohesion: 0.18
Nodes (13): Task 10 Remediation Report, Audit Lifecycle Fixture Fix, Browser Fixture Graph Bootstrap, 44px Minimum Touch Target Remediation, useLabWorkspace Authorization Fix, Task 10 Report — Gate 9/10 Evidence, gate10-quality.yml CI Pipeline, Exact-SHA Browser Evidence (+5 more)

### Community 86 - "Community 86"
Cohesion: 0.23
Nodes (5): InspectDeadLetters, InspectReadingTimeSemantics, PurgeFutureSensorReadings, ReplayDeadLetter, Illuminate\Console\Command

### Community 90 - "Community 90"
Cohesion: 0.26
Nodes (3): ExampleTest, MigrationIntegrityTest, PHPUnit\Framework\TestCase

### Community 91 - "Community 91"
Cohesion: 0.23
Nodes (9): hasInteractionFailure(), isCleanResult(), isPageCorrect(), healthyRun, clean, __dirname, filter, lines (+1 more)

### Community 92 - "Community 92"
Cohesion: 0.17
Nodes (10): alertsStore, currentAlert, currentDevice, currentMessage, currentSeverity, currentTime, currentTitle, currentValue (+2 more)

### Community 93 - "Community 93"
Cohesion: 0.30
Nodes (10): boundariesFromRegions(), buildZonesViewModel(), KNOWN_SEVERITIES, moreSevere(), normalizeBoundaries(), normalizeRegions(), normalizeSeverity(), PRECEDENCE (+2 more)

### Community 94 - "Community 94"
Cohesion: 0.20
Nodes (9): alerts, alertsStore, count, error, loading, flush(), getActiveAlerts, mountActiveAlertsCard() (+1 more)

### Community 95 - "Community 95"
Cohesion: 0.26
Nodes (11): onResync(), ADR-0002, normalizeReading(), RECOVERY_WINDOW_MS, resolveSensorId(), SENSOR_EVENT, SENSOR_EVENT_CLASS, useSensorRealtime() (+3 more)

### Community 97 - "Community 97"
Cohesion: 0.18
Nodes (10): autoload-dev, psr-4, description, license, minimum-stability, name, prefer-stable, Tests\\ (+2 more)

### Community 99 - "Community 99"
Cohesion: 0.31
Nodes (10): ALLOWED_ANON, apiLogin(), authenticatedScenario(), __dirname, guestScenario(), LEAK_ENDPOINTS, main(), OBSERVE_MS (+2 more)

### Community 100 - "Community 100"
Cohesion: 0.18
Nodes (8): backendRoots, compose, __dirname, FRONT, RELAY_TOKENS, REPO, RETIRED_ENDPOINTS, violations

### Community 101 - "Community 101"
Cohesion: 0.18
Nodes (10): dependencies, emailVerification, envExample, missing, missingDependencies, packageJson, requiredDependencies, requiredFiles (+2 more)

### Community 103 - "Community 103"
Cohesion: 0.20
Nodes (8): error, errors, form, loading, payload(), save(), saving, success

### Community 104 - "Community 104"
Cohesion: 0.18
Nodes (9): adminActions, alerts, diagnostics, email, general, loading, loadNotice, sections (+1 more)

### Community 105 - "Community 105"
Cohesion: 0.24
Nodes (7): BackendClient, BackendClientError, Any, Raised when backend ingestion endpoint cannot be reached or rejects payload., test_backend_client_sends_token_header_and_payload(), RuntimeError, Session

### Community 106 - "Community 106"
Cohesion: 0.38
Nodes (9): PayloadValidationError, Any, Raised when a payload does not satisfy minimum ingestion requirements., validate_payload(), test_validate_payload_accepts_valid_payload(), test_validate_payload_rejects_missing_sensors(), test_validate_payload_rejects_sensor_without_value(), valid_payload() (+1 more)

### Community 107 - "Community 107"
Cohesion: 0.18
Nodes (11): ADR-003: Do Not Move Blade Directly To /front, Blade Views (Legacy UI), script_datos.py (Python IoT Simulator), Operational Risks, Functional State: Blade Legacy, Legacy Views Still Dependent on Blade, Lost or Not-Found Features, Manual Validation Checklist (Not Yet Executed) (+3 more)

### Community 108 - "Community 108"
Cohesion: 0.20
Nodes (7): getActiveAlerts, transport, channelCallbacks, connectionWatchers, fetchActiveAlerts, listenOnChannel, resyncWatchers

### Community 111 - "Community 111"
Cohesion: 0.22
Nodes (4): Permission, Collection, Illuminate\Database\Eloquent\Relations\BelongsToMany, Illuminate\Database\Eloquent\Relations\HasMany

### Community 112 - "Community 112"
Cohesion: 0.24
Nodes (5): AlertObserver, EventServiceProvider, Illuminate\Auth\Events\Registered, Illuminate\Auth\Listeners\SendEmailVerificationNotification, Illuminate\Foundation\Support\Providers\EventServiceProvider

### Community 113 - "Community 113"
Cohesion: 0.24
Nodes (4): AppServiceProvider, ViewServiceProvider, Illuminate\Cache\RateLimiting\Limit, Illuminate\Support\ServiceProvider

### Community 116 - "Community 116"
Cohesion: 0.20
Nodes (10): dependencies, chart.js, laravel-echo, lightweight-charts, pusher-js, laravel-echo, pusher-js, laravel-echo (+2 more)

### Community 117 - "Community 117"
Cohesion: 0.22
Nodes (7): authStore, deviceStatuses, props, getDevices, mountDeviceStatusList(), mountedApps, visibleDevices

### Community 119 - "Community 119"
Cohesion: 0.20
Nodes (4): props, severityClass, authStore, mountedApps

### Community 120 - "Community 120"
Cohesion: 0.33
Nodes (8): FALLBACK_TOKENS, hexToRgba(), readCssVar(), resolveChartTokens(), resolveZoneTokens(), ZONE_CSS_VARS, ZONE_FALLBACKS, ZONE_FILL_ALPHA

### Community 121 - "Community 121"
Cohesion: 0.20
Nodes (6): error, errors, form, loading, saving, success

### Community 124 - "Community 124"
Cohesion: 0.22
Nodes (9): require, laravel/framework, laravel/sanctum, laravel/tinker, laravel/ui, php, predis/predis, pusher/pusher-php-server (+1 more)

### Community 125 - "Community 125"
Cohesion: 0.22
Nodes (8): __dirname, envExample, __filename, forbiddenKeys, frontDockerfile, frontRoot, repoRoot, requiredFiles

### Community 129 - "Community 129"
Cohesion: 0.28
Nodes (6): emit, filteredSensors, form, nullableNumber(), props, submit()

### Community 130 - "Community 130"
Cohesion: 0.25
Nodes (8): devices, fetchWindow, flush(), mountBoard(), mountedApps, resultForQuery, subscribeSensor, unsubscribeSensor

### Community 131 - "Community 131"
Cohesion: 0.22
Nodes (6): alert, error, loading, props, resolving, success

### Community 133 - "Community 133"
Cohesion: 0.25
Nodes (8): require-dev, fakerphp/faker, laravel/pail, laravel/pint, laravel/sail, mockery/mockery, nunomaduro/collision, phpunit/phpunit

### Community 140 - "Community 140"
Cohesion: 0.36
Nodes (7): close(), dialogEl, emit, focusableElements(), onKeydown(), props, titleId

### Community 141 - "Community 141"
Cohesion: 0.36
Nodes (6): channelKey(), getChannelRefCount(), leaveChannelName(), listenOnChannel(), refCounts, echoMock

### Community 142 - "Community 142"
Cohesion: 0.25
Nodes (3): connectionWatchers, echoMock, resyncWatchers

### Community 143 - "Community 143"
Cohesion: 0.36
Nodes (3): activeControllersByConsumer, buildGraphQueryKey(), useGraphSeriesQueryStore

### Community 144 - "Community 144"
Cohesion: 0.25
Nodes (6): authStore, error, loading, resending, route, success

### Community 145 - "Community 145"
Cohesion: 0.29
Nodes (8): Phase 8A API Additions (CRUD/Admin), Fase 7B: Operational Validation Before Blade Cleanup, Fase 8A: Admin CRUD/Catalog SPA Migration, QueueSmokeJob (queue infrastructure smoke test), High Risks, Fase 6B: Blade Legacy Cleanup and Production SPA Adjustment (Pending), Fase 8: Final Cleanup (Pending), Session Progress: Fase 8A Work Log

### Community 148 - "Community 148"
Cohesion: 0.29
Nodes (7): pestphp/pest-plugin, php-http/discovery, config, allow-plugins, optimize-autoloader, preferred-install, sort-packages

### Community 152 - "Community 152"
Cohesion: 0.48
Nodes (6): BaseModel, DevicePayload, MqttPayload, QCPayload, RawIngestionEvent, SensorValue

### Community 153 - "Community 153"
Cohesion: 0.29
Nodes (6): allowScripts, esbuild@0.21.5, name, private, type, version

### Community 154 - "Community 154"
Cohesion: 0.29
Nodes (6): missing, navbar, requiredFiles, root, router, srcFilesToCheck

### Community 157 - "Community 157"
Cohesion: 0.38
Nodes (3): getGraphSeries(), getPrivateGraphSeries(), toWindowParam()

### Community 158 - "Community 158"
Cohesion: 0.52
Nodes (4): composeGraphSeries(), computeStats(), idCompare(), toPoint()

### Community 159 - "Community 159"
Cohesion: 0.29
Nodes (4): connectionWatchers, echoMock, getDeviceStatusSnapshot, resyncWatchers

### Community 160 - "Community 160"
Cohesion: 0.29
Nodes (5): error, form, loading, route, success

### Community 161 - "Community 161"
Cohesion: 0.29
Nodes (7): applyDevicesPage(), closeForm(), deleteSelectedDevice(), devicePayload(), load(), loadMore(), saveDevice()

### Community 162 - "Community 162"
Cohesion: 0.33
Nodes (7): ADR-007: Docker Per Service, Docker/Docker Compose (absent at audit time), Phase 6 Physical Separation Notes, Phase 7 Docker Consumer Notes, Fase 6: Physical Separation Into /back, Fase 7: Docker and Compose, Functional State: Docker

### Community 163 - "Community 163"
Cohesion: 0.29
Nodes (7): Pusher / Laravel Echo Realtime, Broadcast Event: NewAlertTriggered (channel alerts), Broadcast Event: NewSensorReading (channel sensor.{id}), Phase 4 Frontend Consumer Notes, Phase 5 Realtime Contract Notes, Fase 4: Progressive Screen Migration, Fase 5: Realtime (Echo/Pusher) Integration

### Community 164 - "Community 164"
Cohesion: 0.52
Nodes (7): Task 8 Report — Authenticated Graph Catalog, GET /api/dashboard/graph-catalog Endpoint, DashboardGraphCatalogController, getAuthenticatedGraphCatalog() Frontend Client, Public Bootstrap + Authenticated Catalog Merge, RuleToGraphZones Mapping, Sanctum API Authentication Group

### Community 169 - "Community 169"
Cohesion: 0.33
Nodes (5): private, scripts, build, dev, type

### Community 174 - "Community 174"
Cohesion: 0.47
Nodes (4): buildAuthTransitionEvidence(), isAuthTransitionPass(), route, runAuthTransition()

### Community 175 - "Community 175"
Cohesion: 0.47
Nodes (5): installAuth(), ALLOWED_ONE_SHOT, __dirname, isRecurring(), main()

### Community 176 - "Community 176"
Cohesion: 0.33
Nodes (4): email, error, loading, success

### Community 177 - "Community 177"
Cohesion: 0.33
Nodes (4): authStore, form, route, router

### Community 178 - "Community 178"
Cohesion: 0.33
Nodes (4): error, items, loading, system

### Community 179 - "Community 179"
Cohesion: 0.47
Nodes (5): configApi, flush(), mountView(), response(), setResponses()

### Community 180 - "Community 180"
Cohesion: 0.40
Nodes (5): flush(), getDevice, getDeviceSensors, mountDeviceDetailView(), mountedApps

### Community 181 - "Community 181"
Cohesion: 0.40
Nodes (5): flush(), getDevices, mountDevicesView(), mountedApps, updateDeviceStatus

### Community 182 - "Community 182"
Cohesion: 0.33
Nodes (6): iot-platform-v2 Project, Incremental Migration Principle, Front/Back Separation Goal, ADR-002: Backend As REST API + Broadcasting, ADR-004: Incremental Migration, Laravel 12 / PHP 8.2+ Backend

### Community 183 - "Community 183"
Cohesion: 0.33
Nodes (6): ADR-001: Keep A Monorepo Initially, Migration Hygiene Rules, Fase 0: Read-only Audit and Real Contract, Fase 1: Headless Auth (Sanctum Bearer), Fase 2: API Normalization Replacing Blade, Codex Working Notes Operational Rules

### Community 185 - "Community 185"
Cohesion: 0.40
Nodes (5): autoload, psr-4, App\\, Database\\Factories\\, Database\\Seeders\\

### Community 186 - "Community 186"
Cohesion: 0.40
Nodes (4): Monolog\Handler\NullHandler, Monolog\Handler\StreamHandler, Monolog\Handler\SyslogUdpHandler, Monolog\Processor\PsrLogMessageProcessor

### Community 187 - "Community 187"
Cohesion: 0.40
Nodes (3): chart, lineSeries, lineSeriesDef

### Community 191 - "Community 191"
Cohesion: 0.40
Nodes (5): chart.js, chart.js, Functional State: Frontend, Functional Inventory: Frontend SPA Table, Partially Migrated Features

### Community 192 - "Community 192"
Cohesion: 0.40
Nodes (5): Front SPA HTML Entrypoint, ADR-008: Frontend Framework (Vue 3 + Bootstrap 5, proposed), Vite (laravel-vite-plugin), Phase 3 Frontend Consumer Notes, Fase 3: Vue 3 SPA Initialization

### Community 193 - "Community 193"
Cohesion: 0.50
Nodes (3): metrics, props, renderMetricsCards()

### Community 195 - "Community 195"
Cohesion: 0.60
Nodes (3): normalizeReading(), normalizeReadings(), useSensorReadingsStore

### Community 196 - "Community 196"
Cohesion: 0.50
Nodes (5): ADR-009: SPA Auth Uses Sanctum Bearer Tokens Initially, Laravel Sanctum, Authentication Strategy: Sanctum Bearer Tokens, Bearer Token in localStorage Risk, SEC-003: Bearer Token in localStorage

### Community 197 - "Community 197"
Cohesion: 0.90
Nodes (5): Task 9 Report — Oversized Graph Window Validation, PublicGraphController, PublicGraphSeriesService, SensorApiController, 24-Hour Window Validation (HTTP 422)

### Community 198 - "Community 198"
Cohesion: 0.50
Nodes (4): post-create-project-cmd, @php artisan key:generate --ansi, @php artisan migrate --graceful --ansi, @php -r \"file_exists('database/database.sqlite') || touch('database/database.sqlite');\

### Community 200 - "Community 200"
Cohesion: 0.83
Nodes (3): getAudioContext(), playAlertSound(), unlockAlertSound()

### Community 204 - "Community 204"
Cohesion: 0.67
Nodes (3): extra, laravel, dont-discover

### Community 205 - "Community 205"
Cohesion: 0.67
Nodes (3): keywords, framework, laravel

### Community 238 - "Community 238"
Cohesion: 0.67
Nodes (3): User model, ResetPasswordNotification, VerifyEmailNotification

### Community 245 - "Community 245"
Cohesion: 0.67
Nodes (3): ADR-005: Separate Environment Variables, ADR-006: API-Only Frontend Communication, FIX-001: front/.env Configured With Only Public VITE_* Vars

### Community 246 - "Community 246"
Cohesion: 0.67
Nodes (3): Functional State: Backend, Functional Inventory: Backend API Table, Conserved Features (Blade to Vue Parity)

### Community 247 - "Community 247"
Cohesion: 0.67
Nodes (3): Task 11 Report — Mobile/Accessibility, AppLayout Route Stub, Dependency Audit (npm audit --offline)

## Ambiguous Edges - Review These
- `AlertService` → `NewSensorReading event`  [AMBIGUOUS]
  INFORME_ALERTAS_NOTIFICACIONES.md · relation: calls

## Knowledge Gaps
- **676 isolated node(s):** `DashboardPreferenceController`, `__dirname`, `envExample`, `__filename`, `forbiddenKeys` (+671 more)
  These have ≤1 connection - possible missing edges or undocumented components. (Counts symbols only; 1389 node(s) total have ≤1 connection when file, concept and rationale nodes are included.)
- **87 thin communities (<3 nodes) omitted from report** — run `graphify query` to explore isolated nodes.

## Suggested Questions
_Questions this graph is uniquely positioned to answer:_

- **What is the exact relationship between `AlertService` and `NewSensorReading event`?**
  _Edge tagged AMBIGUOUS (relation: calls) - confidence is low._
- **Why does `TestCase` connect `Alert Rule Tests` to `User Auth & Policy Tests`, `Device Tests`, `CDC Domain Event Pipeline`, `Community 134`, `Community 135`, `Domain Models & Events`, `CDC Outbox Stream Consumer`, `Community 136`, `Community 137`, `Sensor & Public Graph`, `Community 138`, `Laravel Controllers & Auth`, `Community 139`, `Auth Notifications`, `API Controllers`, `Community 147`, `Raw Event Ingestion`, `Community 149`, `Community 150`, `Blade Request Handlers`, `Community 151`, `Alert Rule Backend`, `Community 29`, `Community 31`, `Community 34`, `Community 36`, `Community 170`, `Community 171`, `Community 172`, `Community 43`, `Community 44`, `Community 173`, `Community 50`, `Community 188`, `Community 189`, `Community 190`, `Community 72`, `Community 78`, `Community 88`, `Community 89`, `Community 96`, `Community 98`?**
  _High betweenness centrality (0.054) - this node is a cross-community bridge._
- **Why does `User` connect `User Auth & Policy Tests` to `Alert Rule Tests`, `Device Tests`, `Laravel Controllers & Auth`, `Domain Models & Events`, `Community 137`, `API Controllers`, `Auth Notifications`, `Blade Request Handlers`, `Community 149`, `Community 150`, `Email & Role Controllers`, `Community 151`, `Alert Rule Backend`, `Community 31`, `Community 34`, `Community 168`, `Community 42`, `Community 171`, `Community 172`, `Community 43`, `Community 44`, `Community 188`, `Community 69`, `Community 72`, `Community 89`, `Community 96`, `Community 98`, `Community 111`?**
  _High betweenness centrality (0.042) - this node is a cross-community bridge._
- **Why does `Device` connect `Device Tests` to `User Auth & Policy Tests`, `Alert Rule Tests`, `CDC Domain Event Pipeline`, `Community 132`, `Laravel Controllers & Auth`, `Community 135`, `Domain Models & Events`, `Ingestion Services`, `CDC Outbox Stream Consumer`, `Community 137`, `Sensor & Public Graph`, `Community 139`, `Raw Event Ingestion`, `Blade Request Handlers`, `Community 149`, `Alert Rule Backend`, `Email & Role Controllers`, `Sensor Data Projection`, `Community 151`, `Event Broadcasting`, `Community 29`, `Community 165`, `Community 37`, `Community 42`, `Community 170`, `Community 172`, `Community 43`, `Community 48`, `Community 50`, `Community 190`, `Community 87`, `Community 96`, `Community 98`, `Community 109`, `Community 110`?**
  _High betweenness centrality (0.030) - this node is a cross-community bridge._
- **What connects `DashboardPreferenceController`, `__dirname`, `envExample` to the rest of the system?**
  _676 weakly-connected nodes found - possible documentation gaps or missing edges._
- **Should `User Auth & Policy Tests` be split into smaller, more focused modules?**
  _Cohesion score 0.0305060835831423 - nodes in this community are weakly interconnected._
- **Should `Alert Rule Tests` be split into smaller, more focused modules?**
  _Cohesion score 0.033873343151693665 - nodes in this community are weakly interconnected._