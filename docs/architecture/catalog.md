# Catálogo, precios y costos

`product_categories`, `products`, `price_lists`, `product_prices` y `product_costs` llevan `tenant_id`. Las claves compuestas impiden asociar productos, categorías o listas de empresas distintas. No hay inventario ni contabilidad en esta etapa.

Una lista puede ser `public`, `wholesale` o `internal`. Las vistas de vendedores y la API comercial solo exponen listas públicas activas. `products.manage_prices` habilita listas y precios internos; `products.view_costs` y `products.manage_costs` controlan los costos por separado. Los recursos JSON incluyen solo campos enumerados expresamente, sin `product_costs` ni atributos internos. Un producto inactivo no se muestra al vendedor. Las listas internas nunca se usan en presupuestos que puedan exportarse al cliente.

Los importes se guardan como `NUMERIC(18,2)`. PostgreSQL impide importes negativos y períodos superpuestos por producto y lista mediante una exclusión GiST sobre rangos de fechas inclusivos. Los costos tienen la misma regla por producto. Un precio sin inicio o fin representa vigencia abierta. Para reemplazar un precio o costo, se cierra su vigencia y se registra uno nuevo que comienza después. Una lista activa puede ser predeterminada por empresa; un índice parcial impide dos predeterminadas activas.

El servicio `PriceService` resuelve el precio aplicable en una fecha y exige producto y lista activos del mismo tenant. El presupuesto guarda SKU, descripción y precio unitario en cada ítem; modificar un precio no reescribe presupuestos existentes. No se permiten descuentos hasta definir una política comercial de límites. El panel usa `/admin/products`; la API ofrece consulta de productos públicos en `/api/v1/products`.
