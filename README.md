# App Cobranzas

SaaS multiempresa con panel administrativo, aplicación móvil de cobradores y módulos de clientes, catálogo, presupuestos, ventas, cuotas y cobranzas conciliadas por empresa.

## Requisitos

- Docker Engine y Docker Compose para el backend. No hace falta instalar PHP, Composer ni PostgreSQL en el equipo.
- Node.js 24 y npm para desarrollar la app móvil fuera de Docker.
- Android Studio para emulador Android o macOS/Xcode para simulador iOS. Un dispositivo físico puede usar una development build de Expo.

## Estructura

- `apps/backend`: Laravel 13, Blade, Livewire, API REST, migraciones y pruebas.
- `apps/mobile`: Expo, React Native, TypeScript, navegación y SQLite.
- `infra/docker`: imágenes y configuración de Nginx/PostgreSQL.
- `packages/api-contract`: contrato inicial de la API.
- `docs/architecture`: arquitectura y plan de implementación.

## Preparación y arranque

1. Copiá `.env.example` a `.env` y `apps/backend/.env.example` a `apps/backend/.env`.
2. Cambiá `DB_PASSWORD` en ambos archivos por el mismo valor local. Definí `DEV_SEED_PASSWORD`, `DEV_OWNER_EMAIL`, `DEV_ADMIN_EMAIL` y `DEV_PLATFORM_EMAIL` en `apps/backend/.env` para crear las cuentas locales. La contraseña debe tener al menos 12 caracteres. Los archivos `.env` están ignorados por Git.
3. Construí y arrancá con `make up`. Ese comando crea, si hace falta, el volumen persistente de PostgreSQL antes de iniciar los servicios.
4. Generá la clave de Laravel: `docker compose exec app php artisan key:generate`.
5. Ejecutá `make migrate` y, si querés cargar datos demo explícitamente, `make seed CONFIRM_DATABASE_WRITE=yes`.
6. Abrí `http://localhost:8080/login` e ingresá con el correo y la contraseña configurados. Mailpit para recuperación de contraseña está en `http://localhost:8025`.

El primer arranque descarga imágenes e instala dependencias dentro de la imagen PHP. Si cambian dependencias de Composer o assets web, reconstruí con `make up`. Si cambiás solo PHP/Blade, el volumen de desarrollo refleja el cambio sin reconstruir. Los puertos HTTP y PostgreSQL del equipo pueden cambiarse con `APP_PORT` y `DB_PORT` en `.env`; PostgreSQL escucha por defecto en `127.0.0.1:55432` para evitar conflictos con instalaciones locales.

### Persistencia local de datos

PostgreSQL usa el volumen externo `app-cobranzas_postgres_data`. Por ser externo,
`docker compose down -v` no puede eliminarlo: las cuentas demo y sus datos
persisten entre reinicios y recreaciones de contenedores. Usá siempre `make up`
para iniciar el entorno. Solo `docker volume rm app-cobranzas_postgres_data` o
una operación explícita como `migrate:fresh` puede borrar o reinicializar los
datos locales.

La aplicación usa `America/Argentina/Buenos_Aires` como zona horaria operativa
en backend, colas, scheduler y app móvil. `APP_TIMEZONE` permite declarar ese
valor en el entorno, pero la aplicación y las empresas rechazan otra zona.

## Comandos de desarrollo

| Comando | Acción |
| --- | --- |
| `make up` / `make down` | Arrancar o detener servicios |
| `make logs` | Seguir los registros |
| `make migrate` | Aplicar migraciones pendientes sin reconstruir la base |
| `make seed CONFIRM_DATABASE_WRITE=yes` | Ejecutar el seeder local con confirmación explícita |
| `make test` | Pruebas backend contra la base PostgreSQL de pruebas |
| `make shell` | Shell del contenedor PHP |
| `make artisan ARGS='route:list'` | Ejecutar Artisan |
| `make composer ARGS='install'` | Ejecutar Composer |
| `make assets` | Compilar assets Blade/Livewire con Node en Docker |

La base `app_cobranzas_test` se crea al inicializar el volumen PostgreSQL. Si ya existe un volumen creado antes de añadirla, créala con `docker compose exec postgres createdb -U app_cobranzas app_cobranzas_test`. PHPUnit fuerza esta base y la suite aborta antes de `RefreshDatabase` si la conexión efectiva no termina en `_test`. Los comandos que pueden borrar o reemplazar datos y `db:seed` quedan bloqueados salvo que la ejecución incluya `ALLOW_DESTRUCTIVE_DATABASE_COMMANDS=true`; esa habilitación solo se usa después de una solicitud explícita del usuario.

