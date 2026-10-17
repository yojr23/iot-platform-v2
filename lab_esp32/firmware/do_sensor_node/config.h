// =====================================================================
// config.h  -  Ajusta TODO esto en el laboratorio.
// Sin secretos: WIFI_SSID, WIFI_PASSWORD, MQTT_HOST y MQTT_PASSWORD vienen de
// lab_esp32/.env via secrets.h, que genera lab_esp32/flash.sh (gitignored).
// =====================================================================
#pragma once

#if __has_include("secrets.h")
#include "secrets.h"
#else
#error "Falta secrets.h: compila con lab_esp32/flash.sh (lee lab_esp32/.env)"
#endif

// ---------- WiFi ----------
// WIFI_SSID, WIFI_PASSWORD: en lab_esp32/.env

// ---------- MQTT broker ----------
// IP del equipo donde corre Mosquitto (mañana en el lab).
// Ej: "192.168.1.50". NO uses "localhost": el ESP32 no es el broker.
// MQTT_HOST: en lab_esp32/.env
#define MQTT_PORT        1883
// El broker NO permite anónimos: usuario = NODE_ID, clave generada con
// lab_esp32/broker/make-users.sh <node_id> (se imprime una sola vez).
#define MQTT_USERNAME    NODE_ID
// MQTT_PASSWORD (clave del nodo): en lab_esp32/.env

// ---------- Identidad del nodo ----------
// node_id DEBE coincidir con el serial_number del Device en el backend.
// (RawReadingNormalizer busca Device::where('serial_number', node_id).)
#define NODE_ID          "lab_postgrado_nodo_01"
#define FIRMWARE_VERSION "1.0.0"
#define NODE_LOCATION    "Laboratorio Posgrado Quimica - UNAB"

// Topic = iot/<node_id>/readings  (el patrón del ingestion_service es iot/+/readings)
// Se arma solo en el .ino a partir de NODE_ID.

// ---------- Sensor: Oxígeno Disuelto (DO) ----------
// La clave del sensor en el JSON. DEBE existir como external_key en
// device_sensor_mappings (source='ingestion_service'), en minúsculas.
#define SENSOR_KEY       "dissolved_oxygen"
#define SENSOR_UNIT      "mg/L"
#define SENSOR_MODEL     "Atlas Scientific DO"   // ajusta al modelo real

// Pin analógico donde entra la señal del DO (sonda analógica).
// En ESP32 usa un pin ADC1 (GPIO32-39) para que funcione con WiFi activo.
#define DO_ADC_PIN       34

// ---------- Calibración DO (lineal simple: volts -> mg/L) ----------
// Reemplaza con tu calibración real (2 puntos). Por defecto: identidad aprox.
// mg/L = DO_CAL_SLOPE * voltaje + DO_CAL_OFFSET
#define DO_CAL_SLOPE     4.0f    // mg/L por voltio  (PLACEHOLDER - calibrar)
#define DO_CAL_OFFSET    0.0f    // mg/L             (PLACEHOLDER - calibrar)

// ---------- Cadencia ----------
#define PUBLISH_PERIOD_MS 5000UL  // cada cuánto publica una lectura

// ---------- Resiliencia offline ----------
// Lecturas guardadas en RAM cuando WiFi/MQTT estan caidos (360 = 30 min a 5 s, 6 h a 60 s).
// ~16 bytes por lectura. Si se llena se descarta la mas vieja (session.dropped).
#define OFFLINE_BUFFER_SIZE     360
#define OFFLINE_FLUSH_PER_LOOP  10      // lecturas vaciadas por iteracion del loop
#define WIFI_RETRY_MS           30000UL // reintento de WiFi (no bloqueante)
#define MQTT_RETRY_MS           5000UL  // reintento de MQTT (no bloqueante)
