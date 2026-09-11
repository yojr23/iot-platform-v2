# Estado tangible del repositorio — 11 de septiembre de 2026

## Propósito y método

Este es el punto de referencia para documentación de estado. Cada afirmación se limita a una de estas categorías:

- **Implementado en fuente:** se localizó en rutas, servicios, configuración o pruebas.
- **Ejecutado en esta sesión:** se corrió el comando indicado y terminó correctamente.
- **Evidencia histórica:** resultado fechado conservado en otro documento; no se extrapola a esta máquina ni a producción.

No se deduce disponibilidad, capacidad, certificación o comportamiento de producción sólo porque exista código.

## Inventario de capacidades

| Capacidad | Estado | Evidencia fuente |
| --- | --- | --- |
| Dashboard SPA canónico | Implementado en fuente | front/src/router/index.js; back/routes/web.php redirige / y /dashboard a FRONT_URL/dashboard. |
| Gráfica pública limitada por sensor | Implementado en fuente | back/routes/api.php, Api/PublicGraphController.php, Services/Monitoring/PublicGraphVisibility.php y migración 2026_09_09_000002_add_public_monitoring_enabled_to_sensors_table.php. |
| Serie autenticada para sensor restringido | Implementado en fuente | GET /api/sensors/{sensor}/series en routes/api.php; Api/SensorApiController.php; front/src/api/graph.js. |
| Consulta histórica cancelable por consumidor | Implementado en fuente | front/src/stores/graphSeriesQuery.js. |
| Lecturas públicas y privadas en tiempo real | Implementado en fuente | Events/NewSensorReading.php, routes/channels.php y front/src/realtime/useSensorRealtime.js. |
| Alertas y estado de dispositivo privados | Implementado en fuente | Events/AlertResolved.php, Events/DeviceStatusUpdated.php, routes/channels.php, realtime/useAlertsRealtime.js y realtime/useDeviceStatusRealtime.js. |
| Ingesta raw transaccional | Implementado en fuente | Api/IngestionController.php, Models/RawEventOutbox.php y migración 2026_09_07_000003_create_raw_event_outbox_table.php. |
| Publicación CDC y consumidores Redis Streams | Implementado en fuente/configuración | docker-compose.yml, cdc/application.properties, Console/Commands/ConsumeCdcOutboxes.php, ConsumeRawEvents.php y ConsumeDomainEvents.php. |
| Alertas por correo fuera de la respuesta de ingesta | Implementado en fuente | Observers/AlertObserver.php despacha SendDangerAlertEmailJob después de commit. |
| Retiro total de Blade | No afirmar | routes/web.php todavía expone operaciones Blade de administración y compatibilidad, aunque las vistas de dashboard y lectura de sensores redirigen a la SPA. |
| ACL por recurso para alertas o sensores | No implementado | routes/channels.php autoriza a cualquier usuario autenticado para alerts, device-status y sensores existentes. |
| Prueba de fallos CDC A–E ejecutada en esta sesión | No afirmar | gate-10-evidence.md la mantiene como trabajo pendiente de ejecución scriptada. |

## Arquitectura actual

~~~mermaid
flowchart TB
    subgraph Browser["Navegador"]
        SPA["Vue SPA<br/>Pinia + Chart.js + Echo"]
    end

    subgraph Laravel["back/ — Laravel 12"]
        PublicGraph["PublicGraphController"]
        Api["API autenticada y administrativa"]
        RawApi["IngestionController"]
        Cdc["cdc:consume-outboxes"]
        Raw["raw:consume"]
        Domain["domain:consume"]
    end

    subgraph Data["Datos y mensajería"]
        MySQL[(MySQL + binlog)]
        Redis[(Redis Streams)]
        Debezium["Debezium Server"]
    end

    SPA --> PublicGraph
    SPA --> Api
    RawApi --> MySQL
    MySQL --> Debezium
    Debezium --> Redis
    Redis --> Cdc
    Cdc --> Redis
    Redis --> Raw
    Raw --> MySQL
    Raw --> MySQL
    Redis --> Domain
    Domain --> SPA
~~~

Este diagrama representa conexiones de código y Compose. No representa una topología desplegada, balanceadores, secretos administrados ni un servidor Pusher local.

## Superficies HTTP

| Audiencia | Rutas relevantes | Garantía que sí existe |
| --- | --- | --- |
| Invitado | GET /api/public/graph/bootstrap; GET /api/public/graph/sensors/{sensor}/series | Sólo sensores con public_monitoring_enabled verdadero; series acotadas por el servicio. |
| Infraestructura | GET /api/health | Endpoint de liveness; no equivale a disponibilidad funcional. |
| Dispositivo compatible | GET /api/iot/sensors; POST /api/sensors/{sensor}/readings | Validación de API key en el controlador y rate limit. |
| Adaptador de ingesta | POST /api/ingestion/events | X-Ingestion-Token, validación y recibo raw transaccional. |
| Usuario autenticado | /api/devices, /api/sensors, /api/alerts, /api/dashboard y series privadas | auth:sanctum y rate limits. |
| Administrador | catálogos, reglas, roles, configuración e internas de métricas | auth:sanctum + admin. |

La fuente de rutas es back/routes/api.php. La lista completa se reproduce con php artisan route:list --path=api en un entorno con PHP y dependencias.

## Verificación ejecutada durante esta actualización

| Comando | Resultado |
| --- | --- |
| npm.cmd run test:unit | 36 archivos y 184 pruebas correctas. |
| npm.cmd run build | Build de producción correcto. |
| npm.cmd run audit:no-polling:source | PASS: no se encontraron construcciones de polling de estado de producto en la fuente rastreada. |

Vitest emitió advertencias de canvas JSDOM no implementado y de contexto de Vue Router; no hubo pruebas fallidas. Estas pruebas no prueban el backend, Docker ni WebSocket reales.

No se pudo ejecutar php artisan test, php artisan route:list ni Docker Compose en esta máquina porque php y docker no estaban disponibles. Por ello este documento no actualiza el resultado de la suite backend ni reclama una ejecución local del pipeline CDC.

## Cómo leer el resto de la documentación

| Documento | Uso correcto |
| --- | --- |
| README.md | Entrada operativa y arquitectura actual resumida. |
| INGESTION_PIPELINE.md | Flujo raw-first/CDC implementado. |
| implementation/gate-10-evidence.md | Evidencia de una sesión Docker y navegador del 11 de septiembre; conservar fecha y límites. |
| implementation/gates-6-7-8-evidence.md | Evidencia de una sesión previa; sus contadores no son los contadores de la sesión actual. |
| FINAL_AUDIT_REPORT.md | Auditoría puntual del 10 de septiembre; sus hallazgos se conservan, pero no es un dashboard de estado vivo. |
| PLAN.md | Plan y bitácora de migración; puede contener decisiones, etapas y evidencia fechada. |
| memory/ | Memoria histórica del refactor; no usar como especificación actual sin contrastar con fuente. |
| graphify-out/ | Índice generado de código. Se actualizó con graphify update . el 11 de septiembre; no editarlo manualmente. |

## Mantenimiento de esta referencia

Cuando cambie una capacidad, actualizar primero la ruta o servicio propietario y su prueba. Después:

1. actualizar README.md si cambia la operación o la frontera de acceso;
2. actualizar INGESTION_PIPELINE.md si cambia el camino durable;
3. registrar evidencia ejecutada con fecha y entorno, sin reemplazar un resultado histórico;
4. ejecutar graphify update . para regenerar graphify-out/.

No se deben convertir comentarios de plan, nombres de etapas ni código no ejecutado en afirmaciones de despliegue.

