# 04 — Escenarios operativos y pruebas de diseño

Los escenarios son **contratos objetivo**, no una afirmación de que funcionen en la rama `main` actual.

## Empresa A: mayorista con vendedores y cobradores (Trotamundo)

**Módulos:** customers, catalog, quotations, sales, financing/accounts, collections, payments/reconciliation, cash settlements. `deliveries` puede permanecer apagado.

**Secuencia**

1. Vendedor con permiso crea cliente/propuesta. Si el cliente o venta requiere aprobación de oficina, queda pendiente.
2. Administración valida documentación/crédito y aprueba o rechaza. Separación de funciones si corresponde.
3. Se confirma venta: obligaciones y cuotas exactas, con varias ventas activas por cliente.
4. Administración asigna cartera/visitas y publica hojas para cobradores; el cobrador inicia su jornada.
5. Cobrador declara pago **en visita** o desde un canal remoto expresamente habilitado. El comprobante o el mensaje de WhatsApp no equivalen por sí solos a un pago confirmado.
6. La declaración móvil se sincroniza de forma idempotente. El cobro informado no reduce deuda contractual hasta validación administrativa según flujo vigente.
7. Transferencias se verifican por Administración o por un proveedor confiable con conciliación; efectivo se recibe físicamente y se rinde por hoja. Diferencias generan incidencia.
8. Administración confirma/imputa y puede reconstruir quién registró, quién recibió, quién verificó y cuándo.

**Prueba:** desactivar entregas no rompe cobranzas. Un vendedor no puede verificar su propia venta ni apropiarse de una cartera de cobrador por haber creado el cliente.

## Empresa B: distribuidora con repartidores que también cobran

**Módulos:** customers, catalog, sales/orders, delivery, payments/reconciliation, cash custody/settlements; financing opcional. Un empleado puede combinar entregas y cobranzas.

**Secuencia**

1. Administración o vendedor toma pedido/venta con productos y destino.
2. Depósito prepara y asigna entrega con evidencia del contenido/estado. No afirmar inventario disponible si no existe módulo de stock confiable.
3. Repartidor recibe listado de entregas autorizado, inicia recorrido y marca entrega completa/parcial/fallida con evidencia.
4. En una entrega puede declarar efectivo recibido o informar transferencia asociada a cliente/obligación.
5. **La entrega exitosa no cancela automáticamente la deuda.** El cobro de reparto ingresa al mismo circuito financiero que una declaración del cobrador, con origen `delivery`.
6. Efectivo recibido genera responsabilidad/custodia del repartidor. Al fin de jornada se transfiere a Administración y se concilia.
7. Rechazos, devoluciones, mercadería faltante, diferencia de efectivo o captura offline se investigan con trazabilidad independiente.

**Prueba:** la misma persona puede entregar y cobrar, pero no aprobar su propia rendición; otro empleado puede solo repartir sin permiso de registrar cobros.

## Empresa C: cobranza remota sin visita

**Módulos:** customers, accounts, payments/reconciliation, posiblemente collections; entregas apagadas. La asignación de cartera no presupone visita física.

**Secuencia**

1. Persona autorizada registra una declaración remota (transferencia informada, link de cobro o notificación).
2. Si proviene de Mercado Pago u otra plataforma, confirmar únicamente con evidencia confiable del proveedor (webhook validado y consulta/conciliación adecuada), no con un mensaje o captura de pantalla.
3. Aplicar a obligaciones de forma idempotente con actor, origen y referencia externa únicos por tenant/proveedor.
4. Exponer diferencias y créditos pendientes según política explícita.

**Prueba:** empresa C no necesita inventar una hoja de ruta para poder registrar un cobro remoto.

## Flujos que deben compartir verdad

- **Cliente/cuenta/obligación** compartidos según el módulo que los genera.
- **Cobro y conciliación** únicos: origen de campo, entrega, oficina o proveedor.
- **Custodia de valores** única y verificable para empleados que transportan efectivo.
- **Autorizaciones** siempre por tenant, módulo, membresía, capacidad y recurso concreto.
- **Offline** con identidad de operación, versionado, reintento y conflictos persistidos.

## Observaciones de diseño

- Evitar convertir `collection_routes` en ruta universal de logística por renombrar una tabla. Extraer capacidades comunes a un módulo de visitas de campo solo cuando exista la primera entrega real.
- El detalle de cuotas nunca debe surgir del tipo de rol. La obligación pertenece al cliente y se valida según cuenta/venta.
- La recepción de dinero y la entrega de mercadería tienen estados independientes, aunque sucedan en el mismo momento.
