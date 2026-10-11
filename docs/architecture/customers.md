# Clientes y domicilios

`customers` y `customer_addresses` pertenecen a una empresa mediante `tenant_id`. Todas las búsquedas del panel y de `/api/v1` se acotan al tenant resuelto por sesión o token Sanctum. Las relaciones cliente–domicilio se protegen con claves foráneas compuestas `(tenant_id, id)`. Los controladores usan Gates y Policies; una URL con UUID ajeno no concede acceso.

El número comercial `CLI-000001` se obtiene de `tenant_counters` bajo bloqueo de fila en una transacción. El documento no es la clave del cliente. Su normalización para deduplicar elimina caracteres que no sean letras ASCII o dígitos y convierte letras a mayúsculas. Un índice único parcial impide repetir `(tenant_id, tipo, documento normalizado)` entre clientes no eliminados. Se conserva el valor original para mostrarlo. Se exige tipo y número juntos.

Cada cliente puede tener varios domicilios; un índice parcial permite uno principal activo. El servicio bloquea la fila del cliente al cambiar el principal. Los domicilios se eliminan lógicamente. Un presupuesto copia los datos básicos del cliente y el domicilio principal al crearse, por lo que una edición posterior no altera la propuesta histórica.

`active`, `inactive` y `blocked` son estados disponibles. En esta etapa solo se permite crear presupuestos para clientes `active`; las reglas futuras sobre crédito y ventas se definirán separadamente. El borrado lógico no elimina presupuestos ni auditoría. No hay verificación externa de identidad ni geocodificación.

El panel usa `/admin/customers`; la API de lectura usa `/api/v1/customers` y `/api/v1/customers/{id}/addresses`. `owner` y `admin` pueden cambiar estado; `seller` puede crear y editar, y los roles con `customers.view` pueden consultar. Los cambios generan `audit_events`.
