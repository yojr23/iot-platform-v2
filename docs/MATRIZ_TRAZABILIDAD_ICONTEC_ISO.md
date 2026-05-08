# Matriz de Trazabilidad Normativa - ICONTEC e ISO

Fecha de corte: 2026-05-08  
Estado global: alineacion documental, de codigo y de estructura (sin certificacion externa).

## 1. ICONTEC (documentacion)

| Norma | Criterio | Evidencia | Metodo de verificacion | Estado |
|---|---|---|---|---|
| NTC 1486 | Estructura formal por secciones numeradas | `DOCUMENTACION_PROYECTO.md`, `ANALISIS_PROYECTO.md` | Revisar encabezados y tabla de contenido | Cumple |
| NTC 1486 | Inclusion de conclusiones y anexos | `DOCUMENTACION_PROYECTO.md` secciones 10-12 | Buscar `## 10. Conclusiones` y `## 12. Anexos` | Cumple |
| NTC 5613 | Bibliografia en formato tecnico consistente | `DOCUMENTACION_PROYECTO.md` seccion 11, `ANALISIS_PROYECTO.md` seccion 10 | Validar patron autor-entidad-titulo-edicion-ano | Cumple |
| NTC 4490 | Fuentes electronicas con fecha de consulta | `docs/REFERENCIAS_ICONTEC.md` seccion 4 | Verificar bloque `consultado:` | Cumple |
| NTC 5613 + 4490 | Reglas de citacion operacional para el equipo | `docs/REFERENCIAS_ICONTEC.md` seccion 5 | Revisar reglas obligatorias | Cumple |

## 2. ISO (codigo, estructura y proceso)

| Norma | Criterio aplicado | Evidencia objetiva (archivo:linea) | Verificacion | Estado |
|---|---|---|---|---|
| ISO 9001:2015 | Informacion documentada y control de calidad | `README.md:17-36`, `docs/ICONTEC_COMPLIANCE.md:1-8` | Revisar versionado y declaraciones de alcance | Alineado |
| ISO 9001:2015 | Plan de pruebas como control de calidad | `composer.json:58-61`, `phpunit.xml:7-19` | Ejecutar `composer test` | Parcial (3 fallos unitarios detectados el 2026-05-08) |
| ISO/IEC 27001:2022 | Control de acceso administrativo | `app/Http/Middleware/EnsureUserIsAdmin.php:14-20`, `routes/api.php:69-77` | Verificar middleware `admin` en rutas sensibles | Alineado |
| ISO/IEC 27001:2022 | Autenticacion API y sesion segura | `routes/api.php:14-20`, `routes/api.php:47-57` | Verificar uso de `auth:sanctum` y limites | Alineado |
| ISO/IEC 27001:2022 | Registro de incidentes y errores | `bootstrap/app.php:25-74` | Revisar callbacks de `Log::warning/error/critical` | Alineado |
| ISO/IEC 27001:2022 | Proteccion contra abuso por tasa | `app/Providers/AppServiceProvider.php:30-64` | Verificar politicas `api-read/api-write/auth-login` | Alineado |
| ISO/IEC 25010:2023 | Adecuacion funcional (flujos IoT y API) | `routes/api.php:23-45`, `app/Http/Controllers/API/SensorApiController.php:20-153` | Probar `GET /api/iot/sensors` y `POST /api/sensors/{id}/readings` | Alineado |
| ISO/IEC 25010:2023 | Confiabilidad y manejo de errores | `SensorApiController.php:124-144`, `bootstrap/app.php:54-74` | Revisar manejo `QueryException/Throwable/PDOException` | Alineado |
| ISO/IEC 25010:2023 | Eficiencia y observabilidad | `TrackApiPerformance.php:16-25`, `routes/api.php:24-45` | Verificar middleware `api.metrics` | Alineado |
| ISO/IEC 25010:2023 | Mantenibilidad por modularidad | `app/Services/*`, `app/Observers/*`, `app/Http/Controllers/*` | Revisar separacion de responsabilidades | Alineado |
| ISO/IEC/IEEE 29148:2018 | Definicion de objetivos y alcance | `DOCUMENTACION_PROYECTO.md:30-60`, `ANALISIS_PROYECTO.md:26-45` | Revisar objetivos, alcance y criterios | Alineado |
| ISO/IEC/IEEE 29148:2018 | Trazabilidad de requisitos normativos | `docs/MATRIZ_TRAZABILIDAD_ICONTEC_ISO.md` | Confirmar correspondencia norma-evidencia | Alineado |
| ISO/IEC/IEEE 12207:2026 | Estructura de ciclo de vida de software | `app/`, `routes/`, `database/`, `tests/` | Revisar organizacion por procesos y capas | Alineado |
| ISO/IEC/IEEE 12207:2026 | Verificacion y validacion | `tests/Feature/*.php`, `tests_python/test_script_datos.py` | Ejecutar suites de prueba | Alineado |
| ISO/IEC/IEEE 12207:2026 | Gestion de configuracion | repositorio Git y archivos versionados | `git log`, `git status` | Alineado |

## 3. Brechas abiertas (accionables)

1. Formalizar una politica de gestion de secretos para produccion.
2. Automatizar verificacion de sincronia entre rutas y `docs/api/openapi.yaml`.
3. Corregir `tests/Unit/DashboardMetricsServiceTest.php` para inyectar `AlertService` o mock equivalente.
4. Publicar protocolo experimental con metricas reproducibles para articulo cientifico.

## 4. Declaracion de alcance

Esta matriz evidencia alineacion normativa aplicada al repositorio en terminos documentales y tecnicos. No sustituye una auditoria externa ni representa certificacion institucional.
