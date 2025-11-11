<div align="center">
  
  <h1>IoT Platform · Smart Classroom Monitoring</h1>
  <p>End-to-end IoT monitoring platform built with a clean Laravel 12 architecture.</p>
</div>

---

## 📌 About the Project

This repository contains an IoT monitoring platform designed to manage connected devices and sensors across classrooms. The application delivers real-time dashboards, configurable alerting, and an administrative experience that showcases modern Laravel engineering practices: a layered domain architecture, SOLID-compliant services, and an event-driven design that keeps the codebase extensible.

### Highlights
- 🛰️ **Device & Sensor Management** – register, classify, and monitor IoT hardware by classroom or type.
- 📈 **Real-Time Dashboard** – live metrics and charts powered by Pusher Channels and Chart.js.
- 🚨 **Rule-Based Alerting** – configurable thresholds, severity levels, and automated danger notifications via email.
- 🔐 **Role-Aware Access Control** – Laravel Sanctum API tokens, admin middleware, and fine-grained dashboards.
- 🧪 **Confidence in Code** – feature and unit tests covering alert workflows, email delivery, and dashboard metrics.

---

## 🏗️ Architecture Overview

### Clean MVC + Domain Layering
The platform follows Laravel’s MVC conventions while introducing dedicated **Domain Services**, **Observers**, and **Event/Listener** pipelines to keep business rules isolated from controllers and views.

- **Presentation Layer:** Blade templates and Tailwind/Bootstrap components under `resources/views`.
- **Application Layer:** REST controllers in `app/Http/Controllers`, form validation, Sanctum guards, and middleware.
- **Domain Layer:** Business logic encapsulated in services (`app/Services`), observers (`app/Observers`), and domain events (`app/Events`).
- **Infrastructure Layer:** Eloquent models (`app/Models`), database migrations, factories, and seeders.

### Directory Structure at a Glance
```
app/
  Events/           # Domain events for broadcasting & async workflows
  Http/
    Controllers/    # Web + API controllers (separated by context)
    Middleware/     # Role-based access control (EnsureUserIsAdmin)
  Listeners/        # Event listeners (e.g., UpdateDeviceLastCommunication)
  Mail/             # Mailable classes for alert notifications
  Models/           # Eloquent models with relationships and scopes
  Observers/        # Model observers handling side effects
  Providers/        # Service providers registering bindings & observers
  Services/         # Domain services (dashboard metrics, device logic)

resources/views/
  dashboard/        # Modular dashboard partials (summary, alerts, charts)
  layouts/          # Reusable layouts and navigation components
  ...               # CRUD UIs for devices, sensors, alerts, configuration

routes/
  web.php           # Authenticated dashboard, administration, and CRUD routes
  api.php           # Token-protected endpoints for IoT devices & integrations

database/
  migrations/       # Schema definitions (devices, sensors, alerts, roles, prefs)
  factories/        # Model factories for realistic test data
  seeders/          # Default catalog (device types, sensor types, alert rules)
```

---

## 🧭 SOLID Principles in Practice

| Principle | Implementation | Notes |
|-----------|----------------|-------|
| **Single Responsibility** | Domain services (`DashboardMetricsService`, `DeviceService`) encapsulate business rules; observers manage side effects. | Future work: extract email delivery from `Alert` model into a dedicated notification service. |
| **Open/Closed** | Event-driven architecture (model observers, broadcast events) allows new behaviors without touching existing classes. | Enhancement path: introduce alert strategies for custom rule types. |
| **Liskov Substitution** | Controllers and models extend Laravel bases without breaking contracts. | Fully compliant. |
| **Interface Segregation** | Traits (`HasFactory`, `Notifiable`) provide focused capabilities. | Next step: add service interfaces to maximize testability and swapability. |
| **Dependency Inversion** | Constructor injection and service providers decouple controllers from concrete implementations. | Evolution: adopt repository interfaces for persistence boundaries. |

