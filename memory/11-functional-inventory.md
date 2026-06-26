# Functional Inventory

Estados permitidos:
- Funcional
- Parcial
- Pendiente
- Legacy
- Requiere validacion manual

## Backend API

| Modulo | Funcionalidad | Endpoint(s) | Estado | Requiere auth | Consumido por frontend | Validado manualmente | Validado en Docker | Observaciones |
|---|---|---|---|---|---|---|---|---|
| Auth | Login headless | `POST /api/auth/login` | Funcional | No | Si | No | Si | Devuelve Bearer token. |
| Auth | Bootstrap de sesion | `GET /api/auth/me` | Funcional | Si | Si | No | Si | Base de la SPA. |
| Auth | Logout | `POST /api/auth/logout` | Funcional | Si | Si | No | Si | Revoca token actual. |
| Auth | Registro | `POST /api/auth/register` | Funcional | No | Si | No | Si | Requiere validacion manual con correo real. |
| Auth | Recuperacion de contrasena | `POST /api/auth/forgot-password`, `POST /api/auth/reset-password` | Funcional | No | Si | No | Si | Flujo probado por tests backend. |
| Auth | Verificacion de email | `GET /api/auth/verify-email/{id}/{hash}`, `POST /api/auth/resend-verification` | Funcional | Mixto | Si | No | Si | La API responde; falta prueba manual navegador/correo. |
| Dashboard | Health check | `GET /api/health` | Funcional | No | Si | No | Si | Base para healthchecks y Docker. |
| Dashboard | Metricas privadas | `GET /api/dashboard/metrics` | Funcional | Si | Si | No | Si | Cards y resumen principal. |
| Dashboard | Payload publico | `GET /api/dashboard/public` | Funcional | No | Parcial | No | Si | Revisar si debe seguir publico despues de retirar Blade. |
| Dashboard | Preferencias | `GET|PUT /api/dashboard/preferences` | Parcial | Si | Parcial | No | Si | API existe; UX SPA completa no confirmada. |
| Sensores | Listado | `GET /api/sensors` | Funcional | Si | Si | No | Si | Lista operativa SPA. |
| Sensores | Detalle | `GET /api/sensors/{sensor}` | Funcional | Si | Si | No | Si | Vista de detalle SPA. |
| Sensores | Lecturas historicas | `GET /api/sensors/{sensor}/readings` | Funcional | Si | Si | No | Si | Tabla, paginacion y filtro por `from`/`to`. |
| Sensores | Ultimas lecturas | `GET /api/sensors/{sensor}/latest-readings` | Funcional | No | Si | No | Si | Compatibilidad con dashboard y grafica. |
| Sensores | Lecturas agregadas | `GET /api/sensors/all/readings` | Funcional | Si | Parcial | No | Si | Disponible para tableros/analitica. |
| Sensores | CRUD completo y export | `POST|PUT|DELETE /api/sensors...`, `GET /api/sensors/{sensor}/readings/export` | Funcional | Si/admin para escritura | Si | No | No | Implementado en Fase 8A; validado por tests host y build frontend, falta prueba manual navegador. |
| Dispositivos | Listado | `GET /api/devices` | Funcional | Si | Si | No | Si | Lista y resumen SPA. |
| Dispositivos | Detalle | `GET /api/devices/{device}` | Funcional | Si | Si | No | Si | Vista SPA dedicada agregada en Fase 8A. |
| Dispositivos | Sensores asociados | `GET /api/devices/{device}/sensors`, `GET /api/devices/{device}/sensor-list` | Funcional | Mixto | Si | No | Si | Mantiene compatibilidad legacy. |
| Dispositivos | Cambio de estado | `POST /api/devices/{device}/status` | Funcional | Si/admin | Si | No | Si | SPA permite toggle basico. |
| Dispositivos | CRUD completo | `POST|PUT|DELETE /api/devices...` | Funcional | Si/admin | Si | No | No | Implementado en Fase 8A; delete limpia status logs cuando no hay sensores asociados. |
| Alertas | Feed activo | `GET /api/alerts/active` | Funcional | No | Si | No | Si | Base para polling y reconciliacion realtime. |
| Alertas | Historial | `GET /api/alerts` | Funcional | Si | Si | No | Si | Listado SPA. |
| Alertas | No resueltas | `GET /api/alerts/unresolved` | Funcional | Si | Si | No | Si | Badge y filtro SPA. |
| Alertas | Resolver una | `PATCH /api/alerts/{alert}/resolve` | Funcional | Si | Si | No | Si | Accion SPA. |
| Alertas | Resolver todas | `POST /api/alerts/resolve-all` | Funcional | Si | Si | No | Si | Accion SPA. |
| Alertas | Detalle individual | `GET /api/alerts/{alert}` | Funcional | Si | Si | No | No | Vista `/alerts/:id` agregada en Fase 8A. |
| Reglas de alerta | CRUD JSON | `GET|POST|PUT|DELETE /api/alert-rules*` | Funcional | Si/admin | Si | No | Si | SPA administra reglas. |
| Configuracion publica | Flags seguros | `GET /api/config/public` | Funcional | No | Si | No | Si | Sonido y datos publicos del frontend. |
| Configuracion alertas | Leer/guardar | `GET|PUT /api/config/alerts` | Funcional | Si/admin | Si | No | Si | Pantalla SPA integrada. |
| Configuracion email | Leer/guardar/probar | `GET /api/config/email`, `PUT /api/config/email`, `POST /api/config/email/test` | Funcional | Si/admin | Si | No | Si | No expone password SMTP. |
| Realtime/Pusher | Alertas | Canal `alerts`, evento `NewAlertTriggered` | Requiere validacion manual | N/A | Si | No | Parcial | Implementado; la SPA local no esta configurada con `VITE_PUSHER_*`. |
| Realtime/Pusher | Sensores | Canal `sensor.{id}`, evento `NewSensorReading` | Requiere validacion manual | N/A | Si | No | Parcial | Preparado para `SensorDetailView`; falta evento real en navegador. |
| Ingestion IoT | Descubrimiento sensores | `GET /api/iot/sensors` | Funcional | API key | No directo | No | Si | Consumido por simulador/dispositivos. |
| Ingestion IoT | Ingreso de lectura | `POST /api/sensors/{sensor}/readings` | Funcional | API key | No directo | No | Si | Base del flujo IoT. |
| Simulador IoT | Script Python | `script_datos.py` | Funcional | API key | No | No | No | Sigue fuera de la SPA; usa endpoints backend. |
| Queue/runtime | Cola Docker | Servicio `queue` + `QueueSmokeJob` | Funcional | N/A | No | No | Si | Infra validada con un smoke job controlado; el sistema aun no tiene workload de negocio encolado. |
| Docker/runtime | Desarrollo containerizado | `back/Dockerfile`, `front/Dockerfile`, `docker-compose.yml` | Funcional | N/A | Si | No | Si | `back`, `front`, `db` y `redis` validados. |
| Catalogos admin | Labs, sensor-types, device-types | `GET|POST|PUT|DELETE /api/labs`, `/api/sensor-types`, `/api/device-types` | Funcional | Si/admin | Si | No | No | CRUD SPA/API agregado en Fase 8A. |
| Roles/metricas admin | User roles, metricas internas | `GET /api/users`, `PATCH /api/users/{user}/role`, `GET /api/metrics` | Funcional | Si/admin | Si | No | No | Migrado a SPA en Fase 8A. |
| Perfil | Perfil de usuario | `GET /api/profile` | Funcional | Si | Si | No | No | Vista SPA read-only equivalente al Blade legacy. |

