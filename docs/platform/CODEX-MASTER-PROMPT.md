# Prompt maestro para Codex — evolución modular multitenant

**Iniciativa:** convertir `mauroponce/app_cobranzas` en la plataforma compartida de una **empresa de servicios de digitalización operativa**, manteniendo una sola base de código configurable para clientes que usan Excel, WhatsApp y procesos manuales.

## Contexto obligatorio

Usar como fuente estos documentos de proyecto: `docs/platform/README.md`, `01-business-model.md`, `02-modules-and-boundaries.md`, `03-tenant-configuration.md`, `04-workflow-scenarios.md`, `05-data-integrity.md`, `06-onboarding-playbook.md`, `07-delivery-roadmap.md`, `08-acceptance-matrix.md`, `09-codex-dev-workflow.md`, `10-new-company-discovery.md` y `templates/company-assessment.template.md`. Leer `AGENTS.md` raíz y de cada app antes de modificar. Para Trotamundo, `docs/definicion_funcional.MD` prevalece en cobranzas.

**Coordinación:** otro Codex está arreglando el circuito de cobranzas. No toques su rama ni reescribas el dominio financiero en paralelo. Comprobá git status, ramas, commits y diffs actuales, y no supongas que la auditoría anterior sigue vigente. Basá la implementación en `main` una vez integrada la estabilización y creá ramas pequeñas por entrega.

## Uso de skills `dev-*` (obligatorio)

Las skills proceden de https://github.com/mauroponce/ai-skills y se invocan `$dev-explore`, `$dev-discovery`, `$dev-plan`, `$dev-implement`, `$dev-review` según su contrato. Usá el flujo del archivo `09-codex-dev-workflow.md`; verificá que existan instaladas. No las copies ni modifiques. No violes las fronteras read-only de discovery/plan/review. Notas temporales en chat o workspace personal, no `SPEC.md`/`PLAN.md` en el repositorio. Para cada entrega, ejecutar el skill apropiado, no fingir su ejecución.

## Objetivo funcional

El mismo despliegue y repositorio debe soportar al menos:

- **Empresa A:** vendedores captan clientes y crean propuestas; oficina aprueba operaciones; cobradores gestionan visitas, efectivo y transferencias; administración recibe dinero y concilia.
- **Empresa B:** vendedores/administración preparan pedidos; repartidores entregan mercadería, pueden cobrar efectivo al entregar y rendir a la oficina; un empleado puede ser repartidor y cobrador a la vez.
- **Empresa C:** cobra remotamente sin visita física, con verificación bancaria/proveedor, sin crear rutas ficticias.

Las tres comparten clientes, autorizaciones, obligaciones, pagos, custodia/conciliación y auditoría cuando sus módulos están activos, pero **no** comparten reglas ni datos entre empresas.

## Decisiones de arquitectura que debés preservar

1. Modular monolith Laravel 13 + PostgreSQL + Expo/React Native; conservar Blade/Livewire y el diseño existente. No microservicios, forks, branches por tenant ni despliegue por empresa.
2. Reutilizar `features`, `tenant_features`, permisos, scopes, overrides, versión de autorización y tenancy existentes; mejorar solo lo necesario para cada escenario.
3. Separar **rol administrativo**, **responsabilidades combinables**, **capabilities** y **asignación de recursos**. Una misma membresía puede repartir y cobrar; el permiso se verifica en servidor, no por `role==='collector'`.
4. Diseñar políticas por tenant tipadas, auditadas y versionadas; usar presets explícitos y allowlist. NO permitir PHP/JS/SQL arbitrario ni un motor universal de reglas.
5. Separar dominio comercial, financiación, visitas de cobro, entregas, recepción del dinero, custodia y conciliación; un cobro originado en una entrega debe usar el **mismo** servicio financiero que uno originado en una visita.
6. No alterar invariantes: declaración no equivale a pago confirmado; transferencias requieren verificación; efectivo de calle requiere rendición; reversas auditadas, exactitud monetaria, idempotencia y control de conciliación.
7. Toda tabla de negocio con `tenant_id`, claves/consultas tenant-safe, seguridad backend, datos privados por tenant. El apagado de módulo nunca borra historial ni impide resolver pendientes.
8. Migrations incremental expand/migrate/contract, compatibilidad con app móvil offline y datos existentes, nada destructivo.

