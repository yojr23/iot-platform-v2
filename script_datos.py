import logging
import os
import queue
import random
import signal
import sys
import threading
import time
import json
import hashlib
import re
import unicodedata
from datetime import datetime

import requests
from requests.exceptions import ConnectionError, RequestException, Timeout
try:
    import paho.mqtt.publish as mqtt_publish
except Exception:  # noqa: BLE001
    mqtt_publish = None

# Endpoint para obtener sensores y publicar payloads MQTT
BASE_URL = os.getenv("IOT_BASE_URL", "http://127.0.0.1:8000")
API_SENSORS_URL = f"{BASE_URL}/api/iot/sensors"
DEFAULT_API_KEY = "E7X1GAFf9xgkdoP69LcYSD4KoNuuYGn_ju01uIY2448"
LOG_LEVEL = os.getenv("IOT_LOG_LEVEL", "INFO").upper()
CYCLE_INTERVAL_SECONDS = max(0.2, float(os.getenv("IOT_CYCLE_INTERVAL", "1.0")))
SENSOR_BATCH_SIZE = max(1, int(os.getenv("IOT_SENSOR_BATCH_SIZE", "3")))
SCHEDULER_MODE = (os.getenv("IOT_SCHEDULER_MODE", "all") or "all").strip().lower()
OUTLIER_PROBABILITY = min(max(float(os.getenv("IOT_OUTLIER_PROBABILITY", "0.02")), 0.0), 0.25)
SLEEP_JITTER_SECONDS = max(0.0, float(os.getenv("IOT_SLEEP_JITTER_SECONDS", "0.0")))
MAX_PARALLEL_SENDS = max(1, int(os.getenv("IOT_MAX_PARALLEL_SENDS", "4")))
SEND_WORKER_COUNT = max(1, int(os.getenv("IOT_SEND_WORKERS", str(MAX_PARALLEL_SENDS))))
SEND_QUEUE_MAXSIZE = max(10, int(os.getenv("IOT_SEND_QUEUE_MAXSIZE", "500")))
DEVICE_QUEUE_MAXSIZE = max(10, int(os.getenv("IOT_DEVICE_QUEUE_MAXSIZE", str(SEND_QUEUE_MAXSIZE))))
SENSORS_FETCH_MAX_RETRIES = max(1, int(os.getenv("IOT_SENSORS_FETCH_MAX_RETRIES", "30")))
SENSORS_FETCH_RETRY_BASE_SECONDS = max(0.5, float(os.getenv("IOT_SENSORS_FETCH_RETRY_BASE_SECONDS", "2.0")))
SENSORS_FETCH_RETRY_MAX_SECONDS = max(
    SENSORS_FETCH_RETRY_BASE_SECONDS,
    float(os.getenv("IOT_SENSORS_FETCH_RETRY_MAX_SECONDS", "20.0")),
)
MQTT_HOST = os.getenv("MQTT_HOST", "127.0.0.1")
MQTT_PORT = max(1, int(os.getenv("MQTT_PORT", "1883")))
MQTT_QOS = max(0, min(2, int(os.getenv("MQTT_QOS", "1"))))
MQTT_CLIENT_ID = os.getenv("MQTT_CLIENT_ID", "iot-platform-v2-simulator")
MQTT_USERNAME = os.getenv("MQTT_USERNAME", "").strip()
MQTT_PASSWORD = os.getenv("MQTT_PASSWORD", "").strip()
MQTT_TOPIC_TEMPLATE = os.getenv("MQTT_TOPIC_TEMPLATE", "iot/{node_id}/readings")
IOT_FIRMWARE_VERSION = os.getenv("IOT_FIRMWARE_VERSION", "sim-1.0.0")
WIFI_RSSI_MIN_DBM = int(os.getenv("IOT_WIFI_RSSI_MIN_DBM", "-75"))
WIFI_RSSI_MAX_DBM = int(os.getenv("IOT_WIFI_RSSI_MAX_DBM", "-55"))

logging.basicConfig(
    level=getattr(logging, LOG_LEVEL, logging.INFO),
    format="%(asctime)s | %(levelname)s | %(message)s",
)
logger = logging.getLogger("iot-simulator")

running = True
SENSOR_LAST_VALUES = {}
DEVICE_SESSION_STATE = {}


def log_event(level, message, **context):
    if context:
        logger.log(level, f"{message} | {context}")
    else:
        logger.log(level, message)


