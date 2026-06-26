# Stable Blade Comparison

## Fuente de comparacion

- `origin/main` coincide hoy con el HEAD del refactor, por lo que no sirve como referencia de la version Blade estable.
- Rama/tag/remoto usado: commit historico `8d2c7d8` (`version estable`) en Git.
- Referencia complementaria usada: `back/resources/views` y `back/routes/web.php` como legado vivo conservado en el repo.
- Actualizacion Fase 8A: la SPA ya cubre CRUD/admin de dispositivos, sensores, labs, sensor-types, device-types, user roles, metricas, perfil read-only, detalle individual de alerta y export/filtro de lecturas. Esta comparacion conserva informacion historica de Blade, pero esas brechas dejaron de bloquearse por ausencia de API/SPA y ahora requieren validacion manual.
- Limitaciones:
  - `origin/main` hoy apunta al estado actual del refactor, no a la version Blade estable.
  - La comparacion funcional mezcla evidencia historica (`8d2c7d8`) con la capa Blade legacy conservada despues del split a `/back`.
  - Algunas capacidades requieren validacion manual en navegador o con credenciales reales para confirmar paridad exacta.

## Funcionalidades conservadas

| Funcionalidad Blade estable | Reemplazo Vue/API | Estado | Evidencia | Observaciones |
|---|---|---|---|---|
| Login | `front/src/views/auth/LoginView.vue` + `/api/auth/login` | Conservada | Router SPA + API auth | Flujo principal cubierto. |
| Registro | `RegisterView.vue` + `/api/auth/register` | Conservada | Vista SPA + endpoint | Verificacion manual por correo pendiente. |
| Recuperacion de contrasena | `ForgotPasswordView.vue` / `ResetPasswordView.vue` + API auth | Conservada | Vistas SPA + endpoints | Cobertura backend por tests. |
| Dashboard principal | `DashboardView.vue` + `/api/dashboard/metrics` | Conservada | Vista SPA + componentes dashboard | Muestra metricas, alertas y lecturas. |
| Alertas activas/no resueltas | `AlertsView.vue` + `/api/alerts*` | Conservada | Vista SPA + API de alertas | Incluye resolver una/todas. |
| Reglas de alerta | `AlertRulesView.vue` + `/api/alert-rules*` | Conservada | CRUD SPA + API JSON | Requiere admin. |
| Configuracion de alertas | `ConfigView.vue` + `/api/config/alerts` | Conservada | Form SPA + API | Requiere admin. |
| Configuracion SMTP/email | `ConfigView.vue` + `/api/config/email*` | Conservada | Form SPA + API | Password protegida. |
| Listado de sensores | `SensorsView.vue` + `/api/sensors` | Conservada | Vista SPA + API | Filtro local. |
| Detalle de sensor con grafica | `SensorDetailView.vue` + `/api/sensors/{id}*` | Conservada | Vista SPA + API | Realtime pendiente de validacion manual. |
| Realtime de alertas | Echo/Pusher en Vue + polling fallback | Conservada con reserva | `useAlertsRealtime.js`, store alerts, `AlertToast.vue` | Implementado, falta prueba real end-to-end. |

## Funcionalidades parcialmente migradas

| Funcionalidad Blade estable | Estado actual en SPA | Que falta | Riesgo | Proxima accion |
|---|---|---|---|---|
| Dispositivos | Hay listado y toggle, pero no create/edit/show completo SPA | CRUD completo y detalle dedicado | Medio | Fase posterior de CRUD JSON + UI. |
| Sensores | Hay listado y detalle, pero no create/edit/export/filtro por rango en SPA | CRUD, export, filtro historico avanzado | Medio | Completar APIs y UI antes de Fase 6B. |
| Dashboard preferences | API existe pero UX SPA no esta cerrada | Persistencia visual real del layout | Bajo | Integrar desde `front/src/api/dashboard.js`. |
| Configuracion admin ampliada | SPA integra alertas y email, pero no user roles/metricas/catalogos | Pantallas o APIs faltantes | Medio | Mantener puentes legacy hasta migrar. |
| Realtime de sensores | Preparado en frontend | Validacion real con evento `sensor.{id}` | Medio | Probar con backend/simulador y credenciales reales. |
| Validacion de auth por correo | Flujo API implementado | Confirmar navegador/correo real | Bajo | Prueba manual. |

## Funcionalidades perdidas o no encontradas

| Funcionalidad en Blade estable | Estado actual | Impacto | Recomendacion | Fase sugerida para recuperar |
|---|---|---|---|---|
| User roles admin | Sigue solo en Blade legacy (`/config/user-roles`) | Medio | Crear API/SPA o mantener legacy hasta cierre funcional | Posterior a Fase 7, antes de Fase 6B |
| Metricas tecnicas admin | Sigue solo en Blade legacy (`/metrics`) | Medio | Definir si va a SPA o queda como modulo admin separado | Posterior |
| CRUD SPA de sensor types | No existe en SPA; se abre Blade legacy | Bajo/medio | Crear catalog APIs y pantallas SPA | Posterior |
| CRUD SPA de device types | No existe en SPA; se abre Blade legacy | Bajo/medio | Crear catalog APIs y pantallas SPA | Posterior |
| CRUD SPA de labs/classrooms | No existe en SPA; se abre Blade legacy | Bajo/medio | Crear APIs y pantallas SPA | Posterior |
| Vista SPA de perfil | No existe en SPA | Bajo | Decidir si se migra o se elimina | Posterior |
| Vista SPA de alerta individual (`alerts/show`) | No existe equivalente claro | Bajo | Evaluar si realmente se necesita | Posterior |