## Cómo ejecutar el trabajo

**Primero:** con `$dev-explore`, inspeccioná código/migraciones/tests actualizados, comprobá qué existe y qué cambió con la estabilización; con `$dev-discovery`, reconciliá los escenarios de la documentación con el código. Usá `$dev-plan` para elegir el menor conjunto de refactors y entregas verticales que habiliten configuraciones sin acoplar clientes. Documentá trade-offs relevantes.

**Después:** con `$dev-implement`, implementá las entregas **una por una** siguiendo `07-delivery-roadmap.md`, empezando por capacidades y configuración, y luego desacoplamiento de roles. No implementes entregas/stock/mercado pago «por las dudas»; primero soportá el caso B mediante contratos y tests solo hasta donde esté justificado. Si una entrega nueva necesita decisión irreversible o con implicaciones financieras, explicitá alternativas y no improvises reglas.

**Revisión:** después de cada corte, revisá sus tests y solicita revisión independiente con `$dev-review` en otro contexto. No mezcles todos los cambios en un único PR.

## Pruebas de aceptación mínimas

Estos criterios son **acumulativos y por fase**. No exigir una entrega real ni marcarla completa durante Entregas 1–3: el módulo delivery se valida solo al implementarse la Entrega 5.

- Dos tenants reales de testing con módulos/policies distintos operan simultáneamente en una misma instancia sin filtración de datos.
- A no tiene delivery; B sí; al deshabilitar módulos se bloquean mutaciones, no desaparecen históricos.
- Una persona B reparte y cobra con permisos específicos sin heredar facultad de aprobar rendiciones.
- Vendedor A solo crea propuestas; aprobación es de oficina según regla y permiso.
- Cobro remoto funciona sin hoja ficticia, manteniendo validación/verificación adecuada.
- Cobro de entrega y cobro de visita comparten **única** implementación de imputación financiera; no doble pago ni confirmación anticipada.
- Offline: reintentos, revocación de permisos, reasignación y versiones de API no duplican cobros.
- Migración de settings/responsabilidades conserva Trotamundo y no rompe cuentas/hojas históricas.
- Cada cambio con pruebas PostgreSQL de aislamiento, autorización, concurrencia e invariantes; CI con backend y móvil exitoso.

## Exclusiones

No reescribas todo desde cero. No conviertas `collection_routes` en logística universal sin evidencia. No generes un motor de workflows, ERP fiscal, IA, extensiones ejecutables por tenant, RLS parcial sin diseño, microservicios ni personalizaciones por condicional de slug/ID.

## Resultado esperado

Para cada entrega: explicación del problema real, soluciones evaluadas, decisión mínima, cambios implementados, migraciones/versionado, pruebas y resultados **efectivamente ejecutados**, riesgos pendientes y referencia a PR. Actualizá documentos permanentes **solo cuando corresponda**. Si un test no pasa, indicá que falla; no declares producción lista sin piloto validado.

**Próximo paso concreto:** comenzá con `$dev-explore` y `$dev-discovery` sobre Entregas 0 y 1. No saltes directamente a una reescritura masiva.

## Ingreso de nuevas empresas (instrucción permanente)

**Cada nueva empresa requiere primero descubrimiento guiado en Codex modo Plan**, según `docs/platform/10-new-company-discovery.md` y `$dev-discovery`; no comenzar modificando código. Hacer preguntas progresivas al equipo, seleccionar ramas según negocio, verificar cada requisito contra código actual y clasificar E0–E6. El informe fit-gap y encaje comercial usa la plantilla de `docs/platform/templates/company-assessment.template.md`. Preguntas sin respuesta que cambien pagos, roles, privacidad, integraciones o alcance bloquean una propuesta cerrada; la ejecución se autoriza después y por entregas verticales. Nunca guardar datos de clientes reales en el repositorio público.
