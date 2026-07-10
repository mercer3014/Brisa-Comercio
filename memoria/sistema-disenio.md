# Geodata — Sistema de diseno (identidad FCEE - UAGRM)

Identidad visual editorial e institucional. **Regla de oro del color: el AZUL manda,
el ROJO es acento puntual.** Nada de grandes areas rojas; el portal debe verse serio y
profesional, no estridente.

Todos los tokens estan centralizados en `resources/css/app.css` dentro de `@theme`
(Tailwind CSS v4). **No** hay `tailwind.config.js`: en Tailwind v4 la configuracion vive
en el CSS y cada custom property `--color-*`, `--font-*`, `--radius-*`, `--shadow-*` se
expone automaticamente como clase utilitaria. No usar hexadecimales sueltos en el codigo.

## Paleta

### Azul institucional — DOMINANTE (clase `institucional-*`)
Ancla de marca: **`institucional-900` = `#193153`**. Fondos oscuros del hero:
**`institucional-950` = `#10203a`**. Escala completa 50→950 (50 el mas claro).
Usar para: estructura, fondos oscuros, encabezados, barras, texto principal.

### Rojo institucional — ACENTO (clase `rojo-*`)
Ancla de marca: **`rojo-600` = `#e10f1c`**. Escala completa 50→950.
Usar SOLO en dosis pequenias: subrayado del titular, item de menu activo, boton
principal, dato resaltado, primer puesto de un ranking.

### Grises auxiliares (clase `gris-*`)
`gris-50`→`gris-900`. Textos secundarios (`gris-500/600`), fondos alternos
(`gris-50/100`), bordes (`gris-200/300`). `bg-gris-50` es el fondo base del `<body>`.

### Semanticos de variacion
- `positivo` `#15803d` (+ `positivo-suave` de fondo) — variaciones que suben.
- `negativo` `#c81e2a` (+ `negativo-suave` de fondo) — variaciones que bajan.

### `marca-*` (heredada)
Escala azul previa, conservada solo por compatibilidad con vistas del panel aun no
migradas. **No usar en codigo nuevo**; preferir `institucional-*`.

## Tipografia
- **Cuerpo**: `font-sans` → **Inter** (sans limpia).
- **Titulares grandes**: `font-display` → **Fraunces** (serif editorial estilo prensa).
  Helper `.titular-editorial` aplica la serif + tracking/line-height de titular.
- Carga: `fonts.bunny.net` (mirror de Google Fonts) en `resources/views/app.blade.php`.

## Radios y sombras
- `rounded-tarjeta` (0.875rem) — tarjetas e indicadores.
- `rounded-panel` (1.25rem) — paneles grandes (p. ej. "Resumen del periodo").
- `shadow-tarjeta` — elevacion sutil de tarjetas claras.
- `shadow-flotante` — panel flotante sobre fondo oscuro del hero.

## Componentes (clases en `@layer components`)
- **Botones** (combinar base + variante): `btn btn-primario` (rojo, accion principal),
  `btn btn-secundario` (azul solido), `btn btn-contorno` (contorno azul sobre claro),
  `btn btn-contorno-claro` (contorno blanco sobre fondo oscuro).
- **`.pildora`** — etiqueta/pill en mayusculas (p. ej. periodo del hero, con punto rojo).
- **`.tarjeta`** — tarjeta clara estandar (borde gris + `shadow-tarjeta`).
- **Badges de estado** (base + variante): `badge badge-ok` (verde), `badge badge-error`
  (rojo), `badge badge-info` (azul), `badge badge-neutro` (gris).
- **`.subrayado-rojo`** — resalta una palabra/linea del titular con acento rojo.

## Criterios de uso rapido
- Hero y secciones principales: fondo `institucional-950`/`900`, texto blanco.
- Contenido y tarjetas: fondo blanco / `gris-50`, texto `institucional-900`.
- Acento rojo: subrayado de titular, activo de menu, boton primario, primer puesto.
- Barras de ranking: azul; el primer puesto en rojo.
- Variaciones: verde sube / rojo baja.
