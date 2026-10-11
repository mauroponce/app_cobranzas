# Aplicación móvil

Esta aplicación usa Expo, TypeScript estricto, React Native Paper y React Navigation. Antes de usar una API nativa, comprobá la versión instalada del SDK y su documentación. Instalá módulos nativos con `npx expo install` para resolver versiones compatibles.

El servidor define las reglas de negocio y autorización. No introducir cálculo financiero ni cobros offline sin un contrato de sincronización que contemple idempotencia, reintentos y conflictos. La base SQLite actual solo prepara almacenamiento local.

Los archivos `ios/` y `android/` se generan con Expo; no editarlos como fuente principal. Usar development builds para comprobar módulos nativos. Ejecutar `npm run typecheck` y `npm run lint`. Código e identificadores en inglés; documentación y contenido visible en español.

## Sistema de diseño móvil

Consultá `../../docs/design/mobile-design-system.md` antes de modificar pantallas. El primario oficial proviene de `oklch(58.5% 0.233 277.117)` y su conversión sRGB `#615FFF`; no reutilices el violeta del mockup. Usá los temas MD3 claro/oscuro, la escala de espaciado y los componentes compartidos de `src/components`. La barra inferior tiene cinco pestañas y las pantallas secundarias viven en el stack. Respetá áreas seguras, teclado y tamaños de fuente ampliados. Los módulos de datos todavía vacíos deben indicar ausencia real y no introducir importes ficticios ni simular cobros.
