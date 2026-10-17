#!/usr/bin/env bash
# Preflight del laboratorio: PASS/FAIL por chequeo, exit != 0 si algo falla.
# Uso: lab_esp32/preflight.sh [--e2e]
# Env opcionales: COMPOSE_PROJECT (docker compose -p), BACK_PORT, MQTT_USERNAME/MQTT_PASSWORD,
#   PREFLIGHT_MQTT_HOST (host del broker visto desde ESTA maquina; default MQTT_HOST de .env).
set -u
cd "$(dirname "$0")/.." || exit 2
NODE_ID=lab_postgrado_nodo_01
E2E=0; [ "${1:-}" = "--e2e" ] && E2E=1
DC=(docker compose); [ -n "${COMPOSE_PROJECT:-}" ] && DC=(docker compose -p "$COMPOSE_PROJECT")
FAILS=0
ok()   { echo "PASS  $1"; }
bad()  { echo "FAIL  $1"; FAILS=$((FAILS+1)); }
check(){ local name=$1; shift; if "$@" >/dev/null 2>&1; then ok "$name"; else bad "$name"; fi; }
# Valor de .env (sin imprimirlo): las variables ya exportadas ganan.
envval(){ local v; v=$(printenv "$1" 2>/dev/null); [ -n "$v" ] || v=$(grep -E "^$1=" .env 2>/dev/null | tail -1 | cut -d= -f2- | tr -d '"'"'"); printf '%s' "$v"; }
sql(){ "${DC[@]}" exec -T db sh -c 'mysql -N -uroot -p"$MYSQL_ROOT_PASSWORD" "$MYSQL_DATABASE" -e "$0"' "$1" 2>/dev/null; }

# 1. Servicios (workers = profile workers)
RUNNING=$("${DC[@]}" --profile workers ps --status running --services 2>/dev/null)
for s in db redis back reverb front ingestion debezium outbox-cdc-consumer raw-consumer domain-event-consumer; do
  check "servicio $s corriendo" grep -qx "$s" <<<"$RUNNING"
done

# 2. Variables obligatorias (nunca se imprimen valores)
for v in INGESTION_SERVICE_TOKEN DEBEZIUM_DB_PASSWORD MQTT_HOST; do
  check "$v no vacio en .env" test -n "$(envval $v)"
done

# 3. API
BACK_PORT=$(envval BACK_PORT); BACK_PORT=${BACK_PORT:-8000}
check "GET /api/health" curl -fsS -m 10 "http://localhost:$BACK_PORT/api/health"

# 4. Broker
MQ_HOST=${PREFLIGHT_MQTT_HOST:-$(envval MQTT_HOST)}; MQ_PORT=$(envval MQTT_PORT); MQ_PORT=${MQ_PORT:-1883}
MQ_AUTH=(); [ -n "$(envval MQTT_USERNAME)" ] && MQ_AUTH=(-u "$(envval MQTT_USERNAME)" -P "$(envval MQTT_PASSWORD)")
mqpub(){ mosquitto_pub -h "$MQ_HOST" -p "$MQ_PORT" ${MQ_AUTH[@]+"${MQ_AUTH[@]}"} "$@"; }
if command -v mosquitto_pub >/dev/null 2>&1; then
  check "broker alcanzable (pub a iot/preflight/ping)" mqpub -t iot/preflight/ping -m ping -q 1
else
  bad "mosquitto_pub no instalado (brew/apt install mosquitto-clients)"
fi

# 5. Dispositivo + mapping
check "device $NODE_ID existe" test "$(sql "SELECT COUNT(*) FROM devices WHERE serial_number='$NODE_ID'")" -ge 1
check "mapping ingestion_service activo" test "$(sql "SELECT COUNT(*) FROM device_sensor_mappings m JOIN devices d ON d.id=m.device_id WHERE d.serial_number='$NODE_ID' AND m.source='ingestion_service' AND m.is_active=1")" -ge 1

# 6. E2E opcional
if [ "$E2E" = 1 ]; then
  COUNT_SQL="SELECT COUNT(*) FROM sensor_readings r JOIN sensors s ON s.id=r.sensor_id JOIN devices d ON d.id=s.device_id WHERE d.serial_number='$NODE_ID'"
  BEFORE=$(sql "$COUNT_SQL"); BEFORE=${BEFORE:-0}
  N=$(date +%s)  # boot_count/reading_index unicos: nunca duplicado
  PAYLOAD="{\"device\":{\"node_id\":\"$NODE_ID\",\"firmware_version\":\"preflight\"},\"timestamp\":\"$(date -u +%Y-%m-%dT%H:%M:%SZ)\",\"session\":{\"boot_count\":$N,\"reading_index\":$N,\"uptime_ms\":1000},\"network\":{\"wifi_rssi_dbm\":-60},\"sensors\":{\"dissolved_oxygen\":{\"value\":8.0,\"unit\":\"mg/L\",\"sensor_model\":\"preflight\",\"status\":\"ok\"}},\"qc\":{\"valid\":true}}"
  if mqpub -t "iot/$NODE_ID/readings" -m "$PAYLOAD" -q 1; then
    got=0
    for _ in $(seq 1 30); do
      sleep 2
      AFTER=$(sql "$COUNT_SQL"); [ "${AFTER:-0}" -gt "$BEFORE" ] && { got=1; break; }
    done
    if [ "$got" = 1 ]; then ok "e2e: lectura sintetica llego a sensor_readings"; else bad "e2e: sin fila nueva en sensor_readings tras 60 s"; fi
  else
    bad "e2e: no se pudo publicar la lectura"
  fi
fi

echo "---"; [ "$FAILS" -eq 0 ] && echo "PREFLIGHT OK" || echo "PREFLIGHT: $FAILS chequeo(s) fallaron"
[ "$FAILS" -eq 0 ]
