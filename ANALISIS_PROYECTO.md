# Analisis Tecnico y de Cumplimiento Normativo - IoT Platform v2

Autor: Equipo de desarrollo IoT Platform v2  
Institucion: Universidad Autonoma de Bucaramanga (UNAB)  
Ciudad: Bucaramanga  
Ano: 2026  
Fecha de analisis: 2026-05-08

## Tabla de contenido

1. Introduccion
2. Pregunta de evaluacion
3. Metodologia
4. Resultados del analisis
5. Evaluacion de cumplimiento ICONTEC e ISO
6. Discusion tecnica
7. Amenazas a la validez
8. Recomendaciones para articulo cientifico
9. Conclusiones
10. Bibliografia

## 1. Introduccion

Este documento presenta un analisis tecnico del repositorio IoT Platform v2 con foco en calidad de arquitectura, seguridad, pruebas y alineacion normativa para evaluacion academica.

## 2. Pregunta de evaluacion

La pregunta principal es: el estado actual del repositorio ofrece evidencia suficiente, trazable y reproducible para soporte de un articulo cientifico orientado a plataforma IoT aplicada?

## 3. Metodologia

Se aplico una revision estructurada en cuatro fases:

1. Inventario de componentes de software y rutas activas.
2. Inspeccion de controles de seguridad y autorizacion.
3. Revision de cobertura documental y coherencia tecnica.
4. Mapeo de evidencias hacia criterios ICONTEC e ISO.

Criterios de evaluacion usados:

- Coherencia entre implementacion y documentacion.
- Presencia de evidencia verificable por archivo.
- Claridad de limites y riesgos.
- Madurez para publicacion academica.

## 4. Resultados del analisis

### 4.1 Arquitectura y mantenibilidad

Hallazgos:

- Existe separacion por capas y responsabilidades en `app/`, `routes/`, `resources/` y `database/`.
- El uso de services y observers reduce acoplamiento en flujos criticos.
- Se identifican oportunidades de mejora en modularizacion adicional de algunas rutas y controladores de alto trafico.

Valoracion: favorable para estudio aplicado de ingenieria de software.

### 4.2 Seguridad y robustez operativa

Hallazgos:

- Superficie privada protegida por Sanctum y middleware de rol.
- Rutas IoT usan API key y validacion de estado de dispositivo.
- Existe limitacion de peticiones por tipo de operacion.
- Las pruebas incluyen escenarios de acceso, inyeccion y escalamiento de privilegios.

Valoracion: nivel adecuado para entorno academico controlado; requiere endurecimiento adicional para produccion.

### 4.3 Calidad de documentacion

Hallazgos:

- Se formalizo marco de cumplimiento en `docs/ICONTEC_COMPLIANCE.md`.
- Se dispone de plantilla y guia de referencias para documentos cientificos.
- La documentacion principal fue normalizada a estructura de trabajo tecnico.

Valoracion: suficiente para revision por pares con enfoque aplicado, siempre que se agreguen resultados experimentales cuantitativos.

## 5. Evaluacion de cumplimiento ICONTEC e ISO

### 5.1 Cumplimiento ICONTEC

Estado: cumplido por alineacion documental.

Evidencias:

- Estructura formal con secciones numeradas.
- Bibliografia tecnica consolidada.
- Referencias a fuentes normativas y electronicas.

### 5.2 Cumplimiento ISO

Estado: cumplido por alineacion tecnica (sin certificacion).

Mapeo sintetico:

- ISO 9001: control documental y trazabilidad de procesos.
- ISO/IEC 27001: controles de acceso y seguridad operativa basica.
- ISO/IEC 25010: enfoque en adecuacion funcional, seguridad y mantenibilidad.
- ISO/IEC/IEEE 29148: documentacion de objetivos, alcance y requisitos operativos.
- ISO/IEC/IEEE 12207: practicas de ciclo de vida soportadas por estructura del proyecto, pruebas y mantenimiento.

