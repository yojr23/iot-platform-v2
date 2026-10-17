# Autopilot: Lab Readiness + Storage Durability

Fecha: 2026-10-17 · Rama de trabajo: `autopilot/lab-readiness-2026-10-17` (sale de `refraccion` @ `a165895`)
Repositorio: único repositorio del sistema. Lo que no existe aquí no existe.
Análisis previo: `docs/superpowers/plans/2026-10-16-conexion-esp32-y-almacenamiento.md`.

## Objetivo

1. Que hoy en el laboratorio se pueda conectar sonda DO → ESP32 → broker → plataforma →
   portal sin depurar en sitio: todo probado de punta a punta antes con un broker local.
2. Que en 2 semanas o 1 mes el PC que hace de servidor **no se quede sin disco ni RAM** y
   **no se pierdan mediciones**, conservando la traza.

## Decisión de almacenamiento (medida, no supuesta)

Benchmark del 2026-10-17: 200.000 filas reales del esquema actual en contenedores temporales.
Las cifras son bytes en disco por fila, incluidos los índices.

| Tabla | MySQL actual | MySQL `ROW_FORMAT=COMPRESSED` | MySQL `ENGINE=ARCHIVE` | Postgres plano | TimescaleDB comprimido |
|---|---|---|---|---|---|
| `sensor_readings` | 136 | 68 | n/a | 142 | 8 |
| `raw_sensor_events` (payload JSON ~424 B) | 944 | 461 | **30** | 903 | 42 |

Logs de Laravel (texto, mediana 130 caracteres por línea): gzip 19x, xz 27x.

**Decisión: seguir con MySQL. No se agrega Postgres ahora.**
- Mover la traza cruda vieja (`raw_sensor_events`, `audit_logs`) a tablas `ENGINE=ARCHIVE`
  (motor que ya trae MySQL, append-only, inmutable) da 31x, mejor que Timescale, sin un
  segundo motor, sin migrar Debezium y sin re-certificar las Gates 9 y 10.
- `sensor_readings` (la medición) se conserva para siempre con `ROW_FORMAT=COMPRESSED` (2x).
  Con 1 sensor cada 5 s son ~430 MB/año; con 4 sensores, ~1,7 GB/año.
- TimescaleDB solo gana en `sensor_readings` (8 vs 68 B). Se reevalúa si se pasa de
  ~20 nodos (Fase futura, fuera de este autopilot).

Proyección con todo aplicado (4 sensores cada 5 s): MySQL ~2,5 GB/año en total, contra
~56 GB/año hoy. Logs ~150 MB/mes comprimidos, contra ~3 GB/mes hoy, sin límite.

## Riesgos de pérdida de datos encontrados (P0)

| ID | Riesgo | Evidencia |
|---|---|---|
| R1 | Con el backend caído ~16 min, el `ingestion_service` manda todos los eventos a `dead_letters` y nunca los reenvía. | `delivery_worker.py:43` trata igual cualquier `BackendClientError`; `spool.py:278` hace dead-letter a los 20 intentos; backoff 2→60 s. |
| R2 | Mosquitto encola por defecto 1000 mensajes por suscriptor desconectado (~83 min a 5 s) y luego descarta. | `lab_esp32/broker/mosquitto/config/mosquitto.conf` no define `max_queued_messages`. |
| R3 | Los logs de los contenedores (driver json-file) no tienen límite: llenan el disco. | `docker-compose.yml` no tiene `logging:`. |
| R4 | `laravel.log` es un solo archivo en `debug`, sin rotación. Los tests escriben en él. | `LOG_STACK=single`, `phpunit.xml` sin override de log. |
| R5 | No hay retención en ninguna tabla ni scheduler. | grep `Prunable\|schedule` = 0. |
| R6 | Sin WiFi o sin broker, el ESP32 descarta la lectura. | `do_sensor_node.ino` publica y olvida. |
| R7 | Nadie avisa cuando se acaba el disco o la RAM. | No hay chequeo de capacidad. |

