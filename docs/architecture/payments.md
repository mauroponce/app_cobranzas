# Cobros manuales

Los pagos se registran en centavos exactos. Un pago en efectivo confirmado se imputa en una transacción a obligaciones de la cuenta corriente, empezando por las vencidas y luego por las futuras. El excedente queda como crédito del cliente.

Una transferencia declarada queda `pending_verification`; el comprobante no modifica deuda. Solo `payments.verify_transfer` la confirma e imputa. Los reversos no eliminan movimientos y no alteran una rendición de caja ya aprobada.

## Alcance de clientes en cobranzas

El alcance `own` no identifica al vendedor de una venta. Vender y cobrar son responsabilidades distintas, y una venta no asigna una cartera de cobranzas. Mientras no exista una relación verificable de cartera, un permiso de cobros con alcance distinto de `all` no puede consultar clientes para sincronización ni confirmar pagos; el servidor responde con denegación y no crea movimientos financieros.

En la etapa 6, `assigned` representará una asignación vigente de cliente a cobrador y `team` una asignación vigente a un equipo de cobranzas. Ambas deberán tener tenant, vigencia, origen auditado y consultas filtradas en servidor. Recién entonces esos alcances podrán habilitar clientes y obligaciones concretos.
