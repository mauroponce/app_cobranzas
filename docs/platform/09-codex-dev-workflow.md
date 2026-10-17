# 09 — Uso del harness `dev-*` de `mauroponce/ai-skills`

## Fuentes verificadas

Repositorio: https://github.com/mauroponce/ai-skills ; skills originales en `.agents/skills/dev-*/SKILL.md`; gobernanza en `.agents/skills/references/workflow-governance.md` y `execution-policy.md`.

**No copiar ni reescribir** estas skills en `app_cobranzas`. Codex debe invocar las versiones instaladas y comprobar disponibilidad. Si no están instaladas en el contexto actual, seguir las instrucciones oficiales del repositorio `ai-skills` antes de ejecutar; no simular que se invocó una skill. No modificar `AGENTS.md` para guardar planes.

## Flujo recomendado: tareas separadas, no un megacomando

1. **`$dev-explore`** (Normal, solo lectura): actualizar mapa de `tenant_features`, permisos, `RoleMatrix`, dominios comerciales y financieros tras integración de la estabilización de cobranzas.
2. **`$dev-discovery`** (Plan, solo lectura): contrastar escenarios A/B/C con código, decidir únicamente ambigüedades de negocio irreversibles y documentar en el chat; no crear SPEC/PLAN dentro de repo.
3. **`$dev-plan`** (Plan, solo lectura): plan de entregas verticales con archivos, migraciones, pruebas y compatibilidad. Plan personal opcional en `~/.codex/workspaces/...` si sirve a otra conversación.
4. **`$dev-implement`** (Normal, cambios): ejecutar **una entrega por PR** desde rama actualizada y con tests. Actualizar docs de producto/arquitectura si cambia el contrato público.
5. **`$dev-review`** (Normal, otra sesión): revisar diff concreto independiente antes del merge. Usar `dev-release` solo en un proceso explícito de publicación con su frontera de aprobación.
6. **`$dev-audit`** solo cuando se requiera otra auditoría amplia, no como requisito recurrente. **`$dev-debug`** diagnostica sin arreglar; después implementa por separado.

## Importante: alcance documental

Las skills prohíben crear `SPEC.md`, `PLAN.md`, `DEBUG.md` y artefactos de proceso versionados por defecto, pero admiten **documentación de producto solicitada expresamente** en `dev-implement`. Por eso estos archivos `docs/platform/*` son arquitectura y negocio permanente, no el estado transitorio de un agente. La investigación y el plan de ejecución de Codex viven en el chat/workspace personal, no aquí.

## Normas de coordinación

- No tocar la rama de estabilización de cobranzas del otro Codex. Esperar su integración o rebasar sobre el trabajo terminado sin mezclar cambios.
- No ejecutar migraciones destructivas y no modificar producción sin aprobación explícita.
- Código/identificadores en inglés; documentación y textos de usuario en español, como pide `AGENTS.md`.
- Confirmar disponibilidad de skills en la instalación de Codex; si faltan, registrar la limitación.
- No lanzar cinco `$dev-*` simultáneos sobre el mismo árbol de trabajo. Respetar el límite de lectura/escritura de cada skill.

## Alta de una nueva empresa: modo Plan

Usar [10 — Protocolo de nueva empresa](10-new-company-discovery.md) al comenzar cada oportunidad. La entrevista corresponde a `$dev-discovery`; tras reunir decisiones y mapear el código, `$dev-plan` diseña cambios. Las preguntas se plantean progresivamente (predeterminado una por turno), no como formulario. La estructura de resultado está en `templates/company-assessment.template.md`. Ningún cliente requiere modificaciones al core antes de validar el fit-gap.

## Mensajes reutilizables

**Exploración:** `$dev-explore Analizá los límites actuales de Tenancy, Authorization, Collections y Sales para adaptar dos empresas con distinto flujo. Leé docs/platform/README.md y no modifiques archivos.`

**Descubrimiento:** `$dev-discovery Aplicá docs/platform/04-workflow-scenarios.md al código real y detectá únicamente decisiones de negocio que aún falten. Mantené la sesión en modo Plan.`

**Planificación:** `$dev-plan Diseñá el menor cambio incremental para la Entrega 1 de docs/platform/07-delivery-roadmap.md. Considerá pruebas y migraciones sin modificar repo.`

**Implementación:** `$dev-implement Ejecutá solamente la entrega planificada, en rama independiente, con pruebas y documentación de producto actualizada.`

**Revisión:** `$dev-review Revisá el diff del PR contra docs/platform/08-acceptance-matrix.md y los invariantes existentes; no modifiques código.`

**Nueva empresa (Plan):** `$dev-discovery Estoy evaluando una empresa nueva. Leé docs/platform/10-new-company-discovery.md y aplicá la entrevista adaptativa, sin tocar archivos. Compará con código real; clasificá E0–E6 y entregá informe fit-gap usando la plantilla genérica.`
