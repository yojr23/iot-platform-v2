# HANDOFF — Nodo ESP32 DO (iot-platform-v2)

> **Actualizado 2026-10-17 (autopilot nocturno terminado).** Leer primero
> `docs/superpowers/plans/autopilot-evidence/REPORTE-FINAL.md` y el **paso 0** de `lab_esp32/README.md`.
> Rama `autopilot/lab-readiness-2026-10-17` (local, sin push). E2E simulado 9/9 PASS.

---
Historial previo:


Fecha: 2026-10-16. Sesión previa: Opus 4.8.

## Por qué el handoff
- Binario `claude` actualizado a 2.1.292 (Homebrew), pero la sesión en curso
  seguía corriendo 2.1.223 en memoria. `/model claude-opus-5-5` fallaba:
  `claude_code_version_too_old`.
- Fix: salir (`/exit` o Ctrl+D), reabrir `claude`, luego `/model claude-opus-5-5`.

## Estado del trabajo (COMPLETO, en disco)
Objetivo: dejar listo ESP32 + sonda Oxígeno Disuelto (DO) para instalación/conexión mañana en el lab.

Pipeline verificado contra código: ESP32 -MQTT- Mosquitto - ingestion_service - POST /api/ingestion/events - raw:consume - lectura normalizada - realtime.

Archivos creados:
- `lab_esp32/firmware/do_sensor_node/do_sensor_node.ino` — sketch WiFi+MQTT+DO+JSON, topic `iot/<node_id>/readings`, QoS1, envía boot_count+reading_index (identidad anti-duplicado).
- `lab_esp32/firmware/do_sensor_node/config.h` — WiFi, IP broker, pin ADC, calibración DO, node_id.
- `lab_esp32/broker/docker-compose.broker.yml` + `mosquitto/config/mosquitto.conf` — broker standalone otra máquina.
- `back/database/seeders/LabDoNodeSeeder.php` — idempotente. Crea Lab + Device `serial_number=lab_postgrado_nodo_01` + Sensor DO + mapping `ingestion_service`/`dissolved_oxygen`.
- `lab_esp32/README.md` — guía 5 pasos + verificación end-to-end + fallos típicos.

Contrato confirmado en código: device por `serial_number==node_id`; sensor_key minúsculas == `external_key`; source `ingestion_service`. SensorType "Oxigeno Disuelto" ya existía.

## Verificado (sesión Opus 5.5, 2026-10-16)
- Firmware compila: `arduino-cli compile --fqbn esp32:esp32:esp32` (core 3.3.8, ArduinoJson 7.4.3, PubSubClient). 72% flash.
- Payload del firmware pasa `validate_payload` + `derive_source_event_id` reales -> `mqtt:sequence:lab_postgrado_nodo_01:<boot>:<idx>`. Tests ingestion_service: 49 passed.
- Seeder: `php -l` OK; columnas/fillable de Lab, Device, Sensor, DeviceSensorMapping coinciden con migraciones.
- Fix: `boot_count` pasó de RTC memory a NVS (`Preferences`). Antes se reiniciaba a 1 al cortar energía -> identidad repetida -> backend descartaba lecturas como duplicadas.
- Fix: ADC usa `analogReadMilliVolts` (calibración de fábrica) en vez de `analogRead` lineal.
- No probado: seeder contra DB real, broker corriendo, ESP32 físico.

Nada commiteado. Rama `refraccion`.

## Pendiente (decidir mañana en lab)
1. IP real broker - `MQTT_HOST` en config.h.
2. Calibración real sonda - `DO_CAL_SLOPE`/`DO_CAL_OFFSET`.
3. Hardware exacto: .ino lee ADC analógico. Si sonda es Atlas EZO-DO (I2C/UART),
   cambiar SOLO `readDissolvedOxygen()`. Pregunta abierta sin responder: ¿analógica o EZO digital?

## Primer paso sesión nueva
Leer `lab_esp32/README.md`. Si confirman tipo de sensor, ajustar `readDissolvedOxygen()`.
