# Graph Report - iot-platform-v2  (2026-09-16)

## Corpus Check
- Large corpus: 643 files · ~317,122 words. Semantic extraction will be expensive (many Claude tokens). Consider running on a subfolder.

## Summary
- 3776 nodes · 7773 edges · 355 communities (151 shown, 96 thin omitted)
- Extraction: 96% EXTRACTED · 4% INFERRED · 0% AMBIGUOUS · INFERRED: 348 edges (avg confidence: 0.85)
- Token cost: 0 input · 0 output

## Community Hubs (Navigation)
- Auth & Security Tests
- User Model & RBAC
- Gate10 Fault Injection
- Device-Sensor Controllers
- Auth API Controller
- Alert Domain Events
- Alert Rule Controller
- API Controllers Hub
- Device Boot & Mappings
- System Settings & Security
- Alert Controller
- Profile & Auth Controllers
- Lab Blue Resource Docs
- Vue Sensor List
- Catalog Admin Tests
- Project Analysis Docs
- Vue Devices View
- Event Pipeline Jobs
- Alert Feed & Public Graph
- Sensor Controller
- Vue Alert Views
- Dashboard Monitor Board
- CDC Outbox Consumer
- Event Pipeline Metrics
- Internal Metrics API
- Config Controller
- Backend Dependencies
- Architecture Decisions
- Ingestion Spool Service
- Device Sensor Mapping
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
- Community 120
- Community 121
- Community 122
- Community 123
- Community 124
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
- Community 146
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
- Community 183
- Community 184
- Community 185
- Community 186
- Community 187
- Community 188
- Community 219
- Community 221
- Community 222
- Community 223
- Community 224
- Community 225
- Community 226
- Community 227
- Community 228
- Community 229
- Community 230
- Community 231
- Community 232
- Community 233
- Community 234
- Community 235
- Community 236
- Community 237
- Community 239
- Community 240
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
- Community 254
- Community 255
- Community 307
- Community 308
- Community 309
- Community 310
- Community 311
- Community 312
- Community 313
- Community 314
- Community 315
- Community 316
- Community 317
- Community 318
- Community 319
- Community 320
- Community 321
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
- Community 354

## God Nodes (most connected - your core abstractions)
1. `User` - 289 edges
2. `Sensor` - 260 edges
3. `Device` - 227 edges
4. `TestCase` - 193 edges
5. `SensorReading` - 104 edges
6. `Alert` - 103 edges
7. `AlertRule` - 82 edges
8. `SensorType` - 75 edges
9. `Controller` - 73 edges
10. `SystemSetting` - 66 edges

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
- **All 6 CI Jobs Verified GREEN on Mac** — backend_476_pass, responsive_e2e_35_35_pass, docs_gate10_mac_verification_evidence_2026_09_16_md, front_audit_e2e_task10_matrix_txt [EXTRACTED 1.00]
- **Docker Stack Core Event Processing Verified (M9–M12)** — consumer_topology_concept, consumer_crash_recovery_concept, m11_vertical_slice_concept, m9_upgrade_path_concept, docs_gate10_mac_verification_evidence_2026_09_16_md [EXTRACTED 1.00]
- **Architecture Decisions Governing the Migration** — adr1_durable_publication_concept, adr2_client_recovery_concept, adr3_dlq_strategy_concept, adr4_internal_fanout_concept, plan_md [EXTRACTED 1.00]

## Communities (355 total, 96 thin omitted)

### Community 0 - "Auth & Security Tests"
Cohesion: 0.03
Nodes (33): AlertRuleNameTest, AlertTransportAuthorizationTest, AuthSecurityTest, RateLimitSecurityTest, RealtimeAuthorizationRegressionTest, ApiAuthTokenTest, AuthSecurityTest, ConfigSystemInfoTest (+25 more)

### Community 1 - "User Model & RBAC"
Cohesion: 0.03
Nodes (20): User, AlertPolicy, DevicePolicy, SensorPolicy, ResourceAccessService, AdminAccessTest, AlertRuleValidationTest, AuthApiHeadlessTest (+12 more)

### Community 2 - "Gate10 Fault Injection"
Cohesion: 0.06
Nodes (32): current_sha(), main(), parse_args(), Any, Namespace, Path, Write a short human index derived exclusively from the evidence document., write_json() (+24 more)

### Community 3 - "Device-Sensor Controllers"
Cohesion: 0.04
Nodes (11): Device, DeviceAuthorizationTest, ResourcePayloadTest, ApiRoutingRegressionTest, DeviceApiStatusUpdateTest, DeviceCredentialLifecycleTest, DeviceSensorListLatestReadingTest, SensorApiControllerTest (+3 more)

### Community 4 - "Auth API Controller"
Cohesion: 0.07
Nodes (10): AuthApiController, DeviceApiController, SensorApiController, SensorDataController, DeviceResource, DeviceStatusSnapshotResource, SensorResource, Illuminate\Http\Request (+2 more)

### Community 5 - "Alert Domain Events"
Cohesion: 0.06
Nodes (15): AlertResolved, HasEventEnvelope, VersionedDomainEvent, DeviceStatusUpdated, NewAlertTriggered, NewSensorReading, EventEnvelopeTest, Illuminate\Broadcasting\Channel (+7 more)

