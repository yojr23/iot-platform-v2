# Procedimiento de Auditoria Normativa Interna

Version: 1.0  
Fecha: 2026-05-08  
Periodicidad sugerida: mensual o por entrega relevante.

## 1. Objetivo

Definir un procedimiento repetible para verificar alineacion ICONTEC e ISO en documentacion, codigo y estructura.

## 2. Entradas

- `README.md`
- `DOCUMENTACION_PROYECTO.md`
- `ANALISIS_PROYECTO.md`
- `docs/ICONTEC_COMPLIANCE.md`
- `docs/MATRIZ_TRAZABILIDAD_ICONTEC_ISO.md`
- `docs/EVIDENCIA_CUMPLIMIENTO_CODIGO_ESTRUCTURA.md`

## 3. Flujo de auditoria

1. Verificar estructura documental y bibliografia (ICONTEC).
2. Verificar controles tecnicos de acceso, logging y rate limiting (ISO).
3. Ejecutar pruebas automatizadas.
4. Registrar brechas y acciones correctivas.
5. Actualizar matriz de trazabilidad con fecha de corte.

## 4. Checklist de ejecucion

### 4.1 Documentacion

- [ ] Secciones formales completas (introduccion, objetivos, metodologia, resultados, conclusiones, bibliografia).
- [ ] Referencias en formato ICONTEC.
- [ ] Fuentes web con fecha de consulta.

### 4.2 Codigo y estructura

- [ ] Rutas sensibles protegidas con autenticacion/autorizacion.
- [ ] Politicas de rate limit activas.
- [ ] Logging de excepciones API activo.
- [ ] Estructura por capas mantenida (`Controllers`, `Services`, `Models`, `Middleware`, `Observers`).

### 4.3 Verificacion tecnica

- [ ] `composer test` ejecutado.
- [ ] `php artisan test` ejecutado.
- [ ] Evidencia de salida registrada en notas de revision.

## 5. Salidas

- Matriz actualizada: `docs/MATRIZ_TRAZABILIDAD_ICONTEC_ISO.md`
- Informe de evidencia actualizado: `docs/EVIDENCIA_CUMPLIMIENTO_CODIGO_ESTRUCTURA.md`
- Plan de brechas (si aplica): acciones, responsable, fecha objetivo.

## 6. Criterio de cierre

La auditoria se considera cerrada cuando todas las no conformidades criticas tienen accion correctiva definida y la documentacion refleja el estado real del codigo.
