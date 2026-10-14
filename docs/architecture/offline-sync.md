# Sincronización offline

La aplicación conserva operaciones de efectivo en `offline_outbox` antes de enviarlas. Cada operación tiene UUID, tenant, usuario y dispositivo. El servidor guarda el hash canónico y el resultado en `mobile_sync_operations`: una repetición igual devuelve el mismo resultado y un UUID reutilizado con otro contenido se rechaza.

Una operación originada por un dispositivo o permiso revocado queda bajo revisión y no afecta los saldos. Los datos locales se separan con la clave de tenant, usuario y dispositivo; los tokens no se guardan en SQLite.

## Hojas y declaraciones móviles

El `pull` sincroniza la hoja del día asignada al cobrador, junto con sus paradas.
La hoja publicada se inicia de forma explícita con
`POST /api/v1/collection-routes/{id}/start`; hasta entonces el servidor rechaza
declaraciones. Expo conserva el identificador y estado de la hoja en SQLite
separados por empresa y usuario.

El registro móvil envía una operación `collection_declaration` con UUID, visita,
fecha e integrantes de efectivo y/o transferencia. Al aceptarla, Laravel crea
una declaración pendiente. La primera marca la visita como registrada con pago;
otras operaciones con UUID distintos pueden agregar nuevos cobros a esa visita
mientras la hoja siga en curso. Cada declaración se concilia por separado. El
UUID y el hash del payload mantienen la idempotencia: reintentar una operación
igual devuelve el resultado persistido y nunca duplica la declaración.

La declaración no reduce deuda. La transferencia pura se concilia de forma
individual; el efectivo y los cobros combinados esperan la rendición de la hoja.

## Actualización del panel por SSE

El panel administrativo abre `GET /admin/collections/stream` mediante
`EventSource`. La ruta usa la sesión web, el middleware `tenant.admin` y el Gate
`payments.view`; toma la empresa activa de la sesión y no acepta `tenant_id` del
navegador.

Luego del commit de una nueva declaración, Laravel guarda una revisión efímera
por empresa en Redis. El stream compara esa revisión, emite
`collections.changed` sin datos financieros y se cierra para que el navegador se
reconecte de manera nativa. Al recibir el evento, Livewire refresca la tabla de
declaraciones y muestra un toast. Nginx desactiva el buffering FastCGI solo para
esta ruta y aplica un timeout de lectura acotado. No se usan WebSockets, Echo,
Reverb ni Pusher.
