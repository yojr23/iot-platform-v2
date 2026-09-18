# Graph Report - iot-platform-v2  (2026-09-18)

## Corpus Check
- Large corpus: 665 files · ~335,370 words. Semantic extraction will be expensive (many Claude tokens). Consider running on a subfolder.

## Summary
- 3938 nodes · 8243 edges · 367 communities (162 shown, 97 thin omitted)
- Extraction: 96% EXTRACTED · 4% INFERRED · 0% AMBIGUOUS · INFERRED: 348 edges (avg confidence: 0.85)
- Token cost: 0 input · 0 output

## Community Hubs (Navigation)
- User Auth & Permissions
- AlertRule Test Suite
- Device Model & Relations
- Raw Ingestion Pipeline
- Fault Injection Scripts
- Sensor Model & Telemetry
- Console Commands & Scheduling
- Device REST API
- Web Controllers
- Roles & RBAC System
- Auth API Endpoints
- AlertRule & Dashboard Controllers
- Config & Settings API
- Lab & Shared Services
- Catalog Admin Tests
- Architecture Documentation
- Model Factories & Seeders
- Python Spool & Delivery
- Vue Router & Views
- Dashboard Sensor Monitor
- Devices Vue View
- API Route Definitions
- Sensors Vue View
- Frontend Dependencies
- Alert Controller
- Event Pipeline Metrics
- Auth Transition Tests
- Sensor Chart Components
- Metrics Controller
- Data Simulator Script
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
- Community 83
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
- Community 118
- Community 119
- Community 120
- Community 121
- Community 122
- Community 123
- Community 124
- Community 125
- Community 126
- Community 129
- Community 130
- Community 131
- Community 132
- Community 133
- Community 134
- Community 135
- Community 136
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
- Community 155
- Community 156
- Community 157
- Community 158
- Community 159
- Community 160
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
- Community 193
- Community 194
- Community 195
- Community 196
- Community 197
- Community 198
- Community 199
- Community 231
- Community 232
- Community 233
- Community 234
- Community 235
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
- Community 250
- Community 251
- Community 254
- Community 255
- Community 256
- Community 257
- Community 258
- Community 259
- Community 260
- Community 261
- Community 262
- Community 263
- Community 264
- Community 265
- Community 318
- Community 319
- Community 320
- Community 321
- Community 322
- Community 323
- Community 324
- Community 325
- Community 326
- Community 327
- Community 328
- Community 329
- Community 330
- Community 331
- Community 332
- Community 333
- Community 349
- Community 350
- Community 351
- Community 352
- Community 353
- Community 354
- Community 355
- Community 356
- Community 357
- Community 358
- Community 359
- Community 360
- Community 361
- Community 362
- Community 363
- Community 364
- Community 365
- Community 366

## God Nodes (most connected - your core abstractions)
1. `User` - 310 edges
2. `Sensor` - 291 edges
3. `Device` - 240 edges
4. `TestCase` - 217 edges
5. `SensorReading` - 121 edges
6. `Alert` - 105 edges
7. `AlertRule` - 82 edges
8. `SensorType` - 82 edges
9. `Controller` - 73 edges
10. `DomainEventOutbox` - 73 edges

## Surprising Connections (you probably didn't know these)
- `useAlertsRealtime` --semantically_similar_to--> `channelRegistry`  [INFERRED] [semantically similar]
  docs/implementation/ownership.md → front_rebuild_plan/SINOA_Agentic_Dashboard_Implementation_Plan_v2.1_PUBLIC_REALTIME.md
- `Responsive Audit Matrix (33 Rows)` --semantically_similar_to--> `44px Minimum Touch Target Remediation`  [INFERRED] [semantically similar]
  task-10-report.md → task-10-remediation-report.md
- `Debezium CDC Pipeline` --conceptually_related_to--> `raw:consume Command`  [INFERRED]
  scripts/gate10/README.md → ingestion_service/README.md
- `SINOA Lab Blue Frontend Handoff` --references--> `LabShell`  [INFERRED]
  front/docs/design/LAB_BLUE_HANDOFF.md → docs/implementation/lab-blue-resource-views-2026-09-10.md
- `SINOA Agentic Dashboard Implementation Plan v2.1` --references--> `LabShell`  [INFERRED]
  front_rebuild_plan/SINOA_Agentic_Dashboard_Implementation_Plan_v2.1_PUBLIC_REALTIME.md → docs/implementation/lab-blue-resource-views-2026-09-10.md

## Import Cycles
- None detected.