def signal_handler(sig, frame):
    global running
    log_event(logging.INFO, "Deteniendo simulacion por señal del sistema", signal=sig)
    running = False
    sys.exit(0)


signal.signal(signal.SIGINT, signal_handler)


def get_api_key():
    return os.getenv("IOT_API_KEY") or os.getenv("API_KEY") or DEFAULT_API_KEY


def require_api_key(api_key=None):
    effective_key = api_key if api_key is not None else get_api_key()
    if not effective_key:
        log_event(logging.ERROR, "Falta API key. Configura IOT_API_KEY o API_KEY en el entorno")
        sys.exit(1)
    return effective_key


def mask_api_key(api_key):
    if not api_key:
        return ""
    if len(api_key) <= 6:
        return "*" * len(api_key)
    return f"{api_key[:3]}{'*' * (len(api_key) - 6)}{api_key[-3:]}"


def validate_sensor_descriptor(sensor):
    if not isinstance(sensor, dict):
        log_event(logging.WARNING, "Descriptor de sensor con formato inesperado", sensor=sensor)
        return False

    if "id" not in sensor:
        log_event(logging.WARNING, "Sensor sin campo requerido id", sensor=sensor)
        return False

    if not isinstance(sensor.get("id"), int):
        log_event(
            logging.WARNING,
            "Sensor con id en formato inesperado",
            sensor_id=sensor.get("id"),
            sensor=sensor,
        )
        return False

    return True


def normalize_token(value):
    if not value:
        return ""

    value = str(value).strip()
    normalized = unicodedata.normalize("NFKD", value)
    ascii_value = "".join(ch for ch in normalized if not unicodedata.combining(ch))
    ascii_value = re.sub(r"[^a-zA-Z0-9]+", "_", ascii_value).strip("_").lower()
    return ascii_value


def get_sensor_key(sensor):
    sensor_type = sensor.get("sensor_type", {}) or {}
    sensor_name = sensor_type.get("name") or sensor.get("name") or "sensor"
    normalized_name = normalize_token(sensor_name)

    aliases = {
        "temperatura": "temperature",
        "oxigeno_disuelto": "dissolved_oxygen",
        "ph": "ph",
        "conductividad": "conductivity",
        "turbidez": "turbidity",
        "orp": "orp",
        "caudal": "flow_rate",
    }

    return aliases.get(normalized_name, normalized_name or "sensor")


def get_node_id(sensor):
    device = sensor.get("device", {}) or {}
    serial = device.get("serial_number")
    if serial:
        return normalize_token(serial)

    device_id = sensor.get("device_id")
    if isinstance(device_id, int):
        return f"device_{device_id:03d}"

    return "device_unknown"


def get_location(sensor):
    device = sensor.get("device", {}) or {}
    lab = device.get("lab", {}) or {}
    if lab.get("name"):
        return lab.get("name")
    return "Ubicacion no definida"


def get_session_payload(node_id):
    state = DEVICE_SESSION_STATE.get(node_id)
    if state is None:
        state = {
            "boot_count": 1,
            "reading_index": 0,
            "started_at": time.time(),
        }
        DEVICE_SESSION_STATE[node_id] = state

    state["reading_index"] += 1

    return {
        "uptime_ms": max(0, int((time.time() - state["started_at"]) * 1000)),
        "boot_count": state["boot_count"],
        "reading_index": state["reading_index"],
    }


def build_qc_checksum(node_id, sensor_key, value, iso_timestamp):
    checksum_seed = f"{node_id}|{sensor_key}|{value:.6f}|{iso_timestamp}"
    return hashlib.sha1(checksum_seed.encode("utf-8")).hexdigest()[:4]


def build_sensor_payload(sensor, value, now_dt):
    sensor_type = sensor.get("sensor_type", {}) or {}
    unit = sensor_type.get("unit", "")
    sensor_key = get_sensor_key(sensor)
    node_id = get_node_id(sensor)
    iso_timestamp = now_dt.strftime("%Y-%m-%dT%H:%M:%SZ")

    return {
        "device": {
            "node_id": node_id,
            "firmware_version": IOT_FIRMWARE_VERSION,
            "location": get_location(sensor),
        },
        "timestamp": iso_timestamp,
        "session": get_session_payload(node_id),
        "network": {
            "wifi_rssi_dbm": random.randint(min(WIFI_RSSI_MIN_DBM, WIFI_RSSI_MAX_DBM), max(WIFI_RSSI_MIN_DBM, WIFI_RSSI_MAX_DBM)),
            "mqtt_reconnections": 0,
        },
        "sensors": {
            sensor_key: {
                "value": value,
                "unit": unit,
                "sensor_model": sensor_type.get("name", "Sensor generico"),
                "status": "ok",
            }
        },
        "qc": {
            "checksum": build_qc_checksum(node_id, sensor_key, value, iso_timestamp),
            "valid": True,
        },
    }, sensor_key, node_id


