#!/usr/bin/env bash
# Uso: lab_esp32/flash.sh [puerto]   ej. lab_esp32/flash.sh /dev/cu.usbserial-0001
# Lee los secretos de lab_esp32/.env (ver .env.example), genera secrets.h temporal,
# compila y, si se da puerto, sube. secrets.h se borra al salir (y está en .gitignore).
set -euo pipefail
here="$(cd "$(dirname "$0")" && pwd)"
sketch="$here/firmware/do_sensor_node"
env_file="$here/.env"
[ -f "$env_file" ] || { echo "falta $env_file (copia .env.example)" >&2; exit 1; }

# Lee KEY=valor literal (sin expansión de shell: claves con $ o % quedan intactas).
val() { grep -m1 "^$1=" "$env_file" | cut -d= -f2- | tr -d '\r'; }
cstr() { printf '"%s"' "$(printf '%s' "$1" | sed -e 's/\\/\\\\/g' -e 's/"/\\"/g')"; }

trap 'rm -f "$sketch/secrets.h"' EXIT
{
  for k in WIFI_SSID WIFI_PASSWORD MQTT_HOST; do
    v="$(val $k)"; [ -n "$v" ] || { echo "falta $k en $env_file" >&2; exit 1; }
    echo "#define $k $(cstr "$v")"
  done
  v="$(val NODE_MQTT_PASSWORD)"; [ -n "$v" ] || { echo "falta NODE_MQTT_PASSWORD en $env_file" >&2; exit 1; }
  echo "#define MQTT_PASSWORD $(cstr "$v")"
} > "$sketch/secrets.h"

if [ $# -ge 1 ]; then
  arduino-cli compile --upload -p "$1" --fqbn esp32:esp32:esp32 "$sketch"
else
  arduino-cli compile --fqbn esp32:esp32:esp32 "$sketch"
fi
