# Runbook del piloto

Este procedimiento implementa un piloto acotado de cobranzas. Las reglas
financieras permanecen en [la definición funcional](../definicion_funcional.MD).

## Modalidades

### Ensayo supervisado

Usa únicamente datos sintéticos o controlados. El sistema previo conserva toda
autoridad; ninguna declaración representa dinero real. Se ensayan instalación,
offline, sincronización, rendición, conciliación, soporte y recuperación.

### Piloto real autorizado

Usa la cartera, jornadas, responsables y dispositivos enumerados en el acta
privada. Antes del corte se define por escrito cuál sistema es la fuente oficial
de saldos y cobranzas. Nunca se registran pagos independientes en dos fuentes:
si el sistema anterior debe seguir disponible, queda en modo consulta o cada
movimiento se anota en un registro único de transición y se concilia antes del
cierre.

## Preparación y corte

1. Completar la puerta GO y registrar commit, imagen, APK y configuración.
2. Inventariar fuentes, custodio y permisos; copiar los archivos reales a un
   repositorio privado y conservar hash, fecha y responsable.
3. Elegir fecha/hora de corte. Cerrar altas y cobros en la fuente anterior o
   aplicar el registro único de transición.
4. Deduplicar clientes por identificadores acordados. Revisar ventas,
   obligaciones, saldos y asignaciones; no inferir operaciones faltantes.
5. Comparar totales por cliente, cartera, vencimiento y total general. Toda
   diferencia requiere decisión registrada.
6. Administración firma saldos de apertura y asignaciones. Si no existe una
   importación aprobada, cargar solo mediante flujos reales soportados; no
   representar saldos con ventas o pagos ficticios.
7. Tomar y comprobar un backup antes de migrar. Una migración real requiere
   autorización específica distinta del GO documental.

## Antes de cada jornada

- Confirmar `/api/v1/health` y `/api/v1/readiness`, contenedores, cola,
  scheduler, almacenamiento y espacio libre.
- Verificar último backup y resultado de restauración vigente.
- Confirmar alcance, fecha, fuente oficial, saldos, usuarios, permisos,
  dispositivos, carteras y hojas.
- Comprobar que cada dispositivo tenga la versión autorizada, HTTPS y hora
  automática; realizar login y sincronización sin usar credenciales ajenas.
- Publicar contacto primario y suplente de soporte, canal y criterio de
  suspensión.
- Abrir [un informe de jornada](pilot-day-report.template.md).

## Durante la jornada

- Administración observa operaciones pendientes, rechazadas e incidencias en
  **Soporte**, correlacionando por UUID; no lee ni edita la base.
- El cobrador registra desde la visita asignada. Si queda offline, no borra la
  aplicación ni la outbox y espera confirmación visible de sincronización.
- Registrar hora, dispositivo, usuario, hoja, visita, UUID y mensaje de cada
  incidente sin copiar tokens, contraseñas, documentos ni payloads completos.
- Antes de reasignar, revisar si el dispositivo original conserva operaciones.
- Ante duplicado aparente, suspender la conciliación del caso hasta confirmar
  UUID, importes, obligación e historial.

## Cierre

1. Confirmar que todas las visitas tienen resultado y que la outbox del
   dispositivo que presenta está vacía.
2. Presentar una rendición por hoja. Contar efectivo físicamente con doble
   control y verificar transferencias individualmente.
3. Atribuir cualquier efectivo recibido menor al declarado; no distribuir una
   diferencia automáticamente.
4. Confirmar saldos, pagos, imputaciones, incidencias y cierre inmutable.
5. Comparar contra la fuente oficial o registro de transición y firmar totales.
6. Completar resultados esperados/observados, diferencias, incidencias,
   pendientes y horas de soporte.
7. Tomar backup y conservar checksums según la política aprobada.

## Suspensión y retorno

Suspender nuevas operaciones ante indisponibilidad sin recuperación dentro del
tiempo acordado, pérdida de trazabilidad, saldo incorrecto no explicado,
duplicación confirmada, imposibilidad de custodiar efectivo o pérdida de un
dispositivo activo. Revocar el dispositivo si corresponde, congelar nuevas
hojas y registrar la hora de corte. Exportar el inventario de operaciones ya
aceptadas, conciliarlas una por una con la fuente anterior y solo entonces
retomar allí. Nunca borrar movimientos, reabrir cierres ni corregir tablas a
mano.