**SOLID Score:** 7/10 — strong foundation with a clear roadmap to full decoupling.

---

## ⚙️ Core Features

### Device & Sensor Lifecycle
- CRUD for devices, types, classrooms, sensors, and sensor types.
- Device activation toggles, API key provisioning, MAC/IP tracking, last communication timestamps.
- User-specific dashboard preferences via `DashboardPreference`.

### Real-Time Monitoring
- WebSocket broadcasting (`App\Events\NewSensorReading`) to Pusher Channels.
- Live charts and alert counters powered by Chart.js and lightweight-charts.
- Personalized monitors persisted per user.

### Alert Engine
- Configurable rules scoped to sensor types, devices, or individual sensors.
- Severity levels (`info`, `warning`, `danger`) with danger-level email escalation.
- Observers (`SensorReadingObserver`, `AlertObserver`) trigger automated workflows.
- Alert history management, bulk resolution, and unresolved counters in the UI.

### Security & Access Control
- Laravel Auth with Sanctum tokens for API authentication.
- Middleware-based role enforcement (`EnsureUserIsAdmin`).
- API key validation for IoT device ingestion.
- Comprehensive request validation and structured logging.

### Testing
- **Feature tests:** alert email workflows, admin access policies, CRUD cases.
- **Unit tests:** dashboard metrics aggregation, alert evaluation logic.
- Factories + seeders provide rich fixtures for demos and CI pipelines.

---

## 🛠️ Technology Stack

| Layer | Technology | Purpose |
|-------|------------|---------|
| Backend | Laravel 12, PHP 8.2 | MVC framework, dependency injection, queues, events, mailing |
| Auth & APIs | Laravel Sanctum | SPA authentication and token issuing for devices |
| Real-Time | Pusher Channels, Laravel Echo | Live sensor streaming and alert broadcasting |
| Frontend | Vite, Tailwind CSS 4, Bootstrap 5, Chart.js 4, lightweight-charts 5 | Build tooling, responsive UI, interactive visualizations |
| Database | SQLite (dev) with Eloquent ORM | Schema migrations, relationships, query builder |
| Tooling | Laravel Pint, PHPUnit 11, Faker, Mockery, Laravel Pail | Code quality, testing, log inspection |

---

## 🚀 Getting Started

### Prerequisites
- PHP 8.2+
- Composer 2+
- Node.js 20+
- npm, pnpm, or yarn
- Pusher credentials (for real-time broadcasting)

### Installation
```bash
# PHP dependencies
composer install

# JavaScript dependencies
npm install

# Environment bootstrap
cp .env.example .env
php artisan key:generate
php artisan migrate --seed

# Run full dev stack (app server, queue, logs, Vite, websocket watcher)
composer run dev
```

### Testing
```bash
php artisan test
```

---

## 📦 API Overview

- `POST /api/sensors/{sensor}/readings` – ingest sensor values (API key protected).
- `GET /api/devices` – list devices with status and metadata.
- `GET /api/sensors/{sensor}/latest-readings` – retrieve rolling sensor data.
- `GET /api/alerts/active` – expose current alert counts for dashboards.
- `POST /api/alert-rules/store` – register new alert rules programmatically.

All endpoints leverage Sanctum guards, API key validation, and rich validation responses so hardware agents and partner systems can integrate securely.

---

## 🧩 Domain Model Snapshot

```
User ──1─────┐
             │
DashboardPreference

DeviceType ──┐
             ├── Device ──┬── Sensor ──┬── SensorReading ──┬── Alert ──┬── (emails)
Classroom ───┘             │             │                  │          │
                            └── DeviceStatusLog             └── AlertRule
```

---

## 📚 Roadmap

- Extract alert evaluation into strategy classes for richer rule types.
- Introduce repository interfaces and DTOs for clearer domain boundaries.
- Expand notification channels (SMS, Slack) via a `NotificationChannel` interface.
- Publish OpenAPI/Swagger documentation for REST endpoints.
- Increase automated coverage across API and personalization flows.

