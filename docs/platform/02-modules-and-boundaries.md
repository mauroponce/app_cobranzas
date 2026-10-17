# 02 — Arquitectura modular y límites de dominio

## Estilo de arquitectura

Un **monolito modular Laravel** con una API versionada y una app Expo que presenta capacidades habilitadas. Un repositorio, un despliegue lógico compartido, base PostgreSQL única al inicio. No microservicios ni motor universal de procesos.

Cada módulo tiene una responsabilidad de negocio, su propia lógica de dominio, sus validaciones y pruebas. Los módulos colaboran mediante **servicios de aplicación/contratos explícitos** y eventos de dominio después del commit cuando haya una necesidad concreta. Evitar acoplamientos por modelos internos o listeners que modifican saldos sin transacciones.

## Mapa recomendado

| Módulo | Responsabilidad | Estado de partida aproximado |
|---|---|---|
| Identity & Tenancy (núcleo) | usuarios, membresías, módulos, permisos, auditoría | existe |
| Parties / Customers | clientes, domicilios, identidad comercial | existe |
| Catalog & Pricing | productos, listas, vigencias | existe |
| Sales & Quotations | presupuestos, aprobación de venta, condiciones | existe |
| Financing & Accounts | planes/cuotas, obligaciones, mora, saldo contractual | existe |
| Field Collections | cartera, agenda, visitas, declaración de cobro | parcial, en estabilización |
| Payments & Reconciliation | verificación, imputaciones, reversas, incidencias | parcial, en estabilización |
| Cash Custody & Settlements | responsabilidad por efectivo y entrega a Administración | requiere consolidar |
| Fulfillment / Delivery | preparación, asignación, entrega, evidencia, devoluciones | futuro; NO implementar sin piloto |
| Integrations | Mercado Pago/transferencias/otros proveedores, notificaciones | adaptadores futuros |
| Implementation / Imports | staging de Excel, validación, mapeo y confirmación | priorizable tras estabilización |

## Diferenciar acción, rol y origen

Una persona puede ser `seller`, `collector`, `delivery_agent` o **ambas** cobrador y repartidor. No equiparar persona con un único valor `tenant_memberships.role`. El rol de empresa es un **preset de capacidades**; las autorizaciones efectivas dependen de empresa, módulos, membresía, delegación/alcance y estado.

**Migración sugerida mínima:** conservar el `role` actual para compatibilidad (y jerarquía administrativa); incorporar responsabilidades/paquetes de capacidades múltiples cuando esté demostrado el caso y retirar `where('role','collector')` como criterio de elegibilidad operativa. Elegibilidad = membresía activa + autorización efectiva + asignación válida. Un `delivery_agent` puede tener `deliveries.execute`, `payments.declare` y `cash_custody.hold` sin recibir `payments.verify` ni `settlements.approve`.

## Entidades clave y límites

- `customer`: a quién se vende/cobra, **no** es un rol de empleado.
- `sale`/`order`: operación comercial; la venta financiada y el pedido de entrega no son sinónimos.
- `account_entry`: obligación de pago.
- `field_visit`: tarea de cobrar en sitio; un pedido entregable genera `delivery` si el módulo está activo. **No forzar** visitas y entregas al mismo agregado si sus invariantes difieren.
- `collection_declaration`: afirmación de un empleado de que recibió un valor, con origen (`collection_visit`, `delivery`, `office` cuando proceda) y evidencia.
- `payment`: dinero verificado/aceptado y sus imputaciones, de acuerdo con la regla financiera única.
- `custody_transfer` / rendición: entrega de valores de una persona a otra; no implica por sí sola venta, visita ni cancelación de deuda.
- `settlement`: conciliación de valores bajo custodia; los detalles de cada empleado y jornada no se mezclan con otros tenants.

## Caso específico: repartidor que cobra

La entrega (`delivery`) confirma estado logístico; si además recibe efectivo, se crea una **declaración de cobro vinculada a esa entrega**, que entra al **mismo dominio de recepción, custodia, conciliación e imputación** utilizado por cobradores. No duplicar `PaymentService` o contabilidad para repartidores. Cobrar y entregar son acciones separadas, autorizadas independientemente y auditadas.

## Regla de dependencia y extensibilidad

La entrega no debe necesitar conocer cuotas para declararse cumplida; la cobranza no debe asumir que existe una entrega. La vinculación ocurre por contratos explícitos sobre obligación/cliente/origen. Los adaptadores externos no escriben cuentas corrientes directamente; registran evidencia verificable que pasa por el servicio financiero autorizado.

## Contratos iniciales (candidatos, no APIs existentes)

- `FieldOperationReference`: `tenant_id`, `source_type` tipado, `source_id` y actor; IDs extranjeros validados por tenant y entidad real.
- `CollectMoney`: `DeclareCollection(command)`, `VerifyElectronicReceipt(command)`, `ReceiveCashCustody(command)`, `AllocateConfirmedPayment(command)`; los límites concretos se validan con la estabilización en curso.
- `TenantCapabilities`: `effectiveModules(tenant)`, `allows(membership, permission, resource)`, `config(tenant, key, context)`; no confiar en una bandera enviada por el cliente.

No introducir interfaces, tablas o buses porque estén aquí escritos: el `$dev-plan` elegirá la refactorización **mínima** necesaria para el escenario que se implemente.
