# Changelog

All notable changes to this project will be documented in this file.

Format based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

## [Unreleased]

### Added
- `BackendClient` exponential backoff retry (3 attempts, 1s/2s/4s) for transient failures (5xx, 429).
- `MonitorCard.vue` extracted from `SensorMonitorBoard.vue` for single-responsibility.
- `useMonitorLayout.js` composable for dashboard layout persistence/restore.
- `MigrationIntegrityTest.php` — structural validation of migration files.
- Vitest coverage configuration with v8 provider and thresholds.
- `CODEOWNERS` file for review ownership.

### Fixed
- `SensorMonitorBoard.vue` reduced from 506 to ~180 lines via component extraction.

## [0.1.0] - 2026-09-09

### Added
- Initial v2 platform release.
- Event-driven architecture with outbox + binlog CDC (ADR-1).
- Redis Streams durable event backbone.
- Vue 3 + Pinia + Laravel Echo SPA frontend.
- Laravel 12 backend with Observer/Event/Listener pipeline.
- Python ingestion service with MQTT support.
- Docker Compose stack (8 services).
- 580-test suite at 99% pass rate.
