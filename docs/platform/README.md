# Plataforma modular de digitalización operativa — guía de lectura

**Estado:** propuesta de arquitectura de producto; **no describe funcionalidades ya implementadas**. Este paquete se diseñó para `mauroponce/app_cobranzas` tomando como referencia su rama `main` revisada el 17/10/2026. Se debe reconciliar con los cambios del Codex que actualmente estabiliza cobranzas **antes de modificar código**.

## Objetivo

Mantener **una sola plataforma compartida**, donde se configuren módulos, autorizaciones, políticas de negocio y procedimientos por empresa, sin `if (tenant_id === …)`, forks, esquemas específicos por cliente ni despliegues separados de manera predeterminada. El servicio comercial se basa en **relevamiento, migración, implementación, capacitación y soporte**, no en autoservicio.

## Orden recomendado

1. [01 — Producto y estrategia comercial](01-business-model.md)
2. [02 — Módulos y límites de dominio](02-modules-and-boundaries.md)
3. [03 — Configuración, roles y políticas](03-tenant-configuration.md)
4. [04 — Escenarios operativos](04-workflow-scenarios.md)
5. [05 — Datos, seguridad y contratos](05-data-integrity.md)
6. [06 — Playbook de implementación](06-onboarding-playbook.md)
7. [07 — Hoja de ruta por entregas verticales](07-delivery-roadmap.md)
8. [08 — Matriz de pruebas de aceptación](08-acceptance-matrix.md)
9. [09 — Cómo trabajar con skills DEV](09-codex-dev-workflow.md)
10. [10 — Entrevista guiada para nuevas empresas en modo Plan](10-new-company-discovery.md)
11. [Plantilla de informe de encaje por empresa](templates/company-assessment.template.md)
12. [Prompt maestro para Codex](CODEX-MASTER-PROMPT.md)

## Fuente de verdad y conflictos

- La definición vigente de cobranzas en `docs/definicion_funcional.MD` prevalece para el **flujo existente de Trotamundo**. Estos documentos definen cómo evolucionar la plataforma; no autorizan cambios silenciosos a ese dominio.
- El código, las migraciones y los tests de la rama **actual** tienen prioridad para conocer qué está implementado; la documentación no reemplaza una inspección.
- Las decisiones comerciales aquí marcadas como **hipótesis** requieren validación con clientes.
- Los documentos permanentes de arquitectura son entregables explícitamente solicitados. Las notas de investigación, planes personales, auditorías temporales y handoffs de las skills `dev-*` siguen su política: chat o workspace personal de Codex, **no** archivos de proceso versionados por defecto.

## Realidad de partida identificada

- Laravel 13 + Blade/Livewire y React Native/Expo; PostgreSQL multitenant, roles y permisos `RoleMatrix`, `features` y `tenant_features`, `membership_permission_overrides`, `authorization_version`, scopes `own/assigned/team/all`.
- Clientes, catálogo, presupuestos, ventas financiadas, cuotas, cartera, API, SQLite y outbox offline con código existente.
- `tenant_memberships.role` representa hoy un rol primario único; algunas consultas obligan a `role='collector'`. Eso **no permite** describir bien a un repartidor que además cobra.
- La conciliación financiera está siendo estabilizada por otro Codex. Esta iniciativa no debe reabrirla ni alterar datos mientras se completa ese trabajo.

## Principios no negociables

1. Integridad financiera y autorización del servidor antes que flexibilidad.
2. Configuración tipada, validada, versionada y auditable; nada de scripts arbitrarios del cliente.
3. Módulos de dominio por capacidad, no por nombre de puesto de trabajo.
4. Operaciones de campo separadas de la recepción de valores y de la imputación contable.
5. Migraciones incrementales, sin borrar historias, datos o rutas existentes.
6. Servicios no autorizan usando `tenant_id` provisto por el navegador.
7. Cambios por empresa **a través de datos de configuración**, no por ramas de código.
8. Complejidad a demanda: sin motor universal de workflows ni marketplace de plugins en primera etapa.

## Cuando aparezca una empresa nueva

No comenzar con `$dev-implement`. Abrir Codex en **modo Plan**, utilizar `$dev-discovery` y seguir [10 — Protocolo de nueva empresa](10-new-company-discovery.md). La entrevista es progresiva y produce una matriz fit-gap E0–E6 según la [plantilla](templates/company-assessment.template.md). Los datos de la empresa y acuerdos comerciales van en espacio privado, no en Git público.