## Reglas para todos los agentes

- Invocar la skill `ponytail:ponytail` al empezar: la solución más simple que funcione,
  reusando lo que ya hay en el repo.
- Trabajar solo en la rama `autopilot/lab-readiness-2026-10-17`. **Prohibido:** `git push`,
  `git stash`, `git checkout`/`switch` de otra rama, `git reset --hard`, `git clean`,
  `migrate:fresh`/`refresh`, `docker compose down -v` sobre el proyecto por defecto, editar
  `.env` con secretos reales (solo `.env.example`).
- Commitear solo los paths propios (`git add <paths>`), con mensajes convencionales en inglés
  (`feat:`, `fix:`, `test:`, `docs:`) y esta línea final:
  `Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>`. Si `index.lock` existe,
  esperar y reintentar.
- Tests del backend aislados:
  `cd back && APP_ENV=testing DB_CONNECTION=sqlite DB_DATABASE=:memory: CACHE_STORE=array SESSION_DRIVER=array QUEUE_CONNECTION=sync php artisan test`.
  Tests de ingesta: venv en `$CLAUDE_JOB_DIR/tmp/venv` (o crear uno) y `python -m pytest -q tests`.
- Las migraciones específicas de MySQL (`ENGINE=ARCHIVE`, `ROW_FORMAT`) llevan guard por
  driver: en SQLite crean una tabla normal.
- Un fallo de test que ya estaba en la línea base (F0) no bloquea, pero se reporta.
- Cada fase termina con un reporte: qué cambió, qué tests corrieron (comando y conteo),
  qué quedó pendiente y por qué.

---

## F0: Línea base (orquestador)

- Crear la rama, commitear el trabajo previo (`lab_esp32/`, seeder, planes).
- Correr las tres suites y anotar el resultado en `docs/superpowers/plans/autopilot-evidence/F0-baseline.md`.
- **DoD:** rama creada; línea base registrada con conteos pass/fail por suite.

## F1: Ingesta sin pérdida (P0: R1, R2). Ola 1, paralela

Paths: `ingestion_service/**`, `lab_esp32/broker/**`.

Requisitos:
1. Clasificar errores en `backend_client.py`: transitorios (red, timeout, 5xx, 408, 429)
   y permanentes (otros 4xx, token vacío).
2. Los transitorios **no consumen** `max_attempts`: se reintentan para siempre con un
   backoff tope (`INGESTION_RETRY_MAX_SECONDS`). Solo los permanentes van a dead-letter.
3. CLI de replay: `python -m app.replay_dead_letters [--all|--id X]` que devuelve eventos
   de `dead_letters` a `pending_events`. Idempotente gracias a `source_event_id`.
4. `mosquitto.conf`: `max_queued_messages 0` (sin límite; la persistencia la acota el
   disco), `autosave_interval 60`, `log_type error warning notice` en vez de `all`.
5. Autenticación del broker (hallazgo de seguridad MEDIUM: broker anónimo = cualquiera en
   la LAN inyecta lecturas): `allow_anonymous false`, `password_file` y `acl_file`. El
   usuario de cada nodo solo puede escribir en `iot/<su node_id>/readings` (patrón
   `pattern write iot/%u/readings` con usuario = node_id). El usuario `ingestion` solo
   lee `iot/+/readings`. Script `lab_esp32/broker/make-users.sh <node_id>...` que genera
   las contraseñas con `mosquitto_passwd` dentro del contenedor y las imprime una vez.
   El firmware ya soporta `MQTT_USERNAME`/`MQTT_PASSWORD` (`config.h`): poner
   `MQTT_USERNAME NODE_ID` por defecto. Puertos: se mantiene 1883 en LAN (TLS fuera de
   alcance hoy; documentarlo como siguiente paso).
6. `ingestion_service`: rechazar (cuarentena existente) los mensajes cuyo node_id del
   topic `iot/<node>/readings` no coincida con `payload.device.node_id`.

