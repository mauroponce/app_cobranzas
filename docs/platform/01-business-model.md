# 01 — Modelo comercial: digitalización gestionada con plataforma propia

## Posicionamiento

**Qué vendemos:** diagnóstico de los procesos actuales, normalización de datos, puesta en marcha de software propio, capacitación del personal de campo y de oficina, acompañamiento hasta estabilizar la operación y mantenimiento recurrente.

**Qué NO prometemos:** automatizar cualquier empresa en días, hacer software exclusivo ilimitado por el abono ni sustituir de inmediato un ERP fiscal/contable completo.

**Propuesta breve:** «Organizamos cómo vende, entrega y cobra tu empresa. Migramos tus planillas, configuramos las reglas de trabajo y capacitamos al equipo para que los datos y el dinero se puedan controlar desde una misma plataforma».

## Cliente ideal inicial (hipótesis)

Empresa comercial argentina con dueño involucrado en la operación, vendedores y/o cobradores/repartidores propios, administración manual con WhatsApp/Excel/papel, pérdidas de tiempo o diferencias repetidas por falta de trazabilidad, y voluntad de profesionalizar sus procesos. No buscamos inicialmente una empresa ya satisfecha con su ERP.

**Segmentos para validar en orden:** (1) mayorista o distribuidora que financia mercadería a revendedores; (2) distribuidora con venta a cuenta corriente y cobranzas en reparto; (3) empresas de preventa con cobranza en calle. Evitar elegir segmento solo por cantidad de empleados; observar volumen de operaciones, dolor económico y presupuesto.

## Metodología comercial

1. **Prospección directa:** referidos, redes de comerciantes, visitas y conversaciones con dueños/administrativos. No asumir que el cliente busca «SaaS».
2. **Descubrimiento:** mapear quién captura pedidos, da crédito, entrega, cobra, recibe efectivo, verifica transferencias y modifica saldos; recolectar ejemplos anonimizados de planillas.
3. **Diagnóstico pago o acotado:** elaborar mapa as-is/to-be y una estimación del esfuerzo, incluyendo calidad de datos.
4. **Demostración específica:** mostrar uno o dos recorridos reales usando datos ficticios; no demo de veinte módulos.
5. **Propuesta:** separar implementación, suscripción, soporte, integraciones y desarrollos adicionales; delimitar criterios de aceptación.
6. **Piloto:** empezar por un proceso crítico y medir si logra operar sin doble carga permanente en WhatsApp/Excel.
7. **Expansión:** añadir módulos por nueva necesidad validada; mantener el código común.

## Unidad económica a medir

- Horas de prospección, relevamiento, migración, configuración y capacitación por empresa.
- Soporte mensual real; tickets por usuario activo y tasa de solución sin cambios de código.
- Porcentaje del código y procesos reutilizados sin modificación por nueva empresa.
- Tiempo de implementación, tasa de adopción, errores de conciliación, satisfacción y renovaciones.

**Hipótesis inicial de monetización, no precio validado:** implementación y migración presupuestadas por alcance, abono mensual por empresa con bandas de empleados/uso, soporte incluido con límites, integraciones y extensiones especiales cotizadas por separado. Nunca regalar dos semanas de trabajo para conseguir una suscripción pequeña.

## Matriz de personalización comercial

| Categoría | Ejemplo | Precio/entrega | Destino técnico |
|---|---|---|---|
| Configuración | Roles, módulos, frecuencias, aprobaciones admitidas | Implementación estándar | Settings/policies declarativas versionadas |
| Extensión reutilizable | Módulo de entregas o regla fiscal aplicable a varios clientes | Proyecto modular | Mismo código para todos; habilitación por tenant |
| Integración | Pasarela de pago o software contable particular | Alcance y soporte pactados | Adaptador externo con credenciales aisladas |
| Exclusiva excepcional | Proceso legal/operativo no generalizable | Aprobación comercial y precio aparte | Extensión aislada solo si justificada; evitar fork |

## Evidencia necesaria antes de ampliar el alcance

- 12 entrevistas con empresas que aún operan manualmente; mostrar el cierre diario y el costo actual del problema.
- 3 propuestas con diagnóstico y precio explícito; al menos 2 pilotos pagos para decidir inversión adicional.
- Medir adaptación de la segunda implementación: si exige cambios sustanciales en core, revisar nicho o diseño antes de vender un tercero.
