#!/usr/bin/env bash
# Phase D: real-MySQL concurrent mapping race. For each iteration, truncate mappings, launch two
# workers that hit mapSensor() for the same identity simultaneously, then assert exactly ONE open
# (valid_until IS NULL) mapping remains. Reports first-ever (empty-set) race violations.
set -u
cd "$(dirname "$0")/../.."

export DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_PORT=33306 DB_DATABASE=iot_test DB_USERNAME=root DB_PASSWORD=root

DEVICE_ID="${1:-1}"
SENSOR_ID="${2:-1}"
ITERATIONS="${3:-50}"
KEY="race-key"

MYSQL="docker exec iot-concurrency-mysql mysql -uroot -proot iot_test -N -s"

violations=0
for i in $(seq 1 "$ITERATIONS"); do
  # Empty-set race: no existing mapping for this identity at the start of each iteration.
  $MYSQL -e "DELETE FROM device_sensor_mappings WHERE external_key='$KEY';" 2>/dev/null

  barrier=$(php -r 'echo microtime(true) + 0.25;')

  php tests/concurrency/mapping_race_worker.php "$barrier" "$DEVICE_ID" "$SENSOR_ID" "$KEY" >/tmp/w1.out 2>/tmp/w1.err &
  p1=$!
  php tests/concurrency/mapping_race_worker.php "$barrier" "$DEVICE_ID" "$SENSOR_ID" "$KEY" >/tmp/w2.out 2>/tmp/w2.err &
  p2=$!
  wait $p1; wait $p2

  open_count=$($MYSQL -e "SELECT COUNT(*) FROM device_sensor_mappings WHERE external_key='$KEY' AND valid_until IS NULL;" 2>/dev/null)
  if [ "$open_count" != "1" ]; then
    violations=$((violations+1))
    echo "iter $i: OPEN MAPPINGS=$open_count (expected 1)  [w1=$(cat /tmp/w1.out) w2=$(cat /tmp/w2.out)]"
  fi
done

echo "----"
echo "iterations=$ITERATIONS violations=$violations"
[ "$violations" -eq 0 ] && echo "RESULT: PASS (invariant COUNT(open)=1 held every iteration)" || echo "RESULT: FAIL"
