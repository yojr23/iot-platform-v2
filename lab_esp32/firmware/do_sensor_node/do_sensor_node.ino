// =====================================================================
// do_sensor_node.ino
// ESP32 -> MQTT -> ingestion_service -> backend Laravel (iot-platform-v2)
//
// Publica lecturas de Oxígeno Disuelto (DO) en el topic
//   iot/<NODE_ID>/readings
// con el JSON exacto que espera el ingestion_service (ver schemas.py /
// validators.py / normalizer.py del repo).
//
// Identidad anti-duplicado: el backend necesita un event_id, O BIEN el
// trío node_id + boot_count + reading_index. Este firmware envía
// session.boot_count y session.reading_index, así que queda cubierto
// sin necesidad de generar un event_id.
//
// Dependencias (Library Manager de Arduino IDE):
//   - PubSubClient  (Nick O'Leary)
//   - ArduinoJson   (Benoit Blanchon, v6 o v7)
// Core: esp32 by Espressif >= 2.0 (verificado compilando con 3.3.8).
//   WiFi.h viene con el core ESP32.
//
// Placa: cualquier "ESP32 Dev Module".
// =====================================================================

#include <WiFi.h>
#include <PubSubClient.h>
#include <ArduinoJson.h>
#include <Preferences.h>
#include <time.h>

#include "config.h"

WiFiClient    netClient;
PubSubClient  mqtt(netClient);

// boot_count: cuántas veces arrancó el nodo. Se guarda en NVS (flash) para que
// sobreviva a cortes de energía. Si se reiniciara a 1, el par boot_count +
// reading_index se repetiría y el backend descartaría las lecturas como duplicadas.
uint32_t bootCount = 0;

uint32_t readingIndex = 0;          // se reinicia cada arranque; junto a boot_count da identidad única
char     topic[96];                 // iot/<node_id>/readings
unsigned long lastPublish = 0;

// ---------------------------------------------------------------------
// WiFi
// ---------------------------------------------------------------------
void connectWifi() {
  if (WiFi.status() == WL_CONNECTED) return;

  Serial.printf("[WiFi] Conectando a %s", WIFI_SSID);
  WiFi.mode(WIFI_STA);
  WiFi.begin(WIFI_SSID, WIFI_PASSWORD);

  unsigned long start = millis();
  while (WiFi.status() != WL_CONNECTED && millis() - start < 20000) {
    delay(500);
    Serial.print(".");
  }

  if (WiFi.status() == WL_CONNECTED) {
    Serial.printf("\n[WiFi] OK  IP=%s  RSSI=%d dBm\n",
                  WiFi.localIP().toString().c_str(), WiFi.RSSI());
  } else {
    Serial.println("\n[WiFi] FALLO (se reintenta en loop)");
  }
}

// ---------------------------------------------------------------------
// Hora UTC vía NTP -> timestamp ISO-8601 con 'Z'
// El backend acepta payload.timestamp como reading_time.
// Si no hay NTP, igual publicamos y el ingestion_service pone received_at.
// ---------------------------------------------------------------------
void syncTime() {
  configTime(0, 0, "pool.ntp.org", "time.google.com");  // UTC
  struct tm t;
  if (getLocalTime(&t, 8000)) {
    Serial.println("[NTP] Hora sincronizada (UTC).");
  } else {
    Serial.println("[NTP] Sin hora; se omite timestamp en el payload.");
  }
}

// Devuelve true si logró formatear la hora actual como ISO-8601 UTC.
bool nowIso8601(char *buf, size_t len) {
  struct tm t;
  if (!getLocalTime(&t, 0)) return false;
  // 2026-10-17T09:00:00Z
  strftime(buf, len, "%Y-%m-%dT%H:%M:%SZ", &t);
  return true;
}

