# Definición funcional — cobranzas

Este documento reúne las decisiones funcionales vigentes para la primera
iniciativa del módulo de cobranzas. Ante una diferencia con propuestas,
prototipos o documentos anteriores, estas decisiones prevalecen.

## Alcance de la primera iniciativa

Incluye programación, hojas de ruta, visitas, declaraciones de cobro,
sincronización offline, rendición, conciliación e incidencias.

Quedan fuera de esta iniciativa los reportes, la logística y entregas, la
refinanciación, los recuperados, la facturación y las reglas comerciales
adicionales.

Las entidades existentes de identidad, empresa, clientes, ventas, cuotas y
cuenta corriente se conservan como soporte interno. El dashboard de
Administración muestra Clientes para consultar la cartera real de la empresa
activa; no se muestran enlaces administrativos ajenos al flujo de cobranzas.
La aplicación móvil se limita al modo cobrador.

## Roles operativos

Administración realiza toda la operación de oficina: programa y reasigna
visitas, concilia cobros, aprueba anticipos, registra ajustes y resuelve
incidencias. El dashboard administrativo y la aplicación móvil del cobrador
solo muestran acciones necesarias para este flujo. El cobrador inicia su hoja y
registra sus visitas y declaraciones de cobro desde la aplicación móvil.

La aplicación móvil del cobrador dispone de Inicio, Ruta, Clientes, Cobros y
Perfil. Inicio resume únicamente las visitas asignadas y las operaciones locales
pendientes de sincronización; no presenta metas ni importes ficticios. Clientes
permite consultar la cartera autorizada sincronizada y sus obligaciones; el
registro de un cobro se inicia desde una visita asignada.

## Hojas y visitas

- Una hoja tiene un único cobrador.
- Una visita corresponde a una venta y puede agrupar sus cuotas exigibles.
- Administración puede programar libremente una venta para cualquier fecha,
  incluso si no tiene cuotas exigibles. La visita informa el saldo vigente y
  solo permite aplicar cobros a obligaciones aprobables.
- La hoja pasa a estar en curso únicamente cuando el cobrador la inicia de
  forma manual.
- La reasignación de una visita pendiente la traslada a una hoja propia del
  cobrador de destino. Se conserva el motivo y la auditoría.
- Cada cuota puede tener una sola promesa de pago activa. Una nueva promesa
  reemplaza o cierra la anterior, sin borrar el historial.
- Si una visita no registra pago, el cobrador debe elegir un motivo de una
  lista. Al elegir `Otro`, debe detallar el motivo.

## Declaraciones de cobro y deuda

El cobrador puede declarar pagos en efectivo, transferencia o una combinación
de ambos, indicando importe y medio de pago. Los comprobantes adjuntos y las
referencias son opcionales.

La declaración queda visible en la cuenta del cliente como pendiente de
conciliación, pero no reduce el saldo exigible ni la deuda definitiva. Solo la
aprobación o conciliación administrativa aplica el cobro a la cuenta corriente.

Las transferencias se revisan y concilian de forma individual por
Administración. Los importes que exceden las cuotas exigibles no se aplican
automáticamente a cuotas futuras: quedan como crédito pendiente de aprobación
administrativa.

El efectivo declarado se aprueba al recibirse físicamente durante la rendición
de esa misma hoja. Cada hoja tiene una rendición independiente. Si el efectivo
recibido difiere de lo declarado, se aprueba solamente el importe recibido y la
diferencia se registra como incidencia.

## Sincronización y cierre

Las operaciones móviles usan identificadores únicos y pueden registrarse sin
conexión. Si una visita fue reasignada y el cobrador anterior sincroniza un
cobro, la operación se rechaza y no modifica la deuda; sus datos y evidencias
se conservan en una incidencia financiera para revisión.

Una hoja con resultados aún sin sincronizar no puede rendirse ni cerrarse. Una
vez cerrada, su resultado original es inmutable. Las diferencias o correcciones
posteriores se tratan mediante una incidencia y un ajuste nuevo auditado; la
hoja no se reabre.
