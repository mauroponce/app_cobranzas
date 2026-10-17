# 08 — Matriz de aceptación transversal

**Matriz objetivo de plataforma completa, no estado actual.** Activar los casos B de reparto únicamente cuando se implemente la Entrega 5; en Entregas 1–3 verificar elegibilidad por capacidades y contratos compartidos sin simular que hay entregas productivas. Ejecutar en PostgreSQL de test y simulaciones móvil SQLite. El CI debe cubrir workflows unitarios e integrales; completar evidencias manuales en Android económico/offline antes de piloto.

| Escenario | Empresa A, cobranzas | Empresa B, reparto+cobro | Criterio de aceptación |
|---|---|---|---|
| Features separados | `deliveries` deshabilitado | `deliveries` habilitado | A no ve ni ejecuta rutas de entrega; B puede gestionarlas con permisos |
| Aislamiento | cobrador A | repartidor B | Prohibidos lectura, write y export cross-tenant incluso adivinando UUID |
| Roles combinados | vendedor/cobrador separados | persona entrega+cobra | Capability efectiva permite ambas acciones solo para B |
| Segregación de funciones | admin concilia | admin concilia | Quien rinde no aprueba su propia rendición |
| Venta múltiple | 2 planes activos | cuenta corriente/venta | Saldo por operación y por cliente no se confunden |
| Cobro offline | capturado visita A | capturado entrega B | Reintentos no crean pagos dobles ni pierden evidencia |
| Cambio de cartera | visita reasignada | entrega reasignada | El empleado viejo no puede afectar saldo; incidente queda investigable |
| Cobro remoto | transferencia manual/proveedor | transferencia desde entrega | Confirmación requiere evidencia válida, no screenshot |
| Efectivo declarado | pendiente rendición hoja | pendiente rendición custodio | No reduce deuda definitiva fuera del flujo autorizado |
| Deshabilitar módulo | cobranza apagada con deuda pendiente | reparto apagado con entregas existentes | Historial y resolución de pendientes accesibles con permiso; no más nuevas operaciones |
| Cambio de política | tasa/cuota histórica | prueba de entrega histórica | Versiones históricas no se reescriben |
| Importar planilla 2 veces | clientes y saldos | clientes, órdenes | Duplicados detectados; no hay cobros/saldos duplicados |
| Personalización | política A | política B | Una misma versión de código satisface ambos escenarios |
| Revocación dispositivo | celular cobrador | celular repartidor | No se confirma operación financiera revocada sin revisión |
| Autorización UI/API | permiso revocado | módulo revocado | Ni esconder botón ni usar API alternativa permite acceso |
| Concurrencia | misma declaración simultánea | mismo pago desde dos requests | Clave única/idempotencia y transacción, un solo resultado consistente |
| Corrupción de datos | referencia cruzada | referencia cruzada | FK compuestas o validación transaccional rechazan referencias a otro tenant |

## Puertas de salida por entrega

- **Tests:** PHP tests `make test` en `_test`, Pint, assets; mobile Jest, typecheck, lint, format. No falsos OK.
- **Seguridad:** pruebas de tenant, módulo, capability, scope y recurso en cada endpoint nuevo.
- **Datos:** plan de migración para estados viejos + índices/constraints; sin `migrate:fresh` ni datos prod expuestos.
- **Compatibilidad:** clientes móviles anteriores, rutas API y persistencia de operations offline.
- **Operación:** logs y audit trail permiten reconstruir el fallo sin acceso privilegiado a documentos sensibles.
- **Comercial:** segundo tenant entra sin tocar código core específico del primero.

Si una capacidad está deliberadamente fuera del alcance de una entrega, documentar que está **pendiente**; no marcarla hecha porque exista una tabla o mockup.
