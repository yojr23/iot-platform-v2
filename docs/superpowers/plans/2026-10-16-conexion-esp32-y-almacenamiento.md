# Conexión sensor → ESP32 → portal y almacenamiento de datos

Fecha: 2026-10-16 · Rama: `refraccion` (local está 14 commits detrás de `origin/refraccion`)

Este documento junta la revisión del repo para la instalación del nodo de oxígeno
disuelto (DO) y el diseño de una solución al crecimiento de la base de datos.
Las cifras de tamaño son estimaciones a partir del esquema, no mediciones; la Fase 0
de la parte B explica cómo medirlas en el laboratorio.

---

## A. Qué falta para conectar sensor → ESP32 → portal

Cadena completa, verificada en el código:

```
Sonda DO → ESP32 ─MQTT→ Mosquitto ─→ ingestion (Python, spool SQLite)
  ─HTTP→ POST /api/ingestion/events → raw_sensor_events + raw_event_outboxes (MySQL)
  ─binlog→ Debezium → Redis iot-cdc.* → outbox-cdc-consumer → Redis iot.raw-events
  → raw-consumer (RawReadingNormalizer) → sensor_readings + domain_event_outboxes
  ─binlog→ Debezium → domain-event-consumer → Reverb (WebSocket) → portal Vue
```

Para que una lectura llegue al portal tienen que estar corriendo **todos** estos
procesos: `db`, `redis`, `back`, `reverb`, `front` y los del perfil `workers`
(`ingestion`, `debezium`, `outbox-cdc-consumer`, `raw-consumer`,
`domain-event-consumer`). Si falta Debezium, la lectura se guarda como evento crudo
pero nunca se normaliza y no aparece en el portal.

### Bloqueantes (sin esto no llega el dato)

| # | Problema | Dónde | Solución |
|---|---|---|---|
| A1 | `MQTT_HOST=host.docker.internal` en el `.env` de la raíz apunta a un broker en esta misma Mac. El broker va a correr en otra máquina. | `.env` (raíz) | Poner la IP LAN del broker. Si el broker termina en la misma máquina que el stack, se puede dejar como está. |
| A2 | El dispositivo y el sensor no existen en la base de datos. | `back/database/seeders/LabDoNodeSeeder.php` | Correr el seeder después de migrar. |
| A3 | La IP del broker y el WiFi en el firmware son placeholders. | `lab_esp32/firmware/do_sensor_node/config.h` | Configurarlos en el laboratorio. |
| A4 | Tipo de sonda sin confirmar (el firmware lee una señal analógica). | `readDissolvedOxygen()` | Si es Atlas EZO-DO (I2C/UART), reescribir solo esa función. |
| A5 | El puerto 1883 tiene que ser alcanzable desde la red del laboratorio. | Firewall de la máquina del broker | Abrir 1883/TCP; probar con `mosquitto_pub -h <ip> -t test -m hola` desde otro equipo. |

Ya revisado y en orden: `INGESTION_SERVICE_TOKEN` está definido y coincide entre
`.env` y `back/.env`; `DEBEZIUM_DB_PASSWORD` está definido; el payload del firmware
pasa los validadores reales; el firmware compila.

### Importantes (llega el dato, pero el portal falla o queda incompleto)

