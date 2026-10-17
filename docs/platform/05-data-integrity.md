# 05 — Contratos de datos, seguridad y operación multitenant

## Estrategia de tenencia

Mantener PostgreSQL con datos compartidos por tabla y `tenant_id` UUID no nulo en tablas empresariales. Reutilizar `BelongsToTenant::forTenant`, la pertenencia activa verificada en sesión/token, claves compuestas `(tenant_id, id)` en referencias cruzadas, autorizaciones Laravel y auditoría. El trait no aplica global scope: **toda consulta debe incluir ámbito tenant**. Los operadores de plataforma no reciben acceso automático a datos empresariales.

**Puntos existentes a conservar:** `users` globales, `tenant_memberships` por empresa, versión de autorización, catálogo de `features`, `tenant_features`, `permissions`, `role_permissions` y overrides; tokens Sanctum con tenant seleccionado y revalidado.

## Cambios potenciales de esquema (propuestos, no ejecutar en bloque)

| Necesidad validada | Evolución mínima candidata | Invariantes |
|---|---|---|
| Políticas operativas por empresa | settings/policy records tipados y versionados | valores validados; auditados; vigencias explícitas |
| Responsabilidades combinadas | bundles/roles adicionales ligados a membership; preservar `role` actual | permiso efectivo + scope + módulo; sin escalación |
| Declaración originada en entrega | referencia de origen tipada y tenant-safe; tablas dependientes solo si necesarias | origen real existente, idempotencia y evidencias |
| Custodia de efectivo | registro append-only de entrega/recepción de valores | responsable actual y transferencia verificable |
| Entrega física | entidades de pedido/entrega/ítems/pruebas de entrega | no conflar con ventas, cuotas o cobranza |
| Importación Excel | lotes de importación + staging + filas/errores + idempotency key | previsualización, totales, commit/reversión controlados |

`source_type` no debe convertirse en un polimorfismo libre que omita claves foráneas. Elegir un diseño que permita validar empresa, tipo y existencia de origen en transacción. Ante varias alternativas, preferir FK concretas o una tabla de referencias de operaciones autorizadas.

## Dinero: invariantes transversales

- Importe exacto en centavos/decimal seguro; nunca floats de JS/PHP.
- Captura de una declaración **no equivale** a recibir dinero ni a confirmar la imputación.
- Confirmación de transferencia exige evidencia bancaria/proveedor confiable o revisión autorizada.
- Efectivo de campo con custodia y rendición; no se confirma por un flag de tenant.
- Pagos e imputaciones idempotentes con índices únicos, actor y origen.
- Reversas y diferencias dejan asientos de compensación/auditoría; ningún borrado de historia para corregir una deuda.
- Diferencias y operaciones offline en conflicto permanecen investigables.
- Congelar versión de política o dato aplicable a operaciones históricas.

## Migrations: expand → migrate → contract

1. **Expand:** crear tablas/campos nuevos nullable, defaults seguros y backfill planificado; mantener lectura/escritura compatibles.
2. **Migrate:** migrar solo en operación explícita, resumible/idempotente, por lotes y con conciliación pre/post; preservar `role` existente, claves e historial.
3. **Switch:** nueva lectura/escritura gated por versión/capacidad y migración verificable por tenant.
4. **Contract:** eliminar campos o caminos heredados solo en entrega posterior, tras tests, telemetría y confirmación de que no quedan clientes antiguos.

Cero `migrate:fresh`, `db:wipe` o borrado de datos de producción. Toda migración tiene prueba PostgreSQL y plan de reversión por despliegue/compensación. No asumir que `down()` destructivo es seguro para datos financieros. Usar backups y procedimientos operativos antes de pasos irreversibles.

## Dispositivos y offline

- Cada operación con UUID, dispositivo, usuario, tenant, esquema/version, payload inmutable y respuesta persistida.
- Nunca se confía en un `tenant_id` recibido del móvil como autorización.
- Tras cambio de permiso o cartera, invalidar cachés apropiados; conservar outbox local con visibilidad acotada para conciliación.
- Una app vieja y una API nueva deben convivir durante ventanas definidas con estrategia de compatibilidad y rechazo explícito de formatos no soportados.
- Evitar usar solo polling/tiempo como prueba de que **todos** los dispositivos sincronizaron.

## Observabilidad y operación

- Auditoría por tenant/usuario/capacidad/operación, sin secretos, tokens ni documentos sensibles en logs.
- Métricas: operaciones pendientes por edad, discrepancias abiertas, errores de sync, latencia de cierre, importaciones fallidas; logs estructurados con `tenant_id`, `operation_id`, `trace_id` cuando aplique.
- Acceso administrativo de soporte debe tener consentimiento, permisos temporales auditados y principio de mínimo privilegio; no hacer bypass silencioso.
- Backups restaurables probados, política de retención, exportación de datos del cliente y proceso de offboarding.
- Secretos de Mercado Pago/terceros por tenant en storage cifrado/secrets manager; credenciales ausentes = funcionalidad no activable.

## Internacionalización

`America/Argentina/Buenos_Aires` está hoy fijada por las reglas actuales. Mantenerla para empresas argentinas mientras el dominio no soporte múltiples zonas; planificar una migración transversal si un cliente LATAM lo requiere, con pruebas de corte de día, cuotas, tareas y sincronización.
