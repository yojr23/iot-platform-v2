# Corrección de colores de gráfica — 11 de septiembre de 2026

Se exploraron las relaciones con `graphify query "SensorReadingChart
zoneBackgroundPlugin RuleToGraphZones demo-server"` y se verificaron contra los
archivos actuales. El backend ya proyecta zonas desde `RuleToGraphZones` y el
frontend ya las pinta con los tokens SINOA.

La respuesta real del servidor demo en `localhost:5173` carecía de `bands` para
sus cuatro sensores. `buildZonesViewModel` convertía esa ausencia en una zona
neutral gris, correctamente para datos sin configuración. Se añadieron intervalos
sintéticos explícitos al servidor de demostración; no se modifican reglas ni datos
del backend. La zona inferior al mínimo de temperatura es amarilla según la
configuración de ejemplo, no se presenta como normal.

La gráfica reserva ahora margen fuera de los límites configurados para mostrar
las bandas exteriores incluso cuando todas las mediciones están dentro del rango.
El gris sigue representando límites no configurados en sensores sin reglas.

Durante la prueba se reprodujeron respuestas Vite `504 Outdated Optimize Dep`:
los servidores demo y normal compartían la caché de dependencias pese a tener
distintas definiciones. `cacheDir` se separó por modo para evitar esa colisión.

Verificación realizada:

- 34 pruebas correctas: `zoneBackgroundPlugin`, `graphZonesProjection`,
  `chartTheme` y `SensorReadingChart`. JSDOM conserva su aviso de canvas sin backend.
- Chrome headless: `node .audit-e2e/chart-colors.mjs`, a 1440 y 390 px, contra
  la demo local. Comprueba más de 100 píxeles verdes, amarillos, rojos y azules
  en cada canvas y ausencia de errores JavaScript. Capturas revisadas visualmente.
- `npm.cmd run build`: correcto.
- Capturas: `front/.audit-e2e/results/chart-colors/dashboard-1440.png` y
  `dashboard-390.png`. Los datos son simulados; no se validó un backend de producción.