| # | Problema | Evidencia | Solución |
|---|---|---|---|
| A6 | **El portal solo funciona desde la máquina del stack.** Si se abre desde otro PC del laboratorio (`http://192.168.x.x:5173`), fallan el login y el tiempo real. | En `docker-compose.yml`, `CORS_ALLOWED_ORIGINS` y `SANCTUM_STATEFUL_DOMAINS` están fijos en `localhost`/`127.0.0.1` y no se pueden cambiar por env. `VITE_API_BASE_URL` y `VITE_PUSHER_HOST` apuntan a `localhost`/`127.0.0.1`. Reverb no recibe `REVERB_ALLOWED_ORIGINS`, así que usa el valor por defecto (localhost). | Volver configurables esas dos líneas (`${CORS_ALLOWED_ORIGINS:-...}`) y definir en `.env`: `LAN_IP`, `CORS_ALLOWED_ORIGINS`, `SANCTUM_STATEFUL_DOMAINS`, `REVERB_ALLOWED_ORIGINS`, `VITE_API_BASE_URL=http://<LAN_IP>:8000/api`, `VITE_PUSHER_HOST=<LAN_IP>`. Esfuerzo: S. |
| A7 | "Última comunicación" del dispositivo queda vacía para siempre. | `DeviceCommunicationReceived` tiene listener (`UpdateDeviceLastCommunication`) pero nadie dispara el evento. El portal lo muestra en `DeviceList.vue` y `DeviceDetailView.vue`. | Actualizar `last_communication` en `RawReadingNormalizer::normalize()` después de resolver el dispositivo (una línea con `saveQuietly`), o despachar el evento ahí. Esfuerzo: S. |
| A8 | La rama local está 14 commits detrás de `origin/refraccion`. Esos commits traen el límite de tasa de ingesta (`INGESTION_EVENTS_RATE_LIMIT_PER_MINUTE=120`), el canal de logs JSON con rotación y cambios en Reverb. | `git status` | `git pull --ff-only` antes del laboratorio. No hay conflicto: lo local son archivos nuevos sin commitear. |
| A9 | `front/.env` tiene `VITE_PUSHER_FORCE_TLS=true` y el host vacío. Con `npm run dev` fuera de Docker, Echo intenta conectarse a Pusher en la nube y el tiempo real no funciona. Con Docker no pasa, porque las variables del contenedor tienen prioridad. | `front/.env` | Copiar los valores de `front/.env.example` (http, 127.0.0.1, 8080), o usar siempre Docker. |
| A10 | No hay regla de alerta para DO. Las lecturas llegan, pero nunca disparan alertas. | `AlertRule` | Crear una regla desde el portal (por ejemplo, DO < 4 mg/L) cuando el químico defina umbrales. |

### Orden recomendado mañana

1. `git pull --ff-only`, y A6 si el portal se va a ver desde otros equipos.
2. `docker compose up -d` y `docker compose --profile workers up -d`.
3. `docker compose exec back php artisan migrate --force` y luego el seeder (A2).
4. Broker en la otra máquina (A1, A5), y flashear el ESP32 (A3, A4).
5. Verificar en este orden: `mosquitto_sub`, logs de `ingestion`, logs de `raw-consumer` y por último el portal.

---

## B. Almacenamiento: diagnóstico

### Qué se escribe por cada lectura de un sensor

| Destino | Qué guarda | Tamaño estimado | ¿Se borra alguna vez? |
|---|---|---|---|
| `raw_sensor_events` | Payload JSON completo + metadatos + 2 índices | ~0,9 KB | No |
| `raw_event_outboxes` | Fila de despacho | ~0,15 KB | No |
| `domain_event_outboxes` | Evento `sensor.reading.created` con datos denormalizados | ~0,8 KB | No |
| `reading_projections` | Vínculo de procedencia + 2 índices únicos | ~0,2 KB | No |
| `sensor_readings` | **El dato real** (sensor, valor, hora) + 2 índices | ~0,12 KB | No |
| Binlog MySQL (ROW, FULL) | Imagen antes/después de los inserts y updates anteriores | ~3–4 KB | Sí, a los 7 días |
| Redis streams (en RAM) | `iot.raw-events`, 2 streams CDC, `iot.domain-events` | ~0,5–1,5 KB por entrada en cada stream | Sí, por tope: 500.000 entradas por stream |
| `storage/logs/laravel.log` | ~20 líneas `Log::info` en el camino de cada lectura | ~5–6 KB | **No**: `LOG_STACK=single`, `LOG_LEVEL=debug`. Ya pesa 10,8 MB sin datos reales. |

**Conclusión:** solo ~5 % de lo que se guarda en MySQL por lectura es la medición.
El resto es infraestructura del pipeline (outbox, crudo, procedencia) que nunca se
purga: no existe scheduler, ni `Prunable`, ni comando de retención en el repo.
`audit.md` (pregunta 9) dejó la retención como decisión pendiente.

