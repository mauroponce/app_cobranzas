# Evaluación de modularidad, capacidades y permisos

## Alcance y fuentes

Esta evaluación caracteriza la implementación de `main` en `7bf0209` para preparar M-02. No modifica reglas de autorización, cobranzas ni lógica financiera. La arquitectura objetivo y los escenarios se mantienen en [módulos y límites](../platform/02-modules-and-boundaries.md), [configuración por tenant](../platform/03-tenant-configuration.md), [escenarios](../platform/04-workflow-scenarios.md), [integridad](../platform/05-data-integrity.md) y [plan de entregas](../platform/07-delivery-roadmap.md); este documento registra solamente el contraste con el código actual.

La fuente ejecutable del preset de roles es `RoleMatrix`. El catálogo persistido se materializa con `AuthorizationCatalogSeeder` en `features`, `tenant_features`, `permissions` y `role_permissions`. Los overrides individuales viven en `membership_permission_overrides`.

## Resolución actual de una autorización

El camino efectivo es:

1. `ResolveAdminTenant` o `ResolveApiTenant` vuelve a resolver usuario, tenant y membresía activos. La sesión web o el `tenant_id` guardado en el token Sanctum seleccionan contexto, pero no sustituyen la validación.
2. Los Gates registrados en `AppServiceProvider` delegan la capability a `TenantAuthorization` y `AuthorizationService`.
3. `AuthorizationService` rechaza membresía o tenant inactivos, busca la capability en `permissions`, exige feature global activo y `tenant_features.is_available = true` e `is_enabled = true`.
4. Un override individual decide antes que el preset. `deny` prevalece; `allow` conserva un scope incluido en `permissions.allowed_scopes`.
5. Sin override, se consulta `role_permissions` para el rol primario de la membresía.
6. El controlador, Policy o servicio debe aplicar el scope al recurso y luego las reglas de negocio. Esta última etapa no está unificada y presenta diferencias entre endpoints.

Si una capability no existe en `permissions`, `AuthorizationService` cae a `RoleMatrix` con scope `all`. Ese fallback mantiene compatibilidad ante un catálogo incompleto, pero también evita el control de módulo y oculta desalineaciones entre código y datos.

### Cinco dimensiones separadas

| Dimensión | Representación actual | Estado |
| --- | --- | --- |
| Disponibilidad comercial | `features.is_active` y `tenant_features.is_available` | Implementada; no se encontró un servicio de plataforma específico para cambiar disponibilidad. |
| Habilitación operativa | `tenant_features.is_enabled`, administrada por `AuthorizationManagementService::setFeature` | Implementada y auditada. Solo puede encenderse una feature disponible. |
| Capacidad del usuario | `role_permissions` más `membership_permission_overrides` | Implementada. El deny individual prevalece y la delegación no puede superar el scope del actor. |
| Alcance sobre recursos | `own`, `assigned`, `team`, `all`; aplicado por consultas, Policies y servicios | Parcial e inconsistente. No todos los endpoints interpretan todos los scopes admitidos. |
| Regla de negocio del tenant | Servicios de dominio y algunas políticas específicas, sin settings genéricos | Deliberadamente limitada. M-01 no propone un motor universal; las reglas tipadas corresponden a M-03 cuando exista evidencia real. |

## Inventario de módulos implementados

