# Rendiciones de efectivo

Cada rendición pertenece a una única hoja y agrupa sus componentes de efectivo
pendientes; nunca parte de pagos ya confirmados. El flujo es borrador,
presentada y aprobada o con diferencia. Presentarla exige todas las visitas con
resultado y una confirmación móvil de cola local vacía. Nadie puede aprobar su
propia rendición.

La aprobación bloquea hoja, rendición, ítems y componentes. Si el recibido
coincide con lo declarado, confirma todos los componentes. Ante una diferencia,
Administración debe atribuir explícitamente el recibido a componentes concretos;
la suma debe coincidir y ningún componente puede superar lo declarado. Se crean
`cash_discrepancies` y `collection_incidents`, se confirma solo lo atribuido y
la hoja queda cerrada. Una segunda aprobación se rechaza. Las transferencias se
concilian fuera de esta secuencia.
