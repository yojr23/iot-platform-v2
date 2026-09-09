# Revisión de planes asistida por skills — v1.2

**Fecha:** 9 de septiembre de 2026  
**Alcance:** `PLAN.md` y todos los planes de `front_rebuild_plan/`; revisión de arquitectura, API, backend, realtime, frontend, UX/UI y verificabilidad. No se modificó código de aplicación, ni se instalaron plugins.

## Evidencia de skills leídas

Se leyó el grafo local `C:\Users\jvrincon\Documents\opencode-skills-plugins\graphify-out\SKILLS_GRAPH.json` (607 skills) y el contenido completo de 110 `SKILL.md` seleccionados por sus etiquetas de arquitectura, system design, backend, frontend y audit. La ejecución verificó existencia, leyó el archivo completo y registró hash SHA-256 corto de cada uno: **27.971 líneas / 1.239.370 caracteres**.

| Cobertura entre las 110 skills | Skills con esa etiqueta |
| --- | ---: |
| Backend | 79 |
| Frontend | 95 |
| System design | 58 |
| Architecture | 52 |
| Audit | 55 |

Las guías aplicadas directamente a los ajustes incluyen `superpowers:writing-plans`, `codex-security:propose-security-hardening`, `product-design:audit`, `typescript-data-visualization-engineering`, `react-and-nextjs-data-visualization`, `react-best-practices`, `frontend-testing-debugging`, `security-scan`, `workers-best-practices`, `auth`, `vercel-api`, `vercel-queues`, `durable-objects`, `workflow`, `agent-browser`, `agent-browser-verify`, `figma-use`, `figma-generate-library`, `canvas2d-data-visualization`, `frontend-app-builder`, `shadcn`, `netlify-caching`, and `supabase-postgres-best-practices`. Las reglas de esas guías se traducen a contratos explícitos, límites de datos, un único propietario por responsabilidad, trazas de red, pruebas de carrera, rendimiento por instancia y evidencia visual; no se trasladan literalmente patrones de React/Next al Vue actual.

No existe una skill llamada `ponytail`, `caveman`, `zero-hallucination` ni una coincidencia exacta `DRY` en el grafo suministrado. No se inventó una. La intención solicitada se aplica como controles verificables:

- **Ponytail:** una extensión pequeña sobre dueños existentes, con supuesto y condición de retiro explícitos; sin un segundo transporte, store, caché o fallback.
- **Caveman:** cortar primero un vertical slice público completo y seguro antes de añadir variantes visuales o capacidades P1.
- **DRY:** `PublicGraphVisibility` es el único propietario de visibilidad; la proyección compartida y el chart owner no duplican HTTP/Echo ni estadística.
- **Zero hallucination:** todo campo, estado y endpoint debe tener propietario confirmado en el repositorio; se degrada a `No disponible`, no se simula.

## Hallazgos confirmados y decisión incorporada

| Prioridad | Evidencia del repositorio | Riesgo | Corrección planificada |
| --- | --- | --- | --- |
| P0 | `back/routes/web.php` deja `/dashboard` anónimo; `DashboardController@index` renderiza métricas, alertas, dispositivos y sensores. A la vez existe `front/src/views/DashboardView.vue`. | Dos dashboards públicos; el Blade antiguo filtra datos aunque Vue deje de llamar APIs. | Elegir un único entry point Lab Blue y retirar/redirigir/proteger el otro en Stage 6 antes del corte de rutas. |
| P0 | `/api/config/public` expone umbral de alerta, sonido, intervalo de polling y datos Pusher. | Contradice “public APIs sólo grafo”; conserva el acoplamiento al polling. | Eliminar/proteger en Stage 6; Echo ya se configura con variables Vite. |
| P0 | `/api/iot/sensors` es anónimo en `routes/api.php`. | Inventario IoT potencialmente expuesto. | Revisar y exigir boundary de ingestión/infraestructura; no tratarlo como endpoint de producto público. |
| P0 | `NewSensorReading` expone sensor/type/unit/device/lab; Blade dashboard y Blade sensor list lo consumen. | Encoger el evento rompe páginas; mantenerlo enriquecido filtra inventario. | Migrar esos consumidores antes de reducir el payload; no hay fallback público enriquecido. |
| P0 | `sensor_readings` no tiene índice compuesto de rango; `SensorReadingProjectionService` guarda sólo 120 lecturas. | Series de 24 h pueden escanear o quedar incompletas. | Índice `(sensor_id, reading_time, id)` y base de datos como única fuente de series. |
| P0 | `APP_TIMEZONE` por defecto es `America/Bogota`; `reading_time` y la ingestión legacy no declaran UTC. | El plan prometía UTC sin base semántica. | Probe, decisión/migración de datos históricos y normalización/rechazo antes de congelar UTC. |
| P1 | No hay modelo/contrato público para precision, thresholds, cadence, quality, coverage o stale cutoff. | El mockup puede inducir ciencia falsa. | P0 sólo muestra valores, timestamps, gaps y min/max/mean/count; lo demás queda bloqueado como P1 con propietario. |
| P1 | `DashboardView` público todavía compone alertas, métricas, estado de dispositivos y tabla global. | La UI guest real no respeta el límite del producto. | Paridad visual del flujo de grafo; slots autorizados omitidos/con acceso requerido y sin red guest. |

## Cambios aplicados a los planes

1. [`PLAN.md`](../PLAN.md) agrega Stage 6.0A/6.0B/6.0C: entrada pública única, inventario de rutas anónimas, semántica UTC/index, datos científicos honestos y compatibilidad de consumidores antes de reducir el evento.
2. [`FRONT_REBUILD_PLAN_ADJUSTMENTS_v1.1.md`](FRONT_REBUILD_PLAN_ADJUSTMENTS_v1.1.md) incorpora el addendum v1.2 como autoridad sobre instrucciones previas y ajusta la salida de Stage 6 a paridad guest/auth del flujo de grafo, no a campos no existentes.
3. [`SINOA_Agentic_Dashboard_Implementation_Plan_v2.1_PUBLIC_REALTIME.md`](SINOA_Agentic_Dashboard_Implementation_Plan_v2.1_PUBLIC_REALTIME.md) reclasifica thresholds, calidad, cadencia, stale y precisión de P0 a placeholders P1 bloqueados; preserva el look & flow Lab Blue para guests.
4. [`PUBLIC_GRAPH_VISIBILITY_AGENTIC_EXECUTION_PLAN.md`](PUBLIC_GRAPH_VISIBILITY_AGENTIC_EXECUTION_PLAN.md) agrega Task 0, contrato temporal exacto, índice, corte de Blade, cierre de `/config/public`, query sin doble `count()` y `ValidationException` existente en vez de una excepción no definida.

## Siguiente orden de ejecución para agentes

```text
Task 0: elegir/cerrar entry point + inventario anónimo + UTC/index + migrar consumidores Blade
  -> Task 1: flag admin fail-closed
  -> Task 2: PublicGraphVisibility como único dueño
  -> Task 3: bootstrap/series indexados y contracción real de rutas
  -> Task 4: gate de browser delivery y evento mínimo
  -> Task 5: una proyección Pinia + chart owner + cero polling
  -> Task 6: trazas guest/auth, rutas, query plan, screenshots y pruebas de carrera
  -> Stage 7: alertas/canal autorizados; Stage 8–10: device, efectos externos y prueba operacional
```

Ningún agente debe declarar completo un stage por compilación o por mocks: necesita la prueba de ruta/red/seguridad y la evidencia visual especificada en el plan.