def get_sensors(get_fn=None, api_key=None):
    get_fn = get_fn or requests.get
    effective_key = require_api_key(api_key)
    headers = {
        "Accept": "application/json",
        "X-Device-Key": effective_key,
    }

    for attempt in range(1, SENSORS_FETCH_MAX_RETRIES + 1):
        try:
            log_event(
                logging.INFO,
                "Consultando sensores desde API",
                url=API_SENSORS_URL,
                attempt=attempt,
                max_retries=SENSORS_FETCH_MAX_RETRIES,
            )
            response = get_fn(API_SENSORS_URL, headers=headers, timeout=10)
        except Timeout as exc:
            retry_seconds = min(SENSORS_FETCH_RETRY_MAX_SECONDS, SENSORS_FETCH_RETRY_BASE_SECONDS * attempt)
            log_event(
                logging.WARNING,
                "Timeout al obtener lista de sensores; reintentando",
                attempt=attempt,
                retry_in_seconds=retry_seconds,
                error=str(exc),
            )
            time.sleep(retry_seconds)
            continue
        except ConnectionError as exc:
            retry_seconds = min(SENSORS_FETCH_RETRY_MAX_SECONDS, SENSORS_FETCH_RETRY_BASE_SECONDS * attempt)
            log_event(
                logging.WARNING,
                "Error de conexión al obtener sensores; reintentando",
                attempt=attempt,
                retry_in_seconds=retry_seconds,
                error=str(exc),
            )
            time.sleep(retry_seconds)
            continue
        except RequestException as exc:
            retry_seconds = min(SENSORS_FETCH_RETRY_MAX_SECONDS, SENSORS_FETCH_RETRY_BASE_SECONDS * attempt)
            log_event(
                logging.WARNING,
                "Error HTTP al obtener sensores; reintentando",
                attempt=attempt,
                retry_in_seconds=retry_seconds,
                error=str(exc),
            )
            time.sleep(retry_seconds)
            continue

        if response.status_code == 200:
            break

        if response.status_code == 429:
            retry_after_header = response.headers.get("Retry-After")
            try:
                retry_after_seconds = float(retry_after_header) if retry_after_header is not None else 0.0
            except ValueError:
                retry_after_seconds = 0.0

            retry_seconds = max(
                retry_after_seconds,
                min(SENSORS_FETCH_RETRY_MAX_SECONDS, SENSORS_FETCH_RETRY_BASE_SECONDS * attempt),
            )
            log_event(
                logging.WARNING,
                "Rate limit al obtener sensores; esperando antes de reintentar",
                attempt=attempt,
                retry_in_seconds=retry_seconds,
                status_code=response.status_code,
                body=response.text[:180],
            )
            time.sleep(retry_seconds)
            continue

        log_event(
            logging.WARNING,
            "Respuesta inesperada al obtener sensores; reintentando",
            status_code=response.status_code,
            attempt=attempt,
            body=response.text[:180],
        )
        retry_seconds = min(SENSORS_FETCH_RETRY_MAX_SECONDS, SENSORS_FETCH_RETRY_BASE_SECONDS * attempt)
        time.sleep(retry_seconds)
    else:
        log_event(
            logging.ERROR,
            "No se pudo obtener la lista de sensores tras múltiples reintentos",
            max_retries=SENSORS_FETCH_MAX_RETRIES,
        )
        sys.exit(1)

    try:
        sensors = response.json()
    except ValueError as exc:
        log_event(
            logging.ERROR,
            "Formato inesperado en respuesta de sensores (JSON inválido)",
            error=str(exc),
            body=response.text[:300],
        )
        sys.exit(1)

    if not isinstance(sensors, list):
        log_event(
            logging.ERROR,
            "Formato inesperado en respuesta de sensores (se esperaba lista)",
            response_type=type(sensors).__name__,
        )
        sys.exit(1)

    valid_sensors = [sensor for sensor in sensors if validate_sensor_descriptor(sensor)]

    if len(valid_sensors) != len(sensors):
        log_event(
            logging.WARNING,
            "Se descartaron sensores con formato inválido",
            received=len(sensors),
            valid=len(valid_sensors),
        )

    return valid_sensors


