# Manual Validation Checklist

Creado en Fase 7B. Este checklist no fue ejecutado manualmente en esta sesion; sirve como guia operativa antes de limpiar Blade o rutas web legacy.

## Auth
- [ ] Login con usuario verificado
- [ ] Login con credenciales invalidas
- [ ] Registro
- [ ] Forgot password
- [ ] Reset password
- [ ] Verify email
- [ ] Logout

## Dashboard
- [ ] Carga metricas
- [ ] Carga alertas activas
- [ ] Carga dispositivos
- [ ] Carga grafica
- [ ] Maneja datos vacios
- [ ] Maneja errores

## Sensores
- [ ] Lista sensores
- [ ] Filtro local
- [ ] Detalle sensor
- [ ] Tabla lecturas
- [ ] Grafica lecturas
- [ ] Maneja sensor sin lecturas

## Dispositivos
- [ ] Lista dispositivos
- [ ] Muestra estado
- [ ] Muestra sensores asociados si aplica

## Alertas
- [ ] Lista alertas
- [ ] Filtra activas/no resueltas
- [ ] Resuelve una alerta
- [ ] Resuelve todas
- [ ] Actualiza UI despues de acciones

## Reglas de alerta
- [ ] Lista reglas
- [ ] Crea regla
- [ ] Edita regla
- [ ] Elimina regla
- [ ] Muestra errores de validacion

## Configuracion
- [ ] Carga configuracion publica
- [ ] Carga configuracion de alertas
- [ ] Actualiza configuracion de alertas
- [ ] Carga configuracion SMTP
- [ ] No muestra contrasena SMTP
- [ ] Prueba email controlada

## Realtime
- [ ] Sin Pusher configurado, polling funciona
- [ ] Con Pusher configurado, badge cambia
- [ ] Toast aparece
- [ ] Sonido respeta `alert_sound_enabled`
- [ ] Sensor realtime si hay evento disponible

## Docker
- [ ] back responde `/api/health`
- [ ] front carga
- [ ] db migra
- [ ] redis responde
- [ ] queue procesa job real o queda documentado