### Proyección para 1 sensor publicando cada 5 s (17.280 lecturas/día)

| Recurso | Por día | Por año | Tipo |
|---|---|---|---|
| Tablas MySQL | ~38 MB | ~14 GB | Disco, crece sin límite |
| `laravel.log` | ~100 MB | ~36 GB | Disco, crece sin límite |
| Binlog | ~65 MB | se estabiliza en ~0,5 GB | Disco, acotado |
| Redis streams | — | se estabiliza en ~1,5–2 GB | **RAM**, acotado pero alto |
| RAM de MySQL | — | ~400–600 MB fijos (buffer pool + performance_schema) | RAM |

Con 4 sensores (pH, temperatura, conductividad, DO), multiplicar por 4.

Lo que preocupa de verdad no es "MySQL ocupa mucho" en sí. Es que **nada se borra**,
que **los logs en debug crecen más rápido que los datos** y que **Redis retiene
hasta 500.000 entradas por stream en RAM**.

---

## C. Opciones evaluadas

| Opción | Qué es | Ahorro | Costo | Veredicto |
|---|---|---|---|---|
| **1. MySQL + retención + downsampling + tuning** | Purgar la infraestructura del pipeline ya procesada, bajar la cadencia, rotar logs, acotar Redis, ajustar la RAM de MySQL; más adelante, tablas agregadas por hora. | 90–99 % en disco; ~1,5 GB de RAM | S–M. Sin migrar motor, sin tocar Debezium ni las Gates certificadas. | **Recomendada** |
| 2. PostgreSQL + TimescaleDB | Hypertable para lecturas, compresión nativa (~90 %), políticas de retención y agregados continuos. | El mejor a largo plazo para series de tiempo | XL. Migrar el esquema, los triggers MySQL (`protect_users_is_admin_with_triggers`), Debezium a replicación lógica de Postgres y re-certificar las Gates 9 y 10. | Solo si el proyecto escala a muchos nodos o años de datos crudos |
| 3. TSDB aparte (InfluxDB / VictoriaMetrics) | Lecturas en la TSDB, lo relacional en MySQL. | Alto | L. Rompe la transacción lectura + evento de dominio (outbox), los observers de alertas y la idempotencia. Dos motores que operar. | Descartada |
| 4. Particionar `sensor_readings` por mes | `DROP PARTITION` en vez de `DELETE`. | Purgas más baratas | M. InnoDB no permite FK en tablas particionadas; hay que quitar las FK de `sensor_readings` y `reading_projections`. | Solo si los `DELETE` por lotes se vuelven lentos (>10 M filas) |

---

## D. Plan (opción 1)

### Fase 0: medir (en el laboratorio, 0 código)

Después de 24 h con el nodo enviando datos:

```sql
SELECT table_name, table_rows,
       ROUND((data_length + index_length)/1024/1024, 1) AS mb
FROM information_schema.tables
WHERE table_schema = 'iot_platform'
ORDER BY mb DESC;
```

```bash
docker compose exec redis redis-cli INFO memory | grep used_memory_human
docker compose exec redis redis-cli XLEN iot.raw-events
du -sh back/storage/logs
```

Con eso se reemplazan las estimaciones de la sección B por cifras reales.

### Fase 1: palancas sin código (antes o durante la instalación)

1. **Cadencia del firmware:** el oxígeno disuelto cambia en minutos, no en segundos.
   `PUBLISH_PERIOD_MS 60000UL` (1 min) divide todo por 12. Decidirlo con el químico.
2. **Logs:** `LOG_LEVEL=warning` y `LOG_STACK=daily` con `LOG_DAILY_DAYS=7` en
   `back/.env` (o `LOG_STACK=json` tras el pull, que ya rota a 14 días).
3. **Redis por antigüedad, no por cantidad:** `INGESTION_RAW_EVENTS_MIN_AGE_MS` y
   `DOMAIN_EVENTS_MIN_AGE_MS` ya existen (`config/app.php`) y pasan a usar
   `XADD MINID ~`. Ponerlos en 72 h (`259200000`) y bajar `CDC_STREAM_MAXLEN` a
   ~50.000. Restricción de `audit.md`: la ventana tiene que ser mayor que el
   tiempo máximo que un consumidor puede estar caído, o se pierden eventos sin
   procesar.
