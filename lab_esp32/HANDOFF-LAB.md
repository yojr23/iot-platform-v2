# HANDOFF: pruebas en el laboratorio (sonda DO → ESP32 → router → plataforma → portal)

Para la sesión de Claude que hace las pruebas físicas. Escrito el 2026-10-17 en el laboratorio.

## 1. Contexto en 30 segundos

- Repositorio: `/Users/j.rinconc/Desktop/ing sistemas/Sistemas Unab /iot-platform-v2`. **Ojo:** "Sistemas Unab " lleva espacio al final; siempre entre comillas.
- Rama: `autopilot/lab-readiness-2026-10-17` (16 commits sobre `refraccion`, **solo local, sin push**).
- Todo el trabajo previo está documentado en `docs/superpowers/plans/autopilot-evidence/REPORTE-FINAL.md`.
  Detalle por fase: `F0`…`F7` en esa misma carpeta. Plan: `docs/superpowers/plans/2026-10-17-autopilot-lab-readiness.md`.
- Ya probado con un nodo **simulado** en Docker aislado: E2E 9/9 PASS. Tests: backend 516 passed / 52 skipped / 0 failed, ingesta 53, front 6/6.
- **No probado:** ESP32 físico, sonda real, red del laboratorio.

## 2. Estado real ahora mismo (medido en el laboratorio)

| Dato | Valor |
|---|---|
| WiFi | SSID `E-bio` |
| IP de esta Mac en la LAN | `172.16.122.235` (gateway `172.16.122.1`) |
| Docker | corriendo |
| Disco de la Mac | **98 % usado, ~5,7 GB libres** (crítico) |
| Contenedores viejos `iotplatformv2-cert-*` | en loop de reinicio; `iotplatformv2-cert-reverb-1` ocupa el puerto 8080 |
| `.env` raíz `VITE_PUSHER_PORT` | `6001` → **incorrecto** (tiene que ser 8080) |
| `.env` raíz `VITE_PUSHER_APP_KEY` | `local-key` → **incorrecto** (Reverb responde 4001; usar `iotlocalkey`) |
| `.env` raíz `MQTT_HOST` | `host.docker.internal` → correcto si el broker corre en esta Mac |
| `.env` raíz `MQTT_USERNAME`/`MQTT_PASSWORD` | **faltan** |

Supuesto de topología para la prueba: broker, plataforma y portal corren **en esta Mac**. El ESP32
se conecta al WiFi `E-bio` y publica a `172.16.122.235:1883`.

## 3. Arquitectura (lo que se va a probar)

```
Sonda DO ─(ADC GPIO34)→ ESP32 ─WiFi E-bio→ Mosquitto :1883 (usuario+ACL, en la Mac)
 → ingestion (Python, spool) ─HTTP token→ back :8000 /api/ingestion/events
 → MySQL raw_sensor_events + outbox ─binlog→ debezium → Redis → outbox-cdc-consumer
 → raw-consumer (RawReadingNormalizer) → sensor_readings → domain-event-consumer
 → reverb :8080 (WebSocket) → front :5173 (portal)
 + scheduler (tiering, logs, capacity, stream trim)
```

Identidad del nodo: `NODE_ID = lab_postgrado_nodo_01` = `devices.serial_number` = usuario MQTT.
Clave del sensor `dissolved_oxygen` = `device_sensor_mappings.external_key`, source `ingestion_service`.
Topic: `iot/lab_postgrado_nodo_01/readings`.

## 4. Pasos para la prueba (en orden)

### 4.0 Preparar la Mac (5 min)
1. Disco: liberar espacio antes de levantar el stack. `docker builder prune` (~3 GB de caché de
   build) es lo más seguro. **Pedir confirmación al usuario antes de borrar cualquier cosa.**
2. Detener los contenedores viejos que ocupan puertos (confirmar con el usuario):
   `docker ps -a --format '{{.Names}}' | grep '^iotplatformv2-cert-' | xargs docker stop`
