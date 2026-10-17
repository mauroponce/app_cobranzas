# 07 — Plan por cortes verticales y fronteras de integración

## Restricción de coordinación

Hay un Codex trabajando en estabilización de cobranzas. Esta iniciativa **no escribe sobre esa rama**, no edita migraciones que el otro agente está creando y no integra cambios financieros hasta revisar su diff, pruebas y merge. Partir del `main` actualizado cuando se complete.

## Entrega 0 — Cerrar y auditar estabilización actual

- Inspeccionar diff del Codex de cobranzas y ejecutar backend tests, Jest, typecheck/lint/format, PostgreSQL.
- Verificar contrato definitivo declaración→recepción→conciliación; identificar qué falta de endpoint/estado.
- Capturar invariantes en tests antes de reestructurar módulos.
- **No hecho hasta** que integridad financiera, CI y pruebas principales estén verificadas.

## Entrega 1 — Catálogo real de capacidades y configuración (sin cambiar cobranzas)

- Inventariar permisos/features actuales; diseñar dependencias/visibilidad; exponer capacidades efectivas en web y móvil.
- Incorporar solo settings que un caso probado requiera, con validación, auditoría y versión.
- Test con dos tenants de configuraciones distintas, sin desplegar código diferente.
- **No hecho hasta** que una empresa pueda encender/apagar un módulo permitido sin acceder al de otra.

## Entrega 2 — Elegibilidad operativa por capacidades, no por rol rígido

- Encontrar `where role=collector`, checks `role === collector` y permisos acoplados.
- Mantener `tenant_memberships.role`; extraer servicios de elegibilidad a capacidades concretas y asignaciones.
- Resolver necesidad real de un segundo rol: evaluar combinación de perfiles/roles por membership vs bundles de permisos existentes. No introducir ambos sin justificación.
- Mantener temporalmente el comportamiento del cliente existente.
- Test: repartidor-cobrador accede a sus tareas; repartidor puro no cobra; cobrador no entrega; nadie se autoaprueba rendición.

## Entrega 3 — Origen de declaraciones reutilizable para visitas remotas/entregas

- Extender contrato de declaración a orígenes **tipados y autorizados**.
- Preservar fuente única de verdad para pagos, custodia, imputación, verificaciones y sync.
- No implementar entregas todavía: primero probar origen remoto o abstracción mínima necesaria con caso definido.
- Validar contra legado y operaciones históricas, sin doble imputación.

## Entrega 4 — Onboarding reutilizable desde Excel

- Diseñar importaciones en staging, validaciones, preview de saldos, dedupe, confirmación de administrador y audit trail.
- Completar carga de clientes, saldos de apertura con origen documentado, carteras y usuarios según necesidad real.
- Test con archivos duplicados, reintentos, filas parciales y errores no destructivos.

## Entrega 5 — Piloto 2: reparto + cobranza (condicional)

- **Solo con contrato/requerimientos validados:** modelo de pedido/entrega con estados, evidencias y permisos.
- Conectar declaración de cobro de reparto al dominio financiero común; custodia de efectivo independiente de la entrega.
- Rutas de entrega, evidencia y devoluciones: mínimo viable de ese cliente, no ERP logístico genérico.
- Test combinado de dos tenants simultáneos: Trotamundo sin delivery, empresa B con delivery y cobranza.

## Entrega 6 — Operación comercial y adaptación continua

- Herramientas de diagnóstico de soporte, configuración visible, reportes mínimos de excepción, runbook de fallas, reconciliación de importes y exportación de datos.
- Medir costo de segunda/tercera implementación para decidir inversiones.

## Reglas para PRs

Cada entrega es un PR independiente con: objetivo observable, migraciones expand-safe, tests de ambos tenants, compatibilidad mobile/servidor, riesgo, docs actualizadas, comandos de verificación y plan de despliegue. No hacer un mega-PR ni fusionar con tests fallidos.
