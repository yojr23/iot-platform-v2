# F5 - Firmware resiliente

## Cambios (lab_esp32/firmware/do_sensor_node)
- `ring_buffer.h` (nuevo): `RingBuffer<T,N>` FIFO puro; push descarta el mas viejo y cuenta `dropped()`; `front()`/`pop()`.
- `config.h`: `OFFLINE_BUFFER_SIZE` 360, `OFFLINE_FLUSH_PER_LOOP` 10, `WIFI_RETRY_MS` 30000, `MQTT_RETRY_MS` 5000.
- `do_sensor_node.ino`:
  - `takeMeasurement()`: un indice por medicion; con hora NTP valida (>2023) se encola siempre y `flushBuffer()` la publica en la misma iteracion si hay MQTT; sin hora, solo se publica en directo, si no se omite con log.
  - `flushBuffer()`: oldest-first, max 10 por iteracion, `pop()` solo si `publish` devolvio true; con falla corta y reintenta luego (sin doble publicacion).
  - Reconexion no bloqueante: `maintainConnections()` (WiFi cada 30 s con `WiFi.begin`, MQTT un intento cada 5 s). Se elimino el `while`+`delay(3000)`. Arranque sigue conectando WiFi (max 20 s) y NTP (max 8 s) una vez.
  - Payload: mismas claves + `session.dropped`; `timestamp` sale del epoch fijado al medir. `uptime_ms` y RSSI son del momento de envio. Buffer JSON/MQTT subido de 512 a 640.
- `test/ring_buffer_test.cpp`: test host.

## Compilacion
`arduino-cli compile --fqbn esp32:esp32:esp32 lab_esp32/firmware/do_sensor_node` OK
- Flash: 955805 bytes (72%) de 1310720
- RAM: 53972 bytes (16%) de 327680

## Test host
```
g++ -std=c++17 -Wall -I../do_sensor_node ring_buffer_test.cpp -o /tmp/rb_test && /tmp/rb_test
ring_buffer_test OK
```

## Riesgos
- Buffer en RAM: reinicio/corte pierde lo pendiente.
- No probado en hardware ni contra broker; `mqtt.connect` aun puede bloquear unos segundos (timeout TCP) cuando el broker es inalcanzable con WiFi arriba.
- QoS 0: publish=true significa escrito al socket, no entregado.
