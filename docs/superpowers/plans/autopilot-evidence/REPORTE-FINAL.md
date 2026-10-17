# Reporte final del autopilot: preparación para el laboratorio y durabilidad del almacenamiento

Fecha: 2026-10-17 · Rama: `autopilot/lab-readiness-2026-10-17` (local, **sin push**), sale de `refraccion` @ `a165895`
15 commits · 54 archivos · +2591 / −76 líneas. Plan: `docs/superpowers/plans/2026-10-17-autopilot-lab-readiness.md`.
La evidencia de cada fase está en esta carpeta (`F0-baseline.md` … `F7-review.md`).

## Resultado en una línea

La cadena completa **sensor → ESP32 → broker → ingesta → backend → portal en tiempo real** se probó de
punta a punta con un nodo simulado en Docker aislado: **9 de 9 escenarios PASS**. Las tres suites de
tests están en verde y se cerraron los riesgos de quedarse sin disco y de perder mediciones.

## Qué hacer en el laboratorio (orden)

Guía detallada: `lab_esp32/README.md`, **paso 0** primero.

1. En el PC servidor: `git fetch` + `git checkout autopilot/lab-readiness-2026-10-17` (antes: `git push -u origin autopilot/lab-readiness-2026-10-17` desde esta Mac).
2. Arreglar el `.env` de la raíz:
   - `VITE_PUSHER_PORT=8080` (hoy dice 6001).
   - `VITE_PUSHER_APP_KEY=iotlocalkey` (hoy dice `local-key`, y Reverb lo rechaza con 4001).
   - `MQTT_HOST`, `MQTT_USERNAME=ingestion`, `MQTT_PASSWORD`.
3. Detener los contenedores viejos `iotplatformv2-cert-*`: ocupan los puertos 8000 y 8080.
4. Broker: `cd lab_esp32/broker && ./make-users.sh lab_postgrado_nodo_01 ingestion && docker compose -f docker-compose.broker.yml up -d`.
5. Plataforma: `docker compose up -d`, después `docker compose --profile workers up -d`, después `migrate --force` + `LabDoNodeSeeder`.
6. Firmware: en `config.h` poner WiFi, `MQTT_HOST`, la clave del nodo, el pin y la calibración. Flashear con `arduino-cli` (ya instalado en esta Mac).
7. `lab_esp32/preflight.sh` y luego `lab_esp32/preflight.sh --e2e`: todo tiene que dar PASS antes de conectar la sonda.

## Qué se hizo, por fase

| Fase | Qué | Commit(s) | Verificación |
|---|---|---|---|
| F0 | Línea base + rama | `3982d4b` `cdde114` | backend 559 sin fallos, ingesta 49, front 6/6 |
| F1 | **Ingesta sin pérdida**: errores transitorios (red, 5xx, 401/403/404/408/429) se reintentan para siempre, solo un payload inválido va a dead-letter; CLI `python -m app.replay_dead_letters`; broker con usuario+clave+ACL; un `node_id` falsificado va a cuarentena; Mosquitto encola sin límite | `aae058f` `ec5b50c` `7788073` | 53 tests; auth probada contra Mosquitto real |
| F2 | Portal en la LAN (CORS/Sanctum/Reverb configurables), "Última comunicación" se actualiza, `preflight.sh` | `c963f69` | feature test; preflight 17/17 en e2e |
| F3 | **Almacenamiento por niveles**: la traza cruda vieja pasa a tablas `ENGINE=ARCHIVE` (31x más chicas, inmutables); `sensor_readings` comprimida (2x); `iot:storage:tier` diario | `a707853` | 4 tests; MySQL 8 real: ARCHIVE + Compressed |
| F4 | **Logs**: rotación diaria, gzip diario (19x), purga el 1 y el 15 de cada mes conservando ≥ 30 días; logs de Docker con tope de 10 MB × 5; `iot:capacity:check` cada 15 min con correo; servicio `scheduler`; Redis `maxmemory`; MySQL con −300 MB de RAM | `ed7db3f` `68a0da6` | 3 tests; `schedule:list` = 5 jobs |
| F5 | **Firmware**: búfer offline de 360 lecturas con su hora real; reconexión no bloqueante; `boot_count` en NVS | `5adca6e` | compila (72 % flash / 16 % RAM); test host del búfer |
| F6 | E2E en vivo, 9 escenarios (abajo) | `a517c99` | evidencia `F6-e2e.md` |
| F7 | Revisión de código + revisión final | `f6a1002` `a5e78e4` `4c7ab41` `e9c27bf` | backend **516 passed / 52 skipped / 0 failed**, ingesta **53 passed**, front **6/6**, firmware compila |

### Escenarios E2E (F6)

