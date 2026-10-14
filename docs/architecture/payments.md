# Cobros manuales

Los pagos se registran en centavos exactos. Un pago en efectivo confirmado se imputa en una transacción a obligaciones de la cuenta corriente, empezando por las vencidas y luego por las futuras. El excedente queda como crédito del cliente.

Una transferencia declarada queda `pending_verification`; el comprobante no modifica deuda. Solo `payments.verify_transfer` la confirma e imputa. Los reversos no eliminan movimientos y no alteran una rendición de caja ya aprobada. Este apartado describe pagos administrativos directos; la captura desde Expo sigue el flujo de declaraciones siguiente.

## Declaraciones del cobrador

La aplicación móvil no confirma pagos directamente. Registra una declaración
asociada a una visita de una hoja iniciada, con uno o más medios admitidos. La
declaración queda pendiente y no reduce el saldo exigible ni la deuda definitiva.
Una transferencia sin efectivo puede conciliarse individualmente desde el
panel; el efectivo y las declaraciones combinadas se resuelven durante la
rendición. El panel lista todos esos estados y recibe una invalidación SSE luego
del commit de cada nueva declaración.

## Alcance de clientes en cobranzas

El alcance `own` no identifica al vendedor de una venta. Vender y cobrar son responsabilidades distintas, y una venta no asigna una cartera de cobranzas. Mientras no exista una relación verificable de cartera, un permiso de cobros con alcance distinto de `all` no puede consultar clientes para sincronización ni confirmar pagos; el servidor responde con denegación y no crea movimientos financieros.

`assigned` representa una asignación vigente de cliente a cobrador y `team` una
asignación vigente a un equipo de cobranzas. Ambas llevan empresa, vigencia y
origen auditado; las consultas se filtran en el servidor. Estos alcances
habilitan únicamente la cartera efectiva del cobrador.
