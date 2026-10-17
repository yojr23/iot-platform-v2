# lab_esp32 — Nodo ESP32 de Oxígeno Disuelto (DO) → iot-platform-v2

Todo lo necesario para conectar mañana el ESP32 + sonda de **Oxígeno Disuelto**
al pipeline de la plataforma.

## Flujo del dato

```
ESP32  --MQTT-->  Mosquitto  -->  ingestion_service  -->  POST /api/ingestion/events (Laravel)
 (publica          (broker)        (suscribe topic,        -->  raw:consume -> lectura normalizada
  JSON)                             valida, spool,               -> alertas + realtime (dashboard)
                                    reenvía al backend)
```

- **Topic:** `iot/<node_id>/readings` → aquí: `iot/lab_postgrado_nodo_01/readings`
- **node_id** debe ser IGUAL al `serial_number` del Device en el backend.
- **sensor_key** (`dissolved_oxygen`) debe existir como `external_key` en
  `device_sensor_mappings` con `source = ingestion_service`.

## Contenido de esta carpeta

```
lab_esp32/
├── firmware/do_sensor_node/
│   ├── do_sensor_node.ino   # sketch Arduino (WiFi + MQTT + DO + JSON)
│   └── config.h             # AJUSTAR: pin y calibración DO (secretos en lab_esp32/.env)
├── broker/
│   ├── docker-compose.broker.yml        # Mosquitto standalone (otra máquina)
│   └── mosquitto/config/mosquitto.conf  # config mínima (anónimo, QoS1)
└── README.md
```

---

## PASOS PARA MAÑANA (en orden)

### 0. Antes de salir / al llegar (5 min): arreglos de configuración encontrados en la prueba e2e
1. `git pull` de la rama con este trabajo en el PC que hace de servidor.
2. En el `.env` de la **raíz** (no está en git):
   - `VITE_PUSHER_PORT=8080` (hoy dice 6001; Reverb escucha en 8080 → sin esto no hay tiempo real).
   - `VITE_PUSHER_APP_KEY=iotlocalkey` (hoy dice `local-key`; Reverb responde 4001 "Application does not exist"),
     o borrar ambas líneas para usar los defaults del compose.
   - `MQTT_HOST=<IP del broker>` (o `host.docker.internal` si el broker corre en el mismo PC),
     `MQTT_USERNAME=ingestion`, `MQTT_PASSWORD=<la que imprime make-users.sh>`.
3. Liberar puertos: contenedores viejos `iotplatformv2-cert-*` ocupan 8000/8080
   (`docker ps -a | grep cert` → `docker stop`/`docker rm` esos). Sin esto `back`/`reverb` no arrancan.
4. Disco: `iot:capacity:check` marcó esta Mac al 97 % (≈6–8 GB libres). Si esta Mac es el servidor,
   liberar espacio antes de dejarla corriendo (p. ej. `docker builder prune` = ~3 GB de caché).
5. Para ver el gráfico **público** del sensor DO: activar "monitoreo público" del sensor en el portal
   (el seeder lo deja apagado a propósito: exponerlo es decisión tuya). Logueado se ve igual.

### 1. Levantar el broker MQTT (máquina que haga de broker)
```bash
cd lab_esp32/broker
# El broker no permite anónimos. Crea un usuario por nodo (= node_id) y el de ingesta;
# las claves se imprimen UNA vez: guárdalas (nodo -> NODE_MQTT_PASSWORD de lab_esp32/.env, ingestion -> MQTT_PASSWORD del .env raíz).
./make-users.sh lab_postgrado_nodo_01 ingestion
docker compose -f docker-compose.broker.yml up -d
docker compose -f docker-compose.broker.yml logs -f broker   # ver que arranca
```
Cada nodo solo puede publicar en `iot/<su node_id>/readings`; `ingestion` solo lee `iot/+/readings`
(ver `broker/mosquitto/config/acl`). El ingestion_service necesita `MQTT_USERNAME=ingestion` y
`MQTT_PASSWORD=<clave>`. `passwd` queda fuera de git. TLS no está activo (1883 plano en la LAN):
siguiente paso, listener 8883 con certificados. Para otro puerto en el host: `MQTT_HOST_PORT=...`.
Anota la **IP LAN** de esa máquina (`ipconfig` / `ip a`). El ESP32 la usa como `MQTT_HOST`.

### 2. Registrar el dispositivo y el sensor en el backend
```bash
docker compose up -d                                   # db, redis, back, reverb, front
docker compose exec back php artisan migrate --force   # crea también las tablas ARCHIVE
docker compose exec back php artisan db:seed --class="Database\Seeders\LabDoNodeSeeder"
```
Esto crea: Lab + Device `serial_number=lab_postgrado_nodo_01` + Sensor DO +
mapping `ingestion_service / dissolved_oxygen`. Es idempotente (re-ejecutable).

### 3. Configurar y arrancar el ingestion_service en modo MQTT
En el `.env` del repo (raíz) / variables del servicio `ingestion`:
```env
MQTT_HOST=<IP_DEL_BROKER>
MQTT_PORT=1883
MQTT_TOPIC=iot/+/readings
MQTT_USERNAME=ingestion
MQTT_PASSWORD=<clave de make-users.sh>
INGESTION_MODE=mqtt
INGESTION_SERVICE_TOKEN=<token-compartido-con-el-backend>
BACKEND_INGESTION_TOKEN=<mismo-token>
DEBEZIUM_DB_PASSWORD=<cualquier-password-para-cdc>
```
Levantar workers (ingestion, debezium, outbox-cdc-consumer, raw-consumer, domain-event-consumer, scheduler):
```bash
docker compose --profile workers up -d
docker compose logs -f ingestion raw-consumer
```

