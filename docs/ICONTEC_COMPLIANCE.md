# Marco de Cumplimiento ICONTEC e ISO - IoT Platform v2

Fecha de adopcion en repositorio: 2026-05-08.
Version del marco: 2.0.

## 1. Objetivo

Definir el marco normativo documental y de calidad para que el repositorio soporte evaluacion academica y revision de articulo cientifico.

## 2. Alcance

Aplica a:

- `README.md` en su rol de documento de entrada.
- `DOCUMENTACION_PROYECTO.md`.
- `ANALISIS_PROYECTO.md`.
- Documentos de `docs/` usados como evidencia tecnica.

No aplica a formato de codigo fuente ni a certificacion oficial de terceros.

## 3. Referencias normativas base

### 3.1 ICONTEC

- `NTC 1486`: Presentacion de trabajos escritos.
- `NTC 5613`: Referencias bibliograficas.
- `NTC 4490`: Referencias documentales para fuentes electronicas.

### 3.2 ISO

- `ISO 9001:2015` (vigente al 2026-05-08): sistema de gestion de calidad.
- `ISO/IEC 27001:2022`: sistema de gestion de seguridad de la informacion.
- `ISO/IEC 25010:2023`: modelo de calidad de producto software.
- `ISO/IEC/IEEE 29148:2018`: ingenieria de requisitos.
- `ISO/IEC/IEEE 12207:2026`: procesos del ciclo de vida de software.

Nota de rigor: este repositorio declara alineacion tecnica y documental con las normas listadas. No declara certificacion ISO emitida por ente acreditado.

## 4. Criterios de cumplimiento para articulo cientifico

1. Estructura formal de documento tecnico en secciones numeradas.
2. Trazabilidad de requisitos normativos a evidencias del repositorio.
3. Bibliografia normalizada con fuentes primarias de estandares.
4. Declaracion explicita de limites, riesgos y trabajo futuro.
5. Consistencia terminologica entre README, documentacion y analisis.

## 5. Reglas operativas obligatorias

1. Todo documento tecnico nuevo parte de `docs/PLANTILLA_TRABAJO_ICONTEC.md`.
2. Toda fuente externa se registra en bibliografia con formato definido.
3. Fuentes web incluyen `fecha de consulta`.
4. Cada entregable cientifico incluye seccion `Metodologia`, `Resultados` y `Conclusiones`.
5. Toda declaracion ISO debe decir si es `alineacion` o `certificacion`.

## 6. Estandar de equivalencia en Markdown

Markdown no codifica margenes, tipografia, ni paginacion de impresion. Para entrega academica final en PDF/DOCX se debe:

1. Exportar el documento desde Markdown.
2. Ajustar formato segun lineamientos institucionales.
3. Conservar estructura y referencias ya validadas en repositorio.

## 7. Matriz de trazabilidad obligatoria

La trazabilidad de cumplimiento se mantiene en:

- `docs/MATRIZ_TRAZABILIDAD_ICONTEC_ISO.md`

Sin matriz actualizada, el documento no se considera listo para evaluacion externa.

## 8. Checklist de cierre

- [ ] Documento con estructura formal.
- [ ] Bibliografia completa y consistente.
- [ ] Evidencias tecnicas verificables por ruta de archivo.
- [ ] Riesgos y limitaciones declarados.
- [ ] Declaracion de alcance normativo (alineacion, no certificacion) visible.