## Hyperedges (group relationships)
- **Sensor reading to alert notification pipeline** — code_back_app_http_controllers_api_sensorapicontroller, code_back_app_observers_sensorreadingobserver, code_back_app_models_sensorreading, code_back_app_services_alerts_alertservice, code_back_app_observers_alertobserver, code_back_app_services_notifications_notificationservice, code_back_app_events_newalerttriggered, code_back_app_mail_dangeralertmail [EXTRACTED 1.00]
- **All 6 CI Jobs Verified GREEN on Mac** — backend_476_pass, responsive_e2e_35_35_pass, docs_gate10_mac_verification_evidence_2026_09_16_md, front_audit_e2e_task10_matrix_txt [EXTRACTED 1.00]
- **Docker Stack Core Event Processing Verified (M9–M12)** — consumer_topology_concept, consumer_crash_recovery_concept, m11_vertical_slice_concept, m9_upgrade_path_concept, docs_gate10_mac_verification_evidence_2026_09_16_md [EXTRACTED 1.00]
- **ICONTEC/ISO normative compliance document set** — docs_icontec_compliance, docs_matriz_trazabilidad_icontec_iso, docs_evidencia_cumplimiento_codigo_estructura, docs_procedimiento_auditoria_normativa, docs_referencias_icontec, docs_plantilla_trabajo_icontec, analisis_proyecto [EXTRACTED 1.00]
- **Ingestion Data Pipeline** — docs_superpowers_plans_2026_09_08_gate_s5r_closure_md_sensor_reading_service, docs_superpowers_plans_2026_09_08_gate_s5r_closure_md_raw_reading_normalizer, docs_superpowers_plans_2026_09_08_gate_s5r_closure_md_domain_event_broadcast_consumer, docs_superpowers_plans_2026_09_08_gate_s5r_closure_md_new_sensor_reading, docs_implementation_ownership_md_raw_sensor_event_publisher [EXTRACTED 1.00]
- **Role Access Control Boundary** — docs_superpowers_plans_2026_09_11_role_access_control_md_role_access_control, docs_superpowers_specs_2026_09_11_role_access_control_design_md_role_access_design, docs_superpowers_plans_2026_09_11_role_access_control_md_can_manage_dashboard, docs_security_security_hardening_plan_md_resource_access_service [INFERRED 0.85]
- **Public Graph Visibility Boundary** — front_rebuild_plan_public_graph_visibility_agentic_execution_plan_md_public_graph_visibility_service, front_rebuild_plan_public_graph_visibility_agentic_execution_plan_md_public_graph_controller, front_rebuild_plan_public_graph_visibility_agentic_execution_plan_md_public_graph_series_service, docs_superpowers_plans_2026_09_08_gate_s5r_closure_md_domain_event_broadcast_consumer [EXTRACTED 1.00]
- **SPA Auth & Security Boundary Design** — memory_01_architecture_decisions_adr_005_env_separation, memory_01_architecture_decisions_adr_006_api_only_communication, memory_01_architecture_decisions_adr_009_sanctum_bearer_auth, memory_15_security_review_sec_003_localstorage_bearer_token [INFERRED 0.85]
- **Front/Back Migration Phase Roadmap (Fase 0 to Fase 8A)** — memory_05_migration_log_fase_0, memory_05_migration_log_fase_1, memory_05_migration_log_fase_2, memory_05_migration_log_fase_3, memory_05_migration_log_fase_4, memory_05_migration_log_fase_5, memory_05_migration_log_fase_6, memory_05_migration_log_fase_7, memory_05_migration_log_fase_7b, memory_05_migration_log_fase_8a [EXTRACTED 1.00]
- **Fase 6B Blade Cleanup Gating Decision** — memory_14_blade_cleanup_readiness_verdict_nogo, memory_13_manual_validation_checklist_checklist, memory_12_stable_blade_comparison_missing_features, memory_06_pending_risks_operational_risks, memory_08_refactor_checklist_fase_6b [EXTRACTED 1.00]
- **CDC Pipeline: MySQL Outbox → Debezium → Redis → Application Stream → Raw Consumer** — scripts_gate10_readme_mysql_outbox, scripts_gate10_readme_debezium_cdc, scripts_gate10_readme_fault_scenarios, ingestion_service_readme_raw_process_v1, ingestion_service_readme_raw_consume [EXTRACTED 0.95]
- **Authenticated Graph Catalog: Endpoint → Controller → Zones Mapping → Frontend Client** — task_8_report_dashboard_graph_catalog, task_8_report_dashboard_graph_catalog_controller, task_8_report_rule_to_graph_zones, task_8_report_getauthenticatedgraphcatalog, task_8_report_sanctum_auth [EXTRACTED 0.95]
- **Graph Series Window Validation: Controllers → Service → HTTP 422** — task_9_report_publicgraph_controller, task_9_report_sensor_api_controller, task_9_report_publicgraphseries_service, task_9_report_window_validation [EXTRACTED 0.95]
- **Gate 10 Certification Phases** — docs_mac_cert_phase_a, docs_mac_cert_phase_b, docs_mac_cert_phase_c, docs_mac_cert_phase_d, docs_mac_cert_phase_e [EXTRACTED 0.95]

## Communities (367 total, 97 thin omitted)

### Community 0 - "User Auth & Permissions"
Cohesion: 0.03
Nodes (18): User, DevicePolicy, SensorPolicy, ResourceAccessService, AdminAccessTest, AlertRuleValidationTest, AuthSecurityTest, BroadcastChannelAuthorizationTest (+10 more)

### Community 1 - "AlertRule Test Suite"
Cohesion: 0.03
Nodes (26): AlertRuleCascadeDeleteTest, AlertRuleNameTest, RateLimitSecurityTest, RealtimeAuthorizationRegressionTest, ApiAuthTokenTest, ConfigSystemInfoTest, DataIntegrityDuplicationTest, DeviceCreateTest (+18 more)

### Community 2 - "Device Model & Relations"
Cohesion: 0.04
Nodes (14): Device, DeviceStatusLog, DataLeakageSentinelTest, DeviceAuthorizationTest, ResourcePayloadTest, ApiRoutingRegressionTest, DeviceApiKeyVisibilityTest, DeviceApiStatusUpdateTest (+6 more)

### Community 3 - "Raw Ingestion Pipeline"
Cohesion: 0.05
Nodes (10): RawEventOutbox, RawSensorEvent, IngestionApiTest, PublicGraphControllerTest, PublisherStreamPrefixTest, RawReadingNormalizerTest, ReadingTimeSemanticsTest, RawSensorEventIdempotencyTest (+2 more)

### Community 4 - "Fault Injection Scripts"
Cohesion: 0.06
Nodes (32): current_sha(), main(), parse_args(), Any, Namespace, Path, Write a short human index derived exclusively from the evidence document., write_json() (+24 more)

### Community 5 - "Sensor Model & Telemetry"
Cohesion: 0.05
Nodes (8): Sensor, SensorAuthorizationTest, SensorExportSecurityTest, DurableReadingEventSelfContainedTest, Gate6PublicSurfaceTest, SensorApiControllerTest, SensorGraphZonesTest, RuleToGraphZonesTest

### Community 6 - "Console Commands & Scheduling"
Cohesion: 0.06
Nodes (16): PurgeFutureSensorReadings, AlertRule, SensorReading, SensorType, NotificationService, AlertSeeder, AlertTriggerTransitionTest, DangerAlertEmailTest (+8 more)

### Community 7 - "Device REST API"
Cohesion: 0.07
Nodes (10): DeviceApiController, SensorApiController, SensorDataController, SensorController, DeviceResource, DeviceStatusSnapshotResource, SensorResource, Illuminate\Http\Request (+2 more)

### Community 8 - "Web Controllers"
Cohesion: 0.06
Nodes (21): AlertFeedController, ProfileController, ConfirmPasswordController, ForgotPasswordController, LoginController, RegisterController, ResetPasswordController, VerificationController (+13 more)

### Community 9 - "Roles & RBAC System"
Cohesion: 0.07
Nodes (11): Permission, Collection, Role, Collection, RolePermissionSeeder, UserSeeder, DashboardMetricsTelemetryPermissionTest, DeviceViewWithoutTelemetryPermissionTest (+3 more)

### Community 10 - "Auth API Endpoints"
Cohesion: 0.07
Nodes (11): AuthApiController, DashboardGraphCatalogController, HealthController, IngestionController, InternalMetricsController, PublicGraphController, RoleController, DashboardPreferenceController (+3 more)

