# ingestion_service

Servicio Python de ingesta inicial para eventos IoT crudos.

## Objetivo en esta fase

- Recibir payload MQTT (o simularlo).
- Validar estructura minima.
- Normalizar al contrato de backend crudo.
- Enviar a `POST /api/ingestion/events` en Laravel.
- Mantener la separacion: ingesta cruda ahora, procesamiento despues.

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
- envio al backend.

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

## Contrato futuro de data_jobs_service (no implementado en esta fase)

El modulo futuro consumira de Redis stream `iot.raw-events` y seguira este flujo:

1. Leer mensaje de stream con `event_id`.
2. Consultar `raw_sensor_events` por `event_id`.
3. Transformar payload multi-sensor a lecturas normalizadas.
4. Resolver mapping `node_id + sensor_key -> sensor_id`.
5. Guardar datos procesados.
6. Activar/alimentar modulo de alertas.
7. Marcar `raw_sensor_events.status`:
   - `processed` con `processed_at`, o
   - `failed` con detalle en `error`.