| Feature persistida | Capacidades asociadas | Dependencias o límite actual |
| --- | --- | --- |
| `administration` | `tenant.*`, `users.*`, `authorization.*` | Incluye gobierno de empresa, membresías, roles, módulos y overrides. |
| `customers` | consulta, alta, actualización y baja lógica de clientes | `customers.view` admite varios scopes, pero no todos los caminos los aplican. |
| `catalog` | consulta y mantenimiento de productos, precios y costos | Las listas no públicas aún contienen checks directos de `owner`/`admin`. |
| `quotations` | consulta, creación, edición, transiciones, exportación y actividades | Visibilidad propia/general separada mediante `quotations.view_all`. |
| `sales` | solicitudes, aprobación, confirmación, cancelación y políticas | Documentos de venta se asignan aquí por prefijo. Mantiene segregación por actor. |
| `documents` | Ninguna capability asignada por el seeder actual | Feature huérfana; los permisos `sales.documents.*` pertenecen a `sales`. Revisar antes de exponerla como módulo independiente. |
| `collections` | `collections.*` y `payments.*` | Comparte declaraciones, pagos y verificación. Depende de obligaciones válidas y del circuito financiero vigente. |
| `collections_assignments` | asignar, desasignar y ver carteras | La elegibilidad por capacidad existe en `CollectionPortfolioService`, pero hojas y selectores conservan checks de rol. |
| `collections_teams` | consulta y mantenimiento de equipos | `team_role` es una asignación operativa local, no el rol primario de la membresía. |
| `collections_zones` | consulta y mantenimiento de zonas | Aporta alcance operativo; no concede por sí sola capacidades. |
| `routes` | consulta, programación y publicación de hojas | La hoja actual exige membresía con rol primario `collector`. No es un módulo de reparto. |
| `cash_settlements` | consulta, creación, presentación, revisión y aprobación | Debe conservar custodia, conciliación y prohibición de autoaprobación. |
| `reports` | `reports.view` | Placeholder de autorización; no demuestra un dominio de reportes completo. |
| `integrations` | `integrations.manage` | Catálogo preventivo; no equivale a integraciones operativas implementadas. |

No existe `delivery` ni reparto en el catálogo actual. Agregarlo antes de un caso validado correspondería a L-01, no a M-02.

## Matriz de permisos actual

| Rol primario | Preset efectivo |
| --- | --- |
| `owner` | Todas las capabilities. Es además el rol de propiedad protegido por transferencia explícita y regla de último propietario. |
| `admin` | Todas las capabilities; no puede asignar ni administrar propietarios. |
| `supervisor` | Consulta de empresa, usuarios, clientes, catálogo, ventas, cobranzas, rutas, rendiciones e informes; aprobación y operación administrativa. En cartera/pagos usa scope `team`, pero otros permisos con varios scopes reciben actualmente `all`. |
| `seller` | Clientes, catálogo público, presupuestos propios, creación de ventas y documentos propios. No incluye aprobación ni cobranza. |
| `collector` | Clientes, cartera, cobros, rutas y rendiciones. `customers.view`, `payments.view`, `payments.create` y `collections.portfolio.view` usan `assigned`; `settlements.view/create/submit` reciben actualmente `all` por el default del seeder. |
| `logistics` | `tenant.view`, `customers.view`, `routes.view`. No existe dominio ni capacidad de entrega. |
| `viewer` | Consulta general de empresa, clientes, catálogo, presupuestos, ventas, cobranzas, rutas, rendiciones e informes. |

Los permisos exactos permanecen en `RoleMatrix::CAPABILITIES` y `RoleMatrix::PERMISSIONS`. Duplicar aquí sus más de cincuenta claves haría que el documento quedara obsoleto; la tabla registra su semántica y las excepciones de scope que influyen sobre M-02.

### Scopes declarados

- `own`, `assigned`, `team`, `all`: `customers.view`, `payments.view`, `payments.create`, `settlements.view`, `settlements.create`, `settlements.submit`, `collections.portfolio.view` y `sales.view`.
- `team`, `all`: capacidades de equipos.
- `all`: las demás.

`CollectionPortfolioService` aplica correctamente `assigned` y `team` para cartera y cobro. En cambio, `CommercialApiController` solo trata `own` y deja pasar `assigned`/`team` como consulta completa; `CustomerController` y `CustomerPolicy` tampoco aplican esos scopes. `CashSettlementApiController` solo restringe `own`, por lo que `assigned` o `team` no limitan el listado. La declaración de un scope no constituye por sí sola autorización de recurso.

## Mapa de dependencias

```text
usuario global activo
  └─ membresía activa + tenant activo
       ├─ rol primario ──> role_permissions ─┐
       ├─ override individual ───────────────┼─> capability + scope
       └─ feature global/tenant disponible ─┘
                                                └─ Policy/consulta/recurso
                                                     └─ regla de negocio y estado
```