def simulate_value(sensor):
    # Simula un valor estable dentro del rango; ocasionalmente dispara un outlier.
    sensor_type = sensor.get("sensor_type", {}) or {}
    min_r = sensor_type.get("min_range")
    max_r = sensor_type.get("max_range")

    if min_r is None or max_r is None or min_r >= max_r:
        log_event(
            logging.WARNING,
            "Rango de sensor inesperado o incompleto; usando valor aleatorio por defecto",
            sensor_id=sensor.get("id"),
            min_range=min_r,
            max_range=max_r,
        )
        return random.uniform(0, 100)

    sensor_id = sensor.get("id")
    range_span = max_r - min_r
    safe_min = min_r + (0.05 * range_span)
    safe_max = max_r - (0.05 * range_span)

    # Valor inicial centrado en rango seguro.
    previous_value = SENSOR_LAST_VALUES.get(sensor_id)
    if previous_value is None:
        previous_value = random.uniform(safe_min, safe_max)

    # Deriva suave para evitar saltos bruscos y lecturas irreales.
    max_step = max(0.02 * range_span, 0.15)
    next_value = previous_value + random.uniform(-max_step, max_step)
    next_value = max(safe_min, min(safe_max, next_value))

    # Outlier raro y controlado.
    if random.random() < OUTLIER_PROBABILITY:
        outlier_margin = max(0.04 * range_span, 0.5)
        if random.random() < 0.5:
            next_value = min_r - random.uniform(outlier_margin * 0.6, outlier_margin * 1.4)
        else:
            next_value = max_r + random.uniform(outlier_margin * 0.6, outlier_margin * 1.4)

    SENSOR_LAST_VALUES[sensor_id] = next_value
    return next_value


def send_sensor_data(sensor, api_key=None, now_fn=None, publish_fn=None):
    if not validate_sensor_descriptor(sensor):
        log_event(logging.ERROR, "No se puede enviar lectura: descriptor de sensor inválido")
        return

    effective_key = require_api_key(api_key)
    now_fn = now_fn or datetime.now
    publish_fn = publish_fn or (mqtt_publish.single if mqtt_publish else None)

    if publish_fn is None:
        log_event(
            logging.ERROR,
            "No se pudo inicializar el cliente MQTT. Instala paho-mqtt o inyecta publish_fn.",
        )
        return

    value = simulate_value(sensor)
    now_dt = now_fn()
    payload, sensor_key, node_id = build_sensor_payload(sensor, value, now_dt)
    topic = MQTT_TOPIC_TEMPLATE.format(
        node_id=node_id,
        sensor_key=sensor_key,
        sensor_id=sensor.get("id"),
        device_id=sensor.get("device_id", "unknown"),
    )
    auth = {"username": MQTT_USERNAME, "password": MQTT_PASSWORD} if MQTT_USERNAME else None

    try:
        publish_fn(
            topic,
            payload=json.dumps(payload, ensure_ascii=False),
            qos=MQTT_QOS,
            retain=False,
            hostname=MQTT_HOST,
            port=MQTT_PORT,
            client_id=MQTT_CLIENT_ID,
            auth=auth,
        )
    except Exception as exc:  # noqa: BLE001
        log_event(
            logging.ERROR,
            "Error publicando lectura MQTT",
            sensor_id=sensor["id"],
            topic=topic,
            host=MQTT_HOST,
            port=MQTT_PORT,
            error=str(exc),
        )
        return

    sensor_type = sensor.get("sensor_type", {}) or {}
    sensor_name = sensor_type.get("name", "Tipo desconocido")
    unit = sensor_type.get("unit", "")

    log_event(
        logging.INFO,
        f"Lectura MQTT publicada para sensor {sensor['id']} ({sensor_name}): {value:.2f} {unit}",
        sensor_id=sensor["id"],
        topic=topic,
        mqtt_host=MQTT_HOST,
        mqtt_port=MQTT_PORT,
        api_key=mask_api_key(effective_key),
    )

def get_sensor_device_key(sensor):
    device_id = sensor.get("device_id")
    if isinstance(device_id, int):
        return f"device:{device_id}"
    return "device:unknown"

