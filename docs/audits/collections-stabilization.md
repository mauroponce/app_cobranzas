# Auditoría correctiva y estabilización de cobranzas

Fecha: 17 de octubre de 2026.

## Problemas comprobados y causas raíz

- La sincronización aceptaba `cash_payment` y llamaba a `registerCash()`, que
  confirmaba e imputaba efectivo antes de una rendición. La causa era la
  convivencia del flujo móvil heredado con el nuevo flujo de declaraciones.
- `approveDeclaration()` confirmaba todos los componentes, incluido efectivo,
  sin exigir una hoja ni recepción física. La declaración no tenía estado de
  conciliación por componente.
- Las rendiciones se armaban con pagos ya confirmados y no tenían `route_id`.
  Por eso no podían representar una rendición independiente por hoja ni ser el
  único origen de confirmación del efectivo.
- `sync/pull` sumaba obligaciones por cliente para cada parada. Con varias
  ventas activas, la visita mostraba un saldo ajeno a la venta programada.
- La pantalla móvil mostraba centavos como pesos y las pantallas de resultado y
  rendición seguían desconectadas o con datos vacíos.
- No existían una transición de presentación/cierre de hoja ni historial de
  reasignación de visitas.
- CI móvil verificaba tipos, lint y formato, pero no ejecutaba Jest. Las pruebas
  de autenticación web esperaban una respuesta 200 en una ruta que ahora
  redirige al panel de cobranzas. La prueba concurrente de invitaciones ya pasa
  con el bloqueo actual de empresa e invitación y no requirió relajar controles.

## Decisiones técnicas

- Los pagos administrativos directos se conservan separados. Un cobrador no
  puede usar ese servicio ni su endpoint para registrar efectivo o
  transferencias por fuera de una visita.
- Cada componente de declaración conserva estado, importe confirmado, pago,
  actor y fecha de conciliación. Una transferencia puede confirmarse sin tocar
  el efectivo de una declaración combinada.
- La rendición pertenece de forma única a una hoja. Se crea desde sus
  componentes de efectivo pendientes y se presenta solo con visitas resueltas
  y confirmación de cola local vacía.
- Una diferencia exige atribuciones administrativas explícitas. No se reparte
  dinero automáticamente. Se crean diferencia e incidencia, y solo el importe
  atribuido genera pagos.
- Los pagos originados en declaraciones se imputan únicamente a obligaciones
  de la venta visitada. Un excedente queda sin imputar, no crea crédito
  automático y genera una incidencia.
- Operaciones financieras offline incompatibles se conservan como
  `pending_review` e incidencia. El UUID y hash canónico mantienen idempotencia;
  reutilizar el UUID con otro contenido se rechaza.
- La reasignación cierra la visita original como `reassigned`, crea una visita
  nueva en la hoja del destino e incrementa la versión de autorización.

## Cambios de esquema

La migración `2026_10_17_120000_stabilize_collection_settlements.php` agrega:

- hash de contenido a declaraciones;
- estado, importe confirmado, pago y auditoría a componentes;
- relación única entre rendición y hoja;
- relación de ítems de rendición con componentes y efectivo recibido;
- importe recibido y actor en diferencias;
- historial `collection_visit_reassignments`;
- estado `submitted` de hoja y restricciones PostgreSQL asociadas.

La migración es incremental y no borra datos históricos. Los ítems históricos
pueden conservar su `payment_id`; los nuevos ítems del flujo de hojas usan
`declaration_component_id`.

## Archivos y áreas modificadas

- Dominio: `PaymentService`, `CashSettlementService`,
  `CollectionWorkflowService`, `CollectionRouteService` y modelos relacionados.
- API: controladores de sincronización, hojas, declaraciones, pagos y
  rendiciones; rutas y contrato compartido.
- Móvil: SQLite, outbox, worker, saldos, detalle de visita, resultado, cobro
  combinado, rutas y rendición.
- Pruebas: cobranzas, carteras, sincronización, autenticación y nueva suite de
  estabilización financiera.
- CI y documentación: workflow móvil, README y documentos funcionales y de
  arquitectura.

## Pruebas agregadas y verificaciones

La suite `CollectionsStabilizationTest` cubre declaraciones combinadas y
múltiples, conciliación separada, diferencias con atribución, doble aprobación,
condiciones de presentación, excedente sin crédito automático, reasignación
offline, reintento idempotente, reversión con historial, bloqueo del canal
administrativo directo, saldos de dos ventas, dispositivo revocado y cruce
entre empresas. También se actualizaron las pruebas del flujo heredado para
demostrar que no crea pagos ni imputaciones.

Resultados finales sobre PostgreSQL de pruebas (`app_cobranzas_test`):

- `make test`: 104 pruebas y 523 aserciones aprobadas;
- `./vendor/bin/pint --test`: 189 archivos aprobados;
- `make assets`: compilación de producción aprobada;
- móvil: typecheck, ESLint sin advertencias y Prettier aprobados;
- Jest móvil: 3 suites y 16 pruebas aprobadas.

## Riesgos residuales y preparación productiva

- La API administrativa permite conciliación y reasignación, pero el panel
  Livewire todavía no ofrece formularios completos para atribuir diferencias o
  reasignar una visita. Es necesario completar esa interfaz antes de un piloto
  que no opere mediante API.
- La confirmación de cola vacía reduce el riesgo en el dispositivo que presenta
  la hoja, pero el servidor no puede conocer operaciones retenidas en otro
  dispositivo inaccesible. Las incidencias posteriores son la salvaguarda.
- Los datos históricos del flujo `cash_payment` se preservan. No se realizó un
  backfill que los vincule retroactivamente con hojas porque esa atribución no
  puede inferirse con seguridad.
- Antes de producción deben aplicarse la migración incremental, verificar los
  tiempos de bloqueo sobre una copia representativa y ensayar el procedimiento
  operativo de diferencias e incidencias.