### Community 11 - "AlertRule & Dashboard Controllers"
Cohesion: 0.08
Nodes (7): AlertRuleController, DashboardController, SensorTypeController, UserRoleController, DeviceController, AuditService, DashboardMetricsService

### Community 12 - "Config & Settings API"
Cohesion: 0.07
Nodes (8): ConfigController, EmailConfigController, ConfigController, EmailConfigController, SystemSetting, SecretSettingService, SystemSettingTest, Illuminate\Contracts\Encryption\DecryptException

### Community 13 - "Lab & Shared Services"
Cohesion: 0.06
Nodes (46): Lab Blue Resource Views, LabShell, AlertService, BaseModal, Echo JS, Reuse and Ownership Freeze Matrix, Polling Inventory, RawSensorEventPublisher (+38 more)

### Community 14 - "Catalog Admin Tests"
Cohesion: 0.05
Nodes (33): apps, flush(), mount(), catalogConfigs, config, deletingId, editingId, error (+25 more)

### Community 15 - "Architecture Documentation"
Cohesion: 0.08
Nodes (38): ANALISIS_PROYECTO.md (analisis tecnico y de cumplimiento), Architecture Audit and Implementation Plan, PurgeFutureSensorReadings command, DeviceStatusUpdated event, NewAlertTriggered event, NewSensorReading event, SensorApiController, EmailConfigController (+30 more)

### Community 16 - "Model Factories & Seeders"
Cohesion: 0.06
Nodes (15): AlertFactory, AlertRuleFactory, DeviceFactory, DeviceStatusLogFactory, DeviceTypeFactory, DomainEventOutboxFactory, LabFactory, RawEventOutboxFactory (+7 more)

### Community 17 - "Python Spool & Delivery"
Cohesion: 0.09
Nodes (26): DurableEventSpool, PendingEvent, Return the spool's current time source for coordinated components., Mark event as failed. Returns True if event is now dead-lettered (exceeded max…, Move an exhausted event to the dead_letter table for later inspection., Return all dead-lettered events for inspection., Persist a bounded, sanitized record of a rejected message (never the raw…, The durable, local owner of MQTT events awaiting backend delivery. (+18 more)

### Community 18 - "Vue Router & Views"
Cohesion: 0.06
Nodes (5): app, authStore, pinia, router, useAuthStore

### Community 19 - "Dashboard Sensor Monitor"
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

### Community 20 - "Devices Vue View"
Cohesion: 0.06
Nodes (33): applyDevicesPage(), authStore, closeForm(), defaultDeviceForm(), deleteSelectedDevice(), deviceForm, devicePayload(), devices (+25 more)

### Community 21 - "API Route Definitions"
Cohesion: 0.11
Nodes (15): Illuminate\Auth\Events\PasswordReset, Illuminate\Auth\Events\Verified, Illuminate\Database\QueryException, Illuminate\Foundation\Application, Illuminate\Foundation\Configuration\Exceptions, Illuminate\Foundation\Configuration\Middleware, Illuminate\Http\Response, Illuminate\Support\Facades\Cache (+7 more)

### Community 22 - "Sensors Vue View"
Cohesion: 0.07
Nodes (30): authStore, closeForm(), defaultSensorForm(), deleteSelectedSensor(), devices, editingSensorId, error, filteredSensors (+22 more)

### Community 23 - "Frontend Dependencies"
Cohesion: 0.06
Nodes (33): dependencies, chart.js, laravel-echo, lightweight-charts, pusher-js, devDependencies, axios, bootstrap (+25 more)

### Community 24 - "Alert Controller"
Cohesion: 0.10
Nodes (5): AlertController, Alert, AlertLifecycleService, AlertEmailTest, AlertAuthorizationTest

### Community 25 - "Event Pipeline Metrics"
Cohesion: 0.12
Nodes (3): EventPipelineMetricsService, Connection, EventPipelineMetricsServiceTest

### Community 26 - "Auth Transition Tests"
Cohesion: 0.08
Nodes (18): buildAuthTransitionEvidence(), isAuthTransitionPass(), consoleErrors, diagnoseOverflow(), __dirname, failedRequests, interact, mode (+10 more)

### Community 27 - "Sensor Chart Components"
Cohesion: 0.08
Nodes (22): accessibleSummary, chartContainer, chartData, chartOptions, hasData, props, renderChart(), sampleViewModel (+14 more)

### Community 28 - "Metrics Controller"
Cohesion: 0.11
Nodes (11): MetricsController, MetricsController, EnsureIngestionToken, EnsureUserHasPermission, EnsureUserIsAdmin, TrackApiPerformance, ApiMetricsService, Closure (+3 more)

### Community 29 - "Data Simulator Script"
Cohesion: 0.12
Nodes (20): build_qc_checksum(), build_sensor_payload(), device_sender_worker(), get_api_key(), get_location(), get_node_id(), get_sensor_key(), get_sensors() (+12 more)

### Community 30 - "Community 30"
Cohesion: 0.11
Nodes (5): LabController, LabController, Lab, DeviceService, DeviceServiceTest

### Community 31 - "Community 31"
Cohesion: 0.19
Nodes (3): DomainEventBroadcastConsumerTest, Connection, RedisManager

### Community 32 - "Community 32"
Cohesion: 0.14
Nodes (8): Attribute, DeviceSensorMapping, self, SensorMappingService, Carbon\Carbon, DateTimeInterface, Illuminate\Database\Eloquent\Casts\Attribute, Illuminate\Support\Str

### Community 33 - "Community 33"
Cohesion: 0.12
Nodes (9): ConsumeCdcOutboxes, DomainEventPublisher, RawSensorEventPublisher, CountingDomainPublisher, CountingRawPublisher, RedisManager, EventPublisherXaddShapeTest, SensorDataControllerTest (+1 more)

### Community 34 - "Community 34"
Cohesion: 0.14
Nodes (5): AlertService, DateTimeInterface, PublicGraphSeriesService, RuleToGraphZones, Illuminate\Support\Collection

### Community 35 - "Community 35"
Cohesion: 0.11
Nodes (7): StoreAlertRuleRequest, StoreRawIngestionEventRequest, UpdateAlertConfigRequest, UpdateEmailConfigRequest, UpdateGeneralConfigRequest, Illuminate\Foundation\Http\FormRequest, Illuminate\Validation\Validator

