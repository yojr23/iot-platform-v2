// =====================================================================
// config.h  -  Ajusta TODO esto en el laboratorio.
// Nada de este archivo es secreto del backend; son datos de red local.
// =====================================================================
#pragma once

// ---------- WiFi ----------
#define WIFI_SSID        "CAMBIAR_SSID"
#define WIFI_PASSWORD    "CAMBIAR_PASSWORD"

// ---------- MQTT broker ----------
// IP del equipo donde corre Mosquitto (mañana en el lab).
// Ej: "192.168.1.50". NO uses "localhost": el ESP32 no es el broker.
#define MQTT_HOST        "192.168.1.50"
#define MQTT_PORT        1883
#define MQTT_USERNAME    ""          // vacío si el broker permite anónimo
#define MQTT_PASSWORD    ""

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