// ---------------------------------------------------------------------
// MQTT
// ---------------------------------------------------------------------
void connectMqtt() {
  mqtt.setServer(MQTT_HOST, MQTT_PORT);
  mqtt.setBufferSize(512);  // el JSON ronda ~400 bytes; el default (256) no alcanza

  while (!mqtt.connected()) {
    connectWifi();
    String clientId = String("esp32-") + NODE_ID;
    Serial.printf("[MQTT] Conectando a %s:%d ...", MQTT_HOST, MQTT_PORT);

    bool ok;
    if (strlen(MQTT_USERNAME) > 0) {
      ok = mqtt.connect(clientId.c_str(), MQTT_USERNAME, MQTT_PASSWORD);
    } else {
      ok = mqtt.connect(clientId.c_str());
    }

    if (ok) {
      Serial.println(" OK");
    } else {
      Serial.printf(" FALLO rc=%d. Reintento en 3s\n", mqtt.state());
      delay(3000);
    }
  }
}

// ---------------------------------------------------------------------
// Lectura del sensor DO
// Promedia varias muestras del ADC y aplica calibración lineal.
// AJUSTA la calibración en config.h con tu sonda real.
// ---------------------------------------------------------------------
float readDissolvedOxygen() {
  const int N = 16;
  uint32_t acc = 0;
  for (int i = 0; i < N; i++) {
    acc += analogReadMilliVolts(DO_ADC_PIN);  // usa la calibración de fábrica del ADC
    delay(5);
  }
  float voltage = (acc / (float)N) / 1000.0f;
  float mgL     = DO_CAL_SLOPE * voltage + DO_CAL_OFFSET;
  if (mgL < 0) mgL = 0;
  return mgL;
}

// ---------------------------------------------------------------------
// Construye el JSON y publica
// ---------------------------------------------------------------------
void publishReading() {
  float doValue = readDissolvedOxygen();
  readingIndex++;

  // Documento JSON (ArduinoJson v6)
  StaticJsonDocument<512> doc;

  JsonObject device = doc.createNestedObject("device");
  device["node_id"]          = NODE_ID;
  device["firmware_version"] = FIRMWARE_VERSION;
  device["location"]         = NODE_LOCATION;

  char ts[25];
  if (nowIso8601(ts, sizeof(ts))) {
    doc["timestamp"] = ts;   // opcional; si no hay NTP se omite
  }

  JsonObject session = doc.createNestedObject("session");
  session["boot_count"]    = bootCount;     // parte de la identidad anti-duplicado
  session["reading_index"] = readingIndex;  // "
  session["uptime_ms"]     = millis();

  JsonObject network = doc.createNestedObject("network");
  network["wifi_rssi_dbm"] = WiFi.RSSI();

  JsonObject sensors = doc.createNestedObject("sensors");
  JsonObject s = sensors.createNestedObject(SENSOR_KEY);  // "dissolved_oxygen"
  s["value"]        = roundf(doValue * 100) / 100.0f;     // 2 decimales
  s["unit"]         = SENSOR_UNIT;
  s["sensor_model"] = SENSOR_MODEL;
  s["status"]       = "ok";

  JsonObject qc = doc.createNestedObject("qc");
  qc["valid"] = true;   // si pones false, el backend archiva pero NO crea lectura

  char buf[512];
  size_t n = serializeJson(doc, buf, sizeof(buf));

  // PubSubClient solo publica con QoS 0; el ingestion_service se suscribe con QoS 1 igual.
  bool ok = mqtt.publish(topic, (const uint8_t *)buf, n, false);  // retain=false
  Serial.printf("[PUB] %s -> %s  (%u bytes) %s\n",
                topic, buf, (unsigned)n, ok ? "OK" : "FALLO");
}

// ---------------------------------------------------------------------
void setup() {
  Serial.begin(115200);
  delay(300);
  Preferences prefs;
  prefs.begin("node", false);
  bootCount = prefs.getUInt("boot_count", 0) + 1;
  prefs.putUInt("boot_count", bootCount);
  prefs.end();
  Serial.printf("\n=== DO Sensor Node  boot_count=%u ===\n", bootCount);

  snprintf(topic, sizeof(topic), "iot/%s/readings", NODE_ID);
  Serial.printf("[CFG] topic = %s\n", topic);

  analogSetAttenuation(ADC_11db);        // rango ~0..3.1V

  connectWifi();
  syncTime();
  connectMqtt();
}

void loop() {
  if (!mqtt.connected()) connectMqtt();
  mqtt.loop();

  unsigned long now = millis();
  if (now - lastPublish >= PUBLISH_PERIOD_MS) {
    lastPublish = now;
    publishReading();
  }
}
