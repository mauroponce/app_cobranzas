# Sistema visual móvil de App Cobranzas

## Referencias y alcance

La interfaz nativa toma la jerarquía del [mockup móvil](mobile-mockup.html): encabezado compacto, tarjeta destacada, métricas 2×2, listas de tarjetas y navegación de cinco pestañas. La [inspiración de RealStateX](https://dribbble.com/shots/26895037-RealStateX-Property-Buy-Rent-Sell-Classified-React-Native) aporta superficies suaves y espacios generosos. Se sustituyó el violeta del mockup por el color oficial.

La aplicación usa componentes React Native y React Native Paper con Material Design 3. No ejecuta el HTML ni sus datos o acciones de demostración.

## Color oficial y conversión

- Valor de diseño: `oklch(58.5% 0.233 277.117)`.
- Conversión a sRGB: `L=0.585`, `a=C·cos(h)`, `b=C·sin(h)`; transformación OKLab → RGB lineal; función de transferencia sRGB; recorte del canal azul que supera el gamut; redondeo a ocho bits.
- Resultado para React Native: **`#615FFF`** (`rgb(97, 95, 255)`). Texto sobre primario: `#FFFFFF` (contraste aproximado 4,58:1).

La hoja actual del panel Laravel conserva reglas específicas con `#4F46E5`. Esta etapa no modifica ese panel; su alineación con el color oficial queda para una etapa del dashboard.

| Uso | Claro | Oscuro |
| --- | --- | --- |
| Primario | `#615FFF` | `#615FFF` |
| Contenedor primario | `#E9E8FF` | `#302D66` |
| Fondo | `#F7F8FC` | `#151927` |
| Superficie | `#FFFFFF` | `#22283A` |
| Texto principal | `#252B42` | `#F2F4FB` |
| Texto secundario | `#737D93` | `#AFB9CB` |
| Borde | `#E8EBF3` | `#465067` |
| Éxito | `#087F66` | tema MD3 y etiqueta contextual |
| Advertencia | `#A8620A` | tema MD3 y etiqueta contextual |
| Error | `#BA3545` | `#BA3545` |

Los valores y temas están en `apps/mobile/src/theme/theme.ts`. La preferencia sistema/claro/oscuro se guarda en la tabla local `app_metadata`; la navegación adopta el mismo tema. Expo usa `userInterfaceStyle=automatic` y `expo-system-ui` para que Android respete la preferencia del sistema.

## Tipografía, espaciado y forma

Se usa la tipografía del sistema a través de las variantes MD3 de React Native Paper para respetar el escalado del dispositivo. Encabezados y cifras llevan peso 800; etiquetas y cuerpo conservan tamaños del tema. La escala de espaciado es 4, 8, 12, 16, 20, 24 y 28 puntos. Radios: 10, 14, 18 y 24 puntos. Las tarjetas tienen borde fino y sombra leve; los banners destacados usan el primario sólido. Los importes ocupan una línea propia en Flexbox y pueden reducir su tamaño si crecen.

## Componentes y navegación

`AppHeader`, `BalanceCard`, `MetricCard`, `SurfaceCard`, `CustomerCard`, `RouteStopCard`, `PaymentCard`, `StatusBadge`, `SyncStatusBanner`, `EmptyState` y `SectionTitle` mantienen la misma escala. Las listas de rutas, clientes, cobros y avisos usan `FlatList`.

Las pestañas persistentes son Inicio, Ruta, Clientes, Cobros y Perfil. Detalle de cliente, detalle y resultado de visita, registro de cobro, rendición y notificaciones se abren en el stack superior y permiten volver. La barra inferior incorpora el área segura y se oculta con el teclado; el contenido también respeta las áreas seguras.

## Tamaños y estados

Las métricas se distribuyen en dos columnas de igual ancho y altura mínima por fila. Las tarjetas y los textos pueden encogerse sin ancho fijo horizontal. Los estados vacíos describen la ausencia de datos reales. `—` indica un valor no disponible; no representa cero. Una operación futura sin conexión deberá mostrar **Pendiente de sincronización** hasta que Laravel la acepte. Transferencias requieren verificación y los pagos online no cancelan visitas. Rendición incluye solo dinero físico del cobrador.

La app autentica al cobrador contra Laravel y sincroniza su cartera, hoja y
visitas autorizadas. Una hoja publicada debe iniciarse desde Ruta antes de
registrar una declaración desde una visita. El cobro se guarda primero en la
outbox local y se muestra como pendiente; cuando Laravel lo acepta permanece
pendiente de conciliación administrativa. No se presentan fixtures financieros
como datos de producción.

## Verificación visual

La development build Android compiló correctamente con JDK 17 y se inspeccionó en un emulador. Las capturas están dentro del repositorio:

- [Pantalla inicial 360 × 800](screenshots/mobile-android-360x800.png).
- [Pantalla inicial 393 × 852](screenshots/mobile-android-393x852.png).
- [Perfil en tema oscuro](screenshots/mobile-android-dark.png).
- [Pantalla inicial con fuente al 130 %](screenshots/mobile-android-large-text.png).

Se verificó que los cinco iconos de navegación se muestran completos y que el tema oscuro respeta la preferencia. El botón flotante de herramientas de Expo aparece en estas capturas de desarrollo y puede tapar parte del encabezado; no forma parte de la interfaz de producción. Quedan pendientes la inspección con teclado abierto y la ejecución en simulador iOS, que no está disponible en este equipo.
