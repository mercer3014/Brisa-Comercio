# Geodata — Contexto del proyecto (para IA)

> Pega este documento a una IA para que entienda el proyecto de inmediato.
> Es un resumen consolidado; el detalle histórico vive en la carpeta `memoria/`.

---

## 1. ¿Qué es Geodata?

Geodata es una **plataforma web de datos de comercio exterior** (exportaciones e
importaciones) que **centraliza estadísticas de varias organizaciones** en un solo
lugar, con portal público, gráficos, rankings, mapas e indicadores.

- Organización principal cargada: **INE de Bolivia** (microdatos de comercio).
- Otras fuentes soportadas: **ALADI**, **MERCOSUR**, **FAOSTAT** (agrícola).
- Objetivo: cargar archivos Excel/CSV grandes (hasta ~400.000 filas por año),
  guardarlos en PostgreSQL y mostrarlos en un portal profesional (paleta azul,
  tarjetas, tablas y charts).

---

## 2. Stack tecnológico

| Capa | Tecnología |
|------|-----------|
| Backend | **Laravel 12** (PHP 8.2) |
| Frontend | **Inertia.js + Vue 3** (Composition API, `<script setup>`) |
| Estilos | **Tailwind CSS v4** (configurado en CSS con `@theme`, sin `tailwind.config.js`) |
| Build | **Vite 7** |
| Base de datos | **PostgreSQL** (nombre configurable en `.env`, por defecto `brisa`) |
| Gráficos | **ApexCharts** (`vue3-apexcharts`, registrado global como `<apexchart>`) |
| Mapas | **Leaflet** (`@vue-leaflet/vue-leaflet`) |
| Colas | driver **database** (cargas pesadas en segundo plano) |
| Idioma | **Todo en español** (código, comentarios, interfaz) |

---

## 3. Arquitectura de datos (idea central)

- **Una sola base** para todas las organizaciones. Cada fila de hechos lleva
  `organizacion_id` (para ver solo INE: `WHERE organizacion_id = 1`).
- Las tablas de hechos se separan por **FORMA del dato**, no por organización.
  Ej.: microdatos INE → `operacion_comercio_exterior`; series MERCOSUR →
  `serie_comercio_zona`; rankings ALADI → `ranking_comercio`; etc.
- Las cabeceras de los archivos **cambian con los años** (mismos datos, distintos
  nombres de columna). El mapeo es por **NOMBRE de columna**, mediante las tablas
  `perfil_mapeo` y `mapeo_columna`. El orden físico de las columnas no importa.
- El esquema tiene ~39 tablas de negocio (referencia exacta en `estructura-db.sql`).
  La base **ya existe**; no se crean/recrean tablas de negocio, solo modelos Eloquent
  (~56 modelos en `app/Models/`).

### Campos canónicos
`config/geodata.php` define los **27 campos canónicos** (destino del mapeo) y un
diccionario de **alias de columnas** (ej. `FOB → valor_fob_usd`, `KILBRU → peso_bruto_kg`).
Toda columna de un archivo se traduce a uno de estos campos por nombre.

---

## 4. Las dos caras del sistema

### A) Portal público (sin login — `LayoutPublico.vue`)
8 secciones navegables, todas alimentadas por la API real:
`Inicio` · `Organizaciones` · `Rankings` · `Comparador` · `Mapa Comercial` ·
`Indicadores` · `Línea de Tiempo` · `Acerca de` · `[Acceder]`.

### B) Panel admin (con login, prefijo `/admin` — `LayoutAdmin.vue`)
- **Activos de verdad:** Dashboard (KPIs de cargas), Cargas (subir Excel → preview
  canónico → procesar ETL → seguimiento del job), Catálogos (solo lectura).
- **Maquetados / próximos:** Explorador, Reportes, Calidad, Organizaciones, Perfiles,
  Usuarios, Roles, Configuración, Bitácora.

`app.js` asigna el layout por convención: páginas en `Pages/Portal/*` → público;
el resto → admin. El login usa `layout: null`.

**Credenciales demo (local):** usuario `admin` / contraseña `Admin12345`
(correo `admin@geodata.local`). El middleware de permisos está en *passthrough*
temporal (un rol con los 27 permisos habilita todo el panel).

---

## 5. Sistema ETL (carga de datos)