### Community 36 - "Community 36"
Cohesion: 0.14
Nodes (6): AuditLog, DashboardPreference, Illuminate\Database\Eloquent\Factories\HasFactory, Illuminate\Database\Eloquent\Model, Illuminate\Database\Eloquent\Relations\BelongsToMany, Illuminate\Database\Eloquent\Relations\HasMany

### Community 37 - "Community 37"
Cohesion: 0.10
Nodes (8): AuthSecurityTest, PasswordResetTest, RegistrationEmailVerificationTest, Illuminate\Support\Facades\Hash, Illuminate\Support\Facades\Http, Illuminate\Support\Facades\Notification, Illuminate\Support\Facades\Password, Laravel\Sanctum\PersonalAccessToken

### Community 38 - "Community 38"
Cohesion: 0.16
Nodes (5): UsesRawRedisCommands, DeadLetterStreamService, DeadLetterStreamServiceTest, Illuminate\Redis\Connections\Connection, InvalidArgumentException

### Community 39 - "Community 39"
Cohesion: 0.14
Nodes (5): RawReadingNormalizer, SensorReadingProjectionService, SensorReadingService, PublicGraphVisibility, Illuminate\Database\Eloquent\Builder

### Community 40 - "Community 40"
Cohesion: 0.09
Nodes (17): alertsStore, authStore, canViewAlerts, canViewDevices, deviceStatusesStore, { subscribeAlerts, unsubscribeAlerts }, { subscribeDeviceStatus, unsubscribeDeviceStatus }, flush() (+9 more)

### Community 41 - "Community 41"
Cohesion: 0.16
Nodes (19): ranges, api, apps, mount(), useLabWorkspace(), add(), changeDevice(), changeRange() (+11 more)

### Community 42 - "Community 42"
Cohesion: 0.15
Nodes (23): ALERTS_CHANNEL, ALERTS_EVENT, ALERTS_EVENT_CLASS, ALERTS_RESOLVED_EVENT, ALERTS_RESOLVED_EVENT_CLASS, bufferDuringRecovery(), CONNECTION_ERROR_MESSAGES, error (+15 more)

### Community 43 - "Community 43"
Cohesion: 0.09
Nodes (18): authStore, device, deviceStatuses, effectiveDevice, error, loading, oneTimeApiKey, props (+10 more)

### Community 44 - "Community 44"
Cohesion: 0.09
Nodes (21): apiMetrics, apiRequestsChart, barOptions, comparisonChart, devicesChart, doughnutOptions, error, gaugeOptions (+13 more)

### Community 45 - "Community 45"
Cohesion: 0.11
Nodes (6): DebeziumChange, self, up(), DeviceProvisioningAtomicityTest, SensorProvisioningAtomicityTest, RuntimeException

### Community 46 - "Community 46"
Cohesion: 0.10
Nodes (22): authStore, canExportTelemetry, canViewTelemetry, clearTelemetryProjection(), error, exporting, filterActive, filteredReadings (+14 more)

### Community 47 - "Community 47"
Cohesion: 0.13
Nodes (22): Admin Routes RBAC Correction (admin-only list narrowed), Backend PHP Suite 476/476 PASS (1826 assertions), CdcOutboxStreamConsumer callable→Closure Fix, Consumer Crash Recovery (no-loss, no-manual-intervention), Consumer Topology (outbox-cdc-consumer, raw-consumer, domain-event-consumer), Device Isolation via X-Device-Key, Gate 10 Mac Verification Evidence Ledger, Task 10 / Gate 9 Mocked Responsive Chromium Matrix (+14 more)

### Community 48 - "Community 48"
Cohesion: 0.10
Nodes (13): MailMessage, ResetPasswordNotification, MailMessage, VerifyEmailNotification, Illuminate\Auth\Access\AuthorizationException, Illuminate\Auth\Notifications\ResetPassword, Illuminate\Auth\Notifications\VerifyEmail, Illuminate\Contracts\Auth\MustVerifyEmail (+5 more)

### Community 49 - "Community 49"
Cohesion: 0.11
Nodes (19): alerts, alertsStore, error, filter, handleResolve(), handleResolveAll(), load(), loading (+11 more)

### Community 50 - "Community 50"
Cohesion: 0.17
Nodes (20): build_raw_event(), derive_source_event_id(), _identity_part(), _iso_now(), MissingEventIdentityError, Any, ValueError, Raised when a payload has neither an explicit event id nor a… (+12 more)

### Community 51 - "Community 51"
Cohesion: 0.20
Nodes (13): Exception, BackendClientError, RuntimeError, Raised when backend ingestion endpoint cannot be reached or rejects payload., DeliveryWorker, retry_delay(), event(), RecordingBackend (+5 more)

### Community 52 - "Community 52"
Cohesion: 0.10
Nodes (21): @fontsource/inter, @fontsource/jetbrains-mono, dependencies, axios, bootstrap, @fontsource/inter, @fontsource/jetbrains-mono, laravel-echo (+13 more)

### Community 53 - "Community 53"
Cohesion: 0.12
Nodes (20): closeModal(), deleteRule(), deletingId, error, formError, load(), loading, loadMetadata() (+12 more)

### Community 54 - "Community 54"
Cohesion: 0.16
Nodes (14): BackendClient, Any, configure_logging(), main(), parse_args(), Any, Namespace, run_mqtt() (+6 more)

### Community 55 - "Community 55"
Cohesion: 0.16
Nodes (3): DeviceTypeController, DeviceTypeController, DeviceType

### Community 56 - "Community 56"
Cohesion: 0.18
Nodes (9): Throwable, QueueSmokeJob, Throwable, SendDangerAlertEmailJob, UpdateDeviceLastCommunication, Illuminate\Bus\Queueable, Illuminate\Contracts\Queue\ShouldQueue, Illuminate\Foundation\Bus\Dispatchable (+1 more)

### Community 57 - "Community 57"
Cohesion: 0.16
Nodes (5): EvaluateSensorReadingAlerts, Throwable, SensorReadingObserver, AlertEmailAsyncDeliveryTest, SensorReadingAlertEvaluationRecoveryTest

### Community 58 - "Community 58"
Cohesion: 0.13
Nodes (13): __dirname, fakeEchoPlugin(), main(), record(), results, root, __dirname, fakeEchoPlugin() (+5 more)

### Community 60 - "Community 60"
Cohesion: 0.18
Nodes (15): alert(), countFor(), device(), deviceSensor(), installAuth(), json(), mockApi(), readings() (+7 more)

### Community 61 - "Community 61"
Cohesion: 0.19
Nodes (14): buildMatrixSummaryRecord(), isPassingMatrixRecord(), parseMatrixRow(), parsedRun, hasInteractionFailure(), isCleanResult(), isPageCorrect(), isPassingAuditResult() (+6 more)