- El rol global de plataforma se resuelve con `platform_administrators` y `platform.access`; no crea membresías ni acceso operativo.
- Web usa una selección de tenant en sesión y la revalida en cada request.
- API guarda el tenant seleccionado en el token Sanctum y lo revalida en `tenant.api`.
- `/api/v1/me/capabilities` publica módulos, permisos efectivos con scope y versiones para el móvil.
- El móvil decide navegación por capabilities, no por nombre de rol. Conserva el rol solo para mostrarlo.
- El servidor recalcula autorización en cada request. El móvil conserva capabilities y datos SQLite hasta refrescar o volver a iniciar sesión; hoy no reacciona automáticamente a `authorization_version`.

## Acoplamientos con roles rígidos

| Código | Clasificación | Efecto |
| --- | --- | --- |
| `CollectionRouteService::program` y `reassignVisit` filtran `role = collector` | Debe usar capacidad más asignación operativa | Una persona con `payments.create` concedido no puede recibir hoja si su rol primario es `seller` o un futuro perfil combinado. |
| `CollectionsDashboard` y `/modules/collectors` listan solo `role = collector` | Debe usar elegibilidad operativa | La UI no ofrece personas habilitadas por capability aunque `CollectionPortfolioService::eligibleCollector` sí las admita. |
| `PaymentService` impide el alta administrativa directa solo cuando `role === collector` | Restricción legítima de custodia implementada con discriminador incorrecto | Al combinar responsabilidades, una persona que cobra con otro rol primario podría entrar al flujo administrativo que confirma dinero sin declaración/rendición de campo. Debe distinguir origen, capability y asignación, no título. |
| `QuotationService` reserva listas no públicas a `owner`/`admin` | Debe usar capability | Duplica una regla de autorización junto a `products.manage_prices` y dificulta delegar una responsabilidad concreta. |
| `MembershipService`, `InvitationService` y vistas de usuarios comprueban `owner`/`admin` | Restricción legítima del dominio de gobierno | Protege propiedad y jerarquía de membresías. No debe convertirse automáticamente en una capability operativa combinable. |
| `RoleMatrix`, `role_permissions` y validación de `tenant_memberships.role` | Preset/compatibilidad legítimos | El problema no es que exista un rol primario, sino usarlo como elegibilidad de cada operación. |
| `collection_team_members.team_role` | Asignación operativa legítima | Representa supervisor o integrante dentro de un equipo y no debe confundirse con el rol general. |
| Respuestas API y perfil móvil exponen `role` | Metadato compatible | No decide navegación móvil; `/permissions` es la excepción heredada que reconstruye capacidades desde el rol. |

### Caso repartidor y cobrador

Con el diseño actual, un futuro repartidor con rol primario `logistics` podría recibir un override `payments.create` y resultar elegible para una cartera, pero no aparecería en selectores ni podría recibir una hoja. Si se cambiara su rol a `collector`, perdería el preset logístico futuro. Además, el check de `PaymentService` variaría el circuito financiero según cuál de ambos títulos quede como rol primario. Por eso sumar overrides aislados sin corregir elegibilidad y origen puede bloquear la operación o abrir un camino de confirmación indebido.

## Capacidades reutilizables

Los conjuntos de M-02 deberían componerse a partir de capabilities existentes, sin convertirlos en nuevos roles jerárquicos:

| Responsabilidad candidata | Capabilities existentes reutilizables | Límites obligatorios |
| --- | --- | --- |
| Venta operativa | `customers.create/update`, `quotations.create/update/submit/send`, `sales.create` | No incluir aprobación, confirmación ni acceso general a ventas ajenas. |
| Cobranza de campo | `customers.view`, `collections.register`, `collections.portfolio.view`, `payments.view/create`, `routes.view`, `settlements.view/create/submit` | Scopes `assigned` o `own` según recurso; declaración y rendición, nunca verificación o autoaprobación. |
| Supervisión de cobranza | scopes `team` de clientes, cartera y pagos; `collections.reassign`, `routes.manage/publish`, revisión de rendiciones | El equipo activo define recursos; no concede propiedad de empresa. |
| Administración financiera | `payments.verify*`, `payments.allocate/reverse`, `settlements.review/approve` | Segregación por actor y estado; no derivar de una responsabilidad de campo. |
| Logística actual | `customers.view`, `routes.view` | No representa entrega. Las futuras capabilities de delivery pertenecen a L-01 y deben ser independientes de cobro. |
| Gobierno de empresa | `tenant.*`, `users.*`, `authorization.*` | Permanece ligado al rol primario y a reglas de propiedad; no es un conjunto operativo combinable. |

Estos agrupamientos son diseño para M-02, no funcionalidades disponibles. Los nombres persistidos y su granularidad final deben definirse en el plan de implementación con una segunda empresa real.

## Limitaciones actuales

- La capability y su scope no garantizan el mismo conjunto de recursos en todos los endpoints.
- Las hojas y varios selectores siguen exigiendo el título `collector`.
- El flujo de pago administrativo distingue roles en vez de responsabilidad y origen.
- El catálogo existe tanto en PHP como en PostgreSQL sin reconciliación cerrada.
- El versionado no cubre todos los cambios de membresía y el móvil no invalida automáticamente toda la información local autorizada.
- El contrato `/permissions` conserva semántica de preset, no de permiso efectivo.
- No existen responsabilidades múltiples, delivery ni políticas empresariales configurables generales.

## Hallazgos priorizados

### AUDIT-001 — Scopes declarados no se aplican de forma uniforme

- **Severidad:** HIGH. **Confianza:** CONFIRMED. **Esfuerzo:** MEDIUM.
- **Evidencia:** `CommercialApiController` solo filtra `customers.view = own`; `CustomerController` y `CustomerPolicy` no aplican `assigned`/`team`; `CashSettlementApiController` solo limita `own`.
- **Impacto:** una membresía con scope acotado puede consultar recursos del mismo tenant fuera de su cartera o equipo por un endpoint alternativo. El aislamiento entre empresas permanece, pero falla el mínimo privilegio interno.
- **Dirección:** definir scopers reutilizables por recurso/capability y hacer que web, API, Policies y sync usen la misma decisión. Agregar primero pruebas negativas por endpoint.
- **Producción:** corregir de forma compatible con Trotamundo; puede reducir resultados antes visibles, por lo que requiere comunicar y observar rechazos legítimos.

### AUDIT-002 — La elegibilidad de hojas continúa atada a `collector`

- **Severidad:** HIGH. **Confianza:** CONFIRMED. **Esfuerzo:** MEDIUM.
- **Evidencia:** filtros directos en `CollectionRouteService`, `CollectionsDashboard` y `routes/web.php`; la suite de caracterización demuestra que una membresía habilitada por capability sigue sin poder recibir hoja.
- **Impacto:** impide vendedor+cobrador y repartidor+cobrador sin reemplazar el rol primario; multiplicaría cambios manuales o ramas por empresa.
- **Dirección:** una consulta/servicio de elegibilidad única: membresía activa, módulo disponible/habilitado, capability efectiva y asignación válida.

### AUDIT-003 — El flujo financiero directo cambia según el título de rol

- **Severidad:** HIGH. **Confianza:** HIGH CONFIDENCE. **Esfuerzo:** MEDIUM.
- **Evidencia:** `PaymentService::declareTransfer` y `register` bloquean el dashboard solo para `role === collector`.
- **Impacto:** una responsabilidad de cobro concedida a `seller` o `logistics` podría evitar el circuito de declaración, custodia y rendición reservado a campo.
- **Dirección:** antes de combinar responsabilidades, separar capabilities/comandos por origen operativo y exigir asignación de campo cuando corresponda. Conservar todas las invariantes de `PaymentService` y `CashSettlementService`; M-02 no debe reescribirlas.
- **Producción:** cambio sensible a dinero; requiere plan, pruebas de regresión y rollout compatible.

