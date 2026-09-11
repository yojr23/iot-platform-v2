





Base: rama `refraccion`, commit `c60b6dc`. Cambio visual solicitado el 10 de septiembre de 2026.

## Implementación

Las rutas `/devices`, `/devices/:id`, `/sensors`, `/sensors/:id`, `/alerts`,
`/alerts/:id` y `/alert-rules` reutilizan `LabShell`, el mismo contenedor del
dashboard: navegación SINOA, cabecera, fondo y navegación móvil. Se conservan
los accesos administrativos y de cuenta, así como el componente de avisos de
alertas para sesiones autenticadas. La lista de navegación permite desplazamiento
vertical cuando no cabe en la pantalla.

`front/src/assets/styles/lab-resources.css` adapta los componentes existentes
de Bootstrap dentro de `.lab-resource-page`, usando los tokens de `lab-blue.css`.
Incluye encabezados, botones, filtros, tablas, etiquetas de estado, paneles de
detalle y formularios modales. Las tablas extensas conservan desplazamiento
horizontal dentro de su propio contenedor. Las acciones de formularios se
reorganizan cuando falta espacio.

Los filtros de sensores tienen nombres accesibles y el filtro de alertas expone
su selección mediante `aria-pressed`. Los textos nuevos describen tareas del
operador, sin referencias a CRUD ni a la API.

Se consultaron los tres Markdown y el contenido del DOCX de `front_rebuild_plan`.
Las correcciones de alcance público prevalecen sobre el mockup. Este cambio no
modifica rutas API, permisos, stores, suscripciones ni reglas de negocio.

## Verificación

- `npm.cmd run build`: correcto. Advertencia de deprecación de la API antigua de Sass.
- `npm.cmd run test:unit`: 27 archivos, 133 pruebas correctas. Los tests existentes
  generan avisos de canvas no implementado en JSDOM y de contexto de Vue Router.
- `node .audit-e2e/lab-resources.mjs`: Chrome headless con fixtures sintéticos;
  siete rutas a 320, 390, 768, 1024, 1280 y 1440 px. Comprueba el contenedor Lab Blue,
  títulos, ausencia de desbordamiento global y ausencia de errores de ejecución.
  También verifica búsqueda vacía de sensores, filtro de alertas, apertura de
  formularios de dispositivo/sensor/regla, cierre con Escape y ausencia de
  desbordamiento en los diálogos.
- Capturas y resultados locales: `front/.audit-e2e/results/lab-resources/`.
  El script requiere Chrome instalado y Vite en `http://127.0.0.1:5175`.
- La revisión visual usa datos sintéticos; no constituye una prueba de escrituras
  contra un backend real ni de transporte WebSocket en producción.

## Hallazgo previo separado

El diálogo de nueva regla se cierra con Escape, pero no restaura el foco al botón
«Nueva regla». El flujo existente deshabilita ese botón durante la carga de
metadatos, antes de que `BaseModal` capture el elemento activo. La restauración
de foco sí se verificó en los formularios de dispositivos y sensores. No se
modificó ese comportamiento como parte de la adaptación visual.

## Mantenimiento

Reutilizar `.lab-resource-page`, sus encabezados y los tokens SINOA para cambios
posteriores. Mantener estilos de esta familia acotados; los significados de
severidad, estado y visibilidad pública siguen perteneciendo a sus owners actuales.
Para revertir esta entrega, revertir únicamente sus cambios de presentación y
composición. No requiere migraciones ni cambios de datos.