| # | Escenario | Resultado |
|---|---|---|
| 1 | migrate + seed + `preflight.sh` / `--e2e` | PASS (17/17) |
| 2 | Lectura con la forma del firmware llega a `sensor_readings`, API del gráfico, broadcast Reverb `NewSensorReading` | PASS |
| 3 | Backend caído + 30 lecturas → al volver llegan las 30, 0 dead letters | PASS |
| 4 | Ingesta caída + 20 lecturas → el broker las encoló, llegan las 20 | PASS |
| 5 | Mismo payload dos veces → 1 fila | PASS |
| 6 | ARCHIVE/Compressed en MySQL real + tiering mueve 25 eventos, 0 lecturas perdidas | PASS |
| 7 | capacity / logs archive / cdc trim / scheduler | PASS |
| 8 | Seguridad: anónimo rechazado, topic ajeno descartado, `node_id` falsificado en cuarentena, API sin token → 401 | PASS |
| 9 | Front 200 + `/api/health` | PASS (sin prueba en navegador) |

## Base de datos: ¿hace falta Postgres?

No. Lo medí con 200.000 filas reales del esquema (bytes por fila):

| Tabla | MySQL antes | MySQL ahora | TimescaleDB comprimido |
|---|---|---|---|
| `raw_sensor_events` (traza cruda) | 944 | **30** (ARCHIVE, después de 14 días) | 42 |
| `sensor_readings` (la medición) | 136 | **68** (Compressed) | 8 |

Con 4 sensores cada 5 s: **~2,5 GB/año en total** (antes ~56 GB/año, sin límite). Además se borra lo
que es solo plomería del pipeline: los outbox ya entregados a los 7 días.

**No se borra nunca:** las mediciones ni la traza archivada. TimescaleDB solo vale la pena con más de
~20 nodos.

## Garantías contra "se quedó sin disco / se perdieron datos"

- La medición se guarda en MySQL **antes** de cualquier cola. Redis y los streams son solo transporte:
  si Redis se llena, falla visiblemente y reintenta desde MySQL, sin borrar nada en silencio.
- El recorte de streams solo quita lo que el consumidor ya entregó (`a5e78e4`). Antes, con un
  consumidor caído más de 3 días, se perdían eventos.
- Spool de ingesta: con el backend caído se acumula en disco y se reenvía al volver (antes, a los
  ~16 min iba todo a dead-letter).
- ESP32 sin WiFi/broker: guarda hasta 360 lecturas en RAM (30 min a 5 s, 6 h a 60 s).
- Alertas: `iot:capacity:check` manda correo al 80 % (WARN) y al 90 % (CRITICAL) de disco, BD o Redis,
  máximo 1 correo cada 6 h por métrica. Usa el `mail_to` de la configuración del sistema: **verifica
  que esté configurado**.
- Logs: todo acotado (Laravel con rotación + gzip + purga a 30 días; Docker con 50 MB por contenedor).

## Pendientes y límites conocidos (decididos, no olvidados)

| Tema | Detalle |
|---|---|
| **Disco de esta Mac al 97 %** | Si esta Mac es el servidor, liberar espacio ya (`docker builder prune` ≈ 3 GB). No borré nada tuyo. |
| Hardware | Firmware compilado pero **no probado en el ESP32**. Falta confirmar sonda analógica o EZO (`readDissolvedOxygen()`) y calibrar `DO_CAL_*`. |
| Cadencia | `PUBLISH_PERIOD_MS=5000`. Para OD, 60 s suele bastar (12x menos datos). Decidirlo con el químico. |
| Búfer del ESP32 | Vive en RAM: un corte de energía pierde lo pendiente. Con QoS 0, una publicación justo cuando se cae la red puede perderse (pocos segundos). |
| TLS MQTT | 1883 sin cifrar en la LAN (con usuario+ACL). Siguiente paso: listener 8883. |
| Gráfico público | El sensor DO tiene `public_monitoring_enabled=0` a propósito; activarlo en el portal si se quiere público. |
| Hallazgos Low (F7) | ARCHIVE no es transaccional: si falla el DELETE se duplica en archivo, nunca se pierde. La clave de dedupe del correo se marca antes del envío. La clave MQTT aparece en `ps` local durante el preflight. Detalle en `F7-review.md`. |
| Zona horaria | MySQL `NOW()` está en UTC y la app en Bogotá: las edades del tiering se desfasan 5 h (irrelevante con ventanas de 14/90 días). |
| `laravel.log` viejo | 14 MB en `back/storage/logs` (gitignored): borrarlo o comprimirlo a mano. |

## Cómo verificar tú mismo

```bash
cd back && APP_ENV=testing DB_CONNECTION=sqlite DB_DATABASE=:memory: CACHE_STORE=array SESSION_DRIVER=array QUEUE_CONNECTION=sync php artisan test
cd ingestion_service && python -m pytest -q tests
arduino-cli compile --fqbn esp32:esp32:esp32 lab_esp32/firmware/do_sensor_node
```
