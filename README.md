# IoT Platform v2

Plataforma de monitoreo IoT desarrollada con Laravel 12 para gestionar dispositivos, sensores, lecturas y alertas en tiempo real.

## Que resuelve este proyecto

Este repositorio implementa un sistema end-to-end para operacion IoT:

- Ingesta de telemetria desde sensores por API.
- Monitoreo visual en dashboard en tiempo real.
- Motor de alertas por reglas configurables.
- Notificacion por correo para eventos criticos.
- Gestion administrativa de catalogos, configuracion y roles.

En terminos de producto, permite pasar de "solo leer datos" a "operar con datos": detectar desbordes, reaccionar mas rapido y mantener trazabilidad de incidentes.

## Propuesta de valor tecnico

Este codigo refleja buenas practicas de ingenieria aplicadas a un caso real:

- Arquitectura por capas: controllers, servicios, modelos, observers, eventos.
- Separacion de responsabilidades: la logica de alertas vive en dominio/observers, no en vistas.
- Diseno orientado a eventos: lectura nueva -> evaluacion -> alerta -> broadcast -> email.
- Seguridad funcional: middleware `auth` y `admin`, validaciones de request, control de API key para ingesta.
- Mantenibilidad: configuracion centralizada en `system_settings`, pruebas automatizadas y estructura clara.

## Estado actual del sistema

Actualmente ya esta implementado:

- Backend web + API con Laravel 12.
- Dashboard con graficas y monitoreo en vivo.
- Ingesta de lecturas por `POST /api/sensors/:sensorId/readings`.
- Evaluacion automatica de reglas con `SensorReadingObserver`.
- Creacion de alertas y broadcast en canal `alerts`.
- Envio de correo para severidad `danger` via `AlertObserver`.
- Simulador Python (`script_datos.py`) para generar trafico de datos.

## Arquitectura y funcionamiento

### Diagrama 1: relacion de componentes

```mermaid
flowchart LR
    SIM[script_datos.py]
    API[Sensor API Controller]
    DBR[(sensor_readings)]
    OBR[SensorReadingObserver]
    ALE[(alerts)]
    OBA[AlertObserver]
    BRS[Canal sensor por Pusher]
    BRA[Canal alerts por Pusher]
    UI[Dashboard Web]
    MAIL[Servicio SMTP]

    SIM --> API
    API --> DBR
    DBR --> OBR
    OBR --> ALE
    ALE --> OBA
    API --> BRS
    OBA --> BRA
    BRS --> UI
    BRA --> UI
    OBA --> MAIL
```

### Diagrama 2: flujo de datos y alertas

```mermaid
sequenceDiagram
    participant Sim as Simulador Python
    participant Api as Laravel API
    participant Db as Base de Datos
    participant Obs as Observers
    participant Dash as Dashboard
    participant Mail as SMTP

    Sim->>Api: GET /api/sensors
    Api-->>Sim: Lista de sensores

    loop Cada ciclo
        Sim->>Api: POST /api/sensors/:sensorId/readings
        Api->>Db: Guarda lectura
        Api-->>Sim: 201 Reading saved
        Api->>Obs: Dispara NewSensorReading
        Obs->>Db: Evalua reglas y crea alertas
        Obs-->>Dash: Broadcast lectura y alerta
        Obs->>Mail: Envia correo si severidad danger
    end
```

### Diagrama 3: modelo de dominio

```mermaid
erDiagram
    DEVICE_TYPE ||--o{ DEVICE : clasifica
    LAB ||--o{ DEVICE : ubica
    DEVICE ||--o{ SENSOR : contiene
    SENSOR_TYPE ||--o{ SENSOR : define
    SENSOR ||--o{ SENSOR_READING : registra
    SENSOR_READING ||--o{ ALERT : dispara
    ALERT_RULE ||--o{ ALERT : origina
    SENSOR_TYPE ||--o{ ALERT_RULE : base
    DEVICE ||--o{ ALERT_RULE : alcance_opcional
    SENSOR ||--o{ ALERT_RULE : alcance_opcional
```

### Diagrama 4: arquitectura por capas

```mermaid
flowchart TB
    subgraph P[Presentacion]
        UI[Blade Views]
        JS[Dashboard JS]
    end

    subgraph A[Aplicacion]
        WC[Web Controllers]
        AC[API Controllers]
        MW[Auth y Admin Middleware]
    end

    subgraph D[Dominio]
        SVC[Services]
        MOD[Models]
        OBS[Observers]
        EVT[Events]
    end

    subgraph I[Infraestructura]
        DB[(MySQL or SQLite)]
        BRC[Pusher Broadcast]
        SMTP[SMTP Mail]
        CACHE[Cache]
    end

    UI --> WC
    JS --> AC
    WC --> SVC
    AC --> SVC
    WC --> MW
    AC --> MW
    SVC --> MOD
    MOD --> OBS
    OBS --> EVT
    MOD --> DB
    EVT --> BRC
    OBS --> SMTP
    SVC --> CACHE
```

### Diagrama 5: flujo de permisos

