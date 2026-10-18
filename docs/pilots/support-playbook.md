# Playbook de soporte

## Información mínima

Solicitar empresa, usuario, dispositivo, versión, hoja, visita, UUID, hora y
mensaje visible. No solicitar contraseña, token, documento completo, payload
financiero ni captura con información innecesaria.

En **Soporte del piloto** se consultan dispositivos, estados de sincronización,
incidencias y auditoría de la empresa activa. `docker compose ps`, logs acotados,
`/api/v1/readiness` y conteos de cola complementan el diagnóstico de servicios.
No corregir directamente la base.

## Escenarios

| Falla | Acción inicial | Interviene | Suspender cuando |
| --- | --- | --- | --- |
| Backend caído | readiness, contenedores y logs; preservar hora y revisión | Técnico | no recupera dentro del RTO o hay escrituras ambiguas |
| PostgreSQL caído | detener escrituras, revisar salud/espacio, preparar restore aislado | Técnico + responsable de datos | no hay réplica/restore verificado |
| Sin conectividad móvil | conservar outbox, no desinstalar, reintentar en red segura | Cobrador + soporte | no puede confirmarse la entrega antes del cierre |
| Dispositivo perdido | revocar en Soporte, suspender usuario/hoja, inventariar pendientes | Administración + seguridad | siempre hasta reconciliar |
| Cobro incorrecto | detener conciliación, identificar UUID y obligación, abrir incidencia | Administración | saldo o custodia no son explicables |
| Duplicado aparente | comparar UUID, hash/resultados, pagos e imputaciones | Administración + técnico | existe doble impacto o falta trazabilidad |
| Saldo incorrecto | comparar obligaciones y pagos con fuente oficial | Finanzas | diferencia material no resuelta |
| Diferencia de caja | contar nuevamente, atribuir explícitamente y documentar | Administración + cobrador | no se puede custodiar o firmar el efectivo |
| Rendición no cierra | revisar resultados/outbox/estado; no reabrir ni editar | Soporte + Administración | debe terminar la jornada sin trazabilidad |
| Operación tardía | conservar `pending_review`, revisar hoja y autorización original | Administración | podría afectar un cierre ya conciliado |

## Escalamiento

Severidad 1: riesgo de dinero, duplicación, acceso cruzado, pérdida de datos o
custodia. Suspender el alcance afectado y avisar a responsables del proyecto y
Administración. Severidad 2: flujo bloqueado sin impacto confirmado; conservar
evidencia y alternativa controlada. Severidad 3: consulta o problema visual;
registrar para la siguiente ventana.

La resolución debe indicar causa comprobada, operaciones afectadas, decisión,
evidencia, responsable y verificación. Un mensaje de un agente o que el código
esté escrito no constituye verificación.
