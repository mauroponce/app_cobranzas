# Contrato inicial de la API

Base: `/api/v1`. Las respuestas devuelven `data` o `error`; la autenticación utiliza tokens Sanctum.

| Método | Ruta | Uso |
| --- | --- | --- |
| GET | `/health` | Estado público |
| GET | `/me` | Identidad global autenticada |
| GET | `/tenants` | Empresas activas del usuario |
| POST | `/tenant/select` | Selección validada para el token; cuerpo `{ "tenant_id": "UUID" }` |
| GET | `/permissions` | Rol y capacidades efectivos del token en la empresa seleccionada |
| GET | `/profile` | Usuario y perfil en la empresa seleccionada |

La selección se guarda en el token, no en un header elegido libremente. La API vuelve a verificar usuario, membresía y empresa en cada solicitud con contexto. Todavía no hay emisión de tokens desde el móvil ni endpoints comerciales.

## Consultas comerciales (etapa 3)

Con token Sanctum y empresa seleccionada mediante `POST /api/v1/tenant/select`:

- `GET /api/v1/customers?search=` y `GET /api/v1/customers/{id}`.
- `GET /api/v1/customers/{id}/addresses`.
- `GET /api/v1/products` y `GET /api/v1/products/{id}`; solo productos activos y listas públicas vigentes. Nunca incluye costos.
- `GET /api/v1/quotations` y `GET /api/v1/quotations/{id}`; un vendedor solo ve los propios.

Las colecciones responden con `data` y `meta.current_page`/`meta.last_page`. Los importes se representan como cadenas decimales, sin números de punto flotante. La API comercial de esta etapa es de lectura; las escrituras permanecen en el panel Laravel hasta definir contratos móviles de sincronización y conflictos.