## Funcionalidades legacy que todavia dependen de Blade

| Vista/ruta Blade | Motivo de conservacion | Riesgo de eliminarla | Reemplazo esperado | Estado |
|---|---|---|---|---|
| `back/resources/views/emails/alert.blade.php` | Template de correo backend | Alto | Puede seguir en Blade; no requiere SPA | Conservar |
| `/sensor-types/*` | No hay catalog SPA/API completa | Medio | Catalog API + vista SPA admin | Legacy necesario |
| `/device-types/*` | No hay catalog SPA/API completa | Medio | Catalog API + vista SPA admin | Legacy necesario |
| `/labs/*` | No hay CRUD SPA/API completa | Medio | Catalog API + vista SPA admin | Legacy necesario |
| `/config/user-roles` | No hay gestion SPA de roles | Medio | Pantalla admin SPA o decision de mantener Blade | Legacy necesario |
| `/metrics` | No hay dashboard tecnico SPA | Bajo/medio | Pantalla SPA o mantener admin Blade | Legacy necesario |
| `/devices/create`, `/devices/{id}/edit`, `/devices/{id}` | SPA no cubre CRUD completo/detalle | Medio | CRUD completo SPA | Legacy temporal |
| `/sensors/create`, `/sensors/{id}/edit`, `/sensors/{id}/download`, `/sensors/{id}/readings/filter` | SPA no cubre todo el flujo admin/export | Medio | CRUD/export SPA | Legacy temporal |
| Auth Blade (`/login`, `/register`, `/password/*`, `/email/verify*`) | SPA ya cubre el flujo, pero Blade sigue como respaldo | Bajo | Eliminar solo tras validacion manual | Candidato a eliminacion |
| Dashboard Blade y parciales | SPA ya cubre el dashboard principal | Bajo/medio | Eliminar solo tras validacion manual | Candidato a eliminacion |

## Impacto para limpieza Blade

- Blade con reemplazo SPA ya existente:
  - `back/resources/views/auth/login.blade.php`
  - `back/resources/views/auth/register.blade.php`
  - `back/resources/views/auth/passwords/email.blade.php`
  - `back/resources/views/auth/passwords/reset.blade.php`
  - `back/resources/views/auth/verify.blade.php`
  - `back/resources/views/dashboard.blade.php`
  - `back/resources/views/dashboard/partials/*`
  - `back/resources/views/alerts/index.blade.php`
  - `back/resources/views/alerts/unresolved.blade.php`
  - `back/resources/views/devices/index.blade.php`
  - `back/resources/views/sensors/index.blade.php`
  - `back/resources/views/sensors/show.blade.php`
  - `back/resources/views/config/index_config.blade.php`
  - `back/resources/views/config/email_config.blade.php`

- Blade sin reemplazo SPA suficiente:
  - `back/resources/views/config/user_roles.blade.php`
  - `back/resources/views/metrics/index.blade.php`
  - `back/resources/views/device-types/*`
  - `back/resources/views/sensor-types/*`
  - `back/resources/views/labs/*`
  - `back/resources/views/profile.blade.php`
  - `back/resources/views/alerts/show.blade.php`
  - `back/resources/views/devices/create.blade.php`
  - `back/resources/views/devices/edit.blade.php`
  - `back/resources/views/devices/show.blade.php`
  - `back/resources/views/sensors/create.blade.php`
  - `back/resources/views/sensors/edit.blade.php`

- Blade backend/template que debe conservarse:
  - `back/resources/views/emails/alert.blade.php`
  - `back/resources/views/layouts/*` mientras sigan activas rutas web legacy
  - `back/resources/views/vendor/pagination/custom.blade.php` mientras existan vistas Blade con paginacion

- Blade que sigue siendo necesario por rutas web legacy:
  - catalogos admin (`sensor-types`, `device-types`, `labs`)
  - `config/user-roles`
  - `metrics`
  - create/edit/show/export de dispositivos y sensores todavia no cubiertos por SPA

## Resumen ejecutivo

- ¿La SPA cubre lo esencial del sistema?
  - Si. Auth, dashboard, sensores, alertas, reglas, configuracion principal y realtime progresivo estan cubiertos.

- ¿Que funcionalidades criticas faltan?
  - CRUD SPA completo de dispositivos y sensores.
  - Modulos admin de catalogos, roles y metricas.
  - Validacion manual de realtime/Pusher y de flujos basados en correo.

- ¿Que no se debe borrar todavia?
  - `back/resources/views/emails/alert.blade.php`
  - Rutas y vistas Blade de catalogos admin (`sensor-types`, `device-types`, `labs`)
  - `config/user-roles`, `metrics`
  - Rutas Blade de create/edit/show/export que la SPA aun no cubre totalmente.

- ¿Que se debe probar manualmente antes de Fase 6B?
  - Login/registro/reset/verificacion en navegador real.
  - Dashboard con datos reales.
  - Toggle de dispositivos desde SPA.
  - Detalle de sensor con nuevas lecturas y evento realtime.
  - Reglas de alerta CRUD como admin.
  - Configuracion SMTP y envio de email real.
  - Realtime de alertas con Pusher credenciales reales.
