# Matriz de Trazabilidad Normativa - ICONTEC e ISO

Fecha de corte: 2026-05-08
Estado global: alineacion documental y tecnica (sin certificacion externa).

## 1. ICONTEC

| Norma | Criterio aplicado | Evidencia en repositorio | Estado |
|---|---|---|---|
| NTC 1486 | Estructura formal de documento tecnico | `DOCUMENTACION_PROYECTO.md`, `ANALISIS_PROYECTO.md` | Cumple |
| NTC 5613 | Bibliografia con formato consistente | `DOCUMENTACION_PROYECTO.md#11`, `ANALISIS_PROYECTO.md#10`, `docs/REFERENCIAS_ICONTEC.md` | Cumple |
| NTC 4490 | Referencias de fuentes electronicas | `docs/REFERENCIAS_ICONTEC.md` | Cumple |

## 2. ISO

| Norma | Criterio de alineacion | Evidencia en repositorio | Estado |
|---|---|---|---|
| ISO 9001:2015 | Control documental y mejora continua | `README.md`, `docs/ICONTEC_COMPLIANCE.md`, `tests/` | Alineado |
| ISO/IEC 27001:2022 | Control de acceso y proteccion de informacion | `routes/api.php`, `app/Http/Middleware/EnsureUserIsAdmin.php`, pruebas de seguridad en `tests/Feature/` | Alineado |
| ISO/IEC 25010:2023 | Calidad de producto: adecuacion funcional, seguridad, mantenibilidad | `DOCUMENTACION_PROYECTO.md#8`, arquitectura en `README.md` y `docs/` | Alineado |
| ISO/IEC/IEEE 29148:2018 | Elicitacion y documentacion de requisitos | secciones de objetivos/alcance en `DOCUMENTACION_PROYECTO.md` y `ANALISIS_PROYECTO.md` | Alineado |
| ISO/IEC/IEEE 12207:2017 | Procesos de ciclo de vida, pruebas y mantenimiento | estructura del proyecto, `tests/`, control de cambios en git | Alineado |

## 3. Brechas abiertas para madurez cientifica

1. Definir protocolo experimental repetible con metricas de desempeno.
2. Establecer plan formal de gestion de secretos para entornos productivos.
3. Integrar control automatizado de consistencia entre OpenAPI y rutas reales.

## 4. Declaracion de alcance

Esta matriz describe alineacion tecnica y documental con normas ICONTEC e ISO para fines academicos y de investigacion aplicada. No equivale a certificacion institucional ni a auditoria de tercera parte.