```mermaid
flowchart TD
    U[Usuario autenticado] --> Q{Es admin}
    Q -- Si --> A1[Gestionar reglas]
    Q -- Si --> A2[Gestionar tipos y labs]
    Q -- Si --> A3[Gestionar roles y email]
    Q -- No --> B1[Ver dashboard]
    Q -- No --> B2[Ver alertas y sensores]
    Q -- No --> B3[Modo solo lectura en configuracion]
```

### Diagrama 6: ciclo de vida de una alerta

```mermaid
stateDiagram-v2
    [*] --> LecturaRecibida
    LecturaRecibida --> EvaluacionReglas
    EvaluacionReglas --> SinAlerta: Valor en rango
    EvaluacionReglas --> AlertaActiva: Valor fuera de rango
    AlertaActiva --> Notificada: severidad danger
    AlertaActiva --> Resuelta: accion usuario
    Notificada --> Resuelta: accion usuario
    Resuelta --> [*]
    SinAlerta --> [*]
```

### Diagrama 7: flujo de ejecucion local

```mermaid
flowchart LR
    DEV[Desarrollador] --> C1[composer install y npm install]
    C1 --> C2[php artisan migrate --seed]
    C2 --> C3[php artisan serve]
    C3 --> C4[python script_datos.py]
    C4 --> C5[Dashboard con datos y alertas]
```

### Diagrama 8: mapa de rutas principales

```mermaid
flowchart TB
    subgraph WEB[Web Routes]
        W1["/dashboard"]
        W2["/devices"]
        W3["/sensors"]
        W4["/alerts"]
        W5["/config"]
    end

    subgraph API[API Routes]
        A1["/api/sensors"]
        A2["/api/sensors/:sensorId/readings"]
        A3["/api/sensors/:sensorId/latest-readings"]
        A4["/api/alerts/active"]
        A5["/api/devices"]
    end

    AUTH[auth middleware] --> WEB
    ADM[admin middleware] --> W5
    KEY[api_key validation] --> A2
```

## Como correr el proyecto

### Requisitos

- PHP 8.2+
- Composer
- Node.js 20+
- npm
- Python 3.10+
- pip

### Instalacion inicial

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
```

### Variables de entorno clave

En `.env` valida al menos:

- `API_KEY` (debe coincidir con la usada por el simulador).
- `DB_CONNECTION` y credenciales de base de datos.
- `BROADCAST_DRIVER`, `PUSHER_APP_KEY`, `PUSHER_APP_CLUSTER` para tiempo real.

Notas:

- `script_datos.py` usa por defecto `IOT_BASE_URL=http://127.0.0.1:8000`.
- Actualmente el simulador define su `API_KEY` en el mismo archivo.

### Ejecucion diaria (orden recomendado)

Terminal 1:

```bash
php artisan serve
```

Terminal 2:

```bash
pip install requests
python script_datos.py
```

Opcional para assets en desarrollo:

```bash
npm run dev
```

## Endpoints principales

- `GET /api/sensors` - lista sensores para simulacion y dashboard.
- `POST /api/sensors/:sensorId/readings` - crea lectura (requiere `api_key` en payload).
- `GET /api/sensors/:sensorId/latest-readings` - ultimas lecturas por sensor.
- `GET /api/alerts/active` - alertas activas para dashboard.
- `GET /api/devices` - dispositivos paginados con metadatos.

## Calidad de codigo y arquitectura limpia

Estos puntos son utiles para explicar la calidad tecnica del repositorio en una entrevista o revision:

- Dominio encapsulado: `SensorReading::checkForAlert()` concentra reglas de disparo.
- Automatizacion desacoplada: `SensorReadingObserver` y `AlertObserver` manejan side effects.
- Eventos en tiempo real: `NewSensorReading` y `NewAlertTriggered` para UI reactiva.
- Servicios de aplicacion: `DashboardMetricsService` y `DeviceService` evitan controllers gordos.
- Roles y autorizacion: middleware `admin` con enforcement en rutas criticas.
- Configuracion dinamica: `SystemSetting` permite operar sin redeploy para ajustes funcionales.
- Pruebas orientadas a comportamiento: permisos, alertas y envio de correo.

## Estructura del repositorio

```text
app/
  Events/
  Http/Controllers/
  Http/Middleware/
  Listeners/
  Models/
  Observers/
  Services/
resources/views/
routes/
database/migrations/
database/seeders/
tests/
script_datos.py
```

## Archivos clave para entender el sistema

- `script_datos.py`
- `routes/api.php`
- `routes/web.php`
- `app/Http/Controllers/Api/SensorApiController.php`
- `app/Models/SensorReading.php`
- `app/Observers/SensorReadingObserver.php`
- `app/Observers/AlertObserver.php`
- `app/Services/DashboardMetricsService.php`
- `app/Services/DeviceService.php`

## Pruebas

```bash
php artisan test
```

## Resumen para quien llega al repo

Si eres reclutador, lider tecnico o miembro nuevo del equipo:

- Este proyecto no es solo CRUD: implementa flujo IoT real con eventos, alertas y operacion.
- Tiene base arquitectonica limpia para evolucionar (mas canales de notificacion, reglas avanzadas, integraciones externas).
- Muestra criterio de ingenieria en separacion de capas, automatizacion de procesos y enfoque en mantenibilidad.