### Community 63 - "Community 63"
Cohesion: 0.12
Nodes (3): UpdateAlertRulesTable, UpdateAlertRulesSeverity, Illuminate\Database\Migrations\Migration

### Community 64 - "Community 64"
Cohesion: 0.15
Nodes (7): AlertRuleSeeder, DeviceTypeSeeder, SensorTypeSeeder, SystemSettingsSeeder, Illuminate\Database\Console\Seeds\WithoutModelEvents, Illuminate\Database\Seeder, Illuminate\Support\Carbon

### Community 65 - "Community 65"
Cohesion: 0.11
Nodes (15): activeAlertsCard, alertsRealtime, alertsStore, alertsView, alertToast, appLayout, echo, envExample (+7 more)

### Community 66 - "Community 66"
Cohesion: 0.18
Nodes (15): booleanEnv(), createEcho(), disconnectEcho(), getEcho(), getEchoConfig(), handleAuthChange(), ADR-0002, notify() (+7 more)

### Community 67 - "Community 67"
Cohesion: 0.13
Nodes (13): icons, paths, props, alerts, auth, links, logout(), open (+5 more)

### Community 68 - "Community 68"
Cohesion: 0.15
Nodes (6): AppServiceProvider, ViewServiceProvider, SecurityRateLimitTest, Illuminate\Cache\RateLimiting\Limit, Illuminate\Support\Facades\RateLimiter, Illuminate\Support\ServiceProvider

### Community 69 - "Community 69"
Cohesion: 0.22
Nodes (3): DatabaseSeeder, AllReadingsResourceBoundsTest, Carbon

### Community 70 - "Community 70"
Cohesion: 0.26
Nodes (11): Client, MQTTIngestionClient, Any, Settings, _message(), _settings(), test_invalid_payload_is_quarantined_and_not_enqueued(), test_malformed_json_is_quarantined_and_not_enqueued() (+3 more)

### Community 71 - "Community 71"
Cohesion: 0.12
Nodes (17): scripts, audit:baseline, audit:events, audit:gate9, audit:network, audit:network:live, audit:no-polling:source, build (+9 more)

### Community 73 - "Community 73"
Cohesion: 0.14
Nodes (13): auth, error, graphDevices, loading, boardDevices, flush(), getActiveAlerts, getAuthenticatedGraphCatalog (+5 more)

### Community 74 - "Community 74"
Cohesion: 0.13
Nodes (17): Dead Letter Queue (DLQ), Exponential Backoff Delivery, Idempotency Ledger via status Column, MQTT Ingestion, POST /api/ingestion/events, QC Validation Gate, raw:consume Command, raw-process-v1 Redis Consumer Group (+9 more)

### Community 76 - "Community 76"
Cohesion: 0.22
Nodes (15): onResync(), DEVICE_STATUS_CHANNEL, DEVICE_STATUS_EVENT, DEVICE_STATUS_EVENT_CLASS, error, eventPayload(), fetchStatusSnapshot(), isConnected (+7 more)

### Community 77 - "Community 77"
Cohesion: 0.17
Nodes (6): ConsumeDomainEvents, ConsumeRawEvents, InspectDeadLetters, InspectReadingTimeSemantics, ReplayDeadLetter, Illuminate\Console\Command

### Community 78 - "Community 78"
Cohesion: 0.21
Nodes (3): AlertRuleController, UpdateAlertRuleRequest, AlertRuleResource

### Community 79 - "Community 79"
Cohesion: 0.13
Nodes (3): SensorTypeController, UserRoleController, Illuminate\Support\Facades\Route

### Community 80 - "Community 80"
Cohesion: 0.13
Nodes (15): scripts, post-autoload-dump, post-create-project-cmd, post-root-package-install, post-update-cmd, test, Illuminate\\Foundation\\ComposerScripts::postAutoloadDump, @php artisan config:clear --ansi (+7 more)

### Community 82 - "Community 82"
Cohesion: 0.34
Nodes (3): Connection, RedisManager, RawStreamConsumerTest

### Community 83 - "Community 83"
Cohesion: 0.17
Nodes (15): Phase A Dependency Security, Phase B Transactional Integrity, Phase C Authorization Consistency, Phase D MySQL Mapping Concurrency, Phase E allReadings Global Bounds, Mac Certification Evidence, Simulated Infra Certification Dry Run, Gate 10 Quality CI Workflow (+7 more)

### Community 84 - "Community 84"
Cohesion: 0.13
Nodes (15): devDependencies, jsdom, @playwright/test, sass, vite, @vitejs/plugin-vue, vitest, ws (+7 more)

### Community 85 - "Community 85"
Cohesion: 0.15
Nodes (12): alertsStore, currentAlert, currentDevice, currentMessage, currentSeverity, currentTime, currentTitle, currentValue (+4 more)

### Community 86 - "Community 86"
Cohesion: 0.14
Nodes (5): props, severityClass, apps, authStore, mountedApps

### Community 88 - "Community 88"
Cohesion: 0.20
Nodes (3): ReadingProjection, ReadingProvenanceService, Illuminate\Database\Eloquent\Relations\BelongsTo

### Community 90 - "Community 90"
Cohesion: 0.26
Nodes (13): onConnectionStateChange(), ADR-0002, normalizeReading(), RECOVERY_WINDOW_MS, resolveSensorId(), SENSOR_EVENT, SENSOR_EVENT_CLASS, useSensorRealtime() (+5 more)

### Community 91 - "Community 91"
Cohesion: 0.15
Nodes (10): email, error, errors, loading, payload(), save(), saving, success (+2 more)

### Community 92 - "Community 92"
Cohesion: 0.35
Nodes (7): DeviceCommunicationReceived, Illuminate\Broadcasting\InteractsWithSockets, Illuminate\Broadcasting\PresenceChannel, Illuminate\Broadcasting\PrivateChannel, Illuminate\Contracts\Broadcasting\ShouldBroadcastNow, Illuminate\Foundation\Events\Dispatchable, Illuminate\Queue\SerializesModels

### Community 96 - "Community 96"
Cohesion: 0.17
Nodes (9): error, form, loading, router, success, authApi, mountedApps, mountRegisterView() (+1 more)

### Community 97 - "Community 97"
Cohesion: 0.15
Nodes (9): exportSensorReadings, getSensor, getSensorLatestReadings, getSensorReadings, mountedApps, mountView(), mountViewNav(), subscribeSensor (+1 more)