4. **RAM de MySQL** (flags en `command:` del servicio `db`):
   `--performance-schema=OFF`, `--innodb-buffer-pool-size=256M`,
   `--max-connections=50`. Ahorra ~200–400 MB de RAM. Si el proyecto necesita
   diagnóstico de queries, dejar `performance_schema` encendido.

### Fase 2: retención automática (esfuerzo S–M, 1 PR)

Laravel ya trae lo necesario: el trait `MassPrunable` más `model:prune` y el scheduler.

| Modelo | Condición de purga | Retención propuesta |
|---|---|---|
| `RawEventOutbox` | `status='published'` y `published_at` anterior al límite | 7 días |
| `DomainEventOutbox` | `delivered_at` no nulo y anterior al límite | 7 días |
| `RawSensorEvent` | `status='processed'` y anterior al límite (nunca `failed`/`received`: son la fuente de replay) | 30 días |
| `AuditLog` | `created_at` anterior al límite | 180 días (confirmar con ICONTEC/ISO; puede exigir más) |
| `sensor_readings` | No se purga en esta fase | — |

Cambios:
- `prunable()` en los 4 modelos, con días configurables por env.
- `Schedule::command('model:prune')->dailyAt('03:00')` en `routes/console.php`.
- Nuevo servicio `scheduler` en `docker-compose.yml` (perfil `workers`) con
  `php artisan schedule:work`.
- `raw_event_outboxes.raw_sensor_event_id` ya tiene `cascadeOnDelete` (verificado): al purgar
  un `RawSensorEvent` se va su fila de outbox. `reading_projections` usa `nullOnDelete`, así que
  la procedencia de la lectura sobrevive.
- Un test por modelo: crea filas viejas y nuevas, corre `model:prune` y comprueba
  que solo se borran las viejas procesadas.
- Gate 10: purgar filas del outbox ya publicadas es seguro para CDC. Debezium emite el `DELETE`
  y `CdcOutboxStreamConsumer` ya ignora updates y deletes (`CdcOutboxStreamConsumer.php:214`,
  verificado). Cada purga diaria genera un pico de binlog equivalente al tamaño borrado.

Resultado estimado (1 sensor cada 60 s): MySQL crece ~0,5 MB/día solo en lecturas,
y la infraestructura del pipeline queda fija en ~90 MB.

### Fase 3: downsampling (cuando haya meses de datos)

- Tabla `sensor_reading_rollups` (`sensor_id`, `bucket` por hora, `min`, `max`,
  `avg`, `count`; PK `sensor_id`+`bucket`). Una fila por hora en vez de 60–720.
- Job horario: `INSERT ... SELECT ... GROUP BY` con `ON DUPLICATE KEY UPDATE`.
- Los gráficos con rango mayor a 7 días leen de los rollups (`PublicGraphController`,
  `DashboardController`).
- Después de eso, se puede purgar `sensor_readings` crudo de más de 1 año si la
  normativa lo permite.

### Fase 4 (solo si escala): TimescaleDB

Disparadores: más de ~20 nodos, o requisito de conservar datos crudos por años
con consultas analíticas pesadas. Implica un proyecto propio de migración y la
re-certificación de las Gates.

---

## E. Resumen de esfuerzo

| Ítem | Esfuerzo | Cuándo |
|---|---|---|
| A1–A5 bloqueantes | Configuración | Mañana |
| A8 pull | 1 comando | Antes del laboratorio |
| A6 portal en LAN | S (compose + .env) | Antes del laboratorio si se ve desde otros PC |
| A7 última comunicación | S (1 línea + test) | Esta semana |
| Fase 1 (cadencia, logs, Redis, RAM MySQL) | Configuración | Esta semana |
| Fase 0 (medir) | 0 código | 24 h después de instalar |
| Fase 2 (retención) | S–M, 1 PR | Próximas 2 semanas |
| Fase 3 (rollups) | M | Cuando haya ~3 meses de datos |
