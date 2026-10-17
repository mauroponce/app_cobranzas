# 03 — Matriz de configuración, permisos y reglas

## Las cuatro dimensiones son diferentes

1. **Disponibilidad comercial de módulo:** plataforma concedió el módulo a la empresa (`tenant_features.is_available`).
2. **Habilitación operativa:** empresa lo encendió (`tenant_features.is_enabled`), respetando disponibilidad y dependencias.
3. **Autorización de usuario:** capability + scope efectivo por membresía, con deny explícito prevaleciendo.
4. **Política de negocio por empresa:** decisión tipada sobre **cómo** ejecutar un proceso habilitado, no **quién** está autorizado a ejecutarlo.

No reemplazar `features`, `tenant_features`, `permissions`, `role_permissions`, `membership_permission_overrides`, `authorization_version`; evolucionarlos gradualmente. No usar feature flag como control de seguridad único.

## Propuesta de settings, solo cuando haya un caso validado

Preferir registros tipados por módulo y validados en backend. Esquema candidato `tenant_settings (id, tenant_id, namespace, key, schema_version, value jsonb, effective_from, effective_until, updated_by, timestamps)` con `unique(tenant_id, namespace, key, version/periodo)` y auditoría del valor anterior/nuevo **sin secretos**. Las restricciones exactas dependen de la migración real; no inventar una tabla universal sin verificar primero alternativas simples como políticas específicas ya existentes (`late_fee_policies`).

**Reglas:** valores predeterminados **explícitos y testeados**, allowlist de keys, validación de JSON schema o DTO tipado en Laravel, control de dependencias, vigencia/historial, cambios autorizados e invalidación de caché/versiones. No permitir `eval`, expresiones PHP/JS almacenadas, SQL de cliente, rutas arbitrarias, bypass de permisos o edición de pagos históricos.

## Configuraciones razonables por rubro

| Clave conceptual | Ejemplos de valores | Cómo se decide |
|---|---|---|
| `sales.approval_mode` | `office_review`, `delegated_supervisor` | Capacidad + política de aprobación |
| `financing.enabled` | `true/false` | Dependencia del módulo financiación |
| `financing.frequencies` | `daily`, `weekly`, `biweekly`, `monthly` | Política existente, mantener cálculos precisos |
| `collections.visit_required` | `true/false` para el origen pertinente | Declaración por visita vs canal remoto, sin relajar revisión |
| `collections.assignment_policy` | `assigned`, `team`, `all` | Permiso de acceso y cartera autorizada |
| `cash_reconciliation.mode` | `office_reception`, `approved_custody_transfer` | Invariantes financieras siguen invariables |
| `deliveries.proof_requirement` | `signature`, `photo`, `code` | Solo módulo entrega |
| `payments.electronic_verification` | `manual`, `verified_provider` | Transferencia no confirmada solo por comprobante |

Estos son **candidatos de configuración**. No crear todas las claves antes de recibir un segundo caso real. La fecha/hora operativa de Buenos Aires permanece según la definición actual; internacionalizarla requiere un proyecto transversal separado y pruebas de timezone.

## Responsabilidades combinables

- `seller`: crea clientes/presupuestos; no aprueba su propia financiación.
- `collector`: cartera asignada, visita, declaración de cobro, rendición propia.
- `delivery_agent`: entregas y evidencia, sin permisos financieros automáticamente.
- `delivery_collector`: combinación efectiva `delivery_agent` + `collector` con **ambos** alcances válidos, autorización de cobro y custodia; nunca permisos de verificación o aprobación por defecto.
- `office_admin`: conciliación, recepción y autorización; sujeto a segregación de funciones.

El mismo usuario podría tener responsabilidades distintas entre empresas. El `role` original puede persistir como rol base durante una transición sin pérdida de datos; agregar bundles/múltiples roles solo si disminuye overrides ad-hoc. Para evitar escalamiento, la concesión de un bundle no puede exceder lo que el actor delegado puede otorgar.

## Dependencias y apagado de módulos

- `payments` sin `sales` puede existir solo si hay obligaciones válidas importadas y modelo explícito de cuentas; no crear saldos inventados.
- `deliveries` requiere entidad comercial entregable, pero **no** financiación.
- `collections` requiere cuentas/obligaciones; `settlements` requiere declaraciones/pagos/custodia según flujo real.
- Deshabilitar un módulo: deja historial consultable bajo permisos administrativos, bloquea nuevas mutaciones e identifica operaciones offline pendientes; nunca borra datos ni impide resolver dinero pendiente.
- Cambiar políticas en mitad de una jornada: congelar/snapshot de la versión aplicable a transacciones relevantes, o posponer vigencia hasta la siguiente jornada. No reescribir el pasado.

## Resolución de políticas

Primero el servidor identifica tenant por sesión/token verificado. Luego calcula disponibilidad del módulo, estado, permiso y scope de la membresía, recurso concreto y policy vigente para esa operación. El frontend solo consume capacidad/configuración publicada para presentación; el servidor recalcula y decide siempre.

**Anti-patrones prohibidos:** `switch($tenant->slug)`; `if ($tenantId === ...)`; columnas `special_for_trotamundo`; credenciales de terceros en JSON general; hardcodear config financiera en mobile; generar un deployment independiente por cliente; cargar clases de código indicadas por un setting.
