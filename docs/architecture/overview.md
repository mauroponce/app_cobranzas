# Arquitectura de App Cobranzas

App Cobranzas es un monorepo con Laravel 13 (`apps/backend`), panel Blade/Livewire/Tailwind/DaisyUI, aplicación Expo/React Native (`apps/mobile`), PostgreSQL, Redis y Mailpit local. El backend decide la autorización. La API REST vive en `/api/v1` y utiliza Sanctum.

## Modelo multiempresa

Una base PostgreSQL compartida contiene `users` (identidad global), `tenants`, `tenant_memberships` (rol y estado por empresa), `tenant_invitations`, `platform_administrators` y `audit_events`. Las claves principales son UUID. La pertenencia `(tenant_id, user_id)` es única. Cada empresa activa se crea con un propietario activo y las acciones de membresía protegen al último propietario.

En el panel, el middleware resuelve membresías activas y empresas activas en cada solicitud. Una única pertenencia se selecciona automáticamente; varias requieren seleccionar empresa. El UUID elegido se guarda en sesión, pero se valida otra vez en cada petición. Una pérdida de acceso invalida la selección. Las rutas de usuario e invitación verifican además que el registro pertenezca a la empresa resuelta. La plataforma tiene rutas `/platform` y autorización independiente; un operador de plataforma no hereda permisos operativos de empresas.

En la API, `POST /api/v1/tenant/select` comprueba la membresía y guarda el UUID autorizado en el token Sanctum. `GET /permissions` y `/profile` lo vuelven a validar, incluyendo estado global del usuario y estado de la empresa. No se utiliza `X-Tenant-ID` como autorización.

## Capacidades

La fuente de verdad ejecutable es `apps/backend/app/Domain/Tenancy/RoleMatrix.php`. Los nombres estables son:

`tenant.view`, `tenant.update`, `users.view`, `users.create`, `users.update`, `users.disable`, `users.assign_roles`, `customers.view`, `customers.create`, `customers.update`, `sales.view`, `sales.create`, `sales.approve`, `collections.view`, `collections.register`, `collections.reassign`, `routes.view`, `routes.manage`, `routes.publish`, `settlements.view`, `settlements.submit`, `settlements.approve`, `reports.view`, `integrations.manage`.

| Rol | Capacidades iniciales |
| --- | --- |
| `owner` | Todas las capacidades de empresa; puede transferir propiedad explícitamente |
| `admin` | Todas las capacidades de empresa; solo puede administrar roles de su nivel o inferiores y no puede tocar propietarios |
| `supervisor` | Ver empresa, usuarios, clientes, ventas, cobros, rutas, rendiciones e informes; actualizar clientes, reasignar cobros, gestionar/publicar rutas y aprobar rendiciones |
| `seller` | Ver empresa, clientes, ventas y rutas; crear/actualizar clientes y crear ventas |
| `collector` | Ver empresa, clientes, cobros, rutas y rendiciones; registrar cobros y presentar rendiciones |
| `logistics` | Ver empresa, clientes y rutas |
| `viewer` | Consultar empresa, clientes, ventas, cobros, rutas, rendiciones e informes |
| `platform_admin` | Solo administración de empresas; no es un rol de membresía |

Los Gates evalúan la capacidad contra la membresía activa del request. Las rutas y servicios autorizan en servidor; ocultar botones solo mejora la interfaz. Clientes, catálogo y presupuestos implementan las capacidades de la etapa 3; las ventas, cuotas y cobranzas siguen pendientes.

## Invitaciones, auditoría y plataforma

El servicio de invitaciones genera 32 bytes aleatorios, envía el enlace por correo y guarda únicamente SHA-256 del token. Existe como máximo una invitación abierta por correo y empresa mediante índice parcial PostgreSQL. Crear otra revoca la anterior. La aceptación valida vencimiento, revocación, estado de empresa e identidad del correo. Una cuenta existente debe iniciar sesión con el correo invitado; una cuenta nueva se crea desde el enlace recibido. La aceptación usa transacción y bloqueo de fila para impedir doble uso. El job de correo lleva `tenant_id` e `invitation_id` y comprueba de nuevo que la empresa y la invitación sigan activas antes del envío. Mailpit captura el correo en desarrollo.

Los cambios de empresa, rol, estado, propiedad e invitaciones generan `audit_events`, sin contraseñas ni tokens. El seeder registra la concesión inicial de operador de plataforma. La creación de empresas en `/platform` requiere una cuenta global activa existente como primer propietario; la suspensión bloquea el contexto operativo sin borrar información.

## Nuevos modelos empresariales y jobs

Una tabla futura de negocio puede usar `app/Models/Concerns/BelongsToTenant.php` para la relación y el scope explícito `forTenant`; este trait no instala un global scope. Debe llevar `tenant_id` UUID no nulo con clave foránea, índices que comiencen por `tenant_id`, consultas acotadas por el tenant resuelto y Policy o Gate para cada operación. Cuando una relación pueda apuntar a otra empresa, agregar clave foránea compuesta `(tenant_id, id)` o validación explícita transaccional. No confiar solo en un global scope de Eloquent. Los jobs deben recibir el UUID de empresa, reconstruir y validar su contexto al ejecutarse y fallar de forma segura si la empresa quedó suspendida. Nunca reutilizar una sesión HTTP en un job.

PostgreSQL Row Level Security es una capa futura de defensa en profundidad. Antes de activarla se debe definir cómo se establece y limpia `app.tenant_id` por transacción en conexiones HTTP, colas y comandos, cómo accede plataforma a metadatos globales y cómo funcionan migraciones y tareas administrativas. No se activan políticas parciales.

Para agregar un módulo, declarar capacidades estables en `RoleMatrix`, asignarlas por rol, definir Gates/Policies y probar accesos autorizados, denegados y cruzados entre empresas.

## Verificación

`make migrate`, `make seed` y `make test` ejecutan migraciones, datos locales y pruebas PostgreSQL. `docker compose exec app ./vendor/bin/pint --test` comprueba formato. En desarrollo, el panel está en `http://localhost:8080` y Mailpit en `http://localhost:8025`.

## Etapa 3: módulo comercial

Los modelos comerciales y sus relaciones se describen en [clientes](customers.md), [catálogo](catalog.md) y [presupuestos](quotations.md). `customers`, `customer_addresses`, `product_categories`, `products`, `price_lists`, `product_prices`, `product_costs`, `quotations`, `quotation_items`, `quotation_status_history` y `quotation_activities` tienen `tenant_id`. Las relaciones entre ellas usan claves compuestas. `tenant_counters` serializa números por empresa y tipo. Los servicios de dominio calculan importes y estados en transacciones; las Policies y Gates delimitan recursos y capacidades.

Permisos agregados: `customers.disable`; `products.view`, `products.create`, `products.update`, `products.manage_prices`, `products.view_costs`, `products.manage_costs`; `quotations.view`, `quotations.view_all`, `quotations.create`, `quotations.update`, `quotations.submit`, `quotations.approve`, `quotations.reject`, `quotations.send`, `quotations.cancel`, `quotations.export` y `quotations.manage_activities`. `owner` y `admin` reciben todos; `supervisor` puede ver y aprobar; `seller` consulta catálogo público y gestiona clientes y propuestas propias; `viewer` solo consulta recursos autorizados. Los costos quedan reservados a administración. La matriz ejecutable sigue en `RoleMatrix`.