### AUDIT-004 — Contrato legado `/permissions` no devuelve permisos efectivos

- **Severidad:** MEDIUM. **Confianza:** CONFIRMED. **Esfuerzo:** LOW.
- **Evidencia:** el endpoint devuelve `RoleMatrix::permissions`; ignora módulos, overrides y scopes. `/me/capabilities` sí usa `AuthorizationService`. La nueva prueba caracteriza la diferencia.
- **Impacto:** un consumidor legado puede mostrar acciones denegadas o interpretar permisos obsoletos. El servidor sigue bloqueando la operación.
- **Dirección:** declarar `/me/capabilities` contrato canónico, migrar consumidores y deprecar o hacer efectivo `/permissions` sin romper clientes.

### AUDIT-005 — Versiones y caché móvil no cubren todos los cambios de acceso

- **Severidad:** HIGH. **Confianza:** CONFIRMED. **Esfuerzo:** MEDIUM.
- **Evidencia:** feature y override incrementan versiones; cambio de rol/estado no lo hace. `AuthContext` refresca capabilities al iniciar/restaurar sesión o por acción explícita; el ciclo de sync no compara la versión para refrescarlas. Un 403 bloquea outbox, pero no elimina inmediatamente clientes/obligaciones locales ya autorizados.
- **Impacto:** el servidor niega requests revocados, pero la navegación y datos locales pueden permanecer visibles en un dispositivo hasta refresco o cierre de sesión.
- **Dirección:** centralizar el bump para rol, estado, override, feature y asignaciones; devolver la versión en sync; ante cambio o 403 refrescar capabilities y purgar caché autorizada del scope, preservando outbox para revisión.

### AUDIT-006 — Catálogo ejecutable y catálogo persistido pueden derivar

- **Severidad:** MEDIUM. **Confianza:** HIGH CONFIDENCE. **Esfuerzo:** LOW.
- **Evidencia:** el seeder agrega/actualiza concesiones presentes pero no elimina `role_permissions` retirados; una capability ausente en `permissions` cae a `RoleMatrix` con `all` y sin feature gate.
- **Impacto:** un despliegue con seed incompleto o una capability retirada puede conservar una concesión inesperada.
- **Dirección:** reconciliación determinística y verificable del catálogo, error cerrado para capabilities registradas incorrectamente y prueba de paridad entre matriz y tablas.

### AUDIT-007 — Scopes por defecto de rendiciones exceden la intención del rol

- **Severidad:** MEDIUM. **Confianza:** CONFIRMED. **Esfuerzo:** LOW.
- **Evidencia:** `settlements.view/create/submit` admiten scopes acotados, pero `defaultScope` no los incluye y asigna `all` al rol `collector`; el listado API solo filtra `own`.
- **Impacto:** un cobrador puede listar rendiciones de otros integrantes del mismo tenant, aunque los servicios protejan mutaciones propias.
- **Dirección:** corregir el preset a `own` y cubrir listado/detalle con pruebas de recursos propios y ajenos antes de migrar datos de autorización.

### AUDIT-008 — Autorización de listas no públicas duplica roles rígidos

- **Severidad:** LOW. **Confianza:** CONFIRMED. **Esfuerzo:** LOW.
- **Evidencia:** `QuotationService` comprueba `owner`/`admin`, mientras el controlador ya usa `products.manage_prices` para definir listas visibles.
- **Impacto:** delegaciones válidas pueden mostrar una lista en UI y ser rechazadas por servicio; el comportamiento depende de dos reglas.
- **Dirección:** elegir una capability explícita y hacerla consistente en consulta y servicio.

## Alternativas para responsabilidades combinables

