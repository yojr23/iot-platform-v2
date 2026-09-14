# ingestion_service

Servicio Python de ingesta inicial para eventos IoT crudos.

## Objetivo en esta fase

- Recibir payload MQTT (o simularlo).
- Validar estructura minima.
- Normalizar al contrato de backend crudo.
- Encolar durablemente cada evento para enviarlo a `POST /api/ingestion/events` en Laravel.
- Mantener la separacion: este servicio recibe MQTT y publica eventos crudos; el procesamiento lo realiza el consumidor backend.

## Modos

### 1) Simulado

```bash
python -m app.main --simulate
```

Envia un payload ejemplo al backend.

### 2) MQTT (preparado)

`mqtt_client.py` ya contiene el flujo de:
- conexion al broker;
- suscripcion al topic;
- parseo JSON;
- validacion;
- encolado SQLite local; el callback no hace llamadas HTTP;
- entrega independiente al backend, con reintento exponencial ante fallos transitorios.

Si Mosquitto no esta listo en el entorno, el modo puede fallar al conectar.

## Variables de entorno

- `MQTT_HOST`
- `MQTT_PORT`
- `MQTT_TOPIC`
- `MQTT_USERNAME`
- `MQTT_PASSWORD`
- `MQTT_CLIENT_ID`
- `MQTT_QOS`
- `BACKEND_BASE_URL`
- `BACKEND_INGESTION_TOKEN`
- `LOG_LEVEL`
- `INGESTION_MODE` (`simulate` o `mqtt`)
- `BACKEND_TIMEOUT_SECONDS`
- `INGESTION_SPOOL_PATH` (por defecto `/data/ingestion-spool.sqlite3`)
- `INGESTION_RETRY_BASE_SECONDS` (por defecto `1`)
- `INGESTION_RETRY_MAX_SECONDS` (por defecto `60`)
- `INGESTION_DELIVERY_BATCH_SIZE` (por defecto `50`)

El spool SQLite usa WAL y conserva los eventos pendientes entre reinicios. El
`docker-compose.yml` del repositorio sí define el servicio `ingestion` y monta el volumen
nombrado `ingestion_spool` en `/app/spool`; la ruta efectiva de Compose es
`/app/spool/ingestion-spool.sqlite3`. Las ejecuciones fuera de Compose deben proporcionar
su propio almacenamiento persistente para el directorio que contiene
`INGESTION_SPOOL_PATH`.

## Trabajo operativo abierto

La implementación no establece todavía una política operativa completa. Siguen abiertos:

- definir y operar límites de capacidad y antigüedad del spool, con alertas y procedimiento
  de recuperación;
- definir la configuración de TLS y la gestión/rotación de certificados de cliente para
  MQTT y las entregas al backend;
- instrumentar telemetría y alertas para recepción, reintentos, edad del spool y entregas;
- documentar el runbook de DLQ, incluyendo retención, inspección, replay y eliminación.

Estos puntos son trabajo operativo pendiente; este README no afirma límites, certificados,
alertas ni retención que aún no estén definidos.

## Contrato enviado a Laravel

```json
{
  "topic": "iot/lab_postgrado_nodo_01/readings",
  "received_at": "2026-05-14T17:30:00Z",
  "source": "ingestion_service",
  "payload": {
    "...": "payload MQTT completo"
  }
}
```

## Procesamiento backend de eventos crudos

Este servicio Python no consume Redis ni transforma lecturas procesadas. Su responsabilidad termina al recibir el payload MQTT (o simulado), validarlo y publicarlo como evento crudo mediante `POST /api/ingestion/events` en Laravel. El backend ejecuta el comando `raw:consume` con el grupo `raw-process-v1` sobre el stream `iot.raw-events` y sigue este flujo:

1. Leer o reclamar un mensaje del stream con `event_id`.
2. Consultar `raw_sensor_events` por `event_id`.
3. Transformar el payload multi-sensor a lecturas normalizadas mediante `RawReadingNormalizer`.
4. Resolver el mapping `node_id + sensor_key -> sensor_id`.
5. Guardar los datos procesados y marcar `raw_sensor_events.status` como `processed` dentro de la misma transaccion; ese estado es el ledger de idempotencia del grupo.
6. Tras errores reintentables, dejar la entrega pendiente para su reclamacion posterior; los fallos terminales se marcan como `failed` y se envian al stream dead-letter configurado.

Cuando `payload.qc.valid` es estrictamente `false`, el recibo crudo se conserva para auditoria,
pero no se crean lecturas normalizadas y no se evaluan reglas de alertas para esas mediciones.
El consumidor marca ese recibo como procesado y lo confirma en Redis sin reintento ni dead-letter.
Si `qc.valid` no esta presente, se mantiene el comportamiento normal de procesamiento, incluido
el reintento de un payload no vacio que no produzca ninguna lectura.

Los estados de `raw_sensor_events.status` son:
   - `received` al aceptar y almacenar el evento, pendiente de procesamiento.
   - `processed` con `processed_at` tras normalizar y guardar sus lecturas.
   - `failed` con detalle en `error` tras agotar los reintentos.
