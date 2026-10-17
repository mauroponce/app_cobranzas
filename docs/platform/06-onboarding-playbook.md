# 06 — Playbook de implementación presencial

## Objetivo

Que una empresa sin ERP deje de depender de hojas Excel y mensajes para el **proceso contratado**, sin forzar el reemplazo de herramientas ajenas al alcance. Entrega repetible, con responsable, documentos operativos y medición de horas.

## Fase previa obligatoria — entrevista técnica guiada

Antes de cotizar adaptaciones, usar Codex en **modo Plan** con `$dev-discovery` y [10 — Protocolo de nueva empresa](10-new-company-discovery.md). Convertir las respuestas en matriz E0–E6 y en un MVP con exclusiones. Este playbook describe **cómo implementar después** de decidir el encaje; no reemplaza el análisis de fit-gap.

## Fase 0 — Calificación y alcance

- Entrevistar dueño, persona administrativa, vendedor, cobrador/repartidor.
- Medir número de clientes, empleados, tipos de venta, volumen mensual, frecuencia de cobro, medios de pago, cantidad de planillas, número de cierres diarios y errores frecuentes.
- Dibujar `as-is`: origen del dato, aprobaciones, excepciones, dinero físico, transferencias y controles.
- Identificar necesidades fiscales/contables y terceros ya utilizados; excluirlas explícitamente cuando no estén implementadas.
- Cotizar alcance y exclusiones por escrito. No prometer funciones que el código no soporta.

## Fase 1 — Datos de origen

- Exportar y resguardar archivos fuente con consentimiento y control de acceso.
- Mapear columnas → dominio existente; identificar duplicados, saldos sin trazabilidad, clientes sin documento y cobradores ambiguos.
- Preparar staging con estado `uploaded`, `validated`, `ready`, `committed`, `failed` y reporte de errores **antes de escribir producción**.
- Conciliar totales por cliente, cartera, vencidos, medios de pago y saldos entre origen y destino.
- Usar `external_source` + `external_id` + `import_batch` (o equivalente) para deduplicar reimportaciones; no generar pagos ficticios para cuadrar cuentas.

## Fase 2 — Configuración

- Registrar módulos contratados/habilitados y responsables autorizados.
- Configurar permisos, cartera, política de aprobación, frecuencia de cuotas y modo de conciliación permitido.
- Probar 2 o 3 casos reales en datos de prueba, con más de una venta por cliente, mora y cobro parcial.
- Registrar excepciones de negocio que no encajan en el estándar: decidir configuración, extensión reutilizable o presupuesto exclusivo.

## Fase 3 — Piloto asistido

- Capacitar a administrativos y operadores de campo con un flujo de trabajo por rol/capacidad.
- Ensayar conexión inestable, cobro rechazado, empleado ausente, reasignación y cierre con diferencia.
- Ejecutar jornada piloto con una muestra acotada; supervisar que no existan pagos duplicados ni deuda incorrecta.
- Mantener registro de incidencias, resolución, responsable y fecha; no hacer correcciones directas a DB sin evidencia.

## Fase 4 — Aceptación

- Verificar **cierre completo** del proceso contratado, no solo login o formularios.
- Conciliar saldos iniciales/actuales y rendiciones reales; contrastar con fuente documentada.
- Confirmar que usuarios pueden operar sin asistencia permanente.
- Registrar acta de aceptación y alcance residual; transferir guías breves, credenciales mediante canal seguro y contactos de soporte.

## Fase 5 — Retención y expansión

- Medir semanas 1, 2 y 4: adopción, dudas, excepciones, tiempos, diferencias y horas de soporte.
- Priorizar mejoras que se repitan entre clientes; rechazar cambios que rompan invariantes comunes.
- Ofrecer entregas/stock/integraciones solo si el núcleo contratado ya es estable.

## Entregables por cliente

- Ficha de empresa y mapa de procesos **sin secretos**.
- Matriz módulos/capacidades/políticas y versión.
- Archivo de validación de migración (origen/destino, totales y errores).
- Casos de prueba firmados, incidencias y acta de aceptación.
- Registro de horas de proyecto y backlog con estimaciones de extras.

Los datos y notas **privadas del cliente** van fuera del repositorio público y sujetos a permisos; el repositorio conserva únicamente **plantillas genéricas**.