### Community 6 - "Alert Rule Controller"
Cohesion: 0.06
Nodes (10): AlertRuleController, DashboardController, LabController, SensorTypeController, UserRoleController, DeviceController, LabController, Lab (+2 more)

### Community 7 - "API Controllers Hub"
Cohesion: 0.07
Nodes (19): IngestionController, RawEventOutbox, UsesRawRedisCommands, Illuminate\Auth\Events\PasswordReset, Illuminate\Auth\Events\Verified, Illuminate\Database\QueryException, Illuminate\Database\UniqueConstraintViolationException, Illuminate\Foundation\Application (+11 more)

### Community 8 - "Device Boot & Mappings"
Cohesion: 0.05
Nodes (16): self, AlertFactory, AlertRuleFactory, DeviceFactory, DeviceStatusLogFactory, DeviceTypeFactory, DomainEventOutboxFactory, LabFactory (+8 more)

### Community 9 - "System Settings & Security"
Cohesion: 0.06
Nodes (8): SystemSetting, DataLeakageSentinelTest, EmailSecretStorageTest, Phase2ApiEndpointsTest, SystemSettingTest, Illuminate\Contracts\Encryption\DecryptException, Illuminate\Support\Facades\Crypt, Laravel\Sanctum\Sanctum

### Community 10 - "Alert Controller"
Cohesion: 0.08
Nodes (7): AlertController, Alert, DomainEventOutbox, AlertLifecycleService, AlertResolveTransitionTest, AlertTriggerTransitionTest, AlertAuthorizationTest

### Community 11 - "Profile & Auth Controllers"
Cohesion: 0.06
Nodes (20): ProfileController, ConfirmPasswordController, ForgotPasswordController, LoginController, RegisterController, ResetPasswordController, VerificationController, Controller (+12 more)

### Community 12 - "Lab Blue Resource Docs"
Cohesion: 0.06
Nodes (46): Lab Blue Resource Views, LabShell, AlertService, BaseModal, Echo JS, Reuse and Ownership Freeze Matrix, Polling Inventory, RawSensorEventPublisher (+38 more)

### Community 13 - "Vue Sensor List"
Cohesion: 0.05
Nodes (34): authStore, props, readingsStore, subscriptions, mountedApps, mountList(), onReadingBySensor, subscribeSensor (+26 more)

### Community 14 - "Catalog Admin Tests"
Cohesion: 0.05
Nodes (33): apps, flush(), mount(), catalogConfigs, config, deletingId, editingId, error (+25 more)

### Community 15 - "Project Analysis Docs"
Cohesion: 0.08
Nodes (38): ANALISIS_PROYECTO.md (analisis tecnico y de cumplimiento), Architecture Audit and Implementation Plan, PurgeFutureSensorReadings command, DeviceStatusUpdated event, NewAlertTriggered event, NewSensorReading event, SensorApiController, EmailConfigController (+30 more)

### Community 16 - "Vue Devices View"
Cohesion: 0.05
Nodes (39): applyDevicesPage(), authStore, closeForm(), defaultDeviceForm(), deleteSelectedDevice(), deviceForm, devicePayload(), devices (+31 more)

### Community 17 - "Event Pipeline Jobs"
Cohesion: 0.10
Nodes (18): DeviceCommunicationReceived, EvaluateSensorReadingAlerts, Throwable, Throwable, QueueSmokeJob, Throwable, SendDangerAlertEmailJob, UpdateDeviceLastCommunication (+10 more)

### Community 18 - "Alert Feed & Public Graph"
Cohesion: 0.09
Nodes (8): AlertFeedController, PublicGraphController, AlertService, DateTimeInterface, PublicGraphSeriesService, RuleToGraphZones, Carbon\CarbonImmutable, Illuminate\Support\Collection

### Community 19 - "Sensor Controller"
Cohesion: 0.09
Nodes (4): SensorController, Sensor, SensorAuthorizationTest, SensorGraphZonesTest

### Community 20 - "Vue Alert Views"
Cohesion: 0.06
Nodes (5): app, authStore, pinia, router, useAuthStore

### Community 21 - "Dashboard Monitor Board"
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

### Community 22 - "CDC Outbox Consumer"
Cohesion: 0.15
Nodes (4): CdcOutboxStreamConsumer, Closure, CdcOutboxStreamConsumerTest, Connection

### Community 23 - "Event Pipeline Metrics"
Cohesion: 0.11
Nodes (4): EventPipelineMetricsService, Connection, EventPipelineMetricsServiceTest, RuntimeException

### Community 24 - "Internal Metrics API"
Cohesion: 0.09
Nodes (13): InternalMetricsController, MetricsController, MetricsController, EnsureIngestionToken, EnsureUserHasPermission, EnsureUserIsAdmin, TrackApiPerformance, ApiMetricsService (+5 more)

### Community 25 - "Config Controller"
Cohesion: 0.10
Nodes (8): ConfigController, DashboardGraphCatalogController, EmailConfigController, HealthController, RoleController, DashboardPreferenceController, SecretSettingService, Illuminate\Http\JsonResponse

### Community 26 - "Backend Dependencies"
Cohesion: 0.06
Nodes (33): dependencies, chart.js, laravel-echo, lightweight-charts, pusher-js, devDependencies, axios, bootstrap (+25 more)

