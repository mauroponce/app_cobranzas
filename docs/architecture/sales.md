# Solicitudes, ventas y financiación

## Flujo

Un presupuesto en estado `accepted` puede originar una sola solicitud. También se puede crear una solicitud directa con `sales.create_direct`; los precios cargados en ese caso son condiciones comerciales explícitas y los ítems quedan congelados. Cada solicitud tiene ítems, cliente, vendedor, modalidad y condiciones propuestas. La secuencia implementada es `draft → pending_review → approved → converted`; desde revisión puede terminar en `rejected` o volver a `pending_documents`. Los cambios de estado y evaluaciones se conservan como registros históricos. La evaluación la firma un usuario distinto del vendedor. Las tasas no se infieren: el revisor aprueba un importe de recargo explícito, inicialmente cero.

`ConfirmSaleAction` bloquea la solicitud en PostgreSQL, comprueba evaluación vigente, ítems, primera fecha de vencimiento y documento de identidad verificado para financiación. Una venta no puede confirmarse dos veces: se combina el bloqueo con una clave única `(tenant_id, sale_application_id)`. La transacción crea venta, snapshots de ítems, plan, cuotas, obligaciones e historial. El PDF se despacha después del commit. La anulación exige una razón y saldos íntegros sin cobros; cancela las obligaciones y cuotas pendientes. No se eliminan los registros históricos.

## Importes y cuenta corriente

`Decimal` convierte importes a centavos enteros. En una venta financiada, `principal = total de productos − anticipo pactado` y `total financiado = principal + recargo explícito`. El anticipo es una obligación pendiente, con vencimiento en la fecha de confirmación; **no** se toma como cobro. En una venta al contado se crea una sola obligación por el total, también pendiente. Cada cuota de una venta financiada crea una obligación; la cuenta corriente suma estas obligaciones una sola vez. Pueden coexistir varias ventas activas de un cliente. El saldo contractual suma obligaciones abiertas pendientes y el saldo exigible suma las ya vencidas.

Los cronogramas admiten frecuencia diaria, semanal, quincenal (cada 14 días) y mensual. El primer vencimiento es explícito. Para meses, se parte siempre de la primera fecha y se ajusta el día al fin del mes correspondiente; así se evitan derivas tras febrero y se contemplan años bisiestos. Se divide en centavos enteros y el resto se coloca en la última cuota. La suma de cuotas es exactamente el total financiado. No se permiten cuotas de cero centavos.

Las políticas de mora se versionan por empresa y rango de vigencia sin solapamiento. Días de gracia, puntos básicos diarios y tope se cargan explícitamente. Si no existe política para el vencimiento original, la mora es cero. La mora mostrada es un cálculo de consulta sobre el saldo pendiente; no crea otra obligación ni se capitaliza. No se registran cobros en esta etapa.

## Integridad y acceso

Las migraciones `160000`, `170000`, `180000`, `190000` y `200000` amplían la migración inicial de etapa 4 sin reescribirla. Claves compuestas impiden que solicitud, venta, plan, cuota, documento u obligación apunten a un cliente o empresa distintos. Índices únicos impiden duplicar obligaciones por cuota o tipo de obligación de venta. Las consultas usan `forTenant` y las rutas exigen una membresía activa. `sales.view_all` permite supervisar todas las ventas de una empresa; sin ella el vendedor ve solo sus ventas. Los documentos de identidad requieren permisos documentales específicos.

El disco privado `r2` usa S3 con `R2_ACCESS_KEY_ID`, `R2_SECRET_ACCESS_KEY`, `R2_BUCKET` y `R2_ENDPOINT`. Los objetos viven bajo `tenants/{tenant_id}/documents/`. La descarga pasa por un controlador autorizado. Resumen, cronograma y contrato básico se generan con Dompdf en `GenerateSaleDocuments` después del commit y se guardan en R2. El job comprueba empresa y membresía activas, y evita generar dos veces cada tipo documental. Los archivos no se alojan en rutas públicas.

## Superficies

El panel ofrece `/admin/sale-applications`, `/admin/sales`, `/admin/customers/{customer}/account`, `/admin/customers/{customer}/documents` y `/admin/late-fee-policies`. La API Sanctum ofrece consultas de solicitudes, ventas, cuenta corriente y políticas bajo `/api/v1`, con tenant ligado al token. La gestión y confirmación permanecen en el panel. Las pruebas de etapa 4 usan PostgreSQL y `Storage::fake('r2')`.
