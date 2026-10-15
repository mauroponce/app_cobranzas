# Instrucciones para trabajar en App Cobranzas

## Arquitectura

Este monorepo contiene `apps/backend` (Laravel 13, Blade y Livewire), `apps/mobile` (Expo y React Native) y `packages/api-contract` (contrato de la API). El backend es el dueño de las reglas de negocio; el móvil solo presenta datos, almacena datos locales autorizados y coordina solicitudes.

El código, los identificadores y los comentarios de código se escriben en inglés. Toda la documentación, las instrucciones y el contenido visible para usuarios se escriben en español.

## Seguridad y datos

- No ejecutar `db:seed`, `migrate:fresh`, `migrate:refresh`, `migrate:reset`,
  `migrate:rollback`, `db:wipe`, `model:prune` ni eliminar volúmenes de datos
  salvo que el usuario lo pida explícitamente en la conversación actual. Una
  solicitud de implementar, probar o migrar no autoriza a borrar o regenerar
  datos. Ejecutar las pruebas backend mediante `make test`; nunca apuntarlas a
  una base que no termine en `_test`.
- Toda tabla de negocio perteneciente a una empresa debe tener `tenant_id`, relaciones y consultas acotadas al tenant activo. El servidor valida la pertenencia vigente antes de usarlo; nunca acepta un `tenant_id` arbitrario del cliente como autorización.
- Toda modificación de PostgreSQL se realiza mediante una migración versionada y una prueba relevante. No editar esquemas manualmente.
- Las operaciones monetarias futuras usarán transacciones y claves de idempotencia donde corresponda. No usar `float` ni `double` para importes; preferir unidades enteras o tipos decimales precisos según el contrato del módulo.
- No duplicar reglas de negocio en React Native. Diseñar los cambios offline pensando en reintentos, conflictos y trazabilidad antes de registrar cobros.
- Agregar pruebas de comportamiento y autorización para cambios relevantes. No incorporar agentes de IA al producto sin una necesidad definida.
- No guardar credenciales ni datos reales en Git. Configurar valores locales mediante archivos `.env` ignorados.

Consultá las instrucciones específicas de cada aplicación antes de modificarla.

## Administración de empresas

Consultá `docs/architecture/overview.md` y la matriz `apps/backend/app/Domain/Tenancy/RoleMatrix.php` antes de añadir un módulo. El rol global de plataforma vive fuera de membresías y no da acceso a datos de empresas. Para datos tenant-aware, acotá consultas y relaciones, aplicá Gates/Policies y probá cruces entre empresas. Los jobs reconstruyen su contexto al ejecutarse. Las invitaciones y los cambios de permisos deben auditarse sin guardar secretos.

## Módulo comercial

Consultá `docs/architecture/customers.md`, `catalog.md` y `quotations.md` antes de modificar clientes, precios o presupuestos. Los importes se calculan con `Domain/Commercial/Decimal.php` en centavos enteros; no uses `float` para cálculos ni visualización de dinero. Mantener snapshots históricos, claves compuestas `(tenant_id, id)`, períodos sin solapamiento y transiciones auditadas. La cuota estimada queda vacía hasta definir una política de financiación.

## Ventas, documentos y cobranzas

Consultá `docs/architecture/sales.md` antes de modificar solicitudes, evaluaciones, ventas, cuotas, cuenta corriente, mora o documentos. La venta se confirma únicamente desde una solicitud aprobada mediante `ConfirmSaleAction`; el anticipo y el contado son obligaciones pendientes hasta registrar un cobro. La confirmación bloquea la solicitud y crea obligaciones una sola vez. Los documentos privados usan el disco `r2`; los PDFs se generan después del commit mediante un job que reconstruye el tenant. No agregues tasas ni recargos por defecto.

Para cualquier modificación de cobranzas, consultá primero `docs/definicion_funcional.MD`: es la fuente de verdad funcional y prevalece sobre propuestas, prototipos y documentación anterior. Cuando una decisión funcional nueva quede confirmada, actualizá ese documento dentro del mismo cambio.
