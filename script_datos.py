import logging
import os
import random
import signal
import sys
import time
from datetime import datetime

import requests
from requests.exceptions import ConnectionError, RequestException, Timeout

# Endpoint para obtener sensores y enviar lecturas
BASE_URL = os.getenv("IOT_BASE_URL", "http://127.0.0.1:8000")
API_SENSORS_URL = f"{BASE_URL}/api/iot/sensors"
API_URL = f"{BASE_URL}/api/sensors/{{sensor_id}}/readings"
DEFAULT_API_KEY = "E7X1GAFf9xgkdoP69LcYSD4KoNuuYGn_ju01uIY2448"
LOG_LEVEL = os.getenv("IOT_LOG_LEVEL", "INFO").upper()
CYCLE_INTERVAL_SECONDS = max(3, int(os.getenv("IOT_CYCLE_INTERVAL", "10")))
SENSOR_BATCH_SIZE = max(1, int(os.getenv("IOT_SENSOR_BATCH_SIZE", "3")))
OUTLIER_PROBABILITY = min(max(float(os.getenv("IOT_OUTLIER_PROBABILITY", "0.02")), 0.0), 0.25)
SLEEP_JITTER_SECONDS = max(0.0, float(os.getenv("IOT_SLEEP_JITTER_SECONDS", "2.0")))

logging.basicConfig(
    level=getattr(logging, LOG_LEVEL, logging.INFO),
    format="%(asctime)s | %(levelname)s | %(message)s",
)
logger = logging.getLogger("iot-simulator")

running = True
SENSOR_LAST_VALUES = {}


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


def get_sensors(get_fn=None, api_key=None):
    get_fn = get_fn or requests.get
    effective_key = require_api_key(api_key)
    headers = {
        "Accept": "application/json",
        "X-Device-Key": effective_key,
    }

    try:
        log_event(logging.INFO, "Consultando sensores desde API", url=API_SENSORS_URL)
        response = get_fn(API_SENSORS_URL, headers=headers, timeout=10)
    except Timeout as exc:
        log_event(logging.ERROR, "Timeout al obtener lista de sensores", error=str(exc))
        sys.exit(1)
    except ConnectionError as exc:
        log_event(logging.ERROR, "Error de conexión al obtener sensores", error=str(exc))
        sys.exit(1)
    except RequestException as exc:
        log_event(logging.ERROR, "Error HTTP al obtener sensores", error=str(exc))
        sys.exit(1)

    if response.status_code != 200:
        log_event(
            logging.ERROR,
            "Respuesta inesperada al obtener sensores",
            status_code=response.status_code,
            body=response.text[:300],
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


def send_sensor_data(sensor, api_key=None, now_fn=None, post_fn=None):
    if not validate_sensor_descriptor(sensor):
        log_event(logging.ERROR, "No se puede enviar lectura: descriptor de sensor inválido")
        return

    headers = {"Content-Type": "application/json", "Accept": "application/json"}
    effective_key = require_api_key(api_key)
    now_fn = now_fn or datetime.now
    post_fn = post_fn or requests.post

    value = simulate_value(sensor)
    payload = {
        "value": value,
        "reading_time": now_fn().strftime("%Y-%m-%d %H:%M:%S"),
        "api_key": effective_key,
    }

    url = API_URL.format(sensor_id=sensor["id"])

    try:
        response = post_fn(url, json=payload, headers=headers, timeout=5)
    except Timeout as exc:
        log_event(logging.ERROR, "Timeout enviando lectura", sensor_id=sensor["id"], url=url, error=str(exc))
        return
    except ConnectionError as exc:
        log_event(logging.ERROR, "Error de conexión enviando lectura", sensor_id=sensor["id"], url=url, error=str(exc))
        return
    except RequestException as exc:
        log_event(logging.ERROR, "Error HTTP enviando lectura", sensor_id=sensor["id"], url=url, error=str(exc))
        return

    sensor_type = sensor.get("sensor_type", {}) or {}
    sensor_name = sensor_type.get("name", "Tipo desconocido")
    unit = sensor_type.get("unit", "")

    if response.status_code == 201:
        log_event(
            logging.INFO,
            f"Sensor {sensor['id']} ({sensor_name}): {value:.2f} {unit}",
            sensor_id=sensor["id"],
            status_code=response.status_code,
        )
        return

    body_snippet = (response.text or "")[:300]

    if response.status_code == 401:
        log_event(
            logging.WARNING,
            "Lectura rechazada por API key inválida",
            sensor_id=sensor["id"],
            status_code=response.status_code,
            api_key=mask_api_key(effective_key),
            body=body_snippet,
        )
    elif response.status_code == 403:
        log_event(
            logging.WARNING,
            "Lectura rechazada: dispositivo inactivo o sin permisos",
            sensor_id=sensor["id"],
            status_code=response.status_code,
            body=body_snippet,
        )
    elif response.status_code == 422:
        log_event(
            logging.WARNING,
            "Lectura rechazada: payload/campos inválidos o formato inesperado",
            sensor_id=sensor["id"],
            status_code=response.status_code,
            payload={
                "value": payload["value"],
                "reading_time": payload["reading_time"],
                "api_key": mask_api_key(payload["api_key"]),
            },
            body=body_snippet,
        )
    elif response.status_code == 429:
        log_event(
            logging.WARNING,
            "Rate limit alcanzado al enviar lecturas",
            sensor_id=sensor["id"],
            status_code=response.status_code,
            body=body_snippet,
        )
    elif response.status_code >= 500:
        log_event(
            logging.ERROR,
            "Error del servidor al procesar lectura",
            sensor_id=sensor["id"],
            status_code=response.status_code,
            body=body_snippet,
        )
    else:
        log_event(
            logging.WARNING,
            "Respuesta inesperada al enviar lectura",
            sensor_id=sensor["id"],
            status_code=response.status_code,
            body=body_snippet,
        )


if __name__ == "__main__":
    log_event(logging.INFO, "Iniciando simulador IoT", base_url=BASE_URL)

    api_key = require_api_key()
    log_event(logging.INFO, "API key cargada", api_key=mask_api_key(api_key))

    sensors = get_sensors(api_key=api_key)
    log_event(
        logging.INFO,
        "Sensores listos para simulacion",
        sensor_count=len(sensors),
        interval_seconds=CYCLE_INTERVAL_SECONDS,
        batch_size=min(SENSOR_BATCH_SIZE, len(sensors)),
        outlier_probability=OUTLIER_PROBABILITY,
    )

    while running:
        if not sensors:
            log_event(logging.WARNING, "No hay sensores disponibles para enviar lecturas")
            time.sleep(CYCLE_INTERVAL_SECONDS)
            continue

        current_batch_size = min(SENSOR_BATCH_SIZE, len(sensors))
        selected_sensors = random.sample(sensors, current_batch_size)

        for sensor in selected_sensors:
            send_sensor_data(sensor, api_key=api_key)

        sleep_seconds = CYCLE_INTERVAL_SECONDS + random.uniform(0, SLEEP_JITTER_SECONDS)
        time.sleep(sleep_seconds)
