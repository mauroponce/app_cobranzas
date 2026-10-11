# Plan de implementación: base inicial de App Cobranzas

## Alcance confirmado

El repositorio comenzó vacío. La primera entrega debe dejar un monorepo ejecutable con Laravel 13, dashboard Blade/Livewire/DaisyUI, PostgreSQL, Redis, Docker Compose y una app Expo/React Native en TypeScript. La API tendrá `/api/v1/health` y `/api/v1/me`. Todavía no se crearán tablas ni reglas de ventas, cuotas, cobranzas, rutas o pagos, ni autenticación móvil completa, ni sincronización financiera.

## Decisiones técnicas

- Laravel permanece como aplicación estándar en `apps/backend`. Las carpetas de dominio preparan ampliaciones posteriores sin introducir capas abstractas prematuras.
- `users` representa una identidad global. `tenants` y `tenant_memberships` definen acceso por empresa; la pareja empresa/usuario es única en PostgreSQL. El rol inicial es `admin` y puede ampliarse después.
- El tenant activo de la sesión web solo puede salir de una pertenencia administrativa vigente. Con una sola empresa se selecciona automáticamente; con varias se exige elección. Cada solicitud protegida vuelve a comprobar la pertenencia. Una revocación invalida el contexto activo.
- El seeder de desarrollo crea Trotamundos y crea el administrador únicamente cuando se proporcionan correo y contraseña en un archivo `.env` ignorado por Git.
- La autenticación web usa sesión y recuperación de contraseña de Laravel. Sanctum protege `/api/v1/me`; la emisión de tokens para móvil se implementará más adelante.
- La app móvil reserva SQLite y una estructura para sincronización, sin outbox ni operaciones monetarias en esta etapa.

## Entregas verificables

1. **Base ejecutable:** estructura del monorepo, Laravel, Dockerfile, Compose, Nginx, PostgreSQL, Redis, worker, scheduler, Mailpit, Makefile y healthcheck. Comprobar compilación, arranque y respuesta HTTP.
2. **Acceso y tenancy:** migraciones, modelos, seeder, login/logout, recuperación, selector de empresa y middleware. Probar con PostgreSQL login, aislamiento, selección, revocación y denegación de acceso.
3. **Dashboard:** instalar Livewire, Tailwind y DaisyUI; crear diseño responsive, componentes reutilizables y módulos vacíos. Comprobar compilación de assets y navegación protegida.
4. **API:** agregar Sanctum y rutas versionadas con respuestas JSON uniformes. Probar healthcheck público, perfil sin token y perfil con token válido.
5. **App móvil:** Expo, React Native Paper, React Navigation, SQLite y cliente HTTP configurado por entorno. Comprobar tipos, lint y arranque de Metro; iniciar Android/iOS cuando exista el SDK correspondiente.
6. **Calidad y entrega:** Pint, pruebas, ESLint, Prettier, EditorConfig, CI separado, README, arquitectura e instrucciones `AGENTS.md`. Repetir pruebas, migraciones, seeder, respuesta del dashboard y revisión de secretos.

## Límites y verificación

Las pruebas de integración del backend usarán PostgreSQL para comprobar las mismas restricciones de base de datos que se usarán en desarrollo. No hay migración de datos existentes ni despliegue productivo. Los comandos definitivos se documentarán con los nombres reales de servicios y scripts una vez implementados. Las versiones exactas se fijarán en `composer.lock` y `package-lock.json` y se comprobarán contra la documentación oficial.

## Ampliación visual solicitada

`docs/design/admin-mockup.html` es la referencia principal del panel. Se reproducirán su barra lateral, selector de empresa, barra superior, escala tipográfica, color índigo, paneles, tabla de usuarios y comportamiento responsive con Blade, Livewire, Tailwind y DaisyUI. Los indicadores financieros conservarán la estructura visual, pero mostrarán estados vacíos porque aún no existen datos reales. La tabla de usuarios usará las pertenencias del tenant activo. En la entrega inicial, la sección de invitaciones mostraba un estado vacío y una acción deshabilitada hasta incorporar persistencia y correo; no se copiarán las interacciones JavaScript ni los registros de ejemplo del mockup.

## Actualización: etapa 2

La administración de empresas, usuarios, permisos e invitaciones se implementó después del plan inicial. El flujo de invitación ahora tiene persistencia PostgreSQL, token guardado como hash, envío a Mailpit, aceptación transaccional y auditoría. El estado actual se describe en [la arquitectura](overview.md); la matriz ejecutable de capacidades está en `apps/backend/app/Domain/Tenancy/RoleMatrix.php`.

## Etapa 3 — módulo comercial (implementada)

1. Esquema PostgreSQL con `tenant_counters`, clientes y domicilios, catálogo y precios/costos, presupuestos, ítems, historial y actividades. Integridad con claves compuestas, índices parciales y exclusión de vigencias solapadas.
2. Servicios de dominio para números concurrentes, documentos normalizados, precios por fecha, importes en centavos y transiciones auditadas.
3. Panel Blade/Livewire para clientes, domicilios, productos, listas, precios, costos y presupuestos; PDF autorizado con Dompdf.
4. API Sanctum de lectura acotada al token y al vendedor; datos demo locales idempotentes y documentación de contratos.
5. Verificación: migraciones y seeders en PostgreSQL, pruebas de permisos/integridad/concurrencia, Pint, compilación de assets y recorridos HTTP del panel.

La conversión de presupuestos en ventas y la política explícita de financiación quedan para la etapa 4.