## Frontend SPA

| Pantalla/componente | Ruta | Funcionalidad | Endpoint(s) usados | Estado | Validado manualmente | Validado en Docker | Observaciones |
|---|---|---|---|---|---|---|---|
| Login | `/login` | Inicio de sesion | `POST /api/auth/login`, `GET /api/auth/me` | Funcional | No | No | Bearer token en localStorage. |
| Register | `/register` | Registro | `POST /api/auth/register` | Funcional | No | No | Validacion manual por correo pendiente. |
| Forgot password | `/forgot-password` | Solicitar reset | `POST /api/auth/forgot-password` | Funcional | No | No | Backend probado; falta prueba manual. |
| Reset password | `/reset-password` | Confirmar reset | `POST /api/auth/reset-password` | Funcional | No | No | Lee token/email del enlace. |
| Verify email | `/verify-email/:id/:hash` | Verificar email | `GET /api/auth/verify-email/{id}/{hash}` | Funcional | No | No | Prueba manual pendiente. |
| Dashboard | `/dashboard` | Cards, alertas activas, dispositivos, grafica, lecturas | `GET /api/dashboard/metrics`, `GET /api/alerts/active`, `GET /api/devices` | Funcional | No | No | Realtime complementa; polling sigue activo. |
| Sensores | `/sensors` | Listado, filtro local, crear, editar, eliminar y exportar | `GET|POST|PUT|DELETE /api/sensors`, `GET /api/sensors/{sensor}/readings/export` | Funcional | No | No | Fase 8A; delete se bloquea si hay lecturas o reglas asociadas. |
| Detalle sensor | `/sensors/:id` | Tabla, grafica, filtro por fechas, export y realtime preparado | `GET /api/sensors/{sensor}`, `GET /api/sensors/{sensor}/readings`, `GET /api/sensors/{sensor}/latest-readings`, export | Funcional | No | No | Realtime real pendiente de validacion manual. |
| Dispositivos | `/devices`, `/devices/:id` | Listado, estado, create/edit/delete y detalle con sensores | `GET|POST|PUT|DELETE /api/devices`, `POST /api/devices/{device}/status`, `GET /api/devices/{device}/sensors` | Funcional | No | No | Fase 8A; delete se bloquea si hay sensores asociados. |
| Alertas | `/alerts`, `/alerts/:id` | Lista, filtros, detalle individual, resolver una/todas | `GET /api/alerts`, `GET /api/alerts/{alert}`, `GET /api/alerts/unresolved`, `GET /api/alerts/active`, `PATCH /api/alerts/{alert}/resolve`, `POST /api/alerts/resolve-all` | Funcional | No | No | Realtime integrado en estructura; falta validacion manual con evento real. |
| Reglas de alerta | `/alert-rules` | CRUD | `GET|POST|PUT|DELETE /api/alert-rules*` | Funcional | No | No | Admin-only. |
| Configuracion | `/config` | Alertas, email y enlaces SPA admin | `GET /api/config/public`, `GET|PUT /api/config/alerts`, `GET|PUT /api/config/email`, `POST /api/config/email/test` | Funcional | No | No | Acciones de catalogos, roles y metricas apuntan a SPA desde Fase 8A. |
| Badge/toast realtime | Layout/Navbar | Badge, toast y sonido | `GET /api/alerts/active`, `GET /api/config/public` + Pusher | Requiere validacion manual | No | Parcial | El frontend no tiene `front/.env` local con `VITE_PUSHER_*`; hoy opera por polling fallback. |
| SMTP/email test | `/config` | Prueba de envio | `POST /api/config/email/test` | Funcional | No | No | Requiere ambiente SMTP real para validacion completa. |
| Dashboard preferences | Interno | API ya disponible | `GET|PUT /api/dashboard/preferences` | Parcial | No | No | Cliente API existe; UX final no esta confirmada. |
| Catalogos admin | `/labs`, `/sensor-types`, `/device-types` | CRUD de catalogos | APIs JSON de catalogos | Funcional | No | No | Reemplaza puentes Blade principales de catalogos. |
| Usuarios/roles | `/users` | Listar usuarios y cambiar rol admin | `GET /api/users`, `PATCH /api/users/{user}/role` | Funcional | No | No | Conserva protecciones contra auto-democion y ultimo admin. |
| Metricas tecnicas | `/metrics` | KPIs y serie API reciente | `GET /api/metrics` | Funcional | No | No | Reemplaza vista Blade de metricas tecnicas. |
| Perfil | `/profile` | Perfil read-only | `GET /api/profile` | Funcional | No | No | Equivalente a `profile.blade.php`; no incluye edicion porque Blade no la tenia. |
