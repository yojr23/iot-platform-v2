# Changelog

All notable changes to this project will be documented in this file.

Format based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

## [Unreleased]

### Added
- Pipeline de publicación durable activo: outboxes transaccionales, Debezium, streams CDC de Redis y consumidores `cdc:consume-outboxes`, `raw:consume` y `domain:consume`.
- API pública mínima para la gráfica y serie privada autenticada de sensores restringidos.
- Canales privados de alertas y estado de dispositivos; el canal de lectura pública queda sujeto a `public_monitoring_enabled`.
- `BackendClient` exponential backoff retry (3 attempts, 1s/2s/4s) for transient failures (5xx, 429).
- `MonitorCard.vue` now owns monitor-card presentation; `SensorMonitorBoard.vue` owns orchestration.
- `useMonitorLayout.js` owns dashboard layout persistence/restore.
- `MigrationIntegrityTest.php` — structural validation of migration files.

### Fixed
- Device reconnect recovery now reconciles missed status events from an authoritative snapshot.

## [0.1.0] - 2026-09-09

### Added
- Initial v2 platform release.
- Transactional outbox with Redis Streams; esta línea registra el punto de partida y no el mecanismo de publicación vigente.
- Redis Streams durable event backbone.
- Vue 3 + Pinia + Laravel Echo SPA frontend.
- Laravel 12 backend with Observer/Event/Listener pipeline.
- Python ingestion service with MQTT support.
- Docker Compose stack (8 services).
