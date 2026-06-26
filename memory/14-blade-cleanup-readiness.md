# Blade Cleanup Readiness

## Veredicto

NO-GO

## Razones

- La validacion manual completa de la SPA no se ejecuto todavia.
- La SPA local no esta configurada actualmente con `VITE_PUSHER_*`; el backend tiene credenciales Pusher, pero el frontend local sigue en fallback polling.
- Persisten modulos funcionales que todavia dependen de rutas web y vistas Blade legacy.
- Existen vistas Blade backend que no son UI migrable sino templates de correo o soporte de layout para rutas web aun activas.

## Lo que si puede limpiarse

Lo siguiente ya tiene reemplazo Vue/API, pero todavia no es seguro borrarlo sin validacion manual completa:

| Vista/ruta Blade | Reemplazo Vue | Evidencia | Riesgo | Recomendacion |
|---|---|---|---|---|
| Auth Blade (`/login`, `/register`, `/password/*`, `/email/verify*`) | Vistas SPA de auth + `/api/auth/*` | Router Vue y tests backend | Bajo/medio | No borrar todavia; validar navegador/correo primero. |
| `dashboard.blade.php` y parciales | `DashboardView.vue` + `/api/dashboard/metrics` | SPA funcional y tests backend | Medio | No borrar todavia; ejecutar checklist manual primero. |
| `alerts/index.blade.php`, `alerts/unresolved.blade.php` | `AlertsView.vue` + `/api/alerts*` | Vista SPA y acciones API | Medio | No borrar todavia; validar filtros/resolucion manualmente. |
| `devices/index.blade.php` | `DevicesView.vue` + `/api/devices` | Vista SPA parcial | Medio | Mantener hasta cerrar create/edit/show. |
| `sensors/index.blade.php`, `sensors/show.blade.php` | `SensorsView.vue`, `SensorDetailView.vue` | Vista SPA funcional | Medio | Mantener hasta validar export/filtros/edicion. |
| `config/index_config.blade.php`, `config/email_config.blade.php` | `ConfigView.vue` + APIs config | SPA parcial | Medio | Mantener mientras existan puentes legacy de admin. |

## Lo que NO debe borrarse todavia

| Vista/ruta Blade | Motivo | Funcionalidad faltante | Fase sugerida |
|---|---|---|---|
| `back/resources/views/emails/alert.blade.php` | Template backend de correo | No aplica; no es UI SPA | Conservar indefinidamente o migrar solo si cambia motor de mail |
| `/config/user-roles` | Sin reemplazo SPA | user roles | Fase de migracion admin antes de 6B |
| `/metrics` | Sin reemplazo SPA | metricas tecnicas/admin | Fase de migracion admin antes de 6B |
| `/sensor-types/*` | Sin CRUD SPA/API completo | sensor-types | Fase de catalogos |
| `/device-types/*` | Sin CRUD SPA/API completo | device-types | Fase de catalogos |
| `/labs/*` | Sin CRUD SPA/API completo | labs | Fase de catalogos |
| `/profile` | Sin vista SPA | perfil | Fase de cuenta/perfil |
| `alerts/show` | Sin vista SPA individual | alerta individual | Fase de alertas extendida |
| `/devices/create`, `/devices/{id}`, `/devices/{id}/edit` | SPA incompleta | create/edit/show de dispositivos | Fase CRUD dispositivos |
| `/sensors/create`, `/sensors/{id}/edit`, `/sensors/{id}/download`, `/sensors/{id}/readings/filter` | SPA incompleta | create/edit/export/filtros avanzados de sensores | Fase CRUD sensores |
| `back/resources/views/layouts/*` | Soporte a rutas web activas | layouts para legacy | Mantener hasta retirar rutas web |

## Funcionalidades que deben migrarse antes de borrar Blade

- user roles
- metrics
- sensor-types
- device-types
- labs
- perfil
- alerta individual
- create/edit/show/export de dispositivos y sensores donde aplique

## Pruebas necesarias antes de borrar

- Ejecutar `memory/13-manual-validation-checklist.md` completo.
- Configurar `front/.env` con `VITE_PUSHER_*` y validar realtime UI end-to-end.
- Confirmar que los links legacy restantes ya no son necesarios desde la SPA.
- Revalidar que emails backend siguen funcionando despues de cualquier limpieza de rutas/vistas.

## Recomendacion final

No iniciar Fase 6B todavia.

Siguiente orden recomendado:
1. Ejecutar validacion manual completa de la SPA.
2. Configurar y validar Pusher real en la SPA local.
3. Cerrar las brechas de admin/catalogos/perfil/detalles/export.
4. Reemitir este reporte con evidencia nueva antes de borrar cualquier Blade o ruta web legacy.
