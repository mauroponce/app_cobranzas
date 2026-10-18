# C-03 — Validación integral de una jornada de cobranzas

Fecha de ejecución: 17 de octubre de 2026.

## Alcance y veredicto

Esta validación ejercitó el flujo integrado desde ventas confirmadas hasta
conciliación, saldos, auditoría e incidencias. Se usaron exclusivamente datos
sintéticos y bases cuyo nombre termina en `_test`. No se ejecutaron migraciones
ni escrituras contra producción o contra la base persistente de desarrollo.

**Resultado general: IMPLEMENTADO / SIN VALIDAR.** El recorrido financiero
automatizado y la restauración de base pasaron, pero C-03 no está `VALIDADO`:
el CI backend del commit base continúa rojo, falta revisión independiente, la
interacción visual móvil quedó bloqueada por el servidor Metro local y no se
ejecutó una carrera verdaderamente simultánea entre dos administradores.

Estados usados en este documento:

- `PASS`: existe una ejecución satisfactoria identificable.
- `FAIL`: la ejecución terminó y contradijo el resultado esperado.
- `BLOCKED`: se intentó ejecutar, pero una limitación concreta lo impidió.
- `NOT RUN`: no se ejecutó y se conserva explícitamente como pendiente.

## Entorno y aislamiento

| Elemento | Evidencia |
| --- | --- |
| Rama | `feat/c-03-collection-day-validation` |
| Base | `c8e939bc2f658590d49253d78b4c8c1baeb8afcd` |
| Backend | Laravel 13 / PHP 8.4 en la imagen local del repositorio |
| Base | PostgreSQL 17 |
| Base de migración | `app_cobranzas_c03_test` |
| Base restaurada | `app_cobranzas_c03_restore_test` |
| Volúmenes | `app-cobranzas-c03_postgres_data`, `app-cobranzas-c03_redis_data`, `app-cobranzas-c03_backend_vendor` y `app-cobranzas-c03_backend_assets` |
| Servicios externos | correo `array`, cola `sync`, caché `array`, R2 sin credenciales |
| Datos | tenant, usuarios, cliente, ventas, obligaciones, hoja y cobros sintéticos |
| Configuración | `compose.validation.yaml`; no reutiliza `app-cobranzas_postgres_data` |