### Community 98 - "Community 98"
Cohesion: 0.18
Nodes (13): Task 10 Remediation Report, Audit Lifecycle Fixture Fix, Browser Fixture Graph Bootstrap, 44px Minimum Touch Target Remediation, useLabWorkspace Authorization Fix, Task 10 Report — Gate 9/10 Evidence, gate10-quality.yml CI Pipeline, Exact-SHA Browser Evidence (+5 more)

### Community 99 - "Community 99"
Cohesion: 0.23
Nodes (4): NewSensorReading, Illuminate\Broadcasting\Channel, Illuminate\Redis\RedisManager, Illuminate\Support\Facades\Event

### Community 103 - "Community 103"
Cohesion: 0.32
Nodes (3): DeadLetterCommandsTest, Connection, RedisManager

### Community 104 - "Community 104"
Cohesion: 0.26
Nodes (3): ExampleTest, MigrationIntegrityTest, PHPUnit\Framework\TestCase

### Community 105 - "Community 105"
Cohesion: 0.30
Nodes (10): boundariesFromRegions(), buildZonesViewModel(), KNOWN_SEVERITIES, moreSevere(), normalizeBoundaries(), normalizeRegions(), normalizeSeverity(), PRECEDENCE (+2 more)

### Community 106 - "Community 106"
Cohesion: 0.20
Nodes (9): alerts, alertsStore, count, error, loading, flush(), getActiveAlerts, mountActiveAlertsCard() (+1 more)

### Community 107 - "Community 107"
Cohesion: 0.36
Nodes (9): PayloadValidationError, Any, ValueError, Raised when a payload does not satisfy minimum ingestion requirements., validate_payload(), test_validate_payload_accepts_valid_payload(), test_validate_payload_rejects_missing_sensors(), test_validate_payload_rejects_sensor_without_value() (+1 more)

### Community 109 - "Community 109"
Cohesion: 0.18
Nodes (10): autoload-dev, psr-4, description, license, minimum-stability, name, prefer-stable, Tests\\ (+2 more)

### Community 110 - "Community 110"
Cohesion: 0.33
Nodes (3): DomainEventBroadcastRetryIdentityTest, Connection, RedisManager

### Community 112 - "Community 112"
Cohesion: 0.31
Nodes (10): ALLOWED_ANON, apiLogin(), authenticatedScenario(), __dirname, guestScenario(), LEAK_ENDPOINTS, main(), OBSERVE_MS (+2 more)

### Community 113 - "Community 113"
Cohesion: 0.18
Nodes (8): backendRoots, compose, __dirname, FRONT, RELAY_TOKENS, REPO, RETIRED_ENDPOINTS, violations

### Community 114 - "Community 114"
Cohesion: 0.18
Nodes (10): dependencies, emailVerification, envExample, missing, missingDependencies, packageJson, requiredDependencies, requiredFiles (+2 more)

### Community 115 - "Community 115"
Cohesion: 0.27
Nodes (9): close(), dialogEl, emit, focusableElements(), onKeydown(), props, flush(), mountBaseModal() (+1 more)

### Community 117 - "Community 117"
Cohesion: 0.20
Nodes (8): error, errors, form, loading, payload(), save(), saving, success

### Community 118 - "Community 118"
Cohesion: 0.18
Nodes (9): adminActions, alerts, diagnostics, email, general, loading, loadNotice, sections (+1 more)

### Community 119 - "Community 119"
Cohesion: 0.18
Nodes (11): ADR-003: Do Not Move Blade Directly To /front, Blade Views (Legacy UI), script_datos.py (Python IoT Simulator), Operational Risks, Functional State: Blade Legacy, Legacy Views Still Dependent on Blade, Lost or Not-Found Features, Manual Validation Checklist (Not Yet Executed) (+3 more)

### Community 120 - "Community 120"
Cohesion: 0.20
Nodes (7): getActiveAlerts, transport, channelCallbacks, connectionWatchers, fetchActiveAlerts, listenOnChannel, resyncWatchers

### Community 123 - "Community 123"
Cohesion: 0.24
Nodes (5): AlertObserver, EventServiceProvider, Illuminate\Auth\Events\Registered, Illuminate\Auth\Listeners\SendEmailVerificationNotification, Illuminate\Foundation\Support\Providers\EventServiceProvider

### Community 126 - "Community 126"
Cohesion: 0.22
Nodes (7): authStore, deviceStatuses, props, getDevices, mountDeviceStatusList(), mountedApps, visibleDevices

### Community 130 - "Community 130"
Cohesion: 0.33
Nodes (8): FALLBACK_TOKENS, hexToRgba(), readCssVar(), resolveChartTokens(), resolveZoneTokens(), ZONE_CSS_VARS, ZONE_FALLBACKS, ZONE_FILL_ALPHA

### Community 131 - "Community 131"
Cohesion: 0.20
Nodes (6): error, errors, form, loading, saving, success

### Community 133 - "Community 133"
Cohesion: 0.22
Nodes (9): pestphp/pest-plugin, php-http/discovery, config, allow-plugins, optimize-autoloader, platform, preferred-install, sort-packages (+1 more)

### Community 134 - "Community 134"
Cohesion: 0.22
Nodes (9): require, laravel/framework, laravel/sanctum, laravel/tinker, laravel/ui, php, predis/predis, pusher/pusher-php-server (+1 more)

### Community 136 - "Community 136"
Cohesion: 0.22
Nodes (8): __dirname, envExample, __filename, forbiddenKeys, frontDockerfile, frontRoot, repoRoot, requiredFiles

### Community 138 - "Community 138"
Cohesion: 0.28
Nodes (6): emit, filteredSensors, form, nullableNumber(), props, submit()

### Community 139 - "Community 139"
Cohesion: 0.25
Nodes (8): devices, fetchWindow, flush(), mountBoard(), mountedApps, resultForQuery, subscribeSensor, unsubscribeSensor

### Community 140 - "Community 140"
Cohesion: 0.25
Nodes (5): copied, copyError, props, mountCredentialModal(), mountedApps

### Community 141 - "Community 141"
Cohesion: 0.22
Nodes (6): alert, error, loading, props, resolving, success

### Community 143 - "Community 143"
Cohesion: 0.25
Nodes (8): require-dev, fakerphp/faker, laravel/pail, laravel/pint, laravel/sail, mockery/mockery, nunomaduro/collision, phpunit/phpunit