3. Editar el `.env` de la raíz (el usuario lo autorizó para estos valores; confirmar):
   `VITE_PUSHER_PORT=8080`, `VITE_PUSHER_APP_KEY=iotlocalkey`, `MQTT_USERNAME=ingestion`,
   `MQTT_PASSWORD=<del paso 4.1>`. Dejar `MQTT_HOST=host.docker.internal`.
4. Firewall de macOS: permitir conexiones entrantes a Docker en los puertos 1883, 8000, 5173 y 8080
   (Ajustes → Red → Firewall). Si no, el ESP32 y los otros PCs no llegan.

### 4.1 Broker
```bash
cd "/Users/j.rinconc/Desktop/ing sistemas/Sistemas Unab /iot-platform-v2/lab_esp32/broker"
./make-users.sh lab_postgrado_nodo_01 ingestion   # imprime las claves UNA vez: anotarlas
docker compose -f docker-compose.broker.yml up -d
```
La clave del nodo va a `config.h` y la de `ingestion` va a `MQTT_PASSWORD` del `.env` raíz.

### 4.2 Plataforma
```bash
cd "/Users/j.rinconc/Desktop/ing sistemas/Sistemas Unab /iot-platform-v2"
docker compose up -d
docker compose --profile workers up -d
docker compose exec back php artisan migrate --force
docker compose exec back php artisan db:seed --class="Database\Seeders\LabDoNodeSeeder"
PREFLIGHT_MQTT_HOST=localhost MQTT_USERNAME=lab_postgrado_nodo_01 MQTT_PASSWORD=<clave_nodo> lab_esp32/preflight.sh --e2e
```
Todo tiene que dar PASS antes de tocar el ESP32. Con poca RAM en Docker (4 GB), levantar por etapas.

### 4.3 Firmware (`lab_esp32/firmware/do_sensor_node/config.h`)
- `WIFI_SSID "E-bio"`, `WIFI_PASSWORD "<clave>"`.
- `MQTT_HOST "172.16.122.235"` (volver a confirmar con `ipconfig getifaddr en0`: puede cambiar por DHCP).
- `MQTT_USERNAME NODE_ID`, `MQTT_PASSWORD "<clave_nodo>"`.
- `DO_ADC_PIN 34` (ADC1; no usar pines ADC2 con WiFi). Calibración: `DO_CAL_SLOPE`, `DO_CAL_OFFSET`.
- `PUBLISH_PERIOD_MS` en 5000 para la prueba; para producción, 60000 (decidirlo con el químico).

Flashear (arduino-cli ya instalado, con el core esp32 3.3.8 y las librerías):
```bash
arduino-cli board list
arduino-cli compile --upload -p /dev/cu.usbserial-XXXX --fqbn esp32:esp32:esp32 lab_esp32/firmware/do_sensor_node
arduino-cli monitor -p /dev/cu.usbserial-XXXX -c baudrate=115200
```
En el monitor serie tienen que aparecer `[WiFi] OK IP=172.16.122.x`, `[NTP] Hora sincronizada`, `[MQTT] ... OK` y `[PUB] idx=N ... OK`.

### 4.4 Verificar de punta a punta
```bash
mosquitto_sub -h localhost -u ingestion -P <clave_ingestion> -t 'iot/+/readings' -v   # llega al broker
docker compose logs -f ingestion          # "Backend delivery completed"
docker compose logs -f raw-consumer       # RawReadingNormalizer completed, created=1
docker compose exec db sh -c 'mysql -uroot -p"$MYSQL_ROOT_PASSWORD" "$MYSQL_DATABASE" -e "SELECT id,sensor_id,value,reading_time FROM sensor_readings ORDER BY id DESC LIMIT 5"'
```
Portal: `http://localhost:5173`. Desde otro PC de la LAN hay que configurar las variables LAN (README,
sección "Portal desde otros PCs"): `CORS_ALLOWED_ORIGINS`, `SANCTUM_STATEFUL_DOMAINS`,
`REVERB_ALLOWED_ORIGINS`, `VITE_API_BASE_URL=http://172.16.122.235:8000/api`, `VITE_PUSHER_HOST=172.16.122.235`.
Después: `docker compose up -d`. El gráfico público necesita activar `public_monitoring_enabled` en el sensor (decisión del usuario).

