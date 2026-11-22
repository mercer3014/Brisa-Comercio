# CAMBIOS_PORTAL.md — Portal público con el estilo del mockup (GEO-12)

Fecha: 22/11/2026. Solo cambios locales: **sin commit y sin push**. Sin cambios de esquema en la BD (no hay migraciones nuevas), sin borrar ni truncar datos.

## Objetivo
El portal público (Laravel 12 + Inertia + Vue 3 + Tailwind v4) adopta el estilo y la estructura del HTML de referencia, pero **todas las cifras salen de PostgreSQL** (vistas `resumen_mensual`, `resumen_anual_producto`, `resumen_anual_pais`, `resumen_anual_departamento`, `v_pais_canonico` y la tabla de hechos `operacion_comercio_exterior`). No se usa ningún número de ejemplo del mockup.

### Cobertura parcial (datos actuales)
- Exportaciones INE: 1992–2026p (2026 solo ene–mar).
- Importaciones INE: 1992–1999 (no hay importaciones desde 2000).
- ALADI, MERCOSUR y FAOSTAT: 0 filas por ahora.

El portal muestra la cobertura por flujo (chips "Datos INE cargados"). Donde falta un flujo aparece "Sin datos" o "—", **nunca 0**. Los selectores de gestión marcan "(solo exp.)". Una gestión preliminar (2026p) se compara con los mismos meses del año anterior. La gestión por defecto es la última exportación completa (2025).

## Backend
| Archivo | Cambio |
|---|---|
| `app/Servicios/PortalVistas.php` (nuevo) | Consultas del portal: cobertura, panorama, productos, ficha de producto, rankings multi-año (Pareto), indicadores (tasa de cobertura, saldo, valor unitario, HHI), línea de tiempo, estado de fuentes y comercio por vía (`viasDetalle`). Cache por versión de datos (`ClavesCache::version()`), TTL 24 h. |
| `app/Http/Controllers/PortalDatosController.php` (nuevo) | Endpoints JSON de solo lectura: `/portal/v2/panorama`, `/portal/v2/productos`, `/portal/v2/productos/{id}`, `/portal/v2/rankings`, `/portal/v2/indicadores`, `/portal/v2/vias`. |
| `app/Http/Controllers/PortalController.php` | Inicio, Productos (nueva), Rankings, Indicadores, Línea de tiempo, Organizaciones, Acerca, Comercio por vía y Mapa reciben los datos de `PortalVistas`. Se mantiene `datos()`. |
| `routes/web.php` | Ruta `/productos` y rutas `/portal/v2/*`, dentro del grupo `throttle:120`. |
| `app/Servicios/PortalApi.php` | Arreglo: el medio "MARITIMA-CARRETERA" se contaba como "otros" (ahora se busca `MARITIM`). `mapaFlujos` devuelve ISO-2/ISO-3 y el nombre canónico desde `v_pais_canonico`, así el mapa ubica los países. |

## Frontend
- `resources/css/portal-mockup.css` (nuevo): CSS del mockup acotado a `.gd` y metido en `@layer components`, así las utilidades de Tailwind siguen mandando. El panel admin no se toca.
- `resources/css/portal.css` (nuevo): tokens del portal (vino/azul marino, esquinas rectas), estados "sin datos", encabezados y ajustes; se importa desde `app.css`.
- `resources/js/lib/gd.js`, `treemap.js` y `centroides.js` (nuevos): formatos ("$1.390 M"), CSV para Excel, treemap y centroides de países (ISO-2) para el mapa.
- `resources/js/Components/Gd/*` (nuevos): GdSinDatos, GdCobertura, GdDona, GdBarras, GdTop3.
- `resources/js/Layouts/LayoutPublico.vue`: barra institucional, menú del mockup (Inicio, Productos, Organizaciones, Rankings y Comparador, Mapa Comercial, Indicadores, Línea de Tiempo, Acerca de) y pie en 4 columnas. El botón Acceder / Ir al panel sigue igual.
- `public/img/portal/*.webp`: 14 imágenes sacadas del mockup.

### Páginas
| Página | Qué muestra (datos reales) | Estados sin datos |
|---|---|---|
| Inicio `/` | Hero por vía de transporte (exp/imp por vía); KPIs de exportación, importación, saldo y cobertura; Top 3 destinos; estructura tradicional/no tradicional; top 10 de exportación e importación; destacados | Importaciones "Sin datos" con un botón para ver 1999; vías "Sin registro en el INE" |
| Productos `/productos` (nueva) | Treemap NANDINA, diagnóstico de la canasta (HHI y top 5), mayores alzas y caídas, composición por sección SA, ranking con ficha del producto (serie, destinos, departamentos, medios) y tabla paginada con descarga a Excel | Flujo sin datos → panel "Sin datos" |
| Rankings y Comparador `/rankings` | De 2 a 6 años, por dimensión (producto, país, departamento, sección), tabla Pareto con resto y total, gráfico comparativo, CSV | Años sin datos aparecen marcados y deshabilitados; aviso de año parcial |
| Indicadores `/indicadores` | Tasa de cobertura, saldo, valor unitario, toneladas, HHI de 5 dimensiones, serie exp/imp | Apertura: "Sin datos: PIB no cargado"; barras de importación vacías y punteadas |
| Línea de Tiempo `/linea-de-tiempo` | Serie 1992–2026p, ficha del año, bloques por década con producto líder y destino | Saldo "—" cuando no hay importaciones |
| Comercio por vía `/comercio-por-via` | Coverflow con fotos (4 vías), tablero de la vía: exp/imp, participación, años con registro, composición por medio, principales productos, serie histórica, CSV | "Sin registro en {año}": el código marítimo solo existe hasta 2022; importaciones "sin datos" |
| Organizaciones `/organizaciones` | Tarjetas con registros reales, cobertura y archivos por fuente, más una tabla de estado | "Sin datos cargados" (ALADI, MERCOSUR, FAOSTAT) |
| Acerca `/acerca` | Banner con el total real de registros y de fuentes con datos, etapas y metodología | — |
| Mapa, Explorar, Comparador, Organización (detalle) | Mismo layout y encabezado del mockup; el mapa toma por defecto 2025 y ubica los países por ISO | Mapa: aviso cuando la gestión no tiene importaciones |

## Aplicar en la PC
`aplicar_portal.ps1` hace esto:
1. Respalda los archivos a `C:\Users\hp\Documents\GEODATA_backup\portal_geo12_<fecha>\` (con las mismas rutas).
2. Copia los archivos del paquete.
3. Corre `npm run build` y `php artisan optimize:clear`.

No toca `.env`, la BD ni los procesos de php. Después basta con refrescar el navegador en http://127.0.0.1:8000.

## Pendiente o notas
- Las importaciones desde 2000 aparecen cuando se carguen; el portal las toma solo, porque la cobertura se calcula desde la BD.
- Apertura comercial (% PIB) necesita cargar el PIB.
- ALADI, MERCOSUR y FAOSTAT aparecen como "Sin datos cargados" hasta que se carguen.
