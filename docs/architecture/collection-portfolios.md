# Carteras de cobranza

La cartera de cobranza es una asignación explícita de un cliente a una membresía cobradora activa. No se deriva del vendedor de la venta. `customer_collector_assignments` conserva el historial; PostgreSQL permite una sola asignación activa por cliente mediante un índice parcial.

`assigned` permite acceder solo a clientes cuya asignación activa apunta a la membresía actual. `team` permite acceder a los clientes asignados a cobradores que pertenezcan a un equipo activo supervisado por la membresía actual. `all` conserva el acceso total dentro de la empresa. El scope `own` comercial mantiene su significado de vendedor, pero no habilita cobros.

`CollectionPortfolioService` aplica esa misma decisión a listados y detalles de clientes en web, API y sincronización. Las Policies consultan el mismo scoper, por lo que conocer el UUID de un cliente del mismo tenant no evita la restricción de cartera. `CollectionAssignmentScope` centraliza las membresías activas que corresponden a `assigned` y `team` para que otros recursos de cobranzas no reconstruyan equipos con reglas distintas.

Las rendiciones usan `CashSettlementScopeService`. `own` y `assigned` representan las hojas de la membresía cobradora actual; `team` incluye solo hojas de integrantes activos de equipos activos supervisados por esa membresía; `all` conserva el acceso completo dentro de la empresa. El preset del rol `collector` usa `own` en `settlements.view`, `settlements.create` y `settlements.submit`. Los presets de supervisión y administración conservan su alcance vigente. El listado API y el listado y detalle Livewire consultan este mismo scoper.

Una reasignación o desasignación incrementa `tenants.authorization_version`. El pull móvil compara esta versión: al cambiar, el servidor reinicia el cursor y Expo elimina de SQLite los clientes y obligaciones previamente autorizados antes de almacenar la nueva cartera. La outbox y los cobros locales se conservan para revisión; el servidor revalida permiso y cartera al recibir cada operación.

Las zonas usan `collection_zones` y `customer_collection_zone_assignments`, también históricas. Son etiquetas operativas sin geolocalización ni ordenamiento de visitas. Los equipos usan `collection_teams` y `collection_team_members`; las altas y bajas de miembros son históricas.

Las capacidades de administración son `customers.assign_collector`, `customers.unassign_collector`, `customers.view_assignments`, `collection_zones.view`, `collection_zones.manage`, `collection_teams.view`, `collection_teams.manage` y `collection_teams.manage_members`. Solo el backend aplica estas decisiones y registra auditoría.

Las asignaciones habilitan la cartera y sus visitas en la hoja diaria del
cobrador, pero no sustituyen la autorización de cada operación. Al sincronizar
una declaración, Laravel vuelve a validar la membresía, la empresa, la hoja
iniciada y la visita. La entrega y el ciclo de vida offline de esas
declaraciones están documentados en [sincronización offline](offline-sync.md).