El CI remoto vigente para la base es [Backend #17](https://github.com/mauroponce/app_cobranzas/actions/runs/39070359160): falló en `php artisan test`. La vista pública no permite acceder al log de la prueba sin autenticación, de modo que no se atribuye una causa no demostrada. La suite local sobre PostgreSQL sí pasó antes de iniciar C-03 con 117 pruebas y 620 aserciones.

Verificación final de esta rama:

- `make test`: 118 pruebas y 668 aserciones aprobadas;
- `CollectionsEndToEndTest`: 1 prueba y 48 aserciones aprobadas;
- `./vendor/bin/pint --test`: 192 archivos aprobados;
- `make assets`: bundle de producción aprobado;
- móvil: TypeScript, ESLint y Prettier aprobados;
- Jest móvil: 3 suites y 16 pruebas aprobadas.

## Escenario principal

La prueba `CollectionsEndToEndTest` construye el escenario con servicios de
dominio, API móvil y componentes Livewire reales:

1. Crea y confirma para el mismo cliente una venta A financiada por
   `$ 300.000,00`, sin anticipo ni cargo, en tres cuotas exactas de
   `$ 100.000,00`, y una venta B de contado por `$ 60.000,00`.
2. Asigna la cartera, programa solamente la venta A, registra un dispositivo y
   descarga la ruta. La app recibe `$ 100.000,00` como exigible hoy para A y
   `$ 360.000,00` como deuda contractual total del cliente.
3. Inicia la hoja y sincroniza una declaración combinada de `$ 30.000,00` en
   efectivo más `$ 50.000,00` por transferencia.
4. Comprueba que declarar y sincronizar no cambia deuda. Administración
   verifica la transferencia desde el componente Livewire y A baja a
   `$ 250.000,00`; B permanece en `$ 60.000,00`.
5. El cobrador presenta la rendición con la cola local declarada vacía.
   Administración recibe `$ 25.000,00`, atribuye ese importe al componente de
   efectivo y documenta un faltante de `$ 5.000,00`.
6. Verifica la hoja completada, la rendición con diferencia, pagos,
   imputaciones, incidencia abierta y evento de auditoría.

### Resultado financiero exacto

| Momento | Venta A | Venta B | Total cliente |
| --- | ---: | ---: | ---: |
| Antes de declarar | $ 300.000,00 | $ 60.000,00 | $ 360.000,00 |
| Declaración sincronizada, sin conciliar | $ 300.000,00 | $ 60.000,00 | $ 360.000,00 |
| Transferencia de $ 50.000,00 verificada | $ 250.000,00 | $ 60.000,00 | $ 310.000,00 |
| Efectivo recibido de $ 25.000,00 conciliado | **$ 225.000,00** | **$ 60.000,00** | **$ 285.000,00** |

No se creó crédito automático, no se imputó a la venta B y el faltante de
`$ 5.000,00` quedó como diferencia e incidencia, no como corrección silenciosa.

## Matriz de validación

| Caso | Estado | Evidencia / límite |
| --- | --- | --- |
| Venta financiada real, tres cuotas exactas y segunda venta independiente | PASS | `CollectionsEndToEndTest` |
| Programación, inicio, pull y declaración combinada por API móvil | PASS | `CollectionsEndToEndTest` |
| Declaración pendiente no reduce deuda | PASS | saldos antes y después del primer push |
| Transferencia conciliada separadamente por Administración | PASS | acción Livewire `approve`; efectivo queda pendiente |
| Rendición por hoja, recepción parcial y atribución explícita | PASS | acción Livewire `approveSettlement` |
| Saldo final A `$ 225.000`, B `$ 60.000`, total `$ 285.000` | PASS | aserciones exactas en centavos/decimal |
| Reintento con el mismo UUID y contenido | PASS | misma declaración; una sola fila y dos componentes |
| Mismo UUID con contenido distinto | PASS | respuesta `rejected`; sin segunda declaración |
| Intento posterior sobre visita/hoja cerrada | PASS | queda `pending_review`, crea `financial_sync_conflict` y no cambia saldo |
| Dispositivo revocado | PASS | `CollectionsStabilizationTest::test_revoked_device_preserves_a_declaration_for_review_without_touching_debt` |
| Permiso o membresía revocados entre descarga y push | PASS | `MobileSyncPushIntegrationTest::test_revoked_device_and_revoked_payment_permission_keep_the_received_operation_under_review` y `test_suspended_membership_and_disabled_feature_are_forbidden_before_financial_processing` |
| Reasignación mientras el origen conserva una operación offline | PASS | `CollectionsStabilizationTest::test_reassignment_preserves_history_and_blocks_the_previous_collector` |
| Cambio de cartera conserva operaciones locales | PASS | Jest `sync worker › removes a revoked portfolio from the cache without deleting pending financial operations` |
| Bloqueo con outbox local pendiente | PASS | `CollectionsStabilizationTest::test_route_cannot_be_presented_with_pending_visits_or_a_reported_local_queue`; Jest verifica conteo pendiente tras 401/403 |
| Operación aceptada como `pending_review` deja de bloquear la cola local | PASS | Jest verifica `pendingCount(...) === 0` después de la aceptación del servidor |
| Doble aprobación administrativa secuencial | PASS | `CollectionsAdministrationTest::test_authorized_user_approves_a_settlement_without_difference_and_cannot_approve_it_twice` |
| Dos administradores aprobando en simultáneo real | NOT RUN | no existe aún un harness multiproceso que conserve dos sesiones Livewire y coordine el mismo bloqueo de fila |
| Render y acciones administrativas | PASS | pruebas Livewire verifican declaración combinada, importes, formularios, diferencia, incidencia y mensajes |
| Revisión visual administrativa en navegador | NOT RUN | no hay automatización de navegador configurada en el repositorio |
| Android API 36.1 y APK debug | PASS | AVD inició; resolución 1080×2400 y APK instalado correctamente |
| Navegación e interacción visual móvil completa | BLOCKED | Metro quedó esperando I/O del filesystem y no abrió `127.0.0.1:8082`; no se obtuvieron capturas ni se afirma validación visual |
| iOS | NOT RUN | no hay simulador iOS disponible en este equipo |

## Migraciones, backup y restauración

La prueba de infraestructura usó el compose aislado y aplicó migraciones
incrementales mediante `php artisan migrate --force`; no se ejecutó
`migrate:fresh`, rollback, wipe ni seed sobre una base existente.

- Las 22 migraciones del repositorio pasaron, incluida
  `2026_10_17_120000_stabilize_collection_settlements`.
- Se obtuvo un dump previo vacío con checksum
  `0f36ae448c173818b1c9344cf0efdc6360a4329440d7769bae5e0e2d26092c85`.
- Después de migrar se insertó un tenant sintético identificable y se obtuvo un
  dump con checksum
  `cf4c75f5ffaa4812c1e098621b6cb1f0fce32f5faa77bd878d0f1abb315df7a7`.
- El segundo dump se restauró, sin `--clean`, en una base nueva llamada
  `app_cobranzas_c03_restore_test`.
- Origen y restauración devolvieron exactamente 22 migraciones, 69 tablas y un
  tenant sintético con el UUID esperado.

Los dumps quedaron en `/tmp/app-cobranzas-c03-before.dump` y
`/tmp/app-cobranzas-c03-after.dump`; contienen solo datos sintéticos, no se
versionan y no sustituyen una política operativa de backup de staging.

### Reproducción segura

```bash
docker compose -p app-cobranzas-c03 \
  -f compose.yaml -f compose.validation.yaml up -d postgres redis app

docker compose -p app-cobranzas-c03 \
  -f compose.yaml -f compose.validation.yaml exec -T app \
  php artisan migrate --force

make test TEST_ARGS='--filter=CollectionsEndToEndTest'
```

Para repetir backup/restore, usar `pg_dump -Fc` sobre
`app_cobranzas_c03_test`, crear una base nueva terminada en `_test` y ejecutar
`pg_restore --no-owner --no-privileges` sin `--clean`. No apuntar estos comandos
a la base de desarrollo ni a producción. Al terminar se pueden detener los
contenedores sin borrar los volúmenes:

```bash
docker compose -p app-cobranzas-c03 \
  -f compose.yaml -f compose.validation.yaml stop
```

## Guion manual móvil pendiente

Este guion debe ejecutarse cuando Metro pueda iniciar o sobre un development
build conectado al mismo sandbox. No usar datos reales.

1. Preparar un tenant `_test` con un cobrador activo, el cliente sintético, las
   ventas A/B y una hoja de hoy que incluya solo A.
2. Instalar el APK, configurar `EXPO_PUBLIC_API_URL` al API aislado y entrar con
   el cobrador sintético.
3. En Inicio y Ruta, confirmar cliente, hoja, venta A, exigible de `$ 100.000`
   y total del cliente `$ 360.000`; iniciar la hoja manualmente.
4. Cortar red. Desde la visita registrar un cobro combinado de `$ 30.000`
   efectivo y `$ 50.000` transferencia con referencia sintética. Cerrar y abrir
   la app: Cobros debe conservar la operación como pendiente.
5. Intentar Rendición: debe permanecer bloqueada mientras la operación esté en
   outbox. Restablecer red, sincronizar dos veces y confirmar que no duplica.
6. Verificar en Administración la transferencia. Volver a la app y comprobar
   el estado pendiente de conciliación del efectivo.
7. Presentar la rendición solo con cola vacía. En Administración recibir
   `$ 25.000`, atribuirlo explícitamente y registrar el motivo del faltante.
8. Volver a sincronizar y revisar estados finales, importes, mensajes y que la
   hoja no admita nuevas declaraciones.
9. Repetir con dispositivo revocado, permiso retirado y visita reasignada antes
   de reconectar; la app debe conservar evidencia y explicar la restricción.
10. Inspeccionar 360×800 y 393×852, tema claro/oscuro, fuente al 130 %, teclado
    abierto, rotación, scroll, botones deshabilitados, mensajes offline y
    ausencia de cortes o solapamientos. Adjuntar capturas a una nueva ejecución
    de este informe; no cambiar este `BLOCKED` a `PASS` sin realizarlas.

## Procedimiento de soporte ante incidencias

1. Identificar tenant, usuario, dispositivo, hoja, visita y UUID de operación;
   no pedir ni guardar credenciales del usuario.
2. Confirmar si la operación sigue en outbox, fue aceptada, quedó
   `pending_review` o fue rechazada. No eliminar la cola para “destrabar”.
3. Consultar versión de autorización, membresía, asignación vigente y estado de
   la hoja. Una reasignación o revocación posterior no habilita a aplicar la
   operación original.
4. Para transferencias, verificar cada componente por separado. Para efectivo,
   recibir únicamente desde la rendición de esa hoja.
5. Ante diferencia, exigir atribuciones cuya suma coincida con lo recibido y
   registrar motivo. No repartir faltantes ni crear créditos automáticamente.
6. Si la hoja ya cerró o el UUID tiene otro contenido, conservar payload y
   evidencia en incidencia; no reabrir ni editar el resultado original.
7. Antes de cualquier reparación de datos, tomar backup verificable en un
   entorno aislado, reproducir el caso y obtener revisión independiente.

## Riesgos y salida pendiente

- El CI backend de la base sigue rojo y debe diagnosticarse con acceso al log.
- Falta ejecutar `$dev-review` independiente; este documento no la reemplaza.
- Falta la inspección móvil visual/interactiva y el escenario de aprobación
  realmente concurrente.
- La confirmación de outbox vacía solo prueba el dispositivo que presenta la
  hoja; no demuestra que otro dispositivo inaccesible no conserve operaciones.
- La migración se midió sobre datos sintéticos, no sobre una copia anonimizada
  de volumen representativo. Antes de producción debe repetirse con esa copia.

C-04 continúa bloqueado hasta cerrar estos puntos, definir aceptación operativa
y contar con un procedimiento de respaldo y soporte aprobado.
