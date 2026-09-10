# Changelog

All notable changes to this project will be documented in this file.

Format based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

## [Unreleased]

### Added
- `BackendClient` exponential backoff retry (3 attempts, 1s/2s/4s) for transient failures (5xx, 429).
- `MonitorCard.vue` now owns monitor-card presentation; `SensorMonitorBoard.vue` owns orchestration.
- `useMonitorLayout.js` owns dashboard layout persistence/restore.
- `MigrationIntegrityTest.php` — structural validation of migration files.

### Fixed
- Device reconnect recovery now reconciles missed status events from an authoritative snapshot.

## [0.1.0] - 2026-09-09

### Added
- Initial v2 platform release.
- Transactional outbox with Redis Streams; binlog-driven CDC remains an ADR-1 target, not the active relay.
- Redis Streams durable event backbone.
- Vue 3 + Pinia + Laravel Echo SPA frontend.
- Laravel 12 backend with Observer/Event/Listener pipeline.
- Python ingestion service with MQTT support.
- Docker Compose stack (8 services).
