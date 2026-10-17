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
│   └── config.h             # AJUSTAR: WiFi, IP del broker, calibración DO
├── broker/
│   ├── docker-compose.broker.yml        # Mosquitto standalone (otra máquina)
│   └── mosquitto/config/mosquitto.conf  # config mínima (anónimo, QoS1)
└── README.md
```

---

## PASOS PARA MAÑANA (en orden)

### 1. Levantar el broker MQTT (máquina que haga de broker)
```bash
cd lab_esp32/broker
docker compose -f docker-compose.broker.yml up -d
docker compose -f docker-compose.broker.yml logs -f broker   # ver que arranca
```
Anota la **IP LAN** de esa máquina (`ipconfig` / `ip a`). El ESP32 la usa como `MQTT_HOST`.

### 2. Registrar el dispositivo y el sensor en el backend
Con el stack `back` corriendo (o dentro del contenedor):
```bash
cd back
php artisan migrate --force          # si aún no está migrado
php artisan db:seed --class="Database\Seeders\LabDoNodeSeeder"
```
Esto crea: Lab + Device `serial_number=lab_postgrado_nodo_01` + Sensor DO +
mapping `ingestion_service / dissolved_oxygen`. Es idempotente (re-ejecutable).

### 3. Configurar y arrancar el ingestion_service en modo MQTT
En el `.env` del repo (raíz) / variables del servicio `ingestion`:
```env
MQTT_HOST=<IP_DEL_BROKER>
MQTT_PORT=1883
MQTT_TOPIC=iot/+/readings
INGESTION_MODE=mqtt
INGESTION_SERVICE_TOKEN=<token-compartido-con-el-backend>
BACKEND_INGESTION_TOKEN=<mismo-token>
DEBEZIUM_DB_PASSWORD=<cualquier-password-para-cdc>
```
Levantar workers (incluye ingestion, raw-consumer, cdc, reverb):
```bash
docker compose --profile workers up -d
docker compose logs -f ingestion raw-consumer
```

### 4. Flashear el ESP32
1. Arduino IDE → instalar soporte ESP32 y librerías **PubSubClient** y **ArduinoJson**.
2. Editar `firmware/do_sensor_node/config.h`:
   - `WIFI_SSID` / `WIFI_PASSWORD`
   - `MQTT_HOST` = IP del broker (paso 1)
   - `DO_ADC_PIN` = pin donde entra la señal del sensor
   - `DO_CAL_SLOPE` / `DO_CAL_OFFSET` = calibración real de tu sonda
3. Abrir `do_sensor_node.ino`, seleccionar placa "ESP32 Dev Module", subir.
   Sin IDE (arduino-cli ya instalado en esta Mac, librerías incluidas):
   ```bash
   arduino-cli board list                      # ver puerto, ej. /dev/cu.usbserial-0001
   arduino-cli compile --upload -p /dev/cu.usbserial-0001 --fqbn esp32:esp32:esp32 firmware/do_sensor_node
   arduino-cli monitor -p /dev/cu.usbserial-0001 -c baudrate=115200
   ```
4. Monitor serie a 115200 baud: debe mostrar `[WiFi] OK`, `[MQTT] OK` y `[PUB] ... OK`.

### 5. Verificar end-to-end
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
