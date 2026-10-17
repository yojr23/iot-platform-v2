# HANDOFF: prueba desde un PC Windows en la LAN del laboratorio

Para la sesión de Claude que corre en el PC Windows. No incluye secretos: las claves MQTT se las pides al usuario.

## Contexto

- Plataforma IoT (SINOA). La sonda de oxígeno disuelto conecta a un ESP32, que publica por MQTT a un broker Mosquitto. De ahí siguen la ingesta, el backend Laravel con MySQL y el portal web.
- Todo el servidor corre en una **Mac** de la misma red WiFi: SSID `E-bio`, IP `172.16.122.235`. La IP viene por DHCP y puede cambiar. Si algo no conecta, pide al usuario que la confirme.
- ESP32 real conectado a `E-bio`, IP `172.16.122.233`. Publica cada 5 s en el topic `iot/lab_postgrado_nodo_01/readings`.
- Estado verificado en la Mac: ESP32 → broker → base de datos OK, con unos 1 s de latencia.
- **Objetivo de esta sesión:** probar desde otro equipo de la LAN que el tráfico pasa por el router. Concretamente: (1) ver en vivo lo que publica el ESP32 y (2) inyectar una lectura de prueba que llegue a la base de datos de la Mac.

| Servicio en la Mac | Puerto |
|---|---|
| Broker MQTT (usuario y clave obligatorios, con ACL) | 1883 |
| API backend (`/api/health`) | 8000 |
| Portal (Vite) | 5173 |
| WebSocket Reverb | 8080 |

Usuarios MQTT:
- `ingestion`: puede leer `iot/+/readings`. Úsalo para mirar.
- `lab_postgrado_nodo_01`: solo puede publicar en su propio topic. Úsalo para inyectar.

**Pide las dos claves al usuario. No las escribas en archivos del repositorio ni en commits.**

## Pasos (PowerShell)

### 0. Herramientas
Instala Mosquitto para Windows (https://mosquitto.org/download/; solo hacen falta los clientes). Después:
```powershell
$env:Path += ";C:\Program Files\mosquitto"
mosquitto_sub --help | Select-Object -First 1
```

### 1. Alcance de red hacia la Mac
```powershell
Test-NetConnection 172.16.122.235 -Port 1883
Test-NetConnection 172.16.122.235 -Port 8000
curl.exe http://172.16.122.235:8000/api/health
```
Si `TcpTestSucceeded` da `False`, este PC tiene que estar en `E-bio`, no en `UNAB-Jardin` ni en `eduroam`. Si ya lo está, las causas posibles son el aislamiento de clientes del router o el firewall de la Mac. Avísale al usuario.

### 2. Ver en vivo lo que publica el ESP32 (deja esta ventana abierta)
```powershell
mosquitto_sub -h 172.16.122.235 -u ingestion -P "<CLAVE_INGESTION>" -t "iot/+/readings" -v
```
Esperado: un JSON cada 5 s con `"node_id":"lab_postgrado_nodo_01"` y `sensors.dissolved_oxygen.value`.

### 3. Inyectar una lectura de prueba (en otra ventana)
En PowerShell las comillas de un JSON en línea dan problemas, así que el payload va en un archivo:
```powershell
$n = [DateTimeOffset]::UtcNow.ToUnixTimeSeconds()
$ts = (Get-Date).ToUniversalTime().ToString("yyyy-MM-ddTHH:mm:ssZ")
@"
{"device":{"node_id":"lab_postgrado_nodo_01","firmware_version":"pc-windows-test"},"timestamp":"$ts","session":{"boot_count":$n,"reading_index":$n,"uptime_ms":1000},"network":{"wifi_rssi_dbm":-60},"sensors":{"dissolved_oxygen":{"value":9.99,"unit":"mg/L","sensor_model":"pc-test","status":"ok"}},"qc":{"valid":true}}
"@ | Set-Content -Encoding ascii payload.json
mosquitto_pub -h 172.16.122.235 -u lab_postgrado_nodo_01 -P "<CLAVE_NODO>" -q 1 -t "iot/lab_postgrado_nodo_01/readings" -f payload.json -d
```
- `value 9.99` sirve para distinguir esta lectura de las del ESP32, que rondan 0,7 mg/L con la calibración de ejemplo.
- `$n` cambia en cada envío. Si lo repites, la plataforma lo descarta como duplicado, porque identifica cada lectura por `boot_count` + `reading_index`.
- Esperado: `-d` muestra `PUBACK` y la ventana del paso 2 imprime el mensaje.

### 4. Confirmar que llegó a la base de datos
Desde Windows no hay acceso directo a MySQL. Pídele al usuario que en la Mac (o su sesión de Claude) corra:
```bash
docker compose exec db sh -c 'mysql -uroot -p"$MYSQL_ROOT_PASSWORD" "$MYSQL_DATABASE" -e "SELECT id,value,reading_time FROM sensor_readings ORDER BY id DESC LIMIT 5"'
```
Esperado: una fila con `9.99` en uno o dos segundos.

### 5. Portal
Abre `http://172.16.122.235:5173`. La página carga, pero el login, la API y el tiempo real desde otro PC fallan hasta que en la Mac se configuren las variables LAN (`CORS_ALLOWED_ORIGINS`, `SANCTUM_STATEFUL_DOMAINS`, `REVERB_ALLOWED_ORIGINS`, `VITE_API_BASE_URL`, `VITE_PUSHER_HOST`). Eso se hace en la sesión de la Mac, no aquí.

## Diagnóstico rápido

| Síntoma | Causa |
|---|---|
| `Connection refused` / timeout en 1883 | PC en otra red, aislamiento de clientes en el router, firewall de la Mac o IP cambiada |
| `not authorised` / rc=5 | Clave equivocada, o el usuario no tiene permiso en ese topic (ACL) |
| El pub da PUBACK pero no aparece en la BD | `node_id` del JSON distinto del topic, o `boot_count`/`reading_index` repetidos |
| `mosquitto_sub` no recibe nada del ESP32 | Al ESP32 le falta alimentación o WiFi. Que el usuario mire el monitor serie en la Mac |

## Reglas
- No guardes claves en archivos versionados. Al terminar, borra `payload.json`.
- Esta sesión solo hace pruebas de cliente. No cambia nada del servidor.