DoD / tests:
- Test: 500 respuestas 503 seguidas → 0 dead letters y el evento sigue pendiente.
- Test: un 422 → dead letter.
- Test: replay mueve dead→pending y luego se entrega.
- Test: topic con node_id distinto del payload → cuarentena, no se entrega.
- `make-users.sh` probado contra el contenedor `eclipse-mosquitto:2`: pub anónimo rechazado, pub con credencial a su topic OK, pub a otro topic denegado.
- `pytest` verde (la línea base era 49).

## F2: Conexión al laboratorio (portal en LAN, última comunicación, preflight). Ola 1, paralela

Paths: `docker-compose.yml` (solo secciones `back`, `reverb`, `front`), `.env.example`
(raíz), `front/.env.example`, `back/app/Services/Ingestion/RawReadingNormalizer.php` +
su test, `lab_esp32/preflight.sh`, `lab_esp32/README.md`.

Requisitos:
1. Hacer configurables por env `CORS_ALLOWED_ORIGINS`, `SANCTUM_STATEFUL_DOMAINS` y
   `REVERB_ALLOWED_ORIGINS` (pasarlo al servicio `reverb`). Los defaults quedan iguales a
   los actuales (localhost), así nada cambia si no se define una IP.
2. Documentar en `.env.example`: `LAN_IP`, `VITE_API_BASE_URL`, `VITE_PUSHER_HOST`.
3. `RawReadingNormalizer::normalize()` actualiza `devices.last_communication` cuando
   resuelve el dispositivo. Usar `saveQuietly` o el evento `DeviceCommunicationReceived`
   existente; elegir el camino más simple que no dispare observers en cascada.
4. `lab_esp32/preflight.sh`: verifica que los contenedores requeridos estén arriba,
   `INGESTION_SERVICE_TOKEN`/`DEBEZIUM_DB_PASSWORD`/`MQTT_HOST` no vacíos (sin imprimir
   valores), `/api/health` OK, broker alcanzable (`mosquitto_pub` a un topic de prueba),
   y que exista el dispositivo `lab_postgrado_nodo_01` con su mapping. Opcional `--e2e`:
   publica una lectura sintética con la forma del firmware y espera a que aparezca en
   `sensor_readings` (timeout de 60 s). Salida PASS/FAIL por chequeo; exit code ≠ 0 si algo falla.

DoD / tests:
- Feature test: normalizar un evento → `last_communication` no nulo.
- `docker compose config -q` OK con y sin `LAN_IP`.
- `bash -n lab_esp32/preflight.sh` OK; la ejecución real se valida en F6.

## F3: Almacenamiento por niveles (R5). Ola 1, paralela

Paths: `back/database/migrations/*`, `back/app/Console/Commands/*` (nuevos),
`back/app/Models/*` (solo si hace falta), `back/config/storage_retention.php` (o una
sección en un config existente), `back/routes/console.php`, `back/tests/**` (nuevos).

Requisitos:
1. Migraciones (guard MySQL): `raw_sensor_events_archive` y `audit_logs_archive` con
   `ENGINE=ARCHIVE`, mismas columnas que el original más `archived_at`; `sensor_readings`
   y `reading_projections` pasan a `ROW_FORMAT=COMPRESSED KEY_BLOCK_SIZE=8`.
2. Comando `iot:storage:tier` (nombre libre, coherente con el repo):
   - Mueve a archivo los `raw_sensor_events` con `status='processed'` más viejos que
     `RAW_HOT_DAYS` (default 14), en lotes por id: `INSERT ... SELECT` y `DELETE` en una
     transacción por lote. El outbox se borra en cascada (FK verificada).
   - **Nunca** toca `received`/`failed`/`processing`: son la fuente de replay.
   - Borra `domain_event_outboxes` entregados más viejos que `DOMAIN_OUTBOX_DAYS` (default 7).
     Son derivables de `sensor_readings`; Debezium ignora los DELETE (`CdcOutboxStreamConsumer.php:214`).
   - Mueve `audit_logs` más viejos que `AUDIT_HOT_DAYS` (default 90) a `audit_logs_archive`.
   - Nunca borra `sensor_readings` ni las tablas de archivo.
   - `--dry-run` muestra los conteos sin modificar nada.
3. Registrar en `routes/console.php`: `Schedule::command(...)->dailyAt('03:15')->withoutOverlapping()`.

DoD / tests:
- Feature test: filas viejas procesadas se mueven; nuevas, `failed` y `received` quedan;
  hot + archivo = total original; correr dos veces da el mismo resultado; `--dry-run` no cambia nada.
- Las migraciones corren en SQLite (tests) y en MySQL 8 real (se verifica en F6 con
  `SHOW TABLE STATUS`: Engine=ARCHIVE, Row_format=Compressed).

## F4: Logs con rotación, purga y guardia de capacidad (R3, R4, R7). Ola 2, después de F3

Paths: `docker-compose.yml` (anchor `x-logging` en todos los servicios, servicio
`scheduler`, flags de `db` y `redis`), `back/config/logging.php`, `back/.env.example`,
`back/phpunit.xml`, `back/routes/console.php`, `back/app/Console/Commands/*` (nuevos),
`back/tests/**` (nuevos).

Requisitos:
1. Logs de Laravel: `LOG_STACK=daily` y `LOG_LEVEL=info` en `.env.example`. Comando
   `iot:logs:archive`: comprime con gzip los `storage/logs/*.log`/`*.json` rotados de más
   de 1 día (nunca el del día) y purga los `.gz` más viejos que `LOG_ARCHIVE_DAYS`
   (default **30**: retención mínima mensual, pedida por el usuario). Programación:
   compresión diaria; purga el 1 y el 15 de cada mes, es decir, cada ~2 semanas como pidió
   el usuario, sin borrar nada de menos de 30 días. `LOG_DAILY_DAYS` (cuántos `.log` sin
   comprimir deja Monolog) tiene que ser ≥ 2 para que el comando alcance a comprimirlos.
   Ojo: Monolog borra según `maxFiles` contando solo su patrón (`*.log`), así que los
   `.gz` no cuentan.
2. `phpunit.xml`: `LOG_CHANNEL=null` para que los tests no escriban en `laravel.log`.
3. Docker: anchor `x-logging` (`json-file`, `max-size: 10m`, `max-file: 5`) en todos los
   servicios de `docker-compose.yml` y de `lab_esp32/broker/docker-compose.broker.yml`.
4. Servicio `scheduler` (perfil `workers`, imagen `back`, `php artisan schedule:work`).
5. Redis: `--maxmemory 512mb --maxmemory-policy noeviction`. Con noeviction, si se llena
   falla ruidosamente: el outbox en MySQL es la fuente y reintenta, y no se descarta nada
   en silencio. Defaults en compose: `INGESTION_RAW_EVENTS_MIN_AGE_MS` y
   `DOMAIN_EVENTS_MIN_AGE_MS` en 72 h (`259200000`). **Verificar antes** que los
   consumidores no dependan de entradas más viejas que eso; si hay duda, dejar MAXLEN y
   documentarlo.
6. MySQL: `--performance-schema=OFF`, `--innodb-buffer-pool-size=256M`, `--max-connections=60`.
7. Comando `iot:capacity:check` cada 15 min: espacio libre del disco donde está
   `storage_path()`, tamaño de la base (information_schema), `used_memory` de Redis contra
   `maxmemory`, tamaño del spool no aplica (es otro contenedor). Umbrales: WARN a 80 % de
   uso, CRITICAL a 90 %. Si se cruza un umbral: `Log::critical` + correo a los admins
   reusando el sistema de mail existente (`SystemSettings`/Mailables), con dedupe de
   1 correo cada 6 h por métrica (cache). Exponer el resumen en `/api/health` solo si
   encaja con `HealthController` sin romper sus tests.

DoD / tests:
- Test del archivador: con un directorio temporal y mtimes falsos, comprime los viejos,
  no toca el del día y purga los `.gz` de más de 30 días.