## 5. Problemas probables en el laboratorio y qué hacer

| Síntoma | Causa probable | Acción |
|---|---|---|
| ESP32 no conecta a `E-bio` | El ESP32 solo usa **2,4 GHz**, o la red es WPA2-Enterprise / tiene portal cautivo | Usar la banda de 2,4 GHz del router o un SSID WPA2-PSK; `WiFi.begin` no soporta Enterprise |
| WiFi OK pero `[MQTT] FALLO rc=-2` | Firewall de la Mac, IP equivocada o el router aísla a los clientes (AP isolation) | `ping 172.16.122.235` desde otro equipo; desactivar el aislamiento de clientes en el router; revisar el firewall |
| `rc=5` | Usuario o clave MQTT incorrectos | Revisar `config.h` y volver a correr `make-users.sh` |
| Publica OK pero no llega nada | Topic ≠ `iot/<NODE_ID>/readings` o `node_id` del payload distinto (cuarentena) | Logs de `ingestion` |
| `no device found for node_id` | Faltó el seeder | Paso 4.2 |
| `[NTP] Sin hora` | El router bloquea NTP | Las lecturas se publican sin `timestamp` (el backend usa la hora de llegada); sin hora real no se usa el búfer offline |
| Valores absurdos | Calibración placeholder (`DO_CAL_SLOPE 4.0`) o sonda EZO (I2C), no analógica | Calibrar a 2 puntos; si es EZO, reescribir `readDissolvedOxygen()` |
| Portal sin tiempo real | `VITE_PUSHER_PORT`/`VITE_PUSHER_APP_KEY` sin corregir, u origen no permitido en Reverb | Paso 4.0.3 y variables LAN |
| `back`/`reverb` no arrancan | Puertos tomados por `iotplatformv2-cert-*` | Paso 4.0.2 |
| `iot:capacity:check` CRITICAL | Disco de la Mac al 98 % | Liberar espacio (paso 4.0.1) |

## 6. Reglas para la nueva sesión

- No usar `migrate:fresh`/`refresh` ni `docker compose down -v` sobre el proyecto por defecto: borran la BD.
- No hacer push ni cambiar de rama sin pedirlo. El trabajo está en la rama local `autopilot/lab-readiness-2026-10-17`.
- Pedir confirmación antes de borrar contenedores, imágenes o caché, o de editar el `.env`.
- Herramientas disponibles en la Mac: `arduino-cli`, `mosquitto_pub`/`mosquitto_sub`, Docker, PHP, Python
  (venv de ingesta en `ingestion_service/.venv` o crear uno con `requirements.txt`).
- Si algo falla: causa raíz y arreglo mínimo, con un commit por arreglo en esta rama, y anotarlo en
  `docs/superpowers/plans/autopilot-evidence/LAB-DAY.md`.

## 7. Archivos clave

| Qué | Dónde |
|---|---|
| Firmware | `lab_esp32/firmware/do_sensor_node/{do_sensor_node.ino,config.h,ring_buffer.h}` |
| Broker | `lab_esp32/broker/{docker-compose.broker.yml,make-users.sh,mosquitto/config/{mosquitto.conf,acl}}` |
| Preflight | `lab_esp32/preflight.sh` (`--e2e`, env `COMPOSE_PROJECT`, `PREFLIGHT_MQTT_HOST`, `MQTT_USERNAME/PASSWORD`) |
| Seeder | `back/database/seeders/LabDoNodeSeeder.php` |
| Normalizador | `back/app/Services/Ingestion/RawReadingNormalizer.php` |
| Ingesta Python | `ingestion_service/app/` (`replay_dead_letters.py` para reenviar dead letters) |
| Comandos ops | `iot:storage:tier`, `iot:logs:archive`, `iot:capacity:check`, `iot:cdc:trim` (scheduler) |
| Guía | `lab_esp32/README.md` |