### 4. Flashear el ESP32
1. Arduino IDE → instalar soporte ESP32 y librerías **PubSubClient** y **ArduinoJson**.
2. Secretos: `cp .env.example .env` (en `lab_esp32/`, gitignored) y completar `WIFI_SSID`,
   `WIFI_PASSWORD`, `MQTT_HOST` (IP del broker, paso 1) y `NODE_MQTT_PASSWORD`. No van en `config.h`.
   En `firmware/do_sensor_node/config.h` solo:
   - `DO_ADC_PIN` = pin donde entra la señal del sensor
   - `DO_CAL_SLOPE` / `DO_CAL_OFFSET` = calibración real de tu sonda
3. Compilar y subir con `flash.sh` (genera `secrets.h` temporal desde `.env`; el IDE solo
   compila si ese archivo existe):
   ```bash
   arduino-cli board list                      # ver puerto, ej. /dev/cu.usbserial-0001
   ./flash.sh /dev/cu.usbserial-0001
   arduino-cli monitor -p /dev/cu.usbserial-0001 -c baudrate=115200
   ```
4. Monitor serie a 115200 baud: debe mostrar `[WiFi] OK`, `[MQTT] OK` y `[PUB] ... OK`.

   **Búfer offline:** si WiFi/MQTT caen, el firmware guarda hasta `OFFLINE_BUFFER_SIZE`
   lecturas (config.h, default 360 = 30 min a 5 s) con su índice y hora NTP, y las
   envía en orden al reconectar. Si se llena descarta las más viejas y lo reporta en
   `session.dropped`. Lecturas sin hora NTP válida no se encolan. Es RAM: un reinicio o
   corte de energía pierde lo pendiente.

### 5. Preflight (antes de verificar)
```bash
lab_esp32/preflight.sh          # PASS/FAIL por chequeo; exit != 0 si falla algo
lab_esp32/preflight.sh --e2e    # ademas publica 1 lectura sintetica y espera la fila (<= 60 s)
```
Revisa servicios (db, redis, back, reverb, front y workers), variables obligatorias del `.env`
(sin imprimirlas), `/api/health`, broker (`mosquitto_pub`; usa `MQTT_USERNAME`/`MQTT_PASSWORD`
si estan definidas, `PREFLIGHT_MQTT_HOST` si el broker se ve distinto desde este PC) y el
dispositivo `lab_postgrado_nodo_01` con su mapping. `COMPOSE_PROJECT=<nombre>` apunta a otro stack.

### 6. Verificar end-to-end
```bash
# a) ¿Llega al broker?  (desde la máquina del broker)
docker compose -f docker-compose.broker.yml exec broker \
  mosquitto_sub -t 'iot/+/readings' -v

# b) ¿El ingestion_service lo reenvía?  (logs sin errores de validación)
docker compose logs -f ingestion

# c) ¿El backend lo normaliza?  (status processed)
docker compose logs -f raw-consumer | grep RawReadingNormalizer

# d) Dashboard: la lectura de DO del nodo debe aparecer en el front (5173).
```

---

## Portal desde otros PCs del lab (LAN)

Por defecto el portal solo acepta `localhost`. En el `.env` de la raiz define (con la IP LAN de
este servidor, ej. `192.168.1.20`) y reinicia `docker compose up -d back reverb front`:

```
CORS_ALLOWED_ORIGINS=http://localhost:5173,http://192.168.1.20:5173
SANCTUM_STATEFUL_DOMAINS=localhost:5173,localhost:8000,192.168.1.20:5173,192.168.1.20:8000
REVERB_ALLOWED_ORIGINS=localhost,127.0.0.1,192.168.1.20
VITE_API_BASE_URL=http://192.168.1.20:8000/api
VITE_PUSHER_HOST=192.168.1.20
```
Abrir despues `http://192.168.1.20:5173` desde el otro PC (puertos 5173, 8000 y 8080 libres en el
firewall). `VITE_PUSHER_PORT` debe ser `8080` (puerto publicado de Reverb), `VITE_PUSHER_SCHEME=http`
y `VITE_PUSHER_FORCE_TLS=false`.

---

## JSON que publica el ESP32 (referencia)

```json
{
  "device": { "node_id": "lab_postgrado_nodo_01", "firmware_version": "1.0.0", "location": "..." },
  "timestamp": "2026-10-17T09:00:00Z",
  "session": { "boot_count": 3, "reading_index": 147, "uptime_ms": 473821 },
  "network": { "wifi_rssi_dbm": -67 },
  "sensors": {
    "dissolved_oxygen": { "value": 8.21, "unit": "mg/L", "sensor_model": "Atlas Scientific DO", "status": "ok" }
  },
  "qc": { "valid": true }
}
```

**Identidad anti-duplicado:** el firmware envía `session.boot_count` +
`session.reading_index`, que junto a `node_id` forman la identidad estable que
exige el backend (`normalizer.py`). No hace falta generar `event_id`.

## Problemas típicos

| Síntoma | Causa probable |
|---|---|
| `raw-consumer`: "no device found for node_id" | No corriste el paso 2, o `NODE_ID` ≠ `serial_number`. |
| `raw-consumer`: "no sensor mapped for payload key" | `SENSOR_KEY` ≠ `external_key`, o `source` ≠ `ingestion_service`. |
| ESP32 no conecta MQTT (`rc=-2`) | `MQTT_HOST` mal / broker caído / firewall al puerto 1883. |
| Lectura archivada pero sin dato | `qc.valid` llegó en `false`. |
| Nada en el dashboard pero sí en DB | Faltan workers de realtime (reverb / domain-event-consumer). |
