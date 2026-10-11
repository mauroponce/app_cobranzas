# Presupuestos y seguimiento

## Flujo

`draft → pending_review → approved → sent → accepted` es la ruta principal. Desde revisión se puede rechazar o cancelar; desde enviado se puede rechazar o marcar vencido. Una propuesta aprobada o enviada no se edita libremente: se duplica como nuevo borrador. Cada transición guarda estado anterior, nuevo estado, actor, fecha y motivo opcional en `quotation_status_history`, además de un evento de auditoría. Un usuario no puede aprobar su propia propuesta, aun si posee `quotations.approve`.

Las fechas horarias del panel se presentan en la zona horaria configurada para la empresa. El vendedor ve solo sus presupuestos; `quotations.view_all` permite supervisión y administración dentro del mismo tenant. La lista, el detalle, el constructor Livewire, las transiciones, la duplicación, las actividades y el PDF validan permisos en servidor. El constructor vuelve a resolver membresía y tenant en cada actualización Livewire. `quotation_activities` conserva llamadas, uso manual de WhatsApp, interés, solicitudes de cambios, falta de respuesta y próximos seguimientos; no conoce conversaciones reales. Si el cliente tiene un teléfono de WhatsApp, el detalle ofrece un enlace externo que abre una conversación precompletada con el número de presupuesto; no envía mensajes ni registra comunicaciones fuera de las actividades cargadas por el usuario.

## Importes y condiciones

Los importes monetarios se convierten a centavos enteros y las cantidades a milésimos. Cada línea se calcula como `redondeo_mitad_arriba(cantidad_milésimos × precio_centavos / 1000)`; subtotal es la suma de líneas; total es subtotal porque el descuento autorizado inicial es cero. El anticipo debe estar entre cero y total, y `financed_amount = total − down_payment_amount`. En contado, el anticipo representa el total y el saldo a financiar es cero. Los valores calculados y precios nunca se aceptan del navegador. PostgreSQL agrega restricciones de importes no negativos y coherencia de saldo.

La modalidad `financed` permite registrar cantidad y frecuencia de cuotas como propuesta. `estimated_installment_amount` permanece nulo: todavía no existe una política de tasa, interés o recargo. La interfaz y el PDF lo indican como pendiente de confirmación. No se crean ventas ni cuotas reales.

`PRE-000001` proviene de `tenant_counters` dentro de una transacción con bloqueo; hay una prueba de cuatro procesos concurrentes. `customer_snapshot`, `address_snapshot` y `quotation_items` congelan datos documentales. El PDF de propuestas aprobadas o emitidas se genera bajo demanda con Dompdf y acceso autorizado; no se guarda en una ruta pública. No incluye costos ni notas internas. La vista de impresión usa la misma plantilla. El PDF se sirve con recursos locales y la carga remota de Dompdf desactivada.

## API y próximos pasos

`GET /api/v1/quotations` y `GET /api/v1/quotations/{id}` son de solo lectura, con tenant ligado al token Sanctum. La conversión a venta y la política financiera corresponden a la etapa 4. Antes de automatizar WhatsApp o crédito deberán definirse reglas verificables con Trotamundos.

No se encontró `PROCEDIMIENTO PARA VENTA.pdf` en el repositorio. El flujo aquí descrito implementa la solicitud de esta etapa; no afirma reproducir reglas adicionales de ese procedimiento.
