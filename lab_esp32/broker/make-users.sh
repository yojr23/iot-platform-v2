#!/usr/bin/env bash
# Uso: ./make-users.sh <usuario>...   (node_ids de los ESP32 y/o "ingestion")
# Crea/actualiza mosquitto/config/passwd con mosquitto_passwd dentro de eclipse-mosquitto:2
# e imprime cada contraseña generada UNA sola vez. Reiniciar el broker después:
#   docker compose -f docker-compose.broker.yml restart broker
set -euo pipefail
[ $# -ge 1 ] || { echo "uso: $0 <usuario>..." >&2; exit 1; }
cd "$(dirname "$0")/mosquitto/config"
touch passwd
for u in "$@"; do
  pw=$(openssl rand -hex 10)
  docker run --rm -v "$PWD:/c" eclipse-mosquitto:2 mosquitto_passwd -b /c/passwd "$u" "$pw" >/dev/null
  echo "$u  $pw"
done
# Mosquitto 2.0 solo avisa por permisos 644; versiones futuras exigirán 0700 (ver README).
chmod 644 passwd
