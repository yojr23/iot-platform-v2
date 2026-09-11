# Pipeline de ingesta raw-first y CDC

**Estado documental:** contrastado con el código el 11 de septiembre de 2026.

Este documento describe el camino que existe en el repositorio. No describe un servicio futuro llamado data_jobs_service: el procesamiento reside actualmente en los comandos y servicios Laravel de back/.

## Contratos de entrada

| Entrada | Protección | Propietario inmediato | Resultado de la petición |
| --- | --- | --- | --- |
| POST /api/ingestion/events | middleware ingestion.token | Api\IngestionController | Persiste un recibo raw y una fila de outbox; devuelve 201 con status received. |
| POST /api/sensors/{sensor}/readings | API key de dispositivo, validada por SensorApiController | Api\SensorApiController | Ruta compatible que crea una lectura directamente. |
| MQTT | Configuración de ingestion_service | ingestion_service/app/mqtt_client.py | Valida y reenvía el evento a POST /api/ingestion/events. |

El token de la ruta raw se envía como X-Ingestion-Token. El servicio Python normaliza el payload MQTT antes de esa llamada, pero no consume Redis ni escribe lecturas procesadas.

## Camino durable implementado

~~~mermaid
sequenceDiagram
    participant P as ingestion_service o productor
    participant API as IngestionController
    participant DB as MySQL
    participant D as Debezium
    participant R as Redis Streams
    participant C as cdc:consume-outboxes
    participant Raw as raw:consume
    participant Domain as domain:consume

    P->>API: POST /api/ingestion/events
    API->>DB: INSERT raw_sensor_events + raw_event_outboxes (una transacción)
    API-->>P: 201 received
    DB->>D: binlog de raw_event_outboxes
    D->>R: iot-cdc.<db>.raw_event_outboxes
    C->>R: XREADGROUP / XAUTOCLAIM
    C->>R: XADD iot.raw-events
    Raw->>R: XREADGROUP raw-process-v1
    Raw->>DB: normaliza lecturas + actualiza raw_sensor_events
    Raw->>DB: INSERT domain_event_outboxes
    DB->>D: binlog de domain_event_outboxes
    D->>R: iot-cdc.<db>.domain_event_outboxes
    C->>R: XADD iot.domain-events
    Domain->>R: XREADGROUP browser-delivery-v1
    Domain->>Domain: emite broadcast compatible con Pusher
~~~

El controlador no publica a Redis ni despierta un relay dentro de la petición. Ese diseño evita afirmar que una respuesta HTTP 201 implica publicación o procesamiento.

## Persistencia y estados

### Recibo raw

La tabla raw_sensor_events conserva el payload original y es el ledger del consumidor raw.

| Campo/estado | Uso en el código |
| --- | --- |
| received | Evento aceptado y pendiente de procesamiento. |
| processed | Lecturas creadas y transacción de procesamiento completada. |
| failed | Error terminal registrado después de los reintentos del consumidor. |
| source_event_id | Identidad del productor para deduplicación del recibo. |

La misma transacción que inserta raw_sensor_events crea raw_event_outboxes con estado pending. Esta es la frontera de durabilidad de la aceptación raw.

### Outboxes y streams

| Outbox / stream | Productor | Consumidor | Finalidad |
| --- | --- | --- | --- |
| raw_event_outboxes → iot-cdc.* → iot.raw-events | Debezium + cdc:consume-outboxes | raw:consume, grupo raw-process-v1 | Llevar un recibo persistido al normalizador. |
| domain_event_outboxes → iot-cdc.* → iot.domain-events | Debezium + cdc:consume-outboxes | domain:consume, grupo browser-delivery-v1 | Llevar hechos de dominio a broadcasting. |
| iot.dead-letter-events | consumidores que alcanzan error terminal | Operación manual | Conservar mensajes no procesables. |

Los consumidores usan XREADGROUP con espera bloqueante y XAUTOCLAIM para reclamar mensajes pendientes. La semántica es al menos una vez; por eso los estados y los marcadores de entrega de outbox participan en deduplicación. No se promete orden global entre consumidores ni exactly-once.

## Procesamiento de lecturas y eventos

RawStreamConsumer llama a RawReadingNormalizer para resolver el mapeo node_id + sensor_key → sensor_id. SensorReadingService es el propietario compartido de creación de lecturas. La evaluación de reglas sigue en AlertService, que registra alert.triggered en la outbox de dominio.

DomainEventBroadcastConsumer maneja, entre otros, estos hechos:

- sensor.reading.created;
- alert.triggered;
- alert.resolved;
- device.status.changed.

Antes de emitir al navegador, comprueba la visibilidad pública de una lectura con PublicGraphVisibility. Las lecturas de sensores restringidos no se difunden por el canal público.

El correo de alerta de severidad danger no bloquea la respuesta de ingesta: AlertObserver despacha SendDangerAlertEmailJob después del commit. Esto no demuestra que haya un worker de colas activo; el perfil queue de Compose es opcional.

## Procesos de ejecución

Los comandos existentes son:

~~~powershell
php artisan cdc:consume-outboxes --block=5000
php artisan raw:consume --block=5000
php artisan domain:consume --block=5000
~~~

En Docker Compose se habilitan con:

~~~powershell
$env:DEBEZIUM_DB_PASSWORD = "una-clave-local"
docker compose --profile workers up -d
~~~

El perfil workers declara los servicios debezium, outbox-cdc-consumer, raw-consumer y domain-event-consumer. MySQL se configura con binlog ROW/FULL y el directorio cdc/ contiene application.properties de Debezium.

## Evidencia y límites

- El código que implementa la transacción de aceptación está en back/app/Http/Controllers/Api/IngestionController.php.
- El consumidor raw está en back/app/Services/Ingestion/RawStreamConsumer.php.
- Los adaptadores de CLI están en back/app/Console/Commands/ConsumeCdcOutboxes.php, ConsumeRawEvents.php y ConsumeDomainEvents.php.
- La composición operativa está en docker-compose.yml.
- La evidencia de una ejecución Docker histórica de extremo a extremo está en implementation/gate-10-evidence.md.

El último elemento es evidencia fechada, no una afirmación de que el stack esté levantado ahora. La suite PHP y Docker no se ejecutaron en la máquina usada para esta actualización documental; el README conserva los comandos para reproducirlos.