### Community 144 - "Community 144"
Cohesion: 0.25
Nodes (7): DB_CONNECTION, DB_DATABASE, DB_HOST, DB_PASSWORD, DB_PORT, DB_USERNAME, run_mapping_race.sh script

### Community 148 - "Community 148"
Cohesion: 0.25
Nodes (4): authStore, props, readingsStore, subscriptions

### Community 149 - "Community 149"
Cohesion: 0.25
Nodes (5): mountedApps, mountList(), onReadingBySensor, subscribeSensor, unsubscribeSensor

### Community 150 - "Community 150"
Cohesion: 0.36
Nodes (6): channelKey(), getChannelRefCount(), leaveChannelName(), listenOnChannel(), refCounts, echoMock

### Community 151 - "Community 151"
Cohesion: 0.25
Nodes (3): connectionWatchers, echoMock, resyncWatchers

### Community 152 - "Community 152"
Cohesion: 0.36
Nodes (3): activeControllersByConsumer, buildGraphQueryKey(), useGraphSeriesQueryStore

### Community 153 - "Community 153"
Cohesion: 0.25
Nodes (6): authStore, error, loading, resending, route, success

### Community 154 - "Community 154"
Cohesion: 0.29
Nodes (7): createDevice, flush(), getDevices, mountDevicesView(), mountedApps, updateDevice, updateDeviceStatus

### Community 155 - "Community 155"
Cohesion: 0.29
Nodes (8): Phase 8A API Additions (CRUD/Admin), Fase 7B: Operational Validation Before Blade Cleanup, Fase 8A: Admin CRUD/Catalog SPA Migration, QueueSmokeJob (queue infrastructure smoke test), High Risks, Fase 6B: Blade Legacy Cleanup and Production SPA Adjustment (Pending), Fase 8: Final Cleanup (Pending), Session Progress: Fase 8A Work Log

### Community 158 - "Community 158"
Cohesion: 0.48
Nodes (6): BaseModel, DevicePayload, MqttPayload, QCPayload, RawIngestionEvent, SensorValue

### Community 159 - "Community 159"
Cohesion: 0.29
Nodes (6): allowScripts, esbuild@0.21.5, name, private, type, version

### Community 160 - "Community 160"
Cohesion: 0.29
Nodes (6): missing, navShell, requiredFiles, root, router, srcFilesToCheck

### Community 163 - "Community 163"
Cohesion: 0.38
Nodes (3): getGraphSeries(), getPrivateGraphSeries(), toWindowParam()

### Community 164 - "Community 164"
Cohesion: 0.52
Nodes (4): composeGraphSeries(), computeStats(), idCompare(), toPoint()

### Community 165 - "Community 165"
Cohesion: 0.33
Nodes (5): deviceStatuses, status, statuses, mountedApps, mountRealtimeStatus()

### Community 166 - "Community 166"
Cohesion: 0.29
Nodes (4): connectionWatchers, echoMock, getDeviceStatusSnapshot, resyncWatchers

### Community 167 - "Community 167"
Cohesion: 0.29
Nodes (5): error, form, loading, route, success

### Community 168 - "Community 168"
Cohesion: 0.33
Nodes (7): ADR-007: Docker Per Service, Docker/Docker Compose (absent at audit time), Phase 6 Physical Separation Notes, Phase 7 Docker Consumer Notes, Fase 6: Physical Separation Into /back, Fase 7: Docker and Compose, Functional State: Docker

### Community 169 - "Community 169"
Cohesion: 0.29
Nodes (7): Pusher / Laravel Echo Realtime, Broadcast Event: NewAlertTriggered (channel alerts), Broadcast Event: NewSensorReading (channel sensor.{id}), Phase 4 Frontend Consumer Notes, Phase 5 Realtime Contract Notes, Fase 4: Progressive Screen Migration, Fase 5: Realtime (Echo/Pusher) Integration

### Community 170 - "Community 170"
Cohesion: 0.52
Nodes (7): Task 8 Report — Authenticated Graph Catalog, GET /api/dashboard/graph-catalog Endpoint, DashboardGraphCatalogController, getAuthenticatedGraphCatalog() Frontend Client, Public Bootstrap + Authenticated Catalog Merge, RuleToGraphZones Mapping, Sanctum API Authentication Group

### Community 174 - "Community 174"
Cohesion: 0.47
Nodes (3): createLogger(), formatTag(), LOG_LEVELS

### Community 175 - "Community 175"
Cohesion: 0.33
Nodes (4): email, error, loading, success

### Community 176 - "Community 176"
Cohesion: 0.33
Nodes (4): authStore, form, route, router

### Community 177 - "Community 177"
Cohesion: 0.33
Nodes (4): error, items, loading, system

### Community 178 - "Community 178"
Cohesion: 0.47
Nodes (5): configApi, flush(), mountView(), response(), setResponses()

### Community 179 - "Community 179"
Cohesion: 0.33
Nodes (6): iot-platform-v2 Project, Incremental Migration Principle, Front/Back Separation Goal, ADR-002: Backend As REST API + Broadcasting, ADR-004: Incremental Migration, Laravel 12 / PHP 8.2+ Backend

### Community 180 - "Community 180"
Cohesion: 0.33
Nodes (6): ADR-001: Keep A Monorepo Initially, Migration Hygiene Rules, Fase 0: Read-only Audit and Real Contract, Fase 1: Headless Auth (Sanctum Bearer), Fase 2: API Normalization Replacing Blade, Codex Working Notes Operational Rules

### Community 181 - "Community 181"
Cohesion: 0.40
Nodes (5): autoload, psr-4, App\\, Database\\Factories\\, Database\\Seeders\\

### Community 182 - "Community 182"
Cohesion: 0.40
Nodes (4): Monolog\Handler\NullHandler, Monolog\Handler\StreamHandler, Monolog\Handler\SyslogUdpHandler, Monolog\Processor\PsrLogMessageProcessor

### Community 183 - "Community 183"
Cohesion: 0.40
Nodes (3): chart, lineSeries, lineSeriesDef

### Community 186 - "Community 186"
Cohesion: 0.40
Nodes (5): Front SPA HTML Entrypoint, ADR-008: Frontend Framework (Vue 3 + Bootstrap 5, proposed), Vite (laravel-vite-plugin), Phase 3 Frontend Consumer Notes, Fase 3: Vue 3 SPA Initialization

### Community 187 - "Community 187"
Cohesion: 0.50
Nodes (3): metrics, props, renderMetricsCards()

### Community 189 - "Community 189"
Cohesion: 0.60
Nodes (3): normalizeReading(), normalizeReadings(), useSensorReadingsStore

