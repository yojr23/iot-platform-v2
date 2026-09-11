# Diseño: configuración híbrida Lab Blue

## Objetivo

Reemplazar la vista monolítica de configuración por una entrada administrativa
clara y cuatro vistas enfocadas: General, Alertas, Correo y Diagnóstico. El
trabajo conserva los contratos API, permisos y operaciones que ya existen.

La referencia visual seleccionada es la tercera propuesta generada el
2026-09-11: resumen superior de estado, lista operativa con acciones `Abrir` y
un bloque de diagnóstico de sólo lectura. Se adapta al shell Lab Blue existente
sin introducir controles ni valores que el backend no soporte.

## Información y rutas

- `/config`: entrada de Configuración. Presenta el nombre/URL de aplicación,
  estado de alertas, estado de la configuración SMTP y un resumen de
  diagnóstico cuando los datos estén disponibles. Cuatro filas navegables
  llevan a las vistas específicas.
- `/config/general`: formulario de `app_name` y `app_url`; guarda mediante
  `PUT /config/general`.
- `/config/alerts`: preferencias de correo, sonido, umbral, intervalo y límite
  de correo crítico; guarda mediante `PUT /config/alerts`.
- `/config/email`: parámetros SMTP y envío de prueba; usa los endpoints de
  correo existentes.
- `/config/diagnostics`: información de PHP, Laravel, entorno y motor de base
  de datos, en sólo lectura.

Todas las rutas continúan protegidas por `requiresAuth` y `requiresAdmin`.
No se crean endpoints, no se muestran secretos y no se modifica el contrato de
ninguna API.

## Interfaz y comportamiento

La entrada mantiene el encabezado Lab Blue y utiliza una única superficie de
lista, separada por líneas finas. Un resumen superior usa estados sencillos
con texto: configurado, requiere revisión o no disponible; no infiere salud del
sistema más allá de las respuestas ya disponibles.

Cada fila tiene icono de la familia existente, título, descripción concreta,
estado opcional y una acción visible `Abrir`. Las subpantallas incluyen una
miga o acción `Volver a Configuración`, título, explicación concisa y su única
acción de guardado o prueba. Los mensajes de éxito y error existentes se
conservan. La vista de correo conserva la indicación de contraseña configurada,
sin leer ni revelar la contraseña.

La paleta usa los tokens actuales: superficies blancas, fondo `--sinoa-bg`,
texto oscuro, borde sutil y azul para acciones. Verde, ámbar y rojo se limitan
a estados semánticos. En móvil, el resumen se apila, las filas conservan un
objetivo táctil de al menos 44 px y las acciones no se desbordan.

## Componentes y datos

`ConfigView.vue` se reduce a la entrada. Las cuatro vistas enfocadas comparten
un encabezado de sección y un estado de carga. Se extraerán helpers locales
cuando eviten duplicar carga, mensajes o navegación, sin modificar los módulos
de API existentes. Los iconos se amplían en el componente Lab Blue existente
con trazos del mismo estilo.

La entrada carga en paralelo la configuración pública autenticada, alertas,
correo y diagnóstico. Un fallo secundario no bloquea las demás secciones: la
fila muestra un estado no disponible y permite abrir la vista correspondiente,
donde el mensaje de error es accionable.

## Verificación

- Pruebas unitarias de rutas, navegación de las cuatro filas, carga parcial,
  estados de sólo lectura y cada operación de guardado/prueba.
- Construcción del frontend y suite unitaria existente.
- Recorrido manual: entrada → General → guardar → volver; entrada → Alertas →
  guardar; entrada → Correo → prueba de email; entrada → Diagnóstico.
- Revisión desktop y móvil contra la referencia visual seleccionada, sin
  desbordamiento ni controles inertes.

## Límites

No se agregan preferencias de zona horaria, idioma, sesión, dos factores,
tema, pruebas de base de datos ni telemetría nueva porque no existen contratos
de backend para ellas.
