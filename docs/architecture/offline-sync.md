# Sincronización offline

La aplicación conserva operaciones de efectivo en `offline_outbox` antes de enviarlas. Cada operación tiene UUID, tenant, usuario y dispositivo. El servidor guarda el hash canónico y el resultado en `mobile_sync_operations`: una repetición igual devuelve el mismo resultado y un UUID reutilizado con otro contenido se rechaza.

Una operación originada por un dispositivo o permiso revocado queda bajo revisión y no afecta los saldos. Los datos locales se separan con la clave de tenant, usuario y dispositivo; los tokens no se guardan en SQLite.
