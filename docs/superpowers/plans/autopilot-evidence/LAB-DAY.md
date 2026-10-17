# Lab day 2026-10-17: real hardware run

Setup: Mac on WiFi `E-bio` (172.16.122.235, gateway 172.16.122.1). E-bio broadcasts on both 2.4 GHz (ch 1) and 5 GHz (ch 157), WPA2-PSK.
Board: classic ESP32 (WROOM-32) on `/dev/cu.usbserial-0001`, FQBN `esp32:esp32:esp32`.

## Fixes

1. **Debezium crash loop** (commit 310f94f). The offset stored in Redis pointed at `mysql-bin.000006`, which MySQL had already purged (`binlog_expire_logs_seconds=604800`). Fix: `snapshot.mode=when_needed`. It re-snapshots the outbox tables, and the consumer skips rows already published.

## Results

- `preflight.sh --e2e`: OK after the fix. Before it, FAIL with "sin fila nueva en sensor_readings".
- ESP32 serial output: `[WiFi] OK IP=172.16.122.233 RSSI=-51 dBm`, `[MQTT] ... OK`, then `[PUB] idx=1..N OK` every 5 s.
- NTP synced a few seconds after boot. The payload grew from 360 to 395 bytes once the timestamp was included.
- Readings arrive in `sensor_readings` (sensor_id=3) about 1 s after `reading_time`. `domain-event-consumer` acks each one with dlq=0.
- Portal answers HTTP 200 on localhost:5173 and on 172.16.122.235:5173.

## Pending

- Calibration is still the placeholder (`DO_CAL_SLOPE 4.0`). Values come in at ~0.7 mg/L.
- `config.h` holds the WiFi and MQTT credentials locally. Do not commit it.
