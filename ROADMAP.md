# ROADMAP — Plataforma de digitalización operativa

> **Punto único de seguimiento del producto.** Este archivo indica qué hacer ahora, qué sigue, qué está bloqueado y cómo comprobar que una entrega terminó. No reemplaza las definiciones funcionales ni la arquitectura detallada.
>
> **Última revisión del roadmap:** 2026-10-18. **Base pública verificada:** `main` y `origin/main` en `7bf02095dc8d545b1aefa12a763c8673c4b64932` (commit del 2026-10-18). C-01, C-02 y C-03 están integrados, pero no validados: el CI backend del commit vigente falló y aún falta una revisión independiente. C-04 está preparado en la rama `feat/c-04-pilot-readiness`, sin autorizar operación real.

## 1. Dirección del producto

No buscamos un SaaS genérico de autoservicio. Vendemos **servicios de digitalización acompañada** a empresas que operan con WhatsApp, Excel y procesos manuales. El software es la plataforma compartida que permite implementar clientes nuevos sin tener una versión de código para cada empresa.

Principios:

1. **Una sola base de código y despliegue compartido** como opción estándar; aislamiento fuerte por tenant.
2. **Configuración antes que personalización:** reutilizar módulos, capacidades y reglas de negocio tipadas. Ningún `if (tenant_id == ...)` ni forks por empresa.
3. **Seguridad financiera por encima de flexibilidad:** precisión monetaria, idempotencia, custodia, autorización, conciliación y auditoría no son opcionales.
4. **Entregas pequeñas y probadas:** ningún mega-refactor ni módulo nuevo porque “podría servir”.
5. **Cliente real antes que complejidad:** implementar necesidades repetidas y medibles. Un módulo previsto no es una funcionalidad disponible.

## 2. Cómo interpretar este documento

Estados permitidos:

- `PENDIENTE`: aún no iniciado.
- `EN PLAN`: alcance y diseño en análisis, sin código de producción.
- `EN DESARROLLO`: existe una rama/PR de implementación identificable.
- `IMPLEMENTADO / SIN VALIDAR`: existe código y evidencia de pruebas, pero falta revisión independiente, CI verde y/o integración.
- `BLOQUEADO`: depende de una condición explícita.
- `VALIDADO`: hay evidencia de pruebas, revisión e integración; no implica validación operativa en cliente real.
- `OPERATIVO`: utilizado y aceptado en un piloto real según criterios de aceptación.

**Fuente de verdad según pregunta:**

- ¿Qué hace realmente el software? Código, migraciones, tests y rama actual.
- ¿Cómo debe funcionar cobranzas de Trotamundo? `docs/definicion_funcional.MD`.
- ¿Qué arquitectura de plataforma queremos? `docs/platform/`.
- ¿Qué priorizamos y qué está pendiente? **Este `ROADMAP.md`.**
- ¿Qué requiere una empresa nueva? `docs/platform/10-new-company-discovery.md` y su informe fit-gap privado.

Si hay contradicción, **no inventar un comportamiento para resolverla**: identificarla, comprobar evidencia y proponer una decisión explícita.

## 3. Estado y orden de trabajo

| Orden | ID | Entrega | Prioridad | Estado | Dependencia / condición de salida |
|---|---|---|---|---|---|
| 1 | **C-01** | Estabilización del dominio de cobranzas | P0 | **IMPLEMENTADO / SIN VALIDAR** | Revisión independiente; obtener CI backend verde; repetir verificaciones sobre el commit vigente |
| 2 | **C-02** | Completar formularios administrativos de diferencias y reasignaciones | P0 | **IMPLEMENTADO / SIN VALIDAR** | C-01 continúa sin validar; falta revisión independiente y CI verde |
| 3 | **C-03** | Jornada completa y rendición en entorno de pruebas | P0 | **IMPLEMENTADO / SIN VALIDAR** | Revisión independiente; CI backend verde; prueba móvil visual; concurrencia administrativa; migración sobre copia anonimizada representativa |
| 4 | **C-04** | Piloto controlado de cobranzas con Trotamundo | P0 | **IMPLEMENTADO / SIN VALIDAR** | La preparación técnica existe; el piloto real sigue bloqueado por revisión, CI, restore integral, validación móvil, datos y aprobaciones operativas |
| 5 | **I-01** | Diseñar importación Excel/CSV y saldos de apertura | P1 | **PENDIENTE** | Definir fuentes reales anonimizadas, contratos y criterios de conciliación |
| 6 | **I-02** | Implementar importación determinística con previsualización | P1 | **BLOQUEADO** | Plan I-01 y dominio financiero estable |
| 7 | **I-03** | Asistente Gemini para mapeos/documentos ambiguos | P2 | **BLOQUEADO** | I-02 validado; evaluación privacidad, costo y precisión |
| 8 | **M-01** | Auditar módulos, capacidades y permisos actuales | P1 | **PENDIENTE** | Reconciliar código después de C-01; mapa de dependencias |
| 9 | **M-02** | Responsabilidades combinables: vendedor, cobrador, repartidor-cobrador | P1 | **BLOQUEADO** | M-01; no alterar invariantes del dominio financiero |
| 10 | **M-03** | Reglas empresariales tipadas, versionadas y auditadas | P1 | **BLOQUEADO** | Casos reales de una segunda empresa; no construir motor universal |
| 11 | **O-01** | Onboarding repetible de empresas y checklist de aceptación | P1 | **EN PLAN** | Proceso comercial + I-01; no requiere esperar todos los módulos |
| 12 | **O-02** | Panel interno mínimo de configuración/soporte por tenant | P2 | **PENDIENTE** | M-01 e indicadores operativos validados |
| 13 | **V-01** | App móvil completa para vendedores | P2 | **PENDIENTE** | Relevamiento y MVP comprobados; módulos comerciales reutilizables |
| 14 | **L-01** | Entregas y repartidor que también recibe dinero | P2 | **BLOQUEADO** | Empresa piloto concreta, discovery fit-gap y M-02 |
| 15 | **X-01** | Integraciones WhatsApp / Mercado Pago / bancos | P3 | **PENDIENTE** | Casos concretos, permisos, verificación y diseño de idempotencia |

**Contraste de estados del 2026-10-18:** C-01, C-02 y C-03 están integrados en `main`, pero conservan `IMPLEMENTADO / SIN VALIDAR`: [Móvil #14](https://github.com/mauroponce/app_cobranzas/actions/runs/39106253493) pasó y [Backend #18](https://github.com/mauroponce/app_cobranzas/actions/runs/39106253501) falló en `php artisan test`; siguen pendientes la revisión independiente y los límites de validación de C-03. C-04 incorpora infraestructura, recuperación, distribución, soporte y procedimientos en una rama identificable, por lo que avanza a `IMPLEMENTADO / SIN VALIDAR`; no existe evidencia de despliegue, APK distribuida ni operación real. O-01 mantiene `EN PLAN`. No se encontró evidencia suficiente para avanzar los demás estados.

**Próxima tarea de ingeniería recomendada:** ejecutar `$dev-review` independiente de C-04 y diagnosticar el CI backend del commit vigente. Después, completar el restore integral en infraestructura aislada, la inspección móvil física, la carrera de aprobación administrativa y la migración sobre copia anonimizada representativa. No autorizar el piloto real mientras la puerta GO conserve pendientes.

## 4. Evidencia existente y criterios de aceptación por hito

### C-01 — Revisar y cerrar la estabilización de cobranzas

La implementación del flujo declaraciones → rendición por hoja → conciliación, pagos por componentes, cierre, reasignaciones, incidencias y sincronización offline quedó integrada en [`7638eb0`](https://github.com/mauroponce/app_cobranzas/commit/7638eb03eff21fc4d6f20e43311fda109a2d8cf0). Evidencia comprobada en esta revisión:

- Existen `apps/backend/database/migrations/2026_10_17_120000_stabilize_collection_settlements.php`, `apps/backend/tests/Feature/CollectionsStabilizationTest.php` y `docs/audits/collections-stabilization.md` en `main` y `origin/main`.
- Verificación local aprobada: `make test` sobre `app_cobranzas_test` con 104 pruebas / 523 aserciones, Pint sobre 189 archivos, `make assets`, TypeScript, ESLint, Prettier y Jest con 3 suites / 16 pruebas.
- GitHub Actions: [Móvil #13](https://github.com/mauroponce/app_cobranzas/actions/runs/39032264486) terminó correctamente; [Backend #16](https://github.com/mauroponce/app_cobranzas/actions/runs/39032264505) falló en `php artisan test`. La causa exacta no quedó disponible en la vista pública y sigue pendiente de diagnóstico.
- `git show --check 7638eb0` informa una línea en blanco al final de `docs/platform/10-new-company-discovery.md`; por eso no se conserva como evidencia la afirmación anterior de que el chequeo de whitespace estaba limpio.
- El mismo commit integra la estabilización financiera y los documentos `docs/platform/`; esa mezcla de alcance está confirmada y debe considerarse en la revisión.

**No cerrar C-01 solo porque el código esté integrado o las pruebas locales pasen.**

- [x] Confirmar commit y diff final contra `main` y `origin/main`.
- [x] Repetir verificaciones locales sin migraciones de producción.
- [ ] Diagnosticar y resolver el fallo de GitHub Actions Backend #16; obtener CI verde en el commit vigente.
- [ ] Ejecutar `$dev-review` independiente: incompatibilidades, autorización, idempotencia, custodia, sincronización, datos existentes y migraciones.
- [ ] Corregir hallazgos bloqueantes y repetir comprobaciones.

### C-02 — Completar operación administrativa

Los formularios Livewire para **atribuir diferencias** y **reasignar visitas** están integrados en `main` mediante `c8e939b`. Reutilizan los servicios del dominio y conservan la segregación por tenant; no se agregaron migraciones ni reglas financieras nuevas.

- [x] Administración puede conciliar rendiciones sin diferencia o con atribución manual explícita y reasignar visitas pendientes por UI, con confirmación, validación y permiso de servidor.
- [x] Se muestran detalle, importes, diferencias, conflictos, historial, auditoría e incidencias relacionadas en modo lectura; no se corrigen datos silenciosamente.
- [x] Pruebas Livewire/integración cubren tenant, permisos, doble/autorrevisión, importes inválidos, atribuciones, reasignaciones válidas e inválidas, estado obsoleto, persistencia y ausencia de efectos financieros ante errores.

**Evidencia local del 2026-10-17:** `make test` sobre `app_cobranzas_test` aprobó 117 pruebas / 620 aserciones; Pint aprobó 191 archivos; `make assets` generó el bundle de producción. La suite nueva está en `apps/backend/tests/Feature/CollectionsAdministrationTest.php` (13 pruebas). El [CI Backend #17](https://github.com/mauroponce/app_cobranzas/actions/runs/39070359160) del commit integrado falló en `php artisan test`; falta además `$dev-review`, por eso C-02 no está `VALIDADO`.

### C-03 — Ensayo de jornada completa (staging)

- [x] Aplicar las 22 migraciones incrementales en `app_cobranzas_c03_test`, con volumen propio, servicios externos anulados y backup/restauración comprobados sobre datos sintéticos.
- [x] Venta financiada → cuotas → asignación → ruta → declaración combinada por API móvil → sincronización → rendición → conciliación → saldos y auditoría.
- [x] Probar efectivo, transferencia, pago combinado, diferencia, reintento idéntico, UUID conflictivo, cambio de cartera, reasignación, revocaciones y conectividad intermitente mediante la prueba vertical y suites existentes.
- [x] Confirmar bloqueos de cierre y documentar el límite: solo el dispositivo que presenta la hoja puede afirmar que su outbox está vacía.
- [x] Registrar procedimiento de soporte y guion manual en [`docs/validation/c03-collection-day.md`](docs/validation/c03-collection-day.md).
- [ ] Repetir migración y medición sobre una copia anonimizada representativa; los datos usados en esta ejecución fueron sintéticos.
- [ ] Completar interacción visual móvil y prueba simultánea de dos administradores; obtener CI backend verde y revisión independiente.

**Evidencia C-03 del 2026-10-17:** `CollectionsEndToEndTest` aprobó el escenario exacto con 48 aserciones: venta A `$ 300.000,00` en 3 × `$ 100.000,00`, venta B `$ 60.000,00`, declaración `$ 30.000,00` efectivo + `$ 50.000,00` transferencia, recepción `$ 25.000,00`, diferencia `$ 5.000,00` y saldos finales A `$ 225.000,00`, B `$ 60.000,00`, total `$ 285.000,00`. La suite final aprobó 118 pruebas / 668 aserciones, Pint 192 archivos, assets, TypeScript, ESLint, Prettier y Jest (3 suites / 16 pruebas). Origen y restauración conservaron 22 migraciones, 69 tablas y el tenant sintético. El AVD Android inició y aceptó el APK, pero Metro no abrió el puerto por espera de I/O; no existe evidencia visual suficiente. C-03 no está `VALIDADO` y la operación real de C-04 continúa bloqueada.

### C-04 — Preparación del piloto controlado

La preparación técnica está implementada en `feat/c-04-pilot-readiness`; el
informe y la puerta vigente están en
[`docs/pilots/c04-readiness.md`](docs/pilots/c04-readiness.md). Esto no equivale
a autorización, ejecución ni aceptación del piloto.

- [x] Configuración reproducible para HTTPS, Laravel, PostgreSQL persistente,
  Redis, cola, scheduler, almacenamiento privado, logs y healthchecks, sin
  secretos versionados ni despliegue real.
- [x] Scripts de backup PostgreSQL + documentos privados, checksums y restore
  que rehúsa sobrescribir y solo acepta una base nueva terminada en `_test`.
- [x] Perfil EAS de APK interna y procedimiento de instalación/actualización
  controlada; no se generó ni distribuyó una build.
- [x] Soporte tenant-aware para dispositivos, sincronizaciones, incidencias y
  auditoría; la revocación exacta del dispositivo invalida su token y se audita.
- [x] Runbook, capacitación, checklist, soporte, informe diario, acta de
  aceptación y GO / NO-GO sin datos identificatorios reales.
- [x] Verificación local: `make test` aprobó 123 pruebas / 696 aserciones; Pint
  aprobó 198 archivos; `make assets`, TypeScript, ESLint, Prettier y Jest (3
  suites / 16 pruebas) aprobaron; sintaxis de scripts, configuración Compose y
  whitespace aprobaron.
- [ ] Completar un ensayo integrado de backup y restore PostgreSQL + documentos:
  el intento aislado de C-04 no terminó de construir la imagen PHP y no produjo
  evidencia de restauración. Se conserva únicamente la evidencia DB sintética
  de C-03.
- [ ] Obtener CI verde de la rama y revisión independiente; corregir cualquier
  defecto bloqueante.
- [ ] Aprobar identificador Android/cuenta Expo y verificar APK en dispositivo
  físico autorizado.
- [ ] Definir participantes, dispositivos, jornadas, fuente oficial, fecha de
  corte, RPO/RTO, responsables y alcance; conciliar saldos sobre una copia
  anonimizada representativa.
- [ ] Obtener autorización humana explícita antes de desplegar, migrar datos,
  distribuir a empleados o registrar dinero real.

**Veredicto del 2026-10-18: NO-GO.** C-04 está `IMPLEMENTADO / SIN VALIDAR`, no
`OPERATIVO`.

### I-01 / I-02 — Importación desde Excel sin inventar deuda

- [ ] Relevar formatos reales **anonimizados**: clientes, domicilios, productos, ventas, cuotas, cuentas por cobrar y asignaciones.
- [ ] Definir si migramos historia completa o saldos de apertura; quién firma la fecha de corte y la conciliación de totales.
- [ ] Preparar importación en staging: parsing exacto, mapeo de columnas, validación, deduplicación, previsualización/dry-run y confirmación humana.
- [ ] Importar de forma idempotente; conservar archivo/hoja/fila de origen, trazabilidad, errores y resumen de totales.
- [ ] No convertir un saldo inicial en pagos o ventas ficticias ni confirmar depósitos con base en una planilla no verificada.
- [ ] Tests multitenant y de reintentos, importaciones parciales, errores de fila y precisión monetaria.

### I-03 — IA asistida (opcional, no bloquea migraciones)

- [ ] Probar Gemini sobre muestras anonimizadas de encabezados/filas y documentos visuales.
- [ ] IA sugiere mapeos, interpreta abreviaturas y formula preguntas; **no escribe operaciones financieras ni autoriza importaciones**.
- [ ] Datos originales y validaciones exactas se procesan determinísticamente en Laravel.
- [ ] Revisar privacidad, contratos y tratamiento de datos, costos, retención, trazabilidad de decisiones y tasa real de errores.
- [ ] Mantener importación manual si la IA no está disponible o no es confiable.

### M-01 / M-02 / M-03 — Arquitectura adaptable

- [ ] Inventariar `features`, `tenant_features`, `RoleMatrix`, overrides, scopes y checks rígidos `role=collector`.
- [ ] Separar activación de módulo, permiso, alcance del recurso y rol/título de puesto.
- [ ] Poder asignar capacidades de reparto+cobranza a la misma persona sin duplicar `PaymentService` ni debilitar segregación de funciones.
- [ ] Tener dos tenants de prueba con configuraciones distintas en la misma implementación.
- [ ] Versionar políticas de negocio **solo** para casos concretos; rechazar configuraciones que vulneren saldos, auditoría o idempotencia.
- [ ] Ningún branch/fork por cliente; cambios E0–E6 evaluados mediante `docs/platform/10-new-company-discovery.md`.

### O-01 — Onboarding y venta acompañada

- [ ] Entrevista de proceso y fit-gap E0–E6 para cada empresa.
- [ ] Alcance de piloto y exclusiones, datos a importar, responsables, aceptación y capacitación definidos.
- [ ] Registro de horas de relevamiento, adaptación, soporte y tiempo hasta primera operación real.
- [ ] No almacenar información identificatoria ni planillas reales de clientes en el GitHub público.

### L-01 / V-01 / X-01 — Extensiones condicionadas

**No implementar hasta validar una necesidad comercial real.** Reparto requiere un dominio de entregas propio y debe reutilizar cobranza/custodia compartidas. La app del vendedor e integraciones externas se definen a partir de pilotos y pruebas concretas. Un pago remoto no debe depender artificialmente de una visita física.

## 5. Próximas decisiones de producto pendientes

1. ¿Cuál es el MVP de la primera jornada de Trotamundo y quién lo acepta formalmente?
2. ¿Qué información histórica real podremos exportar para ensayar la migración? ¿Historial completo o saldo de apertura?
3. ¿Qué reglas de empresa merecen configuración desde el primer día y cuáles conviene mantener fijas hasta un segundo caso real?
4. ¿Cuál será la primera empresa distinta de Trotamundo para validar capacidades combinables y proceso de implementación?
5. ¿Qué alcance comercial, soporte y personalizaciones incluirá el servicio estándar?

Estas decisiones deben resolverse en discovery con evidencia, no suponerse dentro de un PR.

## 6. Cómo trabajar con Codex y mantener vivo el roadmap

Usar las skills originales de `mauroponce/ai-skills` y sus restricciones: `$dev-explore` (leer), `$dev-discovery` (definir), `$dev-plan` (diseñar), `$dev-implement` (código), `$dev-review` (revisión independiente), `$dev-release` (solo acciones de despliegue autorizadas). No copiar skills ni guardar planes personales en este repositorio.

**Al empezar un trabajo:**

1. Leer `ROADMAP.md`, `AGENTS.md` y los documentos específicos relevantes.
2. Comprobar el estado de Git, rama, commits, CI y documentos. No confiar en estados obsoletos.
3. Indicar el **ID del hito** a ejecutar, la dependencia y qué queda fuera de alcance.
4. Si hay decisiones importantes, usar Plan y consultar al usuario antes de alterar reglas.

**Al terminar un trabajo:**

1. Actualizar **solo el estado y evidencia de las tareas afectadas** en `ROADMAP.md` y su fecha de revisión.
2. Añadir enlace a PR/commit, evidencia de tests y límites; no marcar `VALIDADO` sin revisión e integración comprobadas, ni `OPERATIVO` sin piloto real.
3. Mantener la definición funcional y documentos de arquitectura únicamente cuando cambie su contrato; evitar copiar aquí especificaciones completas.
4. Proponer el siguiente hito desbloqueado; no arrancarlo silenciosamente.
5. No modificar datos de producción ni ejecutar migraciones destructivas sin autorización explícita.

**Para consultar desde ChatGPT:** enlazar al `ROADMAP.md` público una vez publicado. Este archivo permite leer prioridades en una conversación nueva, pero no se actualiza por sí solo; necesita commits cuando cambie el estado.

## 7. Documentos relacionados

- `README.md`: instalación y estado general.
- `docs/definicion_funcional.MD`: contrato funcional vigente de cobranzas.
- `docs/architecture/`: diseño técnico implementado por dominio.
- `docs/audits/collections-stabilization.md`: informe de estabilización integrado.
- `docs/platform/README.md`: visión y arquitectura modular propuesta.
- `docs/platform/07-delivery-roadmap.md`: dependencias técnicas detalladas de la evolución modular.
- `docs/platform/10-new-company-discovery.md`: preguntas y clasificación fit-gap por empresa.
- `docs/platform/06-onboarding-playbook.md`: procedimiento de implementación acompañada.
- `docs/platform/09-codex-dev-workflow.md`: integración con skills `dev-*`.
- `docs/platform/templates/company-assessment.template.md`: informe privado de evaluación por empresa.

_Este documento prioriza y sigue la ejecución; no sustituye fuentes funcionales, código, contratos ni pruebas._