- Test de capacidad: umbrales WARN/CRITICAL con valores inyectados y dedupe del correo
  (`Mail::fake`).
- `docker compose config -q` OK; `php artisan schedule:list` muestra los 3 o 4 jobs.

## F5: Firmware resiliente (R6). Ola 2, paralela a F4

Paths: `lab_esp32/firmware/**`.

Requisitos:
1. Búfer circular en RAM de `OFFLINE_BUFFER_SIZE` lecturas (default 360: 30 min a 5 s,
   6 h a 60 s). Cuando falla la publicación o el MQTT está desconectado, la lectura se
   encola con su `reading_index` y su timestamp ya fijados. Al reconectar se vacía en
   orden antes de publicar nuevas. Si el búfer se llena, se descarta la más vieja y se
   cuenta en `session.dropped`.
2. Solo se encolan lecturas con timestamp NTP válido. Sin hora real, el backend usaría
   `received_at` y la lectura quedaría con la hora equivocada.
3. Nada bloquea el loop más de lo que ya bloquea hoy.

DoD / tests:
- `arduino-cli compile --fqbn esp32:esp32:esp32 lab_esp32/firmware/do_sensor_node` OK y
  memoria reportada.
- La lógica del búfer (push/pop/overflow) va en un header puro sin dependencias de
  Arduino, con un test host `g++ -std=c++17` que la ejercita. Es el único check ejecutable
  posible sin hardware.

## F6: E2E en vivo con broker local (todo). Ola 3, después de F1 a F5

Stack aislado: `docker compose -p iot-e2e ...` (volúmenes separados, la base de
desarrollo no se toca), broker con `lab_esp32/broker` y `MQTT_HOST=host.docker.internal`.
Docker Desktop tiene 4 GB: levantar solo lo necesario.

Escenarios (cada uno con comandos y salida en `autopilot-evidence/F6-e2e.md`):
1. `migrate` + seeder → `preflight.sh` todo PASS.
2. `mosquitto_pub` con payload igual al del firmware → fila en `sensor_readings`,
   `last_communication` actualizado y visible por la API pública/dashboard.
3. Backend caído 20 min simulado (detener `back`, publicar ≥ 30 lecturas, esperar a que se
   agoten los reintentos del viejo comportamiento acelerando con
   `INGESTION_RETRY_MAX_SECONDS=2`, levantar `back`) → todas llegan, 0 dead letters.
4. Ingestion caído y luego levantado → el broker entregó lo encolado (R2).
5. Engines reales: `SHOW TABLE STATUS` muestra ARCHIVE y Compressed; `iot:storage:tier`
   con filas envejecidas (`UPDATE ... created_at` en el stack e2e) mueve a archivo.
6. `iot:capacity:check` y `iot:logs:archive` corren sin error contra el stack real.
7. Bajar el stack e2e con `-p iot-e2e down -v` (solo ese proyecto).

DoD: los 6 escenarios en PASS con evidencia; si alguno falla, se corrige (o se crea una
subtarea) y se re-ejecuta.

## F7: Revisión y cierre. Ola 4

- Revisión de código del diff `refraccion..HEAD` (agente revisor): correctness,
  seguridad (sin secretos, sin endpoints abiertos) y sobreingeniería (ponytail-review).
  Corregir los hallazgos confirmados.
- Re-correr las tres suites y compararlas con F0.
- Actualizar `lab_esp32/README.md` (pasos del día con preflight) y `lab_esp32/HANDOFF.md`
  (resumen matutino).
- **DoD:** suites ≥ línea base, revisión sin hallazgos críticos abiertos, README listo
  para seguir en el laboratorio.

## Fuera de alcance (decidido)

- Migrar a Postgres/TimescaleDB: no se justifica con los datos medidos (ver la tabla).
- Push a remoto: queda local; el usuario hace push en la mañana.
- Calibración real de la sonda y tipo exacto (analógica/EZO): requieren hardware.