Flujo: subir archivo → detectar perfil/mapeo → traducir a campos canónicos →
insertar en la tabla de hechos correspondiente → refrescar vistas materializadas.

Comandos de terminal disponibles:

```
php artisan geodata:cargar-ine        # microdatos INE (exp/imp)
php artisan geodata:cargar-aladi      # rankings Excel de ALADI
php artisan geodata:cargar-mercosur   # Excel MERCOSUR (país e item/NCM)
php artisan geodata:cargar-faostat    # .xls de FAOSTAT (agrícola)
php artisan geodata:probar-etl        # cargar un Excel directo al ETL (pruebas)
php artisan geodata:refrescar-vistas  # refresca vistas materializadas del portal
php artisan geodata:calentar-cache    # precalienta cache de explorador/dashboards
```

Vistas materializadas reales del portal: `mv_resumen_anual_ine`,
`mv_resumen_mensual_ine`, `mv_resumen_mercosur_zona`.

---

## 6. API de datos (JSON para ApexCharts)

Prefijo `/api/v1/*`. Formato estándar: `{ categorias, series:[{name,data}], meta }`.
Con cache (`Cache::remember`, TTL 600s charts/kpis, 1800s filtros).

Grupos de endpoints:
- **KPIs:** `kpis`, `kpis/{org}`
- **Organizaciones:** `organizaciones`, `organizaciones/{id}`
- **Charts INE:** `charts/comercio-mensual`, `evolucion-anual`, `top-productos`,
  `top-paises`, `top-departamentos`, `seccion-arancelaria`, `transporte`, `tnt-evolucion`
- **Charts MERCOSUR:** `charts/mercosur/{zona,balanza,productos,paises}`
- **Charts ALADI:** `charts/aladi/{ranking,paises,evolucion}`
- **Charts FAOSTAT:** `charts/faostat/{evolucion,productos,filtros}`
- **Otros:** `charts/mapa-flujos`, `comparador`, `timeline`, `indicadores` (HHI, cobertura)
- **Rankings:** `rankings/{productos,paises,departamentos,aduanas,aladi}`
- **Filtros:** `filtros/{paises,zonas,secciones,gestiones,productos}`

Convenciones: `?gestion=YYYY`, `?flujo=exp|imp|ambos`, `?limit=N`, `?org=N`.
INE: exportación = `valor_fob_usd`, importación = `valor_cif_frontera_usd`.

---

## 7. ¿Qué está HECHO? (fases completadas)

1. **Fase 1** — Conexión a la BD existente, ~56 modelos Eloquent, login admin con rol único.
2. **Fase 2** — ETL: loaders INE, MERCOSUR, ALADI, FAOSTAT probados con datos reales.
3. **Fase 3** — API de charts (~24 endpoints respondiendo 200 con datos reales).
4. **Fase 4** — Esqueleto visual del portal (8 secciones, componentes base, Leaflet, ApexCharts).
5. **Fase 5** — Portal público con datos reales, estados de carga/vacíos, descarga PNG por gráfico.
6. **Fase 6** — Panel admin esencial: Dashboard, Cargas (flujo completo), Catálogos (lectura).

---

## 8. ¿Qué está PENDIENTE?

- **FAOSTAT real:** cargar los Excel fuente para activar sus gráficos/rankings
  (el loader y los endpoints ya están listos).
- **Permisos granulares:** reactivar `VerificarPermiso` (hoy en passthrough).
- **Créditos/descargas reales:** conectar `ExportModal` (hoy es solo maqueta visual) a
  autenticación + cobro.
- **CRUD real** de catálogos, organizaciones, usuarios, roles y configuración.
- **Optimización:** code-splitting del bundle (ApexCharts genera chunks >500 kB).

---

## 9. Cómo levantar el proyecto

Ver **`INSTRUCCIONES.md`** (guía paso a paso). Resumen:

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
# importar backup .sql en PostgreSQL (base "brisa")
npm run build
php artisan serve
```

---

## 10. Convenciones importantes

- **Todo en español** (nombres de variables, rutas, comentarios, UI).
- No inventar nombres de tablas/columnas: respetar `estructura-db.sql`.
- Contraseñas solo como **hash bcrypt**, nunca en texto plano.
- La carpeta `memoria/` es el registro histórico del proyecto (leer al iniciar, actualizar al terminar).
- El proyecto se llama **Geodata** (nombres viejos "comexhub" y "ovxel" ya fueron eliminados).