### Community 27 - "Architecture Decisions"
Cohesion: 0.10
Nodes (33): Admin Routes RBAC Correction (admin-only list narrowed), ADR-1 Durable Publication (Transactional Outbox → CDC → Redis Stream), ADR-2 Client Recovery Protocol, ADR-3 DLQ Strategy, ADR-4 Internal Realtime Fan-out (Direct or Pub/Sub), Audit Roadmap Gates G0–G10 / Tasks TASK-001–012, RBAC Authorization Matrix (sensor.view / sensor_reading.view / system_setting.update), Backend PHP Suite 476/476 PASS (1826 assertions) (+25 more)

### Community 28 - "Ingestion Spool Service"
Cohesion: 0.11
Nodes (21): DurableEventSpool, PendingEvent, Mark event as failed. Returns True if event is now dead-lettered (exceeded max…, Move an exhausted event to the dead_letter table for later inspection., Return all dead-lettered events for inspection., The durable, local owner of MQTT events awaiting backend delivery., Return the spool's current time source for coordinated components., event() (+13 more)

### Community 29 - "Device Sensor Mapping"
Cohesion: 0.12
Nodes (8): Attribute, DeviceSensorMapping, RawReadingNormalizer, SensorMappingService, Carbon\Carbon, DateTimeInterface, Illuminate\Database\Eloquent\Casts\Attribute, Illuminate\Support\Str

### Community 30 - "Community 30"
Cohesion: 0.11
Nodes (9): AuditLog, Permission, Collection, Role, Collection, Illuminate\Database\Eloquent\Factories\HasFactory, Illuminate\Database\Eloquent\Model, Illuminate\Database\Eloquent\Relations\BelongsToMany (+1 more)

### Community 31 - "Community 31"
Cohesion: 0.08
Nodes (18): buildAuthTransitionEvidence(), isAuthTransitionPass(), consoleErrors, diagnoseOverflow(), __dirname, failedRequests, interact, mode (+10 more)

### Community 32 - "Community 32"
Cohesion: 0.09
Nodes (9): SensorReading, SensorReadingObserver, EventServiceProvider, AlertSeeder, AlertEmailAsyncDeliveryTest, AlertEmailTest, Illuminate\Auth\Events\Registered, Illuminate\Auth\Listeners\SendEmailVerificationNotification (+1 more)

### Community 33 - "Community 33"
Cohesion: 0.08
Nodes (22): accessibleSummary, chartContainer, chartData, chartOptions, hasData, props, renderChart(), sampleViewModel (+14 more)

### Community 34 - "Community 34"
Cohesion: 0.10
Nodes (8): StoreAlertRuleRequest, StoreRawIngestionEventRequest, UpdateAlertConfigRequest, UpdateAlertRuleRequest, UpdateEmailConfigRequest, UpdateGeneralConfigRequest, Illuminate\Foundation\Http\FormRequest, Illuminate\Validation\Validator

### Community 35 - "Community 35"
Cohesion: 0.12
Nodes (20): build_qc_checksum(), build_sensor_payload(), device_sender_worker(), get_api_key(), get_location(), get_node_id(), get_sensor_key(), get_sensors() (+12 more)

### Community 36 - "Community 36"
Cohesion: 0.19
Nodes (3): DomainEventBroadcastConsumerTest, Connection, RedisManager

### Community 37 - "Community 37"
Cohesion: 0.14
Nodes (17): Exception, BackendClient, BackendClientError, Any, RuntimeError, Raised when backend ingestion endpoint cannot be reached or rejects payload., DeliveryWorker, retry_delay() (+9 more)

### Community 38 - "Community 38"
Cohesion: 0.15
Nodes (4): AlertRule, AlertRuleCascadeDeleteTest, RuleToGraphZonesTest, SensorReadingTriggeredRulesTest

### Community 39 - "Community 39"
Cohesion: 0.17
Nodes (3): RawSensorEvent, RawReadingNormalizerTest, RawSensorEventIdempotencyTest

### Community 40 - "Community 40"
Cohesion: 0.09
Nodes (23): authStore, closeForm(), defaultSensorForm(), deleteSelectedSensor(), devices, editingSensorId, error, filteredSensors (+15 more)

### Community 41 - "Community 41"
Cohesion: 0.11
Nodes (6): SensorTypeController, SensorType, DangerAlertEmailTest, DashboardGraphCatalogControllerTest, AlertUniqueConstraintTest, SensorReadingAlertTest

### Community 43 - "Community 43"
Cohesion: 0.14
Nodes (5): ReplayDeadLetter, DeadLetterStreamService, DeadLetterStreamServiceTest, Illuminate\Redis\Connections\Connection, InvalidArgumentException

### Community 44 - "Community 44"
Cohesion: 0.16
Nodes (19): ranges, api, apps, mount(), useLabWorkspace(), add(), changeDevice(), changeRange() (+11 more)

### Community 45 - "Community 45"
Cohesion: 0.15
Nodes (23): ALERTS_CHANNEL, ALERTS_EVENT, ALERTS_EVENT_CLASS, ALERTS_RESOLVED_EVENT, ALERTS_RESOLVED_EVENT_CLASS, bufferDuringRecovery(), CONNECTION_ERROR_MESSAGES, error (+15 more)

### Community 46 - "Community 46"
Cohesion: 0.09
Nodes (18): authStore, device, deviceStatuses, effectiveDevice, error, loading, oneTimeApiKey, props (+10 more)

### Community 47 - "Community 47"
Cohesion: 0.09
Nodes (21): apiMetrics, apiRequestsChart, barOptions, comparisonChart, devicesChart, doughnutOptions, error, gaugeOptions (+13 more)

### Community 48 - "Community 48"
Cohesion: 0.14
Nodes (4): DeviceTypeController, ConfigController, DeviceTypeController, DeviceType

### Community 49 - "Community 49"
Cohesion: 0.17
Nodes (19): BaseModel, build_raw_event(), derive_source_event_id(), _identity_part(), _iso_now(), Any, Build a retry-stable identity from device-domain fields, never MQTT packet IDs., _source_identity() (+11 more)

### Community 50 - "Community 50"
Cohesion: 0.11
Nodes (19): alerts, alertsStore, error, filter, handleResolve(), handleResolveAll(), load(), loading (+11 more)

### Community 51 - "Community 51"
Cohesion: 0.10
Nodes (21): @fontsource/inter, @fontsource/jetbrains-mono, dependencies, axios, bootstrap, @fontsource/inter, @fontsource/jetbrains-mono, laravel-echo (+13 more)

### Community 52 - "Community 52"
Cohesion: 0.12
Nodes (20): closeModal(), deleteRule(), deletingId, error, formError, load(), loading, loadMetadata() (+12 more)

### Community 53 - "Community 53"
Cohesion: 0.14
Nodes (4): DeviceStatusLog, DeviceShowStatusLogsTest, DeviceStatusChangeTransitionTest, DeviceServiceTest

### Community 55 - "Community 55"
Cohesion: 0.13
Nodes (13): __dirname, fakeEchoPlugin(), main(), record(), results, root, __dirname, fakeEchoPlugin() (+5 more)

### Community 56 - "Community 56"
Cohesion: 0.18
Nodes (15): alert(), countFor(), device(), deviceSensor(), installAuth(), json(), mockApi(), readings() (+7 more)

### Community 57 - "Community 57"
Cohesion: 0.19
Nodes (14): buildMatrixSummaryRecord(), isPassingMatrixRecord(), parseMatrixRow(), parsedRun, hasInteractionFailure(), isCleanResult(), isPageCorrect(), isPassingAuditResult() (+6 more)

### Community 58 - "Community 58"
Cohesion: 0.18
Nodes (3): PublicGraphVisibility, Gate10PublicGraphBoundaryTest, Illuminate\Database\Eloquent\Builder

### Community 59 - "Community 59"
Cohesion: 0.11
Nodes (15): activeAlertsCard, alertsRealtime, alertsStore, alertsView, alertToast, appLayout, echo, envExample (+7 more)

### Community 60 - "Community 60"
Cohesion: 0.18
Nodes (15): booleanEnv(), createEcho(), disconnectEcho(), getEcho(), getEchoConfig(), handleAuthChange(), ADR-0002, notify() (+7 more)

### Community 61 - "Community 61"
Cohesion: 0.23
Nodes (12): configure_logging(), main(), parse_args(), Any, Namespace, run_mqtt(), run_simulation(), _sample_payload() (+4 more)

### Community 62 - "Community 62"
Cohesion: 0.13
Nodes (13): icons, paths, props, alerts, auth, links, logout(), open (+5 more)

### Community 63 - "Community 63"
Cohesion: 0.20
Nodes (8): ConsumeCdcOutboxes, DomainEventPublisher, RawSensorEventPublisher, CountingDomainPublisher, CountingRawPublisher, RedisManager, Illuminate\Redis\RedisManager, Illuminate\Support\Facades\Redis

### Community 64 - "Community 64"
Cohesion: 0.13
Nodes (9): MailMessage, ResetPasswordNotification, MailMessage, VerifyEmailNotification, Illuminate\Auth\Notifications\ResetPassword, Illuminate\Auth\Notifications\VerifyEmail, Illuminate\Notifications\Messages\MailMessage, Illuminate\Support\Facades\Config (+1 more)

### Community 65 - "Community 65"
Cohesion: 0.15
Nodes (6): AppServiceProvider, ViewServiceProvider, SecurityRateLimitTest, Illuminate\Cache\RateLimiting\Limit, Illuminate\Support\Facades\RateLimiter, Illuminate\Support\ServiceProvider

### Community 66 - "Community 66"
Cohesion: 0.12
Nodes (17): scripts, audit:baseline, audit:events, audit:gate9, audit:network, audit:network:live, audit:no-polling:source, build (+9 more)

### Community 68 - "Community 68"
Cohesion: 0.14
Nodes (13): auth, error, graphDevices, loading, boardDevices, flush(), getActiveAlerts, getAuthenticatedGraphCatalog (+5 more)

### Community 69 - "Community 69"
Cohesion: 0.13
Nodes (17): Dead Letter Queue (DLQ), Exponential Backoff Delivery, Idempotency Ledger via status Column, MQTT Ingestion, POST /api/ingestion/events, QC Validation Gate, raw:consume Command, raw-process-v1 Redis Consumer Group (+9 more)

### Community 70 - "Community 70"
Cohesion: 0.17
Nodes (6): ConsumeDomainEvents, ConsumeRawEvents, InspectDeadLetters, InspectReadingTimeSemantics, PurgeFutureSensorReadings, Illuminate\Console\Command

### Community 71 - "Community 71"
Cohesion: 0.14
Nodes (4): RolePermissionSeeder, UserSeeder, RbacUserProvisioningTest, UserSeederSecurityTest

### Community 72 - "Community 72"
Cohesion: 0.15
Nodes (3): UpdateAlertRulesTable, UpdateAlertRulesSeverity, Illuminate\Database\Migrations\Migration

### Community 73 - "Community 73"
Cohesion: 0.34
Nodes (3): Connection, RedisManager, RawStreamConsumerTest

### Community 74 - "Community 74"
Cohesion: 0.13
Nodes (15): devDependencies, jsdom, @playwright/test, sass, vite, @vitejs/plugin-vue, vitest, ws (+7 more)

### Community 75 - "Community 75"
Cohesion: 0.15
Nodes (12): alertsStore, currentAlert, currentDevice, currentMessage, currentSeverity, currentTime, currentTitle, currentValue (+4 more)

### Community 76 - "Community 76"
Cohesion: 0.23
Nodes (14): DEVICE_STATUS_CHANNEL, DEVICE_STATUS_EVENT, DEVICE_STATUS_EVENT_CLASS, error, eventPayload(), fetchStatusSnapshot(), isConnected, recoveryBuffer (+6 more)

### Community 77 - "Community 77"
Cohesion: 0.14
Nodes (5): props, severityClass, apps, authStore, mountedApps

### Community 78 - "Community 78"
Cohesion: 0.20
Nodes (3): ReadingProjection, ReadingProvenanceService, Illuminate\Database\Eloquent\Relations\BelongsTo

### Community 80 - "Community 80"
Cohesion: 0.14
Nodes (14): scripts, dev, post-autoload-dump, post-root-package-install, post-update-cmd, test, Composer\\Config::disableProcessTimeout, Illuminate\\Foundation\\ComposerScripts::postAutoloadDump (+6 more)

### Community 81 - "Community 81"
Cohesion: 0.21
Nodes (6): AlertRuleSeeder, DeviceTypeSeeder, SensorTypeSeeder, SystemSettingsSeeder, Illuminate\Database\Console\Seeds\WithoutModelEvents, Illuminate\Database\Seeder

### Community 82 - "Community 82"
Cohesion: 0.15
Nodes (10): email, error, errors, loading, payload(), save(), saving, success (+2 more)

### Community 83 - "Community 83"
Cohesion: 0.15
Nodes (3): EmailConfigController, UserRoleController, Illuminate\Support\Facades\Route

### Community 87 - "Community 87"
Cohesion: 0.17
Nodes (10): flush(), getActiveAlerts, getPublicConfig, getRuntimeConfig, mountAppLayout(), mountedApps, subscribeAlerts, subscribeDeviceStatus (+2 more)

### Community 88 - "Community 88"
Cohesion: 0.24
Nodes (12): onConnectionStateChange(), onResync(), ADR-0002, normalizeReading(), RECOVERY_WINDOW_MS, resolveSensorId(), SENSOR_EVENT, SENSOR_EVENT_CLASS (+4 more)

### Community 89 - "Community 89"
Cohesion: 0.17
Nodes (9): error, form, loading, router, success, authApi, mountedApps, mountRegisterView() (+1 more)

### Community 90 - "Community 90"
Cohesion: 0.18
Nodes (13): Task 10 Remediation Report, Audit Lifecycle Fixture Fix, Browser Fixture Graph Bootstrap, 44px Minimum Touch Target Remediation, useLabWorkspace Authorization Fix, Task 10 Report — Gate 9/10 Evidence, gate10-quality.yml CI Pipeline, Exact-SHA Browser Evidence (+5 more)

### Community 95 - "Community 95"
Cohesion: 0.32
Nodes (3): DeadLetterCommandsTest, Connection, RedisManager

### Community 96 - "Community 96"
Cohesion: 0.26
Nodes (3): ExampleTest, MigrationIntegrityTest, PHPUnit\Framework\TestCase

### Community 97 - "Community 97"
Cohesion: 0.30
Nodes (10): boundariesFromRegions(), buildZonesViewModel(), KNOWN_SEVERITIES, moreSevere(), normalizeBoundaries(), normalizeRegions(), normalizeSeverity(), PRECEDENCE (+2 more)

### Community 98 - "Community 98"
Cohesion: 0.20
Nodes (9): alerts, alertsStore, count, error, loading, flush(), getActiveAlerts, mountActiveAlertsCard() (+1 more)

### Community 101 - "Community 101"
Cohesion: 0.18
Nodes (10): autoload-dev, psr-4, description, license, minimum-stability, name, prefer-stable, Tests\\ (+2 more)

### Community 102 - "Community 102"
Cohesion: 0.25
Nodes (3): EventPublisherXaddShapeTest, SensorDataControllerTest, Mockery

### Community 103 - "Community 103"
Cohesion: 0.31
Nodes (10): ALLOWED_ANON, apiLogin(), authenticatedScenario(), __dirname, guestScenario(), LEAK_ENDPOINTS, main(), OBSERVE_MS (+2 more)

### Community 104 - "Community 104"
Cohesion: 0.18
Nodes (8): backendRoots, compose, __dirname, FRONT, RELAY_TOKENS, REPO, RETIRED_ENDPOINTS, violations

### Community 105 - "Community 105"
Cohesion: 0.18
Nodes (10): dependencies, emailVerification, envExample, missing, missingDependencies, packageJson, requiredDependencies, requiredFiles (+2 more)

### Community 106 - "Community 106"
Cohesion: 0.27
Nodes (9): close(), dialogEl, emit, focusableElements(), onKeydown(), props, flush(), mountBaseModal() (+1 more)

### Community 107 - "Community 107"
Cohesion: 0.18
Nodes (7): alertsStore, authStore, canViewAlerts, canViewDevices, deviceStatusesStore, { subscribeAlerts, unsubscribeAlerts }, { subscribeDeviceStatus, unsubscribeDeviceStatus }

### Community 109 - "Community 109"
Cohesion: 0.20
Nodes (8): error, errors, form, loading, payload(), save(), saving, success

### Community 110 - "Community 110"
Cohesion: 0.18
Nodes (9): adminActions, alerts, diagnostics, email, general, loading, loadNotice, sections (+1 more)

### Community 111 - "Community 111"
Cohesion: 0.38
Nodes (9): PayloadValidationError, Any, Raised when a payload does not satisfy minimum ingestion requirements., validate_payload(), test_validate_payload_accepts_valid_payload(), test_validate_payload_rejects_missing_sensors(), test_validate_payload_rejects_sensor_without_value(), valid_payload() (+1 more)

### Community 112 - "Community 112"
Cohesion: 0.18
Nodes (11): ADR-003: Do Not Move Blade Directly To /front, Blade Views (Legacy UI), script_datos.py (Python IoT Simulator), Operational Risks, Functional State: Blade Legacy, Legacy Views Still Dependent on Blade, Lost or Not-Found Features, Manual Validation Checklist (Not Yet Executed) (+3 more)

### Community 113 - "Community 113"
Cohesion: 0.20
Nodes (7): getActiveAlerts, transport, channelCallbacks, connectionWatchers, fetchActiveAlerts, listenOnChannel, resyncWatchers

### Community 117 - "Community 117"
Cohesion: 0.22
Nodes (7): authStore, deviceStatuses, props, getDevices, mountDeviceStatusList(), mountedApps, visibleDevices

### Community 120 - "Community 120"
Cohesion: 0.33
Nodes (8): FALLBACK_TOKENS, hexToRgba(), readCssVar(), resolveChartTokens(), resolveZoneTokens(), ZONE_CSS_VARS, ZONE_FALLBACKS, ZONE_FILL_ALPHA

### Community 121 - "Community 121"
Cohesion: 0.20
Nodes (6): error, errors, form, loading, saving, success

### Community 122 - "Community 122"
Cohesion: 0.22
Nodes (9): require, laravel/framework, laravel/sanctum, laravel/tinker, laravel/ui, php, predis/predis, pusher/pusher-php-server (+1 more)

### Community 124 - "Community 124"
Cohesion: 0.22
Nodes (8): __dirname, envExample, __filename, forbiddenKeys, frontDockerfile, frontRoot, repoRoot, requiredFiles

### Community 127 - "Community 127"
Cohesion: 0.28
Nodes (6): emit, filteredSensors, form, nullableNumber(), props, submit()

### Community 128 - "Community 128"
Cohesion: 0.25
Nodes (8): devices, fetchWindow, flush(), mountBoard(), mountedApps, resultForQuery, subscribeSensor, unsubscribeSensor

### Community 129 - "Community 129"
Cohesion: 0.25
Nodes (5): copied, copyError, props, mountCredentialModal(), mountedApps

### Community 130 - "Community 130"
Cohesion: 0.22
Nodes (6): alert, error, loading, props, resolving, success

### Community 131 - "Community 131"
Cohesion: 0.25
Nodes (8): require-dev, fakerphp/faker, laravel/pail, laravel/pint, laravel/sail, mockery/mockery, nunomaduro/collision, phpunit/phpunit

### Community 137 - "Community 137"
Cohesion: 0.36
Nodes (6): channelKey(), getChannelRefCount(), leaveChannelName(), listenOnChannel(), refCounts, echoMock

### Community 138 - "Community 138"
Cohesion: 0.25
Nodes (3): connectionWatchers, echoMock, resyncWatchers

### Community 139 - "Community 139"
Cohesion: 0.36
Nodes (3): activeControllersByConsumer, buildGraphQueryKey(), useGraphSeriesQueryStore

### Community 140 - "Community 140"
Cohesion: 0.25
Nodes (6): authStore, error, loading, resending, route, success

### Community 141 - "Community 141"
Cohesion: 0.29
Nodes (8): Phase 8A API Additions (CRUD/Admin), Fase 7B: Operational Validation Before Blade Cleanup, Fase 8A: Admin CRUD/Catalog SPA Migration, QueueSmokeJob (queue infrastructure smoke test), High Risks, Fase 6B: Blade Legacy Cleanup and Production SPA Adjustment (Pending), Fase 8: Final Cleanup (Pending), Session Progress: Fase 8A Work Log

### Community 142 - "Community 142"
Cohesion: 0.29
Nodes (7): pestphp/pest-plugin, php-http/discovery, config, allow-plugins, optimize-autoloader, preferred-install, sort-packages

### Community 145 - "Community 145"
Cohesion: 0.29
Nodes (6): allowScripts, esbuild@0.21.5, name, private, type, version

### Community 146 - "Community 146"
Cohesion: 0.29
Nodes (6): missing, navbar, requiredFiles, root, router, srcFilesToCheck

### Community 149 - "Community 149"
Cohesion: 0.38
Nodes (3): getGraphSeries(), getPrivateGraphSeries(), toWindowParam()

### Community 150 - "Community 150"
Cohesion: 0.52
Nodes (4): composeGraphSeries(), computeStats(), idCompare(), toPoint()

### Community 151 - "Community 151"
Cohesion: 0.33
Nodes (5): deviceStatuses, status, statuses, mountedApps, mountRealtimeStatus()

### Community 152 - "Community 152"
Cohesion: 0.29
Nodes (4): connectionWatchers, echoMock, getDeviceStatusSnapshot, resyncWatchers

### Community 153 - "Community 153"
Cohesion: 0.29
Nodes (5): error, form, loading, route, success

### Community 154 - "Community 154"
Cohesion: 0.33
Nodes (6): currentQuery, flush(), getDevices, getSensors, mountedApps, mountView()

### Community 155 - "Community 155"
Cohesion: 0.33
Nodes (7): ADR-007: Docker Per Service, Docker/Docker Compose (absent at audit time), Phase 6 Physical Separation Notes, Phase 7 Docker Consumer Notes, Fase 6: Physical Separation Into /back, Fase 7: Docker and Compose, Functional State: Docker

### Community 156 - "Community 156"
Cohesion: 0.29
Nodes (7): Pusher / Laravel Echo Realtime, Broadcast Event: NewAlertTriggered (channel alerts), Broadcast Event: NewSensorReading (channel sensor.{id}), Phase 4 Frontend Consumer Notes, Phase 5 Realtime Contract Notes, Fase 4: Progressive Screen Migration, Fase 5: Realtime (Echo/Pusher) Integration

### Community 157 - "Community 157"
Cohesion: 0.52
Nodes (7): Task 8 Report — Authenticated Graph Catalog, GET /api/dashboard/graph-catalog Endpoint, DashboardGraphCatalogController, getAuthenticatedGraphCatalog() Frontend Client, Public Bootstrap + Authenticated Catalog Merge, RuleToGraphZones Mapping, Sanctum API Authentication Group

### Community 163 - "Community 163"
Cohesion: 0.47
Nodes (3): Client, Any, MQTTMessage

### Community 164 - "Community 164"
Cohesion: 0.33
Nodes (4): email, error, loading, success

### Community 165 - "Community 165"
Cohesion: 0.33
Nodes (4): authStore, form, route, router

### Community 166 - "Community 166"
Cohesion: 0.33
Nodes (4): error, items, loading, system

### Community 167 - "Community 167"
Cohesion: 0.47
Nodes (5): configApi, flush(), mountView(), response(), setResponses()

### Community 168 - "Community 168"
Cohesion: 0.33
Nodes (6): iot-platform-v2 Project, Incremental Migration Principle, Front/Back Separation Goal, ADR-002: Backend As REST API + Broadcasting, ADR-004: Incremental Migration, Laravel 12 / PHP 8.2+ Backend

### Community 169 - "Community 169"
Cohesion: 0.33
Nodes (6): ADR-001: Keep A Monorepo Initially, Migration Hygiene Rules, Fase 0: Read-only Audit and Real Contract, Fase 1: Headless Auth (Sanctum Bearer), Fase 2: API Normalization Replacing Blade, Codex Working Notes Operational Rules

### Community 170 - "Community 170"
Cohesion: 0.40
Nodes (5): autoload, psr-4, App\\, Database\\Factories\\, Database\\Seeders\\

### Community 171 - "Community 171"
Cohesion: 0.40
Nodes (4): Monolog\Handler\NullHandler, Monolog\Handler\StreamHandler, Monolog\Handler\SyslogUdpHandler, Monolog\Processor\PsrLogMessageProcessor

### Community 172 - "Community 172"
Cohesion: 0.40
Nodes (3): chart, lineSeries, lineSeriesDef

### Community 173 - "Community 173"
Cohesion: 0.40
Nodes (5): Front SPA HTML Entrypoint, ADR-008: Frontend Framework (Vue 3 + Bootstrap 5, proposed), Vite (laravel-vite-plugin), Phase 3 Frontend Consumer Notes, Fase 3: Vue 3 SPA Initialization

### Community 174 - "Community 174"
Cohesion: 0.50
Nodes (3): metrics, props, renderMetricsCards()

### Community 176 - "Community 176"
Cohesion: 0.60
Nodes (3): normalizeReading(), normalizeReadings(), useSensorReadingsStore

### Community 177 - "Community 177"
Cohesion: 0.50
Nodes (5): ADR-009: SPA Auth Uses Sanctum Bearer Tokens Initially, Laravel Sanctum, Authentication Strategy: Sanctum Bearer Tokens, Bearer Token in localStorage Risk, SEC-003: Bearer Token in localStorage

### Community 178 - "Community 178"
Cohesion: 0.90
Nodes (5): Task 9 Report — Oversized Graph Window Validation, PublicGraphController, PublicGraphSeriesService, SensorApiController, 24-Hour Window Validation (HTTP 422)

### Community 180 - "Community 180"
Cohesion: 0.50
Nodes (4): post-create-project-cmd, @php artisan key:generate --ansi, @php artisan migrate --graceful --ansi, @php -r \"file_exists('database/database.sqlite') || touch('database/database.sqlite');\

### Community 183 - "Community 183"
Cohesion: 0.83
Nodes (3): getAudioContext(), playAlertSound(), unlockAlertSound()

### Community 185 - "Community 185"
Cohesion: 0.50
Nodes (4): Chart.js, Functional State: Frontend, Functional Inventory: Frontend SPA Table, Partially Migrated Features

### Community 187 - "Community 187"
Cohesion: 0.67
Nodes (3): extra, laravel, dont-discover

### Community 188 - "Community 188"
Cohesion: 0.67
Nodes (3): keywords, framework, laravel

### Community 223 - "Community 223"
Cohesion: 0.67
Nodes (3): User model, ResetPasswordNotification, VerifyEmailNotification

### Community 230 - "Community 230"
Cohesion: 0.67
Nodes (3): ADR-005: Separate Environment Variables, ADR-006: API-Only Frontend Communication, FIX-001: front/.env Configured With Only Public VITE_* Vars

### Community 231 - "Community 231"
Cohesion: 0.67
Nodes (3): Functional State: Backend, Functional Inventory: Backend API Table, Conserved Features (Blade to Vue Parity)

### Community 232 - "Community 232"
Cohesion: 0.67
Nodes (3): Task 11 Report — Mobile/Accessibility, AppLayout Route Stub, Dependency Audit (npm audit --offline)

## Ambiguous Edges - Review These
- `AlertService` → `NewSensorReading event`  [AMBIGUOUS]
  INFORME_ALERTAS_NOTIFICACIONES.md · relation: calls

## Knowledge Gaps
- **713 isolated node(s):** `$schema`, `name`, `type`, `description`, `laravel` (+708 more)
  These have ≤1 connection - possible missing edges or undocumented components. (Counts symbols only; 1410 node(s) total have ≤1 connection when file, concept and rationale nodes are included.)
- **96 thin communities (<3 nodes) omitted from report** — run `graphify query` to explore isolated nodes.

## Suggested Questions
_Questions this graph is uniquely positioned to answer:_

- **What is the exact relationship between `AlertService` and `NewSensorReading event`?**
  _Edge tagged AMBIGUOUS (relation: calls) - confidence is low._
- **Why does `TestCase` connect `Auth & Security Tests` to `User Model & RBAC`, `Device-Sensor Controllers`, `Community 132`, `Alert Domain Events`, `Community 133`, `API Controllers Hub`, `Community 134`, `System Settings & Security`, `Alert Controller`, `Community 135`, `Community 136`, `Auth API Controller`, `Community 143`, `Community 144`, `Event Pipeline Jobs`, `Alert Feed & Public Graph`, `Sensor Controller`, `CDC Outbox Consumer`, `Event Pipeline Metrics`, `Device Sensor Mapping`, `Community 159`, `Community 32`, `Community 160`, `Community 161`, `Community 162`, `Community 36`, `Community 38`, `Community 39`, `Community 41`, `Community 42`, `Community 43`, `Community 53`, `Community 54`, `Community 181`, `Community 58`, `Community 63`, `Community 65`, `Community 71`, `Community 73`, `Community 93`, `Community 94`, `Community 95`, `Community 100`, `Community 102`, `Community 116`, `Community 123`?**
  _High betweenness centrality (0.041) - this node is a cross-community bridge._
- **Why does `User` connect `User Model & RBAC` to `Auth & Security Tests`, `Device-Sensor Controllers`, `Auth API Controller`, `Alert Rule Controller`, `API Controllers Hub`, `Community 136`, `System Settings & Security`, `Alert Controller`, `Profile & Auth Controllers`, `Community 143`, `Community 144`, `Sensor Controller`, `Event Pipeline Metrics`, `Community 30`, `Community 159`, `Community 160`, `Community 32`, `Community 41`, `Community 53`, `Community 58`, `Community 64`, `Community 71`, `Community 78`, `Community 83`, `Community 94`, `Community 100`, `Community 115`, `Community 116`?**
  _High betweenness centrality (0.034) - this node is a cross-community bridge._
- **Why does `Sensor` connect `Sensor Controller` to `Auth & Security Tests`, `User Model & RBAC`, `Device-Sensor Controllers`, `Auth API Controller`, `Community 132`, `Alert Rule Controller`, `API Controllers Hub`, `Alert Domain Events`, `System Settings & Security`, `Alert Controller`, `Community 135`, `Community 136`, `Community 143`, `Community 144`, `Event Pipeline Jobs`, `Alert Feed & Public Graph`, `Config Controller`, `Device Sensor Mapping`, `Community 30`, `Community 32`, `Community 161`, `Community 34`, `Community 162`, `Community 36`, `Community 38`, `Community 39`, `Community 41`, `Community 42`, `Community 181`, `Community 58`, `Community 63`, `Community 65`, `Community 70`, `Community 73`, `Community 81`, `Community 91`, `Community 93`, `Community 94`, `Community 100`, `Community 102`, `Community 115`, `Community 116`, `Community 123`?**
  _High betweenness centrality (0.033) - this node is a cross-community bridge._
- **What connects `$schema`, `name`, `type` to the rest of the system?**
  _713 weakly-connected nodes found - possible documentation gaps or missing edges._
- **Should `Auth & Security Tests` be split into smaller, more focused modules?**
  _Cohesion score 0.028293545534924844 - nodes in this community are weakly interconnected._
- **Should `User Model & RBAC` be split into smaller, more focused modules?**
  _Cohesion score 0.03036837376460018 - nodes in this community are weakly interconnected._