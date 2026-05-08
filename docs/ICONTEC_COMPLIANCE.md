# Marco de Cumplimiento ICONTEC e ISO - IoT Platform v2

Fecha de adopcion: 2026-05-08  
Ultima actualizacion: 2026-05-08  
Version: 3.0

## 1. Objetivo

Establecer un marco verificable de alineacion normativa para documentacion, codigo y estructura del repositorio IoT Platform v2, orientado a evaluacion academica y soporte de articulo cientifico.

## 2. Alcance

Aplica a:

- Documentacion tecnica del proyecto (`README.md`, `DOCUMENTACION_PROYECTO.md`, `ANALISIS_PROYECTO.md`, `docs/*.md`).
- Estructura del software (`app/`, `routes/`, `tests/`, `config/`, `database/`).
- Controles de calidad, seguridad y trazabilidad.

No aplica a:

- Certificacion formal emitida por organismo acreditado.
- Cumplimiento legal/regulatorio sectorial externo no implementado en el alcance del proyecto.

## 3. Referencias normativas base

### 3.1 ICONTEC

- `NTC 1486`: presentacion de trabajos escritos.
- `NTC 5613`: referencias bibliograficas.
- `NTC 4490`: referencias documentales para fuentes electronicas.

### 3.2 ISO

- `ISO 9001:2015` (ref. ISO 62085).
- `ISO/IEC 27001:2022` (ref. ISO 27001).
- `ISO/IEC 25010:2023` (ref. ISO 78176).
- `ISO/IEC/IEEE 29148:2018` (ref. ISO 72089).
- `ISO/IEC/IEEE 12207:2026` (ref. ISO 90219).

## 4. Politica de declaracion normativa

1. El repositorio declara `alineacion` con las normas listadas.
2. Ningun documento debe afirmar `certificacion` sin auditoria externa formal.
3. Toda afirmacion de cumplimiento debe enlazar evidencia concreta (archivo y linea o comando de verificacion).

## 5. Aplicacion de norma sobre codigo y estructura

### 5.1 Estructura y ciclo de vida (ISO/IEC/IEEE 12207)

- Separacion por capas en `app/Http`, `app/Models`, `app/Services`, `app/Observers`.
- Rutas desacopladas en `routes/web.php` y `routes/api.php`.
- Pruebas de verificacion y regresion en `tests/` y `tests_python/`.

### 5.2 Requisitos y trazabilidad (ISO/IEC/IEEE 29148)

- Objetivos, alcance y criterios de evaluacion en `DOCUMENTACION_PROYECTO.md` y `ANALISIS_PROYECTO.md`.
- Matriz de trazabilidad normativa en `docs/MATRIZ_TRAZABILIDAD_ICONTEC_ISO.md`.

### 5.3 Calidad de producto (ISO/IEC 25010)

- Adecuacion funcional: endpoints y flujos IoT definidos y probados.
- Seguridad: autenticacion, autorizacion, validaciones y controles anti abuso.
- Mantenibilidad: convenciones de estructura y separacion de responsabilidades.

### 5.4 Seguridad de la informacion (ISO/IEC 27001)

- Control de acceso en middleware y autenticacion Sanctum.
- Registro de eventos y errores en flujo API.
- Politicas de limitacion de tasa para reducir abuso.

### 5.5 Gestion de calidad (ISO 9001)

- Informacion documentada versionada en Git.
- Revision periodica de evidencias y brechas.
- Integracion de pruebas como criterio de calidad tecnica.

## 6. Evidencia obligatoria

Los documentos que materializan evidencia de cumplimiento son:

- `docs/MATRIZ_TRAZABILIDAD_ICONTEC_ISO.md`
- `docs/EVIDENCIA_CUMPLIMIENTO_CODIGO_ESTRUCTURA.md`
- `docs/PROCEDIMIENTO_AUDITORIA_NORMATIVA.md`
- `docs/REFERENCIAS_ICONTEC.md`
- `docs/PLANTILLA_TRABAJO_ICONTEC.md`

## 7. Criterios de aceptacion para reporte cientifico

Un entregable se considera listo si cumple:

1. Estructura formal (portada tecnica, objetivos, metodologia, resultados, conclusiones, bibliografia).
2. Bibliografia validada con formato ICONTEC.
3. Trazabilidad normativa con evidencia objetiva.
4. Declaracion explicita de limites, riesgos y trabajo futuro.

## 8. Checklist de cierre

- [ ] Documento en estructura formal.
- [ ] Fuentes citadas segun `NTC 5613` y `NTC 4490`.
- [ ] Matriz de trazabilidad actualizada.
- [ ] Evidencia de codigo y estructura actualizada.
- [ ] Declaracion de `alineacion` (sin afirmar certificacion).
