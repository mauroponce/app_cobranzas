# Revisión visual del módulo comercial

El 11 de octubre de 2026 se inició sesión en Chrome con una cuenta local ficticia y se recorrieron las pantallas de clientes, productos y presupuestos del panel Laravel. También se revisó el constructor Livewire, un detalle de presupuesto y el listado responsive a 393 × 852. Las capturas se guardaron en `docs/design/screenshots/`:

- [Clientes en escritorio](screenshots/admin-customers-1440.png) y [detalle de cliente](screenshots/admin-customer-detail-1440.png).
- [Productos en escritorio](screenshots/admin-products-1440.png) y [detalle de producto](screenshots/admin-product-detail-1440.png).
- [Presupuestos en escritorio](screenshots/admin-quotations-1440.png), [detalle](screenshots/admin-quotation-detail-1440.png) y [constructor](screenshots/admin-quotation-builder-1440.png).
- [Presupuestos en móvil](screenshots/admin-quotations-393.png).

Se corrigió el espaciado de filtros, paneles y etiquetas tras la primera inspección. En móvil, los filtros se apilan y la tabla admite desplazamiento horizontal sin ampliar el ancho de la página. No se validó el flujo completo con un usuario real ni la impresión física del PDF; las pruebas automatizadas comprueban la generación binaria y la exclusión de notas internas.