## API

`GET /api/v1/health` es público. Con un token Sanctum, `/me` muestra la identidad global; `/tenants`, `/tenant/select`, `/permissions` y `/profile` permiten seleccionar y validar la empresa por token. El móvil inicia sesión, registra el dispositivo y sincroniza declaraciones y resultados mediante una outbox persistente. Consultá [el contrato](packages/api-contract/README.md).

## App móvil

Desde `apps/mobile`, ejecutá `npm ci`, copiá `.env.example` a `.env` y luego `npm start`. Para abrir la app en el navegador, ejecutá `npm run web` y visitá `http://localhost:8081/`. Para compilar una development build usá `npx expo run:android` o, en macOS, `npx expo run:ios`. La app no depende exclusivamente de Expo Go; SQLite se comprobará en la development build.

`EXPO_PUBLIC_API_URL` debe apuntar al backend desde el dispositivo: `http://10.0.2.2:8080` en el emulador Android; `http://localhost:8080` en el simulador iOS; `http://IP_LAN_DEL_EQUIPO:8080` en un dispositivo físico conectado a la misma red. Para dispositivos físicos fuera del entorno local configurá HTTPS y una URL alcanzable. El botón de configuración permite comprobar el healthcheck.

Ejecutá `npm run typecheck`, `npm run lint`, `npm run format:check` y `npm test -- --runInBand` dentro de `apps/mobile`. En el backend, `make test` y `docker compose exec app ./vendor/bin/pint --test`.

## Estado funcional

El dashboard incluye gestión multiempresa, módulos comerciales y la operación de cobranzas. Administración programa hojas, revisa declaraciones e incidencias y dispone de APIs autorizadas para reasignar visitas y conciliar transferencias y rendiciones. La aplicación móvil del cobrador inicia hojas, conserva declaraciones y resultados offline, muestra estados de sincronización y presenta la rendición con datos reales. Las declaraciones no reducen deuda hasta su conciliación; el efectivo solo se confirma al recibir la rendición de la misma hoja.

## Módulo comercial

`/admin/customers` permite crear y mantener clientes y domicilios; `/admin/products` organiza el catálogo, listas, precios y costos autorizados; `/admin/quotations` permite preparar presupuestos, enviarlos a revisión, aprobarlos, registrar seguimiento y descargar un PDF. Los vendedores ven solo sus presupuestos. Las propuestas financiadas no calculan cuotas hasta contar con una política comercial explícita. Los datos demo locales incluyen tres clientes ficticios, tres productos y presupuestos en varios estados; `make seed` es idempotente.

La API de solo lectura incluye `/api/v1/customers`, `/api/v1/products` y `/api/v1/quotations` con detalle, siempre bajo un token Sanctum con tenant seleccionado. Consultá [clientes](docs/architecture/customers.md), [catálogo](docs/architecture/catalog.md) y [presupuestos](docs/architecture/quotations.md) para reglas e integridad. `make test`, `docker compose exec app ./vendor/bin/pint --test` y `make assets` verifican el backend.

## Accesos y correo de desarrollo

`/admin/users` lista los integrantes de la empresa activa; `/admin/invitations` envía correo a Mailpit y muestra pendientes; `/admin/settings/company` edita información general. Un usuario puede aceptar la invitación desde el correo, creando una cuenta o iniciando sesión con el correo invitado. `/platform` administra únicamente metadatos de empresas. El operador de plataforma no accede automáticamente a datos operativos. Para conceder o revocar el privilegio global a una cuenta activa de forma auditada: `docker compose exec app php artisan platform:administrator correo@ejemplo.test` o con `--revoke`.

Los cambios de esquema de esta etapa redefinen las migraciones iniciales a UUID porque el repositorio aún tenía solo datos locales descartables. En una instalación existente con datos, prepará una migración de conversión antes de desplegar; `migrate:fresh` elimina datos y solo corresponde a entornos descartables. La [arquitectura](docs/architecture/overview.md) documenta la matriz de permisos, el aislamiento y la estrategia futura de RLS.