### Community 190 - "Community 190"
Cohesion: 0.50
Nodes (5): ADR-009: SPA Auth Uses Sanctum Bearer Tokens Initially, Laravel Sanctum, Authentication Strategy: Sanctum Bearer Tokens, Bearer Token in localStorage Risk, SEC-003: Bearer Token in localStorage

### Community 191 - "Community 191"
Cohesion: 0.90
Nodes (5): Task 9 Report — Oversized Graph Window Validation, PublicGraphController, PublicGraphSeriesService, SensorApiController, 24-Hour Window Validation (HTTP 422)

### Community 193 - "Community 193"
Cohesion: 0.83
Nodes (3): getAudioContext(), playAlertSound(), unlockAlertSound()

### Community 195 - "Community 195"
Cohesion: 0.50
Nodes (4): Chart.js, Functional State: Frontend, Functional Inventory: Frontend SPA Table, Partially Migrated Features

### Community 197 - "Community 197"
Cohesion: 0.67
Nodes (3): extra, laravel, dont-discover

### Community 198 - "Community 198"
Cohesion: 0.67
Nodes (3): keywords, framework, laravel

### Community 199 - "Community 199"
Cohesion: 0.67
Nodes (3): dev, Composer\\Config::disableProcessTimeout, npx concurrently -c \"#93c5fd,#c4b5fd,#fb7185,#fdba74\" \"php artisan serve\" \"php artisan queue:listen --tries=1\" \"php artisan pail --timeout=0\" \"npm run dev\" --names=server,queue,logs,vite

### Community 233 - "Community 233"
Cohesion: 0.67
Nodes (3): User model, ResetPasswordNotification, VerifyEmailNotification

### Community 240 - "Community 240"
Cohesion: 0.67
Nodes (3): exportReadings(), filterReadings(), readingFilterParams()

### Community 241 - "Community 241"
Cohesion: 0.67
Nodes (3): ADR-005: Separate Environment Variables, ADR-006: API-Only Frontend Communication, FIX-001: front/.env Configured With Only Public VITE_* Vars

### Community 242 - "Community 242"
Cohesion: 0.67
Nodes (3): Functional State: Backend, Functional Inventory: Backend API Table, Conserved Features (Blade to Vue Parity)

### Community 243 - "Community 243"
Cohesion: 0.67
Nodes (3): Task 11 Report — Mobile/Accessibility, AppLayout Route Stub, Dependency Audit (npm audit --offline)

## Ambiguous Edges - Review These
- `AlertService` → `NewSensorReading event`  [AMBIGUOUS]
  INFORME_ALERTAS_NOTIFICACIONES.md · relation: calls

## Knowledge Gaps
- **734 isolated node(s):** `$schema`, `name`, `type`, `description`, `laravel` (+729 more)
  These have ≤1 connection - possible missing edges or undocumented components. (Counts symbols only; 1453 node(s) total have ≤1 connection when file, concept and rationale nodes are included.)
- **97 thin communities (<3 nodes) omitted from report** — run `graphify query` to explore isolated nodes.

## Suggested Questions
_Questions this graph is uniquely positioned to answer:_

- **What is the exact relationship between `AlertService` and `NewSensorReading event`?**
  _Edge tagged AMBIGUOUS (relation: calls) - confidence is low._
- **Why does `User` connect `User Auth & Permissions` to `AlertRule Test Suite`, `Device Model & Relations`, `Sensor Model & Telemetry`, `Console Commands & Scheduling`, `Web Controllers`, `Roles & RBAC System`, `Auth API Endpoints`, `AlertRule & Dashboard Controllers`, `Community 145`, `API Route Definitions`, `Alert Controller`, `Event Pipeline Metrics`, `Community 156`, `Community 157`, `Community 36`, `Community 37`, `Community 172`, `Community 173`, `Community 45`, `Community 48`, `Community 184`, `Community 64`, `Community 69`, `Community 79`, `Community 81`, `Community 88`, `Community 89`, `Community 101`, `Community 102`, `Community 111`, `Community 122`, `Community 125`?**
  _High betweenness centrality (0.045) - this node is a cross-community bridge._
- **Why does `TestCase` connect `AlertRule Test Suite` to `User Auth & Permissions`, `Device Model & Relations`, `Raw Ingestion Pipeline`, `Sensor Model & Telemetry`, `Console Commands & Scheduling`, `Community 135`, `Device REST API`, `Roles & RBAC System`, `Config & Settings API`, `Community 145`, `Community 146`, `Community 147`, `API Route Definitions`, `Alert Controller`, `Event Pipeline Metrics`, `Community 156`, `Community 157`, `Community 30`, `Community 31`, `Community 33`, `Community 37`, `Community 38`, `Community 39`, `Community 173`, `Community 45`, `Community 184`, `Community 57`, `Community 185`, `Community 59`, `Community 64`, `Community 68`, `Community 69`, `Community 81`, `Community 82`, `Community 89`, `Community 99`, `Community 101`, `Community 102`, `Community 103`, `Community 110`, `Community 111`, `Community 121`, `Community 122`, `Community 125`?**
  _High betweenness centrality (0.043) - this node is a cross-community bridge._
- **Why does `Sensor` connect `Sensor Model & Telemetry` to `User Auth & Permissions`, `AlertRule Test Suite`, `Device Model & Relations`, `Raw Ingestion Pipeline`, `Console Commands & Scheduling`, `Device REST API`, `Community 135`, `Roles & RBAC System`, `Auth API Endpoints`, `AlertRule & Dashboard Controllers`, `Community 146`, `API Route Definitions`, `Community 156`, `Community 157`, `Community 31`, `Community 32`, `Community 34`, `Community 35`, `Community 36`, `Community 38`, `Community 39`, `Community 45`, `Community 57`, `Community 64`, `Community 68`, `Community 69`, `Community 78`, `Community 82`, `Community 89`, `Community 99`, `Community 101`, `Community 111`, `Community 125`?**
  _High betweenness centrality (0.033) - this node is a cross-community bridge._
- **What connects `$schema`, `name`, `type` to the rest of the system?**
  _734 weakly-connected nodes found - possible documentation gaps or missing edges._
- **Should `User Auth & Permissions` be split into smaller, more focused modules?**
  _Cohesion score 0.027917620137299773 - nodes in this community are weakly interconnected._
- **Should `AlertRule Test Suite` be split into smaller, more focused modules?**
  _Cohesion score 0.034868421052631576 - nodes in this community are weakly interconnected._