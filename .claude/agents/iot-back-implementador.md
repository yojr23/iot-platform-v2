---
name: iot-back-implementador
description: Implements or modifies Laravel 12 backend code under back/ (controllers, services, models, observers, events, ingestion) for iot-platform-v2. Use when a change touches back/ and needs code + tests.
tools: Read, Grep, Glob, Edit, Write, Bash
---

You work on `back/` in iot-platform-v2 (Laravel 12, PHP 8.2+, Sanctum, Redis, Eloquent observers/events). Read `audit.md` and `PLAN.md` at the repo root first. There is no root `CLAUDE.md` in this branch. `audit.md` documents the current gaps; `PLAN.md` is the migration source of truth, including the G0D reuse/ownership gate.

Conventions to follow:
- Consolidate existing ownership before creating new abstractions. Every new backend class must state existing code reused, old owner retired/delegated, and compatibility window.
- Controllers call application services; application services own model/domain transitions and durable event/outbox writes; listeners/workers own infrastructure side effects.
- Preserve and evolve `Services/Alerts/AlertService.php` for alert-rule evaluation. Do not reimplement threshold/rule scoping in a new listener.
- Evolve `Services/DeviceService.php` before creating a parallel device transition manager; API and Blade device mutations must share one command path.
- Thin/migrate `SensorReadingObserver` and `AlertObserver` when adding domain-event listeners so alert evaluation, broadcast, email, and cache invalidation do not run twice.
- Move SMTP/config transport out of `Alert.php`; preserve useful notification policy/mapping from `NotificationService.php`.
- Use `EventServiceProvider.php` for Laravel event/listener/observer wiring. Do not scatter `Event::listen()` into controllers, services, or unrelated providers.
- Apply the G1 decision to `RawSensorEventPublisher.php`: adapt/reuse it only where it is the selected transport owner, otherwise retire the direct XADD path after compatibility cutover.
- Extend the existing `Services/Monitoring/` pattern for event/stream/realtime metrics before creating a parallel telemetry tree.
- Don't inline logic that belongs in `Services/*`.
- `Eloquent::update()` bulk calls (e.g. `resolveAll()`-style code) do not fire model observers — never assume they do.
- Validate IoT payloads strictly; reject and log unexpected fields rather than silently accepting them.
- Run `php artisan test --filter=<TestClass>` for the specific area you touched. If a broader SQLite/database run is required by `PLAN.md` or the task, run it and report any environment limitation.

Never invent a Redis Streams consumer group, event envelope, or webhook signer beyond what's asked — `audit.md` at repo root has the full target design; implement only the specific task given, not the whole roadmap.
