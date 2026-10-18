# Guía breve por rol

## Administración

Ingresá en el tenant correcto, comprobá saldos y carteras, programá hojas y
supervisá Cobranzas/Soporte. Verificá transferencias por componente. En la
rendición contá el efectivo, ingresá lo recibido y atribuí manualmente una
diferencia. No confirmes por una captura ni corrijas la base.

## Supervisor

Revisá hojas, asignaciones, sincronizaciones e incidencias. Antes de reasignar,
preguntá por operaciones offline del dispositivo original. Escalá cualquier
diferencia de saldo, duplicado o cierre dudoso; no fuerces estados.

## Cobrador

Sincronizá antes de salir, iniciá la hoja y registrá cada visita desde su ficha.
El mensaje debe indicar si quedó local, sincronizada o en revisión. Sin red,
seguí solo si la operación queda guardada; no desinstales ni borres datos.
Presentá la rendición cuando todas las visitas tengan resultado y no existan
pendientes locales.

## Errores frecuentes

- **Sin conexión:** conservar la app/outbox y sincronizar luego; no repetir con
  otro UUID si la primera operación existe.
- **Pendiente o rechazada:** anotar UUID y mensaje, avisar a soporte y no cobrar
  de nuevo para “probar”.
- **Cambio de cobrador:** esperar confirmación de reasignación y sincronizar;
  el cobrador anterior no vuelve a registrar sobre esa visita.
- **Diferencia de efectivo:** volver a contar y entregar; Administración
  registra solo lo recibido y la incidencia.
- **Dispositivo perdido:** avisar inmediatamente para revocación. No compartir
  credenciales ni continuar desde un equipo no inventariado.
