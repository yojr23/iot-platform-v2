# Diseño: control de acceso por rol para monitoreo IoT

**Fecha:** 2026-09-11  
**Estado:** aprobado para planificación

## Objetivo

Separar con precisión las capacidades del usuario autenticado estándar (`auth`,
`is_admin = false`) de las del administrador (`admin`, `is_admin = true`). Un
usuario estándar puede monitorear el sistema, consultar métricas y gestionar la
resolución operativa de alertas; no puede administrar recursos ni configuración.
El administrador conserva todas las capacidades existentes.

## Política de capacidades

| Capacidad | Usuario autenticado | Administrador |
| --- | --- | --- |
| Dashboard, dispositivos, sensores y sus detalles | Consultar | Consultar y administrar |
| Alertas e historial | Consultar | Consultar |
| Resolver una alerta / resolver alertas activas | Sí | Sí |
| Métricas operativas | Consultar | Consultar |
| Crear, editar o eliminar dispositivos y sensores | No | Sí |
| Reglas de alerta | No | Sí |
| Configuración, diagnósticos y correo | No | Sí |
| Catálogos (laboratorios, tipos de sensor, tipos de dispositivo) | No | Sí |
| Usuarios y roles | No | Sí |

La resolución de alertas es la única mutación permitida al usuario estándar. No
se interpreta como permiso de administración de recursos.

## Arquitectura y flujo

El campo de sesión existente `user.is_admin` sigue siendo la fuente de la UI.
Las rutas Vue aplican `requiresAdmin` a todas las pantallas administrativas. La
vista de métricas cambia a `requiresAuth` solamente y se presenta en la
navegación de cualquier sesión autenticada.

La API es la frontera de seguridad. `GET /api/metrics` pasa del grupo `admin` al
grupo `auth:sanctum`, con límite de lectura. Los endpoints de dispositivos,
sensores, catálogos, configuración, reglas y roles conservan el middleware
`admin`. Las rutas de alertas permanecen autenticadas y mantienen las acciones
`PATCH /api/alerts/{alert}/resolve` y `POST /api/alerts/resolve-all` disponibles
para ambos roles.

No se conceden capacidades por ocultar controles: el backend debe devolver 403
para cualquier intento de administración hecho por un usuario estándar.

## Componentes afectados

- `front/src/router/index.js`: acceso autenticado a Métricas; administración
  continúa restringida.
- `front/src/components/layout/NavBar.vue` y
  `front/src/components/dashboard/lab/LabShell.vue`: Métricas visible para toda
  sesión autenticada; Configuración sólo para administradores.
- `back/routes/api.php`: lectura de métricas para `auth:sanctum`; escritura y
  administración sin cambios de privilegio.
- Pruebas de router, navegación y feature tests Laravel: verifican la matriz
  completa para usuario estándar y administrador.

## Manejo de errores y seguridad

Una navegación directa de un usuario estándar a una ruta administrativa se
redirige al dashboard, como hoy. Una llamada directa a un endpoint administrativo
debe seguir respondiendo 403. Una sesión anónima sigue recibiendo 401 en métricas
y alertas. La interfaz conserva el manejo actual de errores de API; no se
introducen tokens ni roles nuevos.

## Pruebas

1. Prueba de router: Métricas exige autenticación, no administración; las rutas
   administrativas siguen exigiendo ambas.
2. Pruebas de navegación: el usuario estándar ve Métricas y no ve Configuración;
   el administrador ve ambas.
3. Feature test: usuario estándar obtiene 200 de `GET /api/metrics`.
4. Feature tests de seguridad: usuario estándar conserva 403 en escrituras y
   rutas administrativas representativas; administrador conserva 200/éxito.
5. Suite frontend y backend correspondiente, seguida de build del frontend.

## Límites de alcance

No se cambia el modelo `is_admin`, la emisión de tokens Sanctum, la visibilidad
de datos de sensores/dispositivos, ni las capacidades de invitados. Tampoco se
añade auditoría de quién resolvió una alerta: es una mejora independiente.
