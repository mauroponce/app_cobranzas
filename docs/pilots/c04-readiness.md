# C-04 — Preparación del piloto controlado

Fecha de evaluación: 18 de octubre de 2026.

## Veredicto actual

**NO-GO para operar con datos o dinero reales.** La preparación técnica está
implementada, sin validar, en `feat/c-04-pilot-readiness`, pero la autorización
de inicio permanece bloqueada. Este documento separa preparación,
autorización, ejecución y aceptación; no sustituye
[la definición funcional](../definicion_funcional.MD) ni evidencia un piloto
real.

## Alcance técnico preparado

- Entorno Docker reproducible en `compose.pilot.yaml`, con HTTPS, aplicación,
  PostgreSQL persistente, Redis, cola, scheduler y healthchecks.
- Secretos fuera de Git mediante `.env.pilot`; el repositorio conserva solo
  `.env.pilot.example`.
- Respaldo de PostgreSQL y documentos privados, checksums y restauración que
  solo admite una base nueva terminada en `_test`.
- Perfil EAS `pilot` para generar una APK Android de distribución interna.
- Pantalla de soporte tenant-aware para correlacionar UUID, usuario,
  dispositivo, resultado de sincronización, incidencias y auditoría. La
  revocación invalida el token ligado al dispositivo y queda auditada.
- Procedimientos y plantillas de este directorio.

## Puesta en marcha reproducible

Estos comandos son una referencia para un entorno autorizado; no se ejecutaron
contra infraestructura real durante C-04:

```bash
cp .env.pilot.example .env.pilot
# Completar secretos, dominio, R2, TLS y rutas fuera de Git.
docker compose --env-file .env.pilot -f compose.pilot.yaml config
docker compose --env-file .env.pilot -f compose.pilot.yaml build
docker compose --env-file .env.pilot -f compose.pilot.yaml up -d
docker compose --env-file .env.pilot -f compose.pilot.yaml ps
curl --fail --silent --show-error https://DOMINIO/api/v1/readiness
```

Las migraciones se ejecutan como una acción de cambio separada, luego de backup,
ensayo y autorización explícita. Un reinicio controlado usa `docker compose
restart SERVICIO`, seguido de `ps`, readiness, cola, scheduler y smoke test; no
se usa `down -v` porque eliminaría datos persistentes.

No se eligió un proveedor. Para una primera prueba controlada, un único host
con Docker Compose, disco respaldado y R2 privado conserva la arquitectura y
tiene menor carga operativa. Una base PostgreSQL administrada reduce tareas de
operación a cambio de costo y dependencia del proveedor. Kubernetes,
microservicios y una plataforma de observabilidad completa no están
justificados para este alcance. La decisión final debe comparar cotizaciones,
ubicación de datos, backups del proveedor, soporte y responsable operativo.

## Datos operativos pendientes

Estos valores deben quedar en documentación privada, fuera del repositorio:

| Decisión | Estado |
| --- | --- |
| Cantidad y nombres de cobradores | PENDIENTE |
| Administrativos y supervisor responsable | PENDIENTE |
| Cartera/clientes incluidos | PENDIENTE |
| Cantidad y fechas de jornadas | PENDIENTE |
| Dispositivos Android autorizados | PENDIENTE |
| Procedimiento actual de rendición | PENDIENTE |
| Responsable de verificar saldos | PENDIENTE |
| Responsable de autorizar inicio y cierre | PENDIENTE |
| Sistema o registros de contraste | PENDIENTE |
| Fecha de corte y fuente oficial durante el piloto | PENDIENTE |
| RPO y RTO aceptables | PENDIENTE |

## Puerta GO / NO-GO

| Requisito | Estado | Evidencia o acción pendiente |
| --- | --- | --- |
| C-03 ejecutado | PARCIAL | Integrado en `7bf0209`; recorrido automatizado y restauración sintética pasaron, pero sigue `IMPLEMENTADO / SIN VALIDAR`. |
| C-01/C-02 sin defectos bloqueantes | PENDIENTE | Falta revisión independiente de cambios financieros. |
| Revisión independiente | PENDIENTE | Debe ejecutarse en otra sesión con `$dev-review`. |
| CI backend y móvil | NO-GO | Móvil #14 pasó; Backend #18 falló en `php artisan test`. La rama C-04 debe obtener sus propios resultados. |
| Migraciones ensayadas | PARCIAL | C-03 usó datos sintéticos; falta copia anonimizada representativa. |
| Backup y restauración | PARCIAL | Herramientas implementadas; falta ensayo final del conjunto PostgreSQL + documentos en la rama C-04 y aprobación de RPO/RTO. |
| Validación móvil física | PENDIENTE | APK de C-03 se instaló en AVD, sin recorrido visual completo; falta dispositivo autorizado. |
| Usuarios, dispositivos y responsables | PENDIENTE | Completar fuera de Git. |
| Saldos de apertura conciliados | PENDIENTE | Definir fuente, fecha de corte, totales y firma de Administración. |
| Soporte y suspensión | PREPARADO / SIN VALIDAR | Ver [playbook de soporte](support-playbook.md) y [runbook](pilot-runbook.md). |
| Alcance y aceptación aprobados | PENDIENTE | Completar plantillas y obtener firma. |
| Autorización humana explícita | PENDIENTE | Obligatoria para desplegar, migrar o registrar dinero real. |

Mientras exista un requisito `PENDIENTE`, `PARCIAL` o `NO-GO`, no se distribuye
la aplicación a empleados ni se habilita operación real.

## Evidencia de verificación de esta rama

| Verificación | Resultado |
| --- | --- |
| Backend completo | PASS — `make test`: 123 pruebas, 696 aserciones |
| Estilo backend | PASS — Pint: 198 archivos |
| Assets backend | PASS — bundle de producción generado |
| Móvil | PASS — TypeScript, ESLint, Prettier y Jest: 3 suites, 16 pruebas |
| Configuración y scripts | PASS — Compose renderizado, `sh -n` y `git diff --check` |
| Backup/restauración integral de C-04 | BLOCKED / NOT RUN — la imagen PHP del entorno aislado no terminó de construirse; no se declara restore aprobado |
| Build e instalación Android | NOT RUN — requiere cuenta EAS, configuración aprobada y dispositivo autorizado |
| CI de la rama | NOT RUN — no existe push/PR de esta rama |
| Revisión independiente | PENDIENTE |

La restauración DB sintética comprobada en C-03 no demuestra el nuevo circuito
completo de PostgreSQL más documentos. No se ejecutaron migraciones de
producción, despliegues, accesos a datos reales, integraciones productivas ni
distribuciones a empleados.

## Riesgos abiertos

1. El fallo repetido de CI backend no tiene diagnóstico público; el nuevo
   workflow conserva JUnit para que una ejecución futura tenga evidencia.
2. Falta validar interacción móvil real y aprobación administrativa
   concurrente.
3. La confirmación de outbox vacía solo cubre el dispositivo que presenta la
   hoja.
4. No existe importación general. Los saldos no pueden fabricarse como ventas,
   cuotas o pagos ficticios.
5. El identificador Android actual y la cuenta Expo deben aprobarse antes de
   distribuir una APK real.

## Cambio de veredicto

El responsable del proyecto completa [la checklist](acceptance-checklist.md),
adjunta evidencias privadas y usa [la plantilla de aceptación](pilot-acceptance.template.md).
Solo una decisión humana explícita puede cambiar `NO-GO` a `GO`. `GO` autoriza
el alcance firmado, no acciones distintas ni un despliegue automático.