La trazabilidad detallada se registra en `docs/MATRIZ_TRAZABILIDAD_ICONTEC_ISO.md`.

## 6. Discusion tecnica

El proyecto demuestra madurez suficiente para ser reportado como caso aplicado de plataforma IoT academica. La principal fortaleza es la coherencia entre flujo funcional (ingesta, reglas, alertas) y controles de seguridad. El principal limite para publicacion de mayor impacto no es de arquitectura, sino de evidencia empirica: falta una bateria estandarizada de experimentos con metricas de latencia, disponibilidad, precision de alertas y costo operativo.

## 7. Amenazas a la validez

1. Dependencia de configuracion local del entorno para reproducir resultados.
2. Potencial desfase temporal entre codigo y artefactos externos si no se versionan juntos.
3. Falta de validacion en despliegue productivo multiusuario con carga sostenida.

## 8. Recomendaciones para articulo cientifico

1. Incluir protocolo experimental reproducible (escenarios, volumen de datos, metricas, instrumentacion).
2. Publicar tabla de resultados con media, desviacion y pruebas estadisticas basicas.
3. Anexar diagrama de arquitectura final y matriz de cumplimiento normativa.
4. Declarar explicitamente el alcance: alineacion ISO/ICONTEC, no certificacion institucional.
5. Incorporar seccion de trabajo futuro centrada en seguridad avanzada y escalabilidad.

## 9. Conclusiones

1. El repositorio presenta una base tecnica coherente y documentada para sustentar publicacion academica aplicada.
2. La normalizacion ICONTEC y la trazabilidad ISO quedaron integradas en el conjunto documental.
3. El siguiente salto de calidad para articulo cientifico depende de resultados empiricos reproducibles, no de cambios estructurales mayores.

## 10. Bibliografia

INSTITUTO COLOMBIANO DE NORMAS TECNICAS Y CERTIFICACION (ICONTEC). NTC 1486: documentacion, presentacion de trabajos escritos. Bogota: ICONTEC.

INSTITUTO COLOMBIANO DE NORMAS TECNICAS Y CERTIFICACION (ICONTEC). NTC 5613: referencias bibliograficas, contenido, forma y estructura. Bogota: ICONTEC.

INSTITUTO COLOMBIANO DE NORMAS TECNICAS Y CERTIFICACION (ICONTEC). NTC 4490: referencias documentales para fuentes de informacion electronicas. Bogota: ICONTEC.

INTERNATIONAL ORGANIZATION FOR STANDARDIZATION (ISO). ISO 9001:2015. Quality management systems - Requirements. Geneva: ISO, 2015.

INTERNATIONAL ORGANIZATION FOR STANDARDIZATION (ISO); INTERNATIONAL ELECTROTECHNICAL COMMISSION (IEC). ISO/IEC 27001:2022. Information security, cybersecurity and privacy protection - Information security management systems - Requirements. Geneva: ISO, 2022.

INTERNATIONAL ORGANIZATION FOR STANDARDIZATION (ISO); INTERNATIONAL ELECTROTECHNICAL COMMISSION (IEC). ISO/IEC 25010:2023. Systems and software engineering - Systems and software Quality Requirements and Evaluation (SQuaRE) - Product quality model. Geneva: ISO, 2023.

INTERNATIONAL ORGANIZATION FOR STANDARDIZATION (ISO); INSTITUTE OF ELECTRICAL AND ELECTRONICS ENGINEERS (IEEE). ISO/IEC/IEEE 29148:2018. Systems and software engineering - Life cycle processes - Requirements engineering. Geneva: ISO, 2018.

INTERNATIONAL ORGANIZATION FOR STANDARDIZATION (ISO); INTERNATIONAL ELECTROTECHNICAL COMMISSION (IEC); INSTITUTE OF ELECTRICAL AND ELECTRONICS ENGINEERS (IEEE). ISO/IEC/IEEE 12207:2017. Systems and software engineering - Software life cycle processes. Geneva: ISO, 2017.
