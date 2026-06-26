# Ingestion Pipeline (Raw-First)

## Objetivo

Implementar la arquitectura de ingesta respetando desacople:

1. Dispositivo publica en MQTT.
2. `ingestion_service` valida y normaliza.
3. Backend Laravel persiste evento crudo en `raw_sensor_events`.
4. Backend publica referencia en Redis Stream `iot.raw-events`.
5. `data_jobs_service` (futuro) consume, transforma y persiste datos procesados.

## Endpoint de ingesta cruda

- `POST /api/ingestion/events`
- Seguridad: header `X-Ingestion-Token`
- Variable backend: `INGESTION_SERVICE_TOKEN`

Payload esperado:

```json
{
  "topic": "iot/lab_postgrado_nodo_01/readings",
  "received_at": "2026-05-14T17:30:00Z",
  "payload": {
    "device": {
      "node_id": "lab_postgrado_nodo_01"
    },
    "sensors": {
      "temperature": {
        "value": 23.47
      }
    }
  }
}
```

## Persistencia cruda

Tabla: `raw_sensor_events`

Campos principales:
- `topic`
- `source`
- `node_id`
- `payload` (JSON completo)
- `received_at`
- `status` (`received`, `processed`, `failed`)
- `error`
- `processed_at`

## Mensaje publicado a cola

Stream: `iot.raw-events`

Evento publicado:

```json
{
  "event_id": 123,
  "node_id": "lab_postgrado_nodo_01",
  "topic": "iot/lab_postgrado_nodo_01/readings",
  "received_at": "2026-05-14T17:30:00Z",
  "status": "received"
}
```

La publicación no bloquea ingesta si Redis falla; se registra error y se conserva la persistencia cruda.

## Contrato futuro de data_jobs_service

Responsabilidades previstas:

1. Leer `event_id` desde `iot.raw-events`.
2. Cargar `raw_sensor_events`.
3. Transformar payload multi-sensor.
4. Resolver mapping `node_id + sensor_key -> sensor_id`.
5. Guardar datos procesados.
6. Integrar con alertas/notificaciones.
7. Actualizar `raw_sensor_events` a `processed` o `failed`.
