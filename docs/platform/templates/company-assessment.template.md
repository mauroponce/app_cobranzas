# Plantilla — Evaluación de encaje de una nueva empresa

> **Plantilla genérica**, no completarla con datos personales ni secretos en Git. El informe por cliente debe guardarse en el chat o ubicación privada autorizada. Este archivo es un esquema de entregable; la entrevista se conduce por `10-new-company-discovery.md`.

## Contexto y confianza

- Empresa / seudónimo:
- Fecha y fuente (usuario, cliente, archivo anonimizado, verificación de código):
- Responsable de decisión / responsable operativo:
- Sector y operación diaria:
- Problema principal y costo observado (no asumir):
- Estimación de usuarios/operaciones y supuestos:
- Base de código revisada: repo, rama, commit y alcance de pruebas disponibles:
- **Estado del informe:** preliminar | requiere validaciones | listo para plan técnico.

## 1. Flujos actuales (AS-IS)

| Flujo | Disparador | Actor | Herramienta actual | Datos que modifica | Manejo del dinero | Excepciones | Confirmación/certeza |
|---|---|---|---|---|---|---|---|
| F-01 | — | — | — | — | — | — | — |

## 2. Flujos propuestos (TO-BE)

| Flujo | Resultado de negocio | Actores/capacidades | Módulos | Aprobación/verificación | Datos y evidencia | Estados/offline | Criterio de aceptación |
|---|---|---|---|---|---|---|---|
| F-01 | — | — | — | — | — | — | — |

## 3. Matriz FIT/GAP por requisito

**Categorías:** E0 ya soportado · E1 configuración · E2 composición · E3 extensión reutilizable · E4 adaptador reutilizable · E5 exclusivo/no incorporable al core · E6 no requerido/fuera de MVP.

| ID | Requisito observado | Fuente y confianza | MVP / luego | Código/evidencia | E0–E6 | Solución menor viable | Impacto y riesgos | Prueba de aceptación |
|---|---|---|---|---|---|---|---|---|
| R-001 | — | — | — | — | — | — | — | — |

## 4. Configuración por tenant

| Módulo | Existe probado / parcial / diseñado / ausente | Disponible | Activo | Dependencias | Roles/responsabilidades | Scopes | Política tipada/versionada |
|---|---|---|---|---|---|---|---|
| — | — | — | — | — | — | — | — |

## 5. Dinero y controles

- Qué evento **declara** pago; qué prueba **acreditación**; qué evento **confirma/imputa**:
- Responsable de custodia de efectivo y de recepción física:
- Rendición: unidad (hoja/persona/turno/caja) y aprobador independiente:
- Pago parcial, excedentes, transferencias y reversas:
- Offline, duplicados, dispositivos revocados y operaciones conflictivas:
- Validación de separación de funciones y auditoría:

## 6. Migración, integraciones y capacitación

- Archivos origen (solo estructura y muestras sintéticas):
- Reglas de deduplicación y fecha de corte:
- Quién valida saldos y reconcilia totales:
- Plataformas externas y dueño de cada credencial:
- Piloto, responsables, capacitación y soporte esperados:

## 7. Preguntas pendientes y decisiones

| ID | Pregunta literal para usuario/cliente | Por qué importa | Responsable de confirmación | Impacto si falta | Estado |
|---|---|---|---|---|---|
| P-01 | — | — | — | — | Pendiente |

## 8. Plan incremental propuesto (aún sin autorización de implementar)

| Entrega | Resultado verificable | Cambios de código/config | Migración/API/offline | Tests A/B y backwards | Dependencias | Estimación en rango* |
|---|---|---|---|---|---|---|
| E-01 | — | — | — | — | — | — |

*Estimaciones condicionadas a datos y alcance validados; no prometer fechas ni horas firmes si faltan evidencias.*

## 9. Decisión de encaje y propuesta comercial

- **Encaje:** actual / extensión reutilizable / condicionado / no recomendable.
- Justificación y evidencia:
- MVP contratado y exclusiones:
- Configuración incluida vs desarrollo presupuestado por separado:
- Costos de implementación, soporte recurrente y dependencias externas (rangos/supuestos):
- Riesgos residuales y validación necesaria:
- Próximo paso: nueva pregunta / demo dirigida / piloto / `$dev-plan` / desistir.

## 10. Gates de aprobación

- [ ] Decisión sobre el proceso MVP y su dueño.
- [ ] Autorizaciones y separación de funciones descritas.
- [ ] Declaración, custodia, verificación, rendición y conciliación definidas o excluidas con fundamento.
- [ ] Datos de origen, fecha de corte y responsable de validación claros.
- [ ] Cambios por tenant clasificados E0–E6 con evidence de código.
- [ ] No hay forks/condicionales por tenant ni scripts arbitrarios.
- [ ] Compatibilidad de tenant actual y pruebas multitenant incluidas en plan.
- [ ] Límites y exclusiones comerciales explícitos.
- [ ] Usuario autorizó pasar a planificación técnica (sin implementar).
