# Distribución Android controlada

La app usa Expo SDK 57 y almacena tokens nativos con SecureStore. La URL de API
se incorpora al bundle desde `EXPO_PUBLIC_API_URL` y debe ser HTTPS. No incluir
secretos en variables `EXPO_PUBLIC_*`.

## Preparar la APK

1. Aprobar cuenta Expo, identificador Android definitivo, versión y URL HTTPS.
2. Configurar `EXPO_PUBLIC_API_URL` en el entorno EAS `preview`; no guardarla en
   un archivo versionado si identifica infraestructura privada.
3. Verificar árbol limpio, commit aprobado y CI.
4. Desde `apps/mobile`, ejecutar:

```bash
npm ci
npm run typecheck
npm run lint
npm run format:check
npm test -- --runInBand
npm run build:pilot:android
```

El perfil `pilot` de `eas.json` produce una APK interna. No contiene una acción
de submit a Google Play. Generar la build o autenticar EAS es una acción externa
que requiere autorización y no fue ejecutada durante C-04.

## Inventario e instalación

Registrar fuera de Git: empleado, dispositivo, modelo, versión Android,
identificador interno, fecha, APK/build ID, SHA-256, instalador y estado de
revocación. Descargar la APK por canal restringido; los enlaces internos no se
publican. Comprobar hash, habilitar instalación solo durante el proceso,
instalar, abrir, validar HTTPS/login/registro/sincronización y volver a
deshabilitar orígenes desconocidos.

## Actualización

Cada versión requiere nuevo commit, pruebas, build ID, hash y ensayo sintético.
Instalar sobre la versión anterior y verificar que SQLite/outbox persisten antes
de autorizar el despliegue al grupo. No actualizar con operaciones pendientes.
Si una versión falla, suspender nuevas operaciones; no desinstalar porque puede
eliminar evidencia local.

## Pérdida o robo

Administración abre **Soporte**, identifica el dispositivo, lo revoca y confirma
el evento de auditoría. La revocación invalida el token asociado. Suspender las
hojas del usuario hasta revisar operaciones pendientes o tardías y cambiar su
contraseña si hay sospecha de exposición. El borrado remoto depende de la
gestión corporativa del dispositivo y no está implementado por la app.
