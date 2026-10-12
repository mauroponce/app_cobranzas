# Backend Laravel

Usá la estructura y convenciones nativas de Laravel. Los controladores deben coordinar solicitudes y validación; las reglas de negocio futuras pertenecen a servicios o acciones explícitas del dominio. Evitá introducir arquitectura hexagonal o paquetes de tenancy por defecto.

El dashboard administrativo se implementa solo con Blade y Livewire. Tailwind y DaisyUI proporcionan los componentes visuales. No agregar React, Vue, Inertia ni una SPA.

`users` es identidad global y `tenant_memberships` define acceso por empresa. Verificá pertenencia y rol en cada solicitud protegida, también en API, jobs y consultas que se agreguen en el futuro. El contexto de sesión no sustituye la autorización. Usá transacciones para operaciones monetarias y tipos precisos para importes.

Ejecutá PHP y Composer mediante Docker; no se requiere instalarlos en el host. Pruebas de integración con PostgreSQL: `make test`. Después de cambios relevantes, ejecutar pruebas y Pint. La documentación y los textos visibles van en español; código e identificadores, en inglés.

## Etapa 2: accesos

La matriz ejecutable está en `app/Domain/Tenancy/RoleMatrix.php`; agregá allí cada capacidad nueva y probá el Gate con roles distintos. Las rutas del panel usan `tenant.admin` para reconstruir empresa y membresía en cada solicitud. La API guarda el contexto validado en el token Sanctum. Un job debe recibir `tenant_id` explícito y volver a validar estado y pertenencia. Para un modelo nuevo usá `app/Models/Concerns/BelongsToTenant.php` y aplicá `forTenant` en las consultas; el trait no reemplaza la autorización. Una relación entre dos tablas empresariales requiere el mismo `tenant_id` en ambos extremos, comprobado en consulta/Policy y, si corresponde, clave compuesta. No concedas al operador de `/platform` acceso implícito a datos operativos.

## Etapa 3: módulo comercial

Los modelos de `customers`, `products` y `quotations` están documentados en `docs/architecture/`. Usá `CustomerService`, `PriceService`, `QuotationService`, `Decimal` y `CommercialNumber` para sus reglas. Las Policies protegen recursos y la matriz `RoleMatrix` protege capacidades. Los controladores y componentes Livewire vuelven a resolver el tenant; la API usa `tenant.api` con el contexto ligado a Sanctum. Un precio nuevo requiere vigencia sin solapamiento; un presupuesto aprobado se duplica para revisarlo. No agregues cálculo de cuota sin política aprobada. Ejecutá la suite sobre PostgreSQL.

## Etapa 4: ventas

El flujo está documentado en `docs/architecture/sales.md`. Usá `SaleApplicationService`, `ConfirmSaleAction`, `CancelSaleAction`, `InstallmentSchedule`, `AccountStatementService` y `DocumentStorageService`; no escribas ventas o cuotas directamente desde controladores. Los importes de cuotas y mora usan centavos enteros y puntos básicos enteros. `sales.view_all` controla visibilidad global dentro de la empresa; `sales.approve`, `sales.confirm`, `sales.cancel`, `sales.documents.verify` y `sales.policies.manage` son capacidades separadas. Los documentos se descargan a través de rutas autorizadas; nunca exponer una URL pública permanente de R2. Para pruebas de almacenamiento, `Storage::fake('r2')`.