| Alternativa | Ventajas | Costos y riesgo | Evaluación |
| --- | --- | --- | --- |
| Rol primario + overrides individuales | Sin migración estructural; ya existe | Configuración por persona, difícil de auditar y reutilizar; no resuelve checks rígidos ni scopes inconsistentes | Útil solo para excepciones. No escala como modelo principal. |
| Conjuntos reutilizables de capabilities | Composición explícita, auditable y reutilizable por empresa | Requiere catálogo, asignación y migración aditiva; debe resolver scopes y delegación | Mejor ajuste para responsabilidades operativas combinables. |
| Múltiples roles por membresía | Concepto simple y unión rápida de presets existentes | Mezcla propiedad/jerarquía con tareas; unir `admin`, `seller` y `collector` puede escalar privilegios y scopes de forma difícil de explicar | No recomendado como mecanismo general. |
| Combinación mínima | Conservar rol primario para gobierno/compatibilidad, agregar conjuntos operativos reutilizables y reservar overrides para excepciones | Introduce una sola dimensión nueva y exige migración gradual | **Recomendación para M-02.** |

## Diseño recomendado para M-02

1. Mantener `tenant_memberships.role` y sus reglas de propiedad/administración. No reinterpretarlo como lista de responsabilidades.
2. Introducir conjuntos reutilizables y explícitos de capacidades operativas, asignables varias veces a una membresía. Empezar solo con los conjuntos demostrados por Trotamundo y el primer caso combinado; no crear todavía `delivery`.
3. Resolver permiso efectivo en este orden: estado y módulo → deny individual → concesiones del rol base y conjuntos → allow individual válido → scope del recurso. Los overrides quedan para excepciones, no como plantilla masiva.
4. Evitar una unión silenciosa de scopes. Cada conjunto declara un scope permitido por capability y la resolución debe usar una regla documentada y testeada; ninguna concesión supera el alcance delegable del actor.
5. Extraer una elegibilidad de cobranza reutilizable y aplicarla a consultas, hojas, selectores y asignaciones. Elegibilidad no equivale a acceso a cualquier cartera.
6. Separar capacidades de declarar dinero en campo de capacidades administrativas de verificar/confirmar. El origen y la asignación determinan custodia y rendición; el nombre del rol no.
7. Unificar scopers de recursos antes de habilitar combinaciones. Web, API, sync, Policies y servicios deben producir el mismo conjunto autorizado.
8. Hacer que todo cambio efectivo incremente versiones y que móvil refresque/purgue datos autorizados sin borrar operaciones locales pendientes.

La implementación puede ser expand-safe: tablas nuevas y asignaciones aditivas, backfill idempotente de responsabilidades equivalentes a presets existentes, lectura dual controlada y retiro de checks de rol en un PR posterior. No se elimina ni reescribe `tenant_memberships.role` en M-02.

## Compatibilidad con Trotamundo

- Preservar los presets actuales de vendedor, supervisor, cobrador y administración durante la migración.
- Mantener el flujo venta aprobada → hoja asignada → declaración → rendición → conciliación sin cambiar cálculos ni estados financieros.
- El cobrador de Trotamundo continúa con cartera `assigned` y no recibe verificación, reversa ni aprobación de rendiciones.
- La administración conserva aprobación y segregación. Los conjuntos operativos no conceden `payments.verify*` ni `settlements.approve` por defecto.
- `deliveries` permanece ausente y apagado; M-02 solo debe preparar composición, no implementar reparto.

## Riesgos de migración

- Unión accidental de privilegios y scopes al combinar presets.
- Doble fuente de verdad durante lectura dual entre rol, conjunto y override.
- Catálogo persistido desactualizado en un rolling deploy.
- Dispositivo con capabilities o datos locales anteriores a una revocación.
- Cambiar selectores sin cambiar servicios, o viceversa, y producir UI engañosa.
- Convertir una capacidad de declarar en permiso de verificar, imputar, recibir custodia o autoaprobar.
- Backfill incompleto por tenant o pérdida de trazabilidad del actor que asignó responsabilidades.

Mitigaciones: migración aditiva, auditoría de cambios, paridad de catálogo, pruebas cruzadas entre empresas, lectura compatible, métricas de rechazos y ninguna modificación de historia financiera.

## Pruebas existentes y nuevas