def device_sender_worker(device_key, send_queue):
    while running:
        try:
            item = send_queue.get(timeout=0.5)
        except queue.Empty:
            continue

        if item is None:
            send_queue.task_done()
            break

        sensor, api_key = item
        try:
            send_sensor_data(sensor, api_key=api_key)
        except Exception as exc:  # noqa: BLE001
            log_event(logging.ERROR, "Error inesperado en worker por dispositivo", device_key=device_key, error=str(exc))
        finally:
            send_queue.task_done()


if __name__ == "__main__":
    log_event(
        logging.INFO,
        "Iniciando simulador IoT",
        base_url=BASE_URL,
        mqtt_host=MQTT_HOST,
        mqtt_port=MQTT_PORT,
        mqtt_topic_template=MQTT_TOPIC_TEMPLATE,
        mqtt_qos=MQTT_QOS,
    )

    api_key = require_api_key()
    log_event(logging.INFO, "API key cargada", api_key=mask_api_key(api_key))

    sensors = get_sensors(api_key=api_key)
    log_event(
        logging.INFO,
        "Sensores listos para simulacion",
        sensor_count=len(sensors),
        interval_seconds=CYCLE_INTERVAL_SECONDS,
        scheduler_mode=SCHEDULER_MODE,
        batch_size=min(SENSOR_BATCH_SIZE, len(sensors)),
        max_parallel_sends=MAX_PARALLEL_SENDS,
        send_worker_count=SEND_WORKER_COUNT,
        send_queue_maxsize=SEND_QUEUE_MAXSIZE,
        device_queue_maxsize=DEVICE_QUEUE_MAXSIZE,
        outlier_probability=OUTLIER_PROBABILITY,
    )

    device_queues = {}
    device_workers = {}

    for sensor in sensors:
        device_key = get_sensor_device_key(sensor)
        if device_key in device_queues:
            continue

        device_queue = queue.Queue(maxsize=DEVICE_QUEUE_MAXSIZE)
        worker = threading.Thread(
            target=device_sender_worker,
            args=(device_key, device_queue),
            name=f"iot-{device_key}",
            daemon=True,
        )
        worker.start()
        device_queues[device_key] = device_queue
        device_workers[device_key] = worker

    sensor_cursor = 0

    try:
        while running:
            if not sensors:
                log_event(logging.WARNING, "No hay sensores disponibles para enviar lecturas")
                time.sleep(CYCLE_INTERVAL_SECONDS)
                continue

            if SCHEDULER_MODE == "sample":
                current_batch_size = min(SENSOR_BATCH_SIZE, len(sensors))
                selected_sensors = random.sample(sensors, current_batch_size)
            elif SCHEDULER_MODE == "round_robin":
                current_batch_size = min(SENSOR_BATCH_SIZE, len(sensors))
                selected_sensors = []
                for offset in range(current_batch_size):
                    idx = (sensor_cursor + offset) % len(sensors)
                    selected_sensors.append(sensors[idx])
                sensor_cursor = (sensor_cursor + current_batch_size) % len(sensors)
            else:
                # Modo por defecto: envía lecturas de todos los sensores en cada ciclo.
                selected_sensors = sensors

            for sensor in selected_sensors:
                device_key = get_sensor_device_key(sensor)
                device_queue = device_queues.get(device_key)
                if device_queue is None:
                    device_queue = queue.Queue(maxsize=DEVICE_QUEUE_MAXSIZE)
                    worker = threading.Thread(
                        target=device_sender_worker,
                        args=(device_key, device_queue),
                        name=f"iot-{device_key}",
                        daemon=True,
                    )
                    worker.start()
                    device_queues[device_key] = device_queue
                    device_workers[device_key] = worker

                try:
                    device_queue.put((sensor, api_key), timeout=0.25)
                except queue.Full:
                    log_event(
                        logging.WARNING,
                        "Cola FIFO del dispositivo llena, se omite lectura para evitar bloqueo global",
                        sensor_id=sensor.get("id"),
                        device_key=device_key,
                        queue_maxsize=DEVICE_QUEUE_MAXSIZE,
                    )

            sleep_seconds = CYCLE_INTERVAL_SECONDS + random.uniform(0, SLEEP_JITTER_SECONDS)
            time.sleep(sleep_seconds)
    finally:
        for device_key, device_queue in device_queues.items():
            inserted = False
            while not inserted:
                try:
                    device_queue.put(None, timeout=0.2)
                    inserted = True
                except queue.Full:
                    log_event(
                        logging.WARNING,
                        "Esperando espacio para detener worker por dispositivo",
                        device_key=device_key,
                    )

        for worker in device_workers.values():
            worker.join(timeout=3.0)
