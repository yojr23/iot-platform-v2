# F0 Baseline (2026-10-17, rama autopilot/lab-readiness-2026-10-17 @ 3982d4b)

## Backend (sqlite aislado)
```
  Tests:    553 warnings, 6 passed (16988 assertions)
  Duration: 86.31s

```
Sin fallos. Los 553 warnings son preexistentes ("The use statement with non-compound name").

## ingestion_service
```
49 passed in 155.07s (0:02:35)
```

## Front
```
== test:structure
Phase 3 frontend structure looks complete.
== test:phase4
Phase 4 frontend migration structure looks complete.
== test:phase5
Phase 5 realtime structure looks complete.
== test:phase7
Phase 7 Docker frontend structure looks complete.
== test:unit
   Start at  00:14:47
   Duration  46.45s (transform 7.39s, setup 0ms, collect 23.64s, tests 54.18s, environment 167.86s, prepare 25.47s)

== test:realtime
   Start at  00:15:36
   Duration  5.64s (transform 1.91s, setup 0ms, collect 3.96s, tests 2.66s, environment 17.47s, prepare 3.16s)

```