La suite existente ya cubre selección de tenant, membresías múltiples, revocación web/API, acceso de plataforma, cartera `assigned`/`team`, overrides, apagado de módulo, sync y segregación de rendiciones. `MultitenantAuthorizationCharacterizationTest` agrega una matriz concentrada para:

- dos empresas con módulos diferentes;
- el mismo usuario con roles y permisos distintos por tenant;
- módulo no disponible frente a disponible pero deshabilitado;
- deny individual y grant individual con scope autorizado;
- cambio de permisos y versiones con el mismo token;
- revocación de membresía y rechazo de requests de un token ya emitido;
- acceso cruzado a recursos;
- operador de plataforma sin acceso financiero implícito;
- diferencia entre `/permissions` y `/me/capabilities`;
- limitación actual de una responsabilidad combinada ante hojas ligadas a `collector`.

Los escenarios de repartidor+cobrador, conjuntos reutilizables y segregación configurable son criterios futuros. No hay pruebas fallando para funcionalidades todavía inexistentes.

### Evidencia de verificación de M-01

Ejecución local del 2026-10-18 sobre PostgreSQL 17 y una base aislada terminada en `_test`:

- `make test TEST_ARGS='--filter=MultitenantAuthorizationCharacterizationTest --colors=never'`: 10 pruebas, 46 aserciones, sin fallos ni warnings.
- `make test`: 128 pruebas, 714 aserciones, sin fallos.
- `./vendor/bin/pint --test`: 193 archivos, sin cambios de formato requeridos.

No se observaron fallos preexistentes en esta ejecución. No se ejecutaron migraciones destructivas, seeders generales ni pruebas contra bases ajenas al worktree.

## Criterios de aceptación futuros para M-02

1. Una membresía puede reunir venta y cobranza sin cambiar de tenant ni duplicar usuario.
2. La misma identidad puede tener responsabilidades distintas en dos tenants y cada request usa solo las del tenant seleccionado.
3. Elegibilidad para cartera/hoja depende de capability efectiva y asignación, no de `role = collector`.
4. Un empleado habilitado para cobrar en campo debe declarar y rendir por el circuito vigente; no obtiene confirmación administrativa directa.
5. Un empleado puede entregar y cobrar solo cuando ambas capabilities y ambos alcances sean válidos; entrega sola no concede cobro.
6. Ningún conjunto operativo concede aprobación de la propia venta, documento o rendición.
7. `own`, `assigned`, `team` y `all` producen el mismo alcance en web, API, Policies, sync y listados/detalles.
8. Deshabilitar o retirar un módulo bloquea nuevas mutaciones, conserva historia autorizada y actualiza versiones.
9. Cambio de rol, responsabilidad, override, módulo o membresía invalida navegación y datos locales autorizados; el outbox pendiente se conserva para revisión segura.
10. Trotamundo conserva resultados funcionales y financieros con sus presets actuales.
11. Dos tenants con combinaciones distintas funcionan en el mismo despliegue y no comparten recursos.
12. Catálogo, migración y rollback operativo son compatibles con despliegue gradual y quedan auditados.

## Cambios futuros priorizados

1. **P0:** cerrar `AUDIT-001` y `AUDIT-007` con scopers y pruebas negativas de mínimo privilegio.
2. **P0:** diseñar el reemplazo seguro del discriminador de rol en `PaymentService` (`AUDIT-003`) antes de conceder cobro a otros perfiles.
3. **P1:** implementar conjuntos operativos mínimos, elegibilidad unificada y migración compatible (`AUDIT-002`).
4. **P1:** completar versionado e invalidación móvil (`AUDIT-005`).
5. **P2:** reconciliar catálogo y eliminar el fallback permisivo (`AUDIT-006`).
6. **P2:** deprecar `/permissions`, normalizar listas no públicas y decidir si `documents` merece feature propia (`AUDIT-004`, `AUDIT-008`).

M-02 requiere `dev-plan` específico por su impacto en autorización, datos y compatibilidad de despliegue. No debe comenzar cambiando roles o servicios financieros sin aprobar primero esos cortes.
