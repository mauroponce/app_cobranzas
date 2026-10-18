# Checklist de aceptación del piloto

Cada fila se marca `PASS`, `FAIL`, `BLOCKED` o `NOT RUN`, con fecha, actor y
evidencia. `PASS` exige una observación real; no se hereda de una prueba distinta.

## Administración

- [ ] Login y tenant correcto; intento cruzado devuelve denegación.
- [ ] Cliente y cuenta muestran saldos que coinciden con la fuente firmada.
- [ ] Hoja programada contiene exactamente la cartera y fecha aprobadas.
- [ ] Declaración sincronizada aparece una sola vez y conserva UUID.
- [ ] Transferencia verificada reduce deuda solo por el importe autorizado.
- [ ] Rendición recibe efectivo, conserva diferencia y cierra una sola vez.
- [ ] Incidencia y auditoría permiten reconstruir actor, objeto y hora.
- [ ] Dispositivo revocado pierde su sesión y el evento queda auditado.

## Cobrador

- [ ] Login, registro de dispositivo y cierre de sesión funcionan en el Android autorizado.
- [ ] Solo ve cartera y hoja autorizadas.
- [ ] Inicia la jornada manualmente y no cobra sobre hoja no iniciada/cerrada.
- [ ] Registra visita sin pago y cobros en efectivo, transferencia y combinados.
- [ ] Sin conexión, la operación persiste tras cerrar/abrir la app.
- [ ] Al reconectar, dos sincronizaciones no duplican la operación.
- [ ] Distingue pendiente local, pendiente de revisión y rechazada.
- [ ] No presenta rendición con visitas u outbox pendientes.

## Finanzas y control

- [ ] Declarar y sincronizar no cambia deuda antes de conciliación.
- [ ] Cada componente confirmado se imputa a obligaciones correctas en centavos exactos.
- [ ] UUID repetido igual es idempotente; UUID con contenido diferente no impacta.
- [ ] Diferencias permanecen visibles y no crean créditos automáticos.
- [ ] Un cierre aprobado no se altera silenciosamente.
- [ ] Usuario, dispositivo o tenant revocado no impacta saldo.
- [ ] Totales iniciales y finales coinciden con fuente oficial o tienen diferencias firmadas.

## Infraestructura y recuperación

- [ ] HTTPS válido en dominio aprobado; HTTP no se utiliza desde la app.
- [ ] App, Nginx, PostgreSQL, Redis, queue y scheduler saludables.
- [ ] Logs no exponen tokens, contraseñas ni documentos completos.
- [ ] Backup incluye DB, documentos, checksums y revisión Git.
- [ ] Restore en base nueva `_test` reproduce migraciones, tablas, tenants y muestra documental.
- [ ] Reinicio controlado recupera servicios y operación pendiente.
- [ ] RPO/RTO, retención y responsables están aprobados.

## Criterio de salida

GO requiere todas las filas críticas en `PASS`, ningún `FAIL`, los `BLOCKED` y
`NOT RUN` evaluados y aceptados expresamente, CI/revisión independiente
satisfactorios y firma en la plantilla de aceptación.
