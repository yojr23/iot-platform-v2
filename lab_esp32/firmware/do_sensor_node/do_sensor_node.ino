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
#include "ring_buffer.h"

// ---------------------------------------------------------------------
// Lectura pendiente: valor, indice y hora quedan fijados al medir.
// epoch == 0 -> sin hora real (se publica sin "timestamp").
// ---------------------------------------------------------------------
struct Reading {
  float    value;
  uint32_t index;
  time_t   epoch;
};

RingBuffer<Reading, OFFLINE_BUFFER_SIZE> offlineBuf;

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
    Serial.println("\n[WiFi] FALLO (se reintenta en loop, sin bloquear)");
  }
}

// ---------------------------------------------------------------------
// Hora UTC vía NTP. configTime deja el cliente SNTP corriendo: si no hubo
// hora al arrancar, se sincroniza sola cuando haya red.
// ---------------------------------------------------------------------
void syncTime() {
  configTime(0, 0, "pool.ntp.org", "time.google.com");  // UTC
  struct tm t;
  if (getLocalTime(&t, 8000)) {
    Serial.println("[NTP] Hora sincronizada (UTC).");
  } else {
    Serial.println("[NTP] Sin hora todavia; lecturas sin hora real no se encolan.");
  }
}

// Hora real = epoch posterior a 2023 (sin NTP el reloj arranca en 1970).
time_t validEpochNow() {
  time_t t = time(nullptr);
  return t > 1700000000 ? t : 0;
}

// ---------------------------------------------------------------------
// MQTT: un intento por llamada, sin bucles. loop() reintenta cada MQTT_RETRY_MS.
// ---------------------------------------------------------------------
unsigned long lastMqttTry = 0;
unsigned long lastWifiTry = 0;

void tryMqtt() {
  lastMqttTry = millis();
  String clientId = String("esp32-") + NODE_ID;
  Serial.printf("[MQTT] Conectando a %s:%d ...", MQTT_HOST, MQTT_PORT);
  bool ok = strlen(MQTT_USERNAME) > 0
              ? mqtt.connect(clientId.c_str(), MQTT_USERNAME, MQTT_PASSWORD)
              : mqtt.connect(clientId.c_str());
  if (ok) Serial.println(" OK");
  else Serial.printf(" FALLO rc=%d. Reintento en %lus\n", mqtt.state(), MQTT_RETRY_MS / 1000);
}

// Llamar desde loop(): reconecta WiFi y MQTT sin bloquear la cadencia de medicion.
void maintainConnections() {
  unsigned long now = millis();
  if (WiFi.status() != WL_CONNECTED) {
    if (now - lastWifiTry >= WIFI_RETRY_MS) {
      lastWifiTry = now;
      Serial.println("[WiFi] Reintentando (no bloqueante)");
      WiFi.disconnect();
      WiFi.begin(WIFI_SSID, WIFI_PASSWORD);
    }
    return;
  }
  if (!mqtt.connected() && now - lastMqttTry >= MQTT_RETRY_MS) tryMqtt();
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

// Construye el JSON y publica. Devuelve el resultado de mqtt.publish.
bool publishReading(const Reading &r) {
  // Documento JSON (ArduinoJson v6 API; compatible con v7)
  StaticJsonDocument<640> doc;

  JsonObject device = doc.createNestedObject("device");
  device["node_id"]          = NODE_ID;
  device["firmware_version"] = FIRMWARE_VERSION;
  device["location"]         = NODE_LOCATION;

  char ts[25];
  if (r.epoch) {
    struct tm t;
    gmtime_r(&r.epoch, &t);
    strftime(ts, sizeof(ts), "%Y-%m-%dT%H:%M:%SZ", &t);  // 2026-10-17T09:00:00Z
    doc["timestamp"] = ts;   // opcional; sin NTP se omite
  }

  JsonObject session = doc.createNestedObject("session");
  session["boot_count"]    = bootCount;     // parte de la identidad anti-duplicado
  session["reading_index"] = r.index;       // "
  session["uptime_ms"]     = millis();      // al enviar (no al medir)
  session["dropped"]       = offlineBuf.dropped();  // descartadas por buffer lleno desde el arranque

  JsonObject network = doc.createNestedObject("network");
  network["wifi_rssi_dbm"] = WiFi.RSSI();

  JsonObject sensors = doc.createNestedObject("sensors");
  JsonObject s = sensors.createNestedObject(SENSOR_KEY);  // "dissolved_oxygen"
  s["value"]        = roundf(r.value * 100) / 100.0f;     // 2 decimales
  s["unit"]         = SENSOR_UNIT;
  s["sensor_model"] = SENSOR_MODEL;
  s["status"]       = "ok";

  JsonObject qc = doc.createNestedObject("qc");
  qc["valid"] = true;   // si pones false, el backend archiva pero NO crea lectura

  char buf[640];
  size_t n = serializeJson(doc, buf, sizeof(buf));

  // PubSubClient solo publica con QoS 0; el ingestion_service se suscribe con QoS 1 igual.
  bool ok = mqtt.publish(topic, (const uint8_t *)buf, n, false);  // retain=false
  Serial.printf("[PUB] idx=%u %s (%u bytes) %s\n", (unsigned)r.index, topic, (unsigned)n, ok ? "OK" : "FALLO");
  return ok;
}

// Una medicion = un indice. Con hora real siempre pasa por el buffer (flushBuffer la
// publica enseguida si hay MQTT); sin hora real solo se publica en directo.
void takeMeasurement() {
  Reading r = { readDissolvedOxygen(), ++readingIndex, validEpochNow() };
  if (r.epoch) {
    offlineBuf.push(r);
  } else if (!(mqtt.connected() && publishReading(r))) {
    Serial.printf("[BUF] idx=%u sin hora NTP y sin MQTT: lectura omitida\n", (unsigned)r.index);
  }
}

// Vacia el buffer oldest-first, acotado por iteracion. Solo saca la lectura si publish tuvo exito.
void flushBuffer() {
  for (int i = 0; i < OFFLINE_FLUSH_PER_LOOP && mqtt.connected() && !offlineBuf.empty(); i++) {
    if (!publishReading(offlineBuf.front())) break;  // se reintenta en la proxima iteracion
    offlineBuf.pop();
    mqtt.loop();
  }
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
  mqtt.setServer(MQTT_HOST, MQTT_PORT);
  mqtt.setBufferSize(640);  // el JSON ronda ~420 bytes; el default (256) no alcanza
  tryMqtt();
}

void loop() {
  maintainConnections();
  mqtt.loop();

  unsigned long now = millis();
  if (now - lastPublish >= PUBLISH_PERIOD_MS) {
    lastPublish = now;
    takeMeasurement();
  }
  flushBuffer();
}
