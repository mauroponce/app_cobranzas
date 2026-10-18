# Respaldo y restauración del piloto

## Contenido

Un respaldo completo incluye:

- dump PostgreSQL en formato custom;
- documentos privados copiados desde R2, con ruta, tamaño y SHA-256;
- revisión Git y checksums de la configuración versionada;
- nombres de variables requeridas, nunca sus valores.

Redis es transitorio y no sustituye PostgreSQL ni R2. Certificados, claves,
contraseñas y el `.env.pilot` se recuperan desde el gestor de secretos elegido,
no desde el repositorio ni desde el backup de datos.

## Crear

Con el entorno piloto activo y `.env.pilot` protegido:

```bash
PILOT_ENV_FILE=.env.pilot scripts/pilot/backup.sh
```

El resultado queda bajo `PILOT_BACKUP_ROOT`, fuera de Git. El script falla si
no puede respaldar PostgreSQL o documentos; no declara éxito parcial. Copiar el
directorio cifrado a una segunda ubicación con acceso restringido es una tarea
operativa obligatoria antes del GO.

## Verificar y restaurar

Elegir una base nueva y aislada cuyo nombre termine en `_test`:

```bash
PILOT_ENV_FILE=.env.pilot scripts/pilot/restore-test.sh \
  var/pilot-backups/AAAAMMDDTHHMMSSZ app_cobranzas_restore_test
```

El script comprueba hashes, rehúsa sobrescribir una base existente, restaura
sin `--clean` y deja los documentos en disco para comparar manifiesto y
contenido; nunca los vuelve a subir a R2 automáticamente.

Después de restaurar, registrar migraciones, conteo de tablas, tenants, objetos
y una muestra autorizada de documentos. Ejecutar smoke tests sin datos reales
expuestos. Conservar informe, duración y responsable.

## Frecuencia y recuperación

RPO (pérdida máxima aceptable), RTO (tiempo máximo de recuperación), frecuencia,
retención, cifrado, ubicación secundaria y responsables permanecen
**PENDIENTES DE APROBACIÓN HUMANA**. Hasta que se definan, hay herramientas de
backup verificables pero no una política operativa aprobada.

En una falla: detener escrituras, preservar logs, decidir el punto de
restauración, restaurar en entorno aislado, comprobar integridad y recién luego
autorizar recuperación. No usar `migrate:fresh`, rollback, wipe ni edición SQL
manual como mecanismo de recuperación.
