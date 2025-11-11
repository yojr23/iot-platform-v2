import requests
import json
from datetime import datetime
import time
import signal
import sys
import random

# Endpoint para obtener sensores y enviar lecturas
API_SENSORS_URL = "http://127.0.0.1:8000/api/sensors"
API_URL = "http://127.0.0.1:8000/api/sensors/{sensor_id}/readings"
API_KEY = "E7X1GAFf9xgkdoP69LcYSD4KoNuuYGn_ju01uIY2448"

running = True

def signal_handler(sig, frame):
    global running
    print("\nDeteniendo el envío de datos...")
    running = False
    sys.exit(0)

signal.signal(signal.SIGINT, signal_handler)

def get_sensors():
    try:
        response = requests.get(API_SENSORS_URL)
        if response.status_code == 200:
            return response.json()  # Espera una lista de sensores con sus tipos y rangos
        else:
            print("No se pudo obtener la lista de sensores:", response.status_code)
            sys.exit(1)
    except Exception as e:
        print("Error al obtener sensores:", str(e))
        sys.exit(1)

def simulate_value(sensor):
    # Simula un valor dentro del rango, pero a veces genera un valor fuera de rango para alertas
    min_r = sensor['sensor_type']['min_range']
    max_r = sensor['sensor_type']['max_range']
    # 10% de las veces, genera un valor fuera de rango
    if random.random() < 0.4:
        if random.random() < 0.5:
            return min_r - random.uniform(1, 5)  # Debajo del mínimo
        else:
            return max_r + random.uniform(1, 5)  # Encima del máximo
    # Valor normal dentro del rango
    return random.uniform(min_r + 0.1, max_r - 0.1)

def send_sensor_data(sensor):
    headers = {
        "Content-Type": "application/json",
        "Accept": "application/json"
    }
    value = simulate_value(sensor)
    payload = {
        "value": value,
        "reading_time": datetime.now().strftime("%Y-%m-%d %H:%M:%S"),
        "api_key": API_KEY
    }
    try:
        response = requests.post(
            API_URL.format(sensor_id=sensor['id']),
            data=json.dumps(payload),
            headers=headers,
            timeout=5
        )
        if response.status_code == 201:
            print(f"Sensor {sensor['id']} ({sensor['sensor_type']['name']}): {value:.2f} {sensor['sensor_type']['unit']}")
        else:
            print(f"Error al enviar al sensor {sensor['id']}: {response.status_code} - {response.text}")
    except Exception as e:
        print(f"Error de conexión con el sensor {sensor['id']}: {str(e)}")

if __name__ == "__main__":
    interval = 3  # segundos entre cada ciclo
    print("Obteniendo sensores de la API...")
    sensors = get_sensors()
    print(f"Simulando {len(sensors)} sensores. Presiona Ctrl+C para detener.")
    while running:
        for sensor in sensors:
            send_sensor_data(sensor)
        time.sleep(interval)