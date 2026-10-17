# 10 — Protocolo de descubrimiento y evaluación de una nueva empresa (Codex en modo Plan)

**Tipo:** procedimiento permanente de **preventa técnica + descubrimiento funcional** para incorporar un tenant nuevo. **Estado:** política propuesta; no implica que los módulos futuros estén construidos.

## Propósito y resultado

Cuando el equipo comercial trae una empresa nueva, Codex debe actuar como **analista funcional, arquitecto de producto modular y evaluador de encaje**. Su primera tarea **no es desarrollar** sino **averiguar qué debe operar el cliente, contrastarlo con el código vigente y presentar el menor cambio seguro**. La pregunta de decisión es: **«¿podemos implantar este proceso sobre la plataforma compartida sin perjudicar a otros tenants ni mantener una versión exclusiva?»**

**Interlocutor:** el usuario/equipo de digitalización, no el cliente final. Preguntar al equipo lo que sabe; cuando algo deba averiguarse con la empresa, ofrecer una pregunta literal para transmitirle y marcar el dato como *pendiente de validación con cliente*. No inventar políticas comerciales, financieras, fiscales o de autorización. Una respuesta «no sé» es legítima.

**Inicio de cada oportunidad:** nueva conversación de Codex en **modo Plan**, sin editar el repositorio. Invocar `$dev-discovery` instalado desde `mauroponce/ai-skills`; apoyarse en `$dev-explore` solo si hace falta mapear una zona desconocida. Después de obtener requisitos suficientes, usar `$dev-plan` para el diseño de cambios **sin implementación**. `$dev-implement` corresponde a una autorización posterior, en otra fase y rama.

### Primera pregunta literal

> «Contame brevemente qué hace esta empresa, quiénes trabajan fuera de la oficina y cuáles son los **uno o dos procesos** que más les urge dejar de manejar con WhatsApp, Excel o papel. Si todavía no sabés algún dato, decime cuál hay que consultar al cliente.»

Hacer **solo esa pregunta al comienzo** si el mensaje del usuario no contiene ya esa información. Después continuar con **una pregunta concreta por turno como opción predeterminada**; se pueden agrupar hasta 2–3 preguntas independientes si reduce idas y vueltas. Nunca enviar toda la batería de preguntas de este documento como un formulario gigante.

## 0. Preparación obligatoria — no consultar lo que ya está documentado

1. Inspeccionar rama, commit, estado de las correcciones financieras y documentación `AGENTS.md` actual. No asumir que el estado de `main` en octubre de 2026 sigue vigente. Respetar los límites de trabajo de otras ramas y agentes.
2. Leer `docs/platform/README.md`, `02-modules-and-boundaries.md`, `03-tenant-configuration.md`, `04-workflow-scenarios.md`, `05-data-integrity.md`, `06-onboarding-playbook.md` y este documento. Para cobranzas actuales leer `docs/definicion_funcional.MD`.
3. Leer los puntos relevantes en código y tests, sobre todo `features`, `tenant_features`, `permissions`, `RoleMatrix`, `AuthorizationService`, modelos, controladores y sync móvil. Diferenciar **implementado y probado**, **implementado parcialmente**, **solo diseñado**, **ausente** y **dependiente de servicios externos**.
4. Construir una línea de base de capacidades **sin afirmar que una pantalla mockup, tabla o servicio aislado cumple un flujo extremo a extremo**.
5. Recuperar respuestas ya aportadas en esta conversación o evidencia existente; **no volver a preguntarlas**. Clasificar cada dato como confirmado por cliente, comunicado por el equipo, inferencia pendiente o desconocido.

## 1. Método de entrevista adaptable

En cada turno aplicar este ciclo:

**(a)** Resumir en una frase la decisión o respuesta nueva (sin repetir todo el contexto). **(b)** Identificar el mayor vacío que cambie alcance, dinero, permisos, operación de campo, integraciones o viabilidad. **(c)** Hacer la siguiente pregunta relevante — concreta y con ejemplos, sin dar una clase técnica. **(d)** Cuando la respuesta revele una variante, aplicar la rama correspondiente del banco de preguntas. **(e)** Registrar en el análisis una ficha de requisito: `ID`, necesidad observada, flujo real, actor, disparador, resultado, excepciones, volumen/frecuencia, fuente de información, importancia (MVP/luego), nivel de certeza, módulo afectado, impacto de adaptación y pregunta abierta.

**Regla de prioridades:** primero objetivo y circuitos críticos; luego cobros/custodia y separación de funciones; después roles, ventas/entregas, datos, integraciones y puesta en marcha. Saltar categorías irrelevantes. El banco es una **guía para seleccionar**, no una lista que se deba agotar.

**Ejemplos antes que preferencias abstractas:** ante «necesitamos reglas especiales», preguntar «contame la última operación real, desde que nace hasta que se cierra, y qué pasó cuando salió mal». Pedir escenarios representativos, no los nombres de tablas o clases.

### Bloque A — Identidad, contexto y dolor (siempre investigar)

- **A01.** «¿Qué vende o distribuye la empresa y a quién: consumidores, comercios, revendedores u otras empresas?»
- **A02.** «¿Qué operación quieren digitalizar primero y qué error, demora o pérdida concreta produce hoy?»
- **A03.** «¿Cuántos administrativos, vendedores, cobradores y repartidores trabajan, y cuántas operaciones hacen por día o semana aproximadamente?»
- **A04.** «¿En qué herramientas viven hoy los datos: qué planillas, grupos de WhatsApp, sistema contable, aplicaciones bancarias o papeles?»
- **A05.** «¿Quién decide comprar el servicio y quién va a administrar y validar la puesta en marcha?»

**Criterio de salida:** identificar comprador, responsable operativo, flujo prioritario y volumen aproximado; si no existe un problema prioritario concreto, no presupuestar desarrollo.

### Bloque B — Captación, clientes y autorizaciones (si aplican)

- **B01.** «¿Quién incorpora un cliente al sistema: vendedor, administración o ambos? ¿Puede operar antes de que lo aprueben?»
- **B02.** «¿Qué verifica Administración para aprobarlo y quién puede rechazar, bloquear o reactivar clientes?»
- **B03.** «¿Los clientes se asignan a un vendedor, a un cobrador, a un equipo o a varias personas? ¿Se pueden reasignar? ¿Con qué historial?»
- **B04.** «¿Una misma persona puede vender, entregar y cobrar? ¿Qué cosas no debería poder aprobar por sí misma?»
- **B05.** «¿Hay varias sucursales, depósitos, zonas, marcas o equipos con reglas de visibilidad diferentes?»

**Disparador:** si los puestos se combinan, modelar capacidades y permisos por operación y recurso; **no** crear un rol específico nuevo o `if (tenant_id === …)` sin justificación.

### Bloque C — Venta, pedido, financiación y mercadería (si aplican)

- **C01.** «¿El proceso empieza en presupuesto, pedido, venta directa o visita del vendedor? Contame cómo termina en una obligación concreta.»
- **C02.** «¿Quién puede aplicar descuentos, cambiar precios, aprobar crédito o confirmar la venta?»
- **C03.** «¿Venden al contado, a cuenta corriente, en cuotas o con una combinación? ¿Puede un cliente tener varias ventas abiertas?»
- **C04.** «¿Cuándo nace la deuda: con el pedido, la venta aprobada, la factura o la entrega? ¿Cómo tratan anticipos y pagos parciales?»
- **C05.** «¿Cómo manejan vencimientos, mora, refinanciación, devoluciones y cancelaciones? ¿Cuál es un ejemplo real?»
- **C06.** «¿Llevan stock, preparan pedidos, emiten remitos o facturas? ¿Qué sistema es hoy la fuente de verdad para cada cosa?»

**Disparador:** si no hay financiación, no imponer cuota ficticia. Si ventas depende de stock externo, no prometer un ERP de stock sin relevar la integración o el módulo requerido.

### Bloque D — Trabajo en calle: cobranza, reparto y visitas (si aplican)

- **D01.** «¿Quién arma los recorridos y qué se asigna: cliente, venta, factura, pedido o entrega?»
- **D02.** «¿Necesitan una visita presencial para registrar un cobro o también cobran sin ir al domicilio?»
- **D03.** «¿Qué registra el empleado al visitar: cobro, motivo de no pago, promesa, entrega, foto, firma, ubicación o una combinación?»
- **D04.** «¿Un repartidor puede entregar mercadería y cobrar en el mismo acto? ¿Puede entregar sin cobrar o cobrar sin entregar?»
- **D05.** «¿Qué pasa con entregas parciales, rechazos, mercadería faltante, devoluciones y reintentos?»
- **D06.** «¿Trabajan con teléfonos de la empresa o personales? ¿En qué lugares se quedan sin Internet y qué operaciones tienen que poder hacer offline?»
- **D07.** «¿Quién reasigna un recorrido cuando alguien falta y qué ocurre si el empleado anterior sincroniza una operación después?»

**Disparador:** reparto requiere dominio de entregas; cobrar durante la entrega debe reutilizar pagos/custodia compartidos; no tratar entrega como una visita de cobranza renombrada.

### Bloque E — Dinero, medios de pago, custodia y conciliación (siempre si se registran cobros)

No permitir que una política de empresa relativice idempotencia, permisos, exactitud monetaria, custodia ni la obligación de verificar una transferencia antes de tratarla como confirmada.

- **E01.** «¿Quién recibe efectivamente el dinero en cada caso: cobrador, repartidor, vendedor, caja o cuenta bancaria?»
- **E02.** «¿Cómo pagan los clientes: efectivo, transferencia, Mercado Pago, QR, link o combinaciones? ¿Puede un cobro dividirse entre medios?»
- **E03.** «¿Quién registra que el cliente dice haber pagado, quién verifica que el dinero ingresó y quién imputa ese pago a la deuda?»
- **E04.** «Si un empleado recibe efectivo, ¿cuándo y a quién se lo entrega? ¿Se rinde por hoja, por persona, por turno, por caja o por sucursal?»
- **E05.** «¿Cómo comparan hoy el efectivo declarado con el recibido? ¿Qué pasa cuando faltan $15.000 o aparece un pago duplicado?»
- **E06.** «¿Qué comprobantes, autorizaciones y responsabilidades necesitan para diferencias, reversas, anticipos y excedentes?»
- **E07.** «Si un pago llega por Mercado Pago o banco: ¿cuentan con una integración que confirme acreditación o hoy alguien verifica manualmente el movimiento?»
- **E08.** «¿El empleado puede cobrar una deuda que no estaba en su ruta o cartera? ¿Quién lo autoriza y cómo queda documentado?»

**Rama Mercado Pago / bancos:** indagar cuenta receptora, tipo de integración, referencia e identidad de transacción, webhooks firmados/verificación contra API, conciliación y reversas. Una captura o mensaje de WhatsApp **no es acreditación**. Identificar si el proceso actual es manual o si existe integración verificable; no asumir soporte técnico ya implementado.

**Rama pago remoto:** comprobar que el cobro no depende artificialmente de una visita/hoja física. Una declaración remota puede quedar pendiente; no implica reducción inmediata de deuda.

### Bloque F — Datos históricos y migración (siempre)

- **F01.** «¿Dónde están los datos maestros y los saldos actuales? ¿Quién puede exportarlos y validarlos?»
- **F02.** «¿Qué hojas Excel o archivos tienen: clientes, productos, saldos, cuotas, pedidos, pagos, repartos?»
- **F03.** «¿Qué inconsistencias conocen: documentos repetidos, deudas sin venta, cobros no conciliados o clientes duplicados?»
- **F04.** «¿Con qué fecha de corte y quién firma que los totales importados coinciden con los saldos de origen?»
- **F05.** «¿Necesitan importar todo el historial o comenzar con saldos de apertura claramente documentados?»

**Seguridad:** solicitar primero un **ejemplo anonimizado** de estructura de planilla (encabezados, tipos, casos sintéticos), no datos personales reales, contraseñas ni tokens. La documentación específica del cliente debe guardarse fuera del repositorio público.

### Bloque G — Terceros, regulaciones y límites del servicio (solo si corresponde)

- **G01.** «¿Usan facturación/contabilidad/ERP o plataformas de pagos que tenemos que mantener como fuente de verdad?»
- **G02.** «¿Qué intercambios necesitan con WhatsApp, bancos, Mercado Pago, ARCA, e-commerce, hardware o servicios externos?»
- **G03.** «¿Qué archivos o documentos deben generar y conservar? ¿Hay una exigencia fiscal, contractual o de privacidad?»
- **G04.** «¿Quién es dueño de las cuentas y credenciales de proveedores y quién puede autorizar la conexión?»

**Disparador:** cualquier obligación legal, fiscal o contractual no cubierta pasa a validación especializada. No presentar una integración futura como incluida en el servicio estándar.

### Bloque H — Implementación, aceptación, presupuesto (siempre antes de cotizar)

- **H01.** «¿Cuál sería el primer proceso que debería estar funcionando para que consideren exitosa la implementación?»
- **H02.** «¿Cuántos usuarios y dispositivos entran al piloto, quién los capacita y quién resuelve dudas internamente?»
- **H03.** «¿Cuándo necesitan empezar, y existe una fecha comercial que no se puede mover?»
- **H04.** «¿Qué presupuesto o rango están dispuestos a considerar para instalación, adaptaciones y abono?»
- **H05.** «¿Qué pueden posponer para una segunda etapa sin que el primer proceso pierda valor?»
- **H06.** «¿Quién valida el resultado y cuáles serían dos o tres operaciones reales de aceptación, incluyendo una excepción?»

## 2. Clasificar cada diferencia: elegir la opción menos costosa y más reutilizable

Codex debe proponer **una** categoría primaria por requisito, respaldada por archivos/código actuales y su prueba de aceptación:

| Código | Clasificación | Criterio | Tratamiento |
|---|---|---|---|
| **E0** | Ya soportado | Existe código, contrato, permisos y pruebas suficientes para el escenario | Demostración y onboarding, sin desarrollo |
| **E1** | Configuración | Política tipada, módulo o permiso existente soporta el comportamiento | Datos de tenant; auditoría; sin editar código |
| **E2** | Composición | Capacidad lograble combinando módulos/roles/permisos existentes, sin duplicar dominio | Configurar dependencias, probar flujo integral |
| **E3** | Extensión reutilizable | Necesidad válida del rubro, no soportada, encaja con un límite modular | Implementación genérica, apagada por defecto donde aplique, tests de regresión |
| **E4** | Adaptador reutilizable | Integración particular mediante contrato estable (proveedor bancario, ERP, etc.) | Adaptador aislado, secretos seguros, costos separados; no fork del core |
| **E5** | Exclusivo/no incorporable al core | Necesidad verdaderamente irrepetible o contraria al modelo estándar | Servicio externo o propuesta separada con costo de mantenimiento; si rompe invariantes, **rechazar** |
| **E6** | No requerido / fuera de alcance | No pertenece al MVP contratado | No desarrollar ni prometer |

**Regla de arquitectura:** nunca usar `if ($tenant->id === '...')`, `switch(tenant_slug)`, vistas o tablas exclusivas por cliente, código arbitrario almacenado en JSON, ni permisos otorgados a partir del título del puesto. La capacidad real siempre debe pasar por autorización del servidor y scopes sobre recursos.

**Factibilidad adicional obligatoria:** indicar si la solución requiere (1) código, (2) migración/backfill, (3) cambios de API o app instalada, (4) configuración por tenant, (5) capacitación y operación, y (6) conexión con un tercero; estimar esfuerzo en **rangos y con supuestos**, nunca como promesa de plazo.

**Decisiones diferidas:** cuando dos opciones sean plausibles (por ejemplo, facturar al pedido vs entregar), mostrar recomendación y trade-off, preguntar por el comportamiento real antes de elegir. Nunca inventar una regla para que encaje en la arquitectura.

## 3. Evaluar riesgo de escalabilidad y rentabilidad

Por cada E3/E4/E5, Codex debe responder:

1. ¿Se puede expresar como **política versionada**, **capacidad/permiso**, **módulo** o **adaptador** sin bifurcar el core?
2. ¿Qué otro tipo de empresa podría beneficiarse? Si no se conoce, **marcar hipótesis**.
3. ¿Qué cambios tocarían el núcleo de pagos, custodia, autorización, clientes o sincronización? ¿Cómo se mantienen sus invariantes?
4. ¿Qué comportamiento permanece igual para el tenant actual? ¿Cómo lo garantizan tests tenant A vs tenant B?
5. ¿Qué costo permanente sumaría en soporte, pruebas, mantenimiento y actualizaciones móviles?
6. ¿Se puede entregar inicialmente mediante un procedimiento manual controlado sin falsear saldos ni historial?
7. ¿Requiere datos o permisos sensibles, aprobación legal, servicios de terceros o dependencia del cliente?

**Semáforo de encaje comercial (sin puntuaciones inventadas):**

- **Encaja con la plataforma actual:** solo E0–E2 y esfuerzo de implementación acotado.
- **Encaja con extensión reutilizable:** E3/E4 con alcance, patrocinador y costos validados.
- **Condicionado:** faltan decisiones críticas, evidencia o presupuesto; no cotizar cerrado.
- **No recomendable:** exige alterar garantías financieras, crear un fork por cliente, o soporte/desarrollo exclusivo que vuelva inviable la operación.

No confundir «comercialmente atractivo» con «software listo»; no usar la etiqueta «listo» cuando falte pruebas piloto o validación de datos.

## 4. Entregable en Plan antes de implementar

Al tener respuestas suficientes, mostrar en chat y, **solo si sirve para un handoff autorizado**, en un workspace personal fuera del repo público:

1. **Resumen ejecutivo del cliente:** actividad, empleados, dolores y MVP propuesto; fuente y nivel de certeza.
2. **AS-IS → TO-BE:** paso a paso del proceso actual y del objetivo; actores, entradas, estados, excepciones, dinero y datos.
3. **Matriz fit-gap:** un ID por requisito, prioridad, evidencia, archivo/módulo existente, categoría E0–E6, nueva política o extensión, esfuerzo relativo, dependencia y pruebas.
4. **Mapa de tenant:** módulos disponibles/activos, combinaciones de capacidades y scopes, responsables, políticas tipadas, versiones y dependencias.
5. **Decisiones y preguntas pendientes:** separar lo que respondió el usuario de lo que debe confirmar el cliente. **Si afecta deuda, aprobación, fiscalidad, privacidad, dispositivo offline o custodia, no cerrar la propuesta sin respuesta.**
6. **MVP y fases posteriores:** explícitas exclusiones, módulos futuros y criterios de aceptación por rol; no diseñar todo para el primer día.
7. **Impacto técnico:** migraciones, API, sync/offline, interoperabilidad, datos existentes, seguridad y plan de regresión en al menos dos tenants.
8. **Viabilidad comercial:** rango de complejidad/horas sujeto a validación, tareas de onboarding, terceros, riesgos de soporte y modalidad de cobro (implementación, recurrente, desarrollos exclusivos).
9. **Decisión:** encaja / extensión / condicionado / no recomendable, con evidencias y siguiente paso.

Utilizar la plantilla `docs/platform/templates/company-assessment.template.md` como **estructura de respuesta**, no crear una ficha con datos sensibles dentro de un repositorio público.

### Puerta de salida hacia `$dev-plan`

Solo pasar a plan técnico cuando el MVP, flujos críticos, permisos, quién custodia/conciliará dinero, origen de saldos, integraciones, alcance y criterios de aceptación estén definidos o explícitamente excluidos. La propuesta debe poder describirse en casos verificables. Si hay vacíos críticos, **hacer la siguiente pregunta**, no inventar diseño.

### Puerta de salida hacia `$dev-implement`

La entrevista y `$dev-plan` **no autorizan escritura**: implementación únicamente con instrucción explícita del usuario, rama separada, baseline de cobranzas estable, migraciones forward-only, pruebas de aislamiento y revisión independiente `$dev-review`. Ningún deploy a producción sin aprobación concreta.

## 5. Respuestas de ejemplo (sin confundirlas con compromisos de producto)

**Ejemplo 1 — Trotamundo:** «vendedor crea cliente → oficina aprueba → venta financiada → cobrador visita → declara efectivo o transferencia → rendición/conciliación». Codex compara con módulos actuales; pregunta por reglas de autorización y cierre antes de marcar listo.

**Ejemplo 2 — Repartidor que cobra:** «empleado entrega y recibe efectivo». Codex descubre si la entrega confirma obligación, qué evidencia requiere, quién recibe la plata y qué pasa con entregas fallidas; clasifica `delivery` como extensión si aún no existe, sin duplicar `PaymentService` ni permitir autofirma de rendición.

**Ejemplo 3 — Pago remoto por Mercado Pago:** Codex pregunta dónde se acredita, quién verifica, cómo llega la identificación de operación y qué ocurre con reversas. Clasifica según el código real: ingreso manual autorizado, configuración de origen o adaptador seguro futuro; no llama automáticamente «confirmado» a una captura.

## 6. Prompt corto reutilizable para Codex (modo Plan)

> Estoy evaluando incorporar una nueva empresa a nuestra plataforma de digitalización. **Modo Plan, sin modificar código ni crear archivos en el repositorio.** Invocá `$dev-discovery` y leé `docs/platform/10-new-company-discovery.md` y `docs/platform/README.md`, además de la documentación y el código vigente necesarios. Quiero que **me entrevistes progresivamente**, una pregunta por vez salvo preguntas breves independientes, para entender su operación real. No me mandes un cuestionario completo ni repitas información que ya te haya dado. Cuando falte información exclusiva del cliente, escribí la pregunta exacta que debo hacerle. Comprobá cada requisito contra la plataforma actual y clasificá E0–E6. Priorizá configuración y módulos reutilizables; nunca ramificaciones de código por tenant ni excepciones que comprometan pagos, permisos o auditoría. Al terminar, entregá una matriz fit-gap, mapa de módulos/capacidades/políticas, MVP, preguntas abiertas, riesgos, estimación orientativa y recomendación encaja/condicionado/no recomendable. Usá la plantilla `docs/platform/templates/company-assessment.template.md` como formato de informe en el chat. **No ejecutes `$dev-implement` hasta que autoricemos una entrega concreta.**

