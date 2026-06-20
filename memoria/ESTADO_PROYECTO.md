# OVXEL — ESTADO DEL PROYECTO

> Stack: Laravel 12 + Vue 3 (Inertia) + PostgreSQL 16
> Base de datos: `ovxel_db` (fuente de verdad — creada por SQL completo en pgAdmin)

---

## FASE 1 — COMPLETADA ✅ (2026-06-18)

Objetivo: conectar Laravel a la BD existente sin recrear tablas, crear modelos faltantes
y habilitar un login admin temporal con un solo rol.

### 1. Conexión a la base de datos
- `.env`: `DB_CONNECTION=pgsql`, `DB_HOST=127.0.0.1`, `DB_PORT=5432`,
  `DB_DATABASE=ovxel_db`, `DB_USERNAME=postgres`, `DB_PASSWORD=awd78951230`,
  `DB_SEARCH_PATH=public`.
  - **Cambio clave:** `DB_DATABASE` estaba en `brisa` (BD inexistente) → corregido a `ovxel_db`.
- `config/database.php`: conexión `pgsql` con `search_path => 'public'` (correcto, sin cambios).
- Verificado: `DB::table('organizacion')->count()` = **4**. ✅

### 2. Migraciones sincronizadas SIN recrear tablas
- Se creó la tabla `migrations` con `php artisan migrate:install`.
- Las 5 migraciones de dominio se marcaron como YA EJECUTADAS (INSERT en `migrations`, batch 1):
  `crear_catalogo_central`, `crear_dimensiones_microdato`, `crear_modulo_sistema`,
  `crear_tabla_hechos_microdato`, `create_vistas_materializadas_portal`.
- Las tablas de framework NO existían, así que se ejecutaron con `php artisan migrate` (batch 2):
  - `0001_01_01_000000_create_users_table` — en este proyecto solo crea `sessions`
    (la auth usa la tabla de negocio `usuario`, no `users`). Necesaria porque `SESSION_DRIVER=database`.
  - `0001_01_01_000001_create_cache_table` — `cache`, `cache_locks` (`CACHE_STORE=database`).
  - `0001_01_01_000002_create_jobs_table` — `jobs`, `job_batches`, `failed_jobs` (`QUEUE_CONNECTION=database`).
- `php artisan migrate:status`: todo en estado **Ran**, sin pendientes. ✅
- No se ejecutó `migrate:fresh` ni se recreó ninguna tabla de dominio.

### 3. Modelos Eloquent creados (`app/Models/`)
Todos con `$table`, `$primaryKey`, `public $timestamps = false;`, `$guarded = []`.

ALADI / MERCOSUR / FAOSTAT / CAN (estructura base):
- `ArchivoFuente` → archivo_fuente (PK archivo_id) — rel: organizacion, paisReportante, flujo
- `PaisCodigoExterno` → pais_codigo_externo (PK pais_codigo_externo_id)
- `ProductoCodigoExterno` → producto_codigo_externo (PK producto_codigo_externo_id)
- `RankingComercio` → ranking_comercio (PK ranking_id) — rel: archivo, organizacion, etc.
- `SerieComercioBilateral` → serie_comercio_bilateral (PK serie_bilateral_id)
- `SerieIndicadorAgricola` → serie_indicador_agricola (PK serie_agricola_id)
- `FaostatElemento` → faostat_elemento (PK elemento_id)
- `FaostatSimbolo` → faostat_simbolo (PK simbolo_id)

Tablas nuevas de Ovxel:
- `SerieComercioZona` → serie_comercio_zona (PK serie_zona_id) — rel: zona, pais, organizacion
- `SerieComercioProductoZona` → serie_comercio_producto_zona (PK serie_prod_zona_id)
- `ContenidoPortal` → contenido_portal (PK contenido_id)
- `OrganizacionMedia` → organizacion_media (PK media_id)
- `OrganizacionDetalle` → organizacion_detalle (PK detalle_id)

Pagos/descargas (modelo creado, lógica POSPUESTA):
- `UsuarioPerfilPublico`, `PaqueteCreditos`, `CompraCreditos`, `SolicitudDescarga`

Relaciones añadidas a `Organizacion`: `detalle()` (hasOne), `media()` (hasMany).
(`fuentes()` hasMany ya existía.)

Verificado en tinker: todos los modelos consultan sin error (la mayoría con 0 filas porque
aún no se cargan datos; `OrganizacionDetalle` tiene 4 filas semilla).

### 4. Autenticación simplificada a un solo rol
- Seeders ejecutados (seguros, solo insertan en rol/permiso/usuario, no en tablas de datos):
  - `RolPermisoSeeder` → 4 roles, 27 permisos; rol **administrador** recibe los 27 permisos.
  - `UsuarioAdminSeeder` → usuario admin con rol administrador.
- **Credenciales admin (login):**
  - Usuario: `admin`
  - Contraseña: `Admin12345`
  - Correo: `admin@comexhub.local`
  - Nota: el usuario tiene `debe_cambiar_pwd = true` (forzar cambio en primer ingreso).
- `app/Http/Middleware/VerificarPermiso.php`: **passthrough temporal** — hace
  `return $next($request)` al inicio con comentario `// TODO: reactivar permisos fase 7`.
  La lógica original quedó comentada, no borrada.

### Criterios de aceptación — todos cumplidos ✅
- `DB::table('organizacion')->count()` = 4.
- `migrate:status` sin pendientes que recreen tablas.
- Modelos Eloquent consultables (ej. `RankingComercio::count()`).
- Login admin con rol único de todos los permisos.
- Ninguna tabla de dominio borrada ni recreada.

---

## FASE 2 — ETL COMPLETADA ✅ (2026-06-19)

Loaders probados con datos reales (`php artisan ovxel:probar-etl`):

| Loader | Filas | Tabla destino |
|--------|-------|---------------|
| INE Exportaciones V1 | 3/3 | `operacion_comercio_exterior` |
| MERCOSUR País | 5/5 | `serie_comercio_zona` |
| MERCOSUR Item/NCM | 4/4 | `serie_comercio_producto_zona` |
| ALADI Ranking | 3/3 | `ranking_comercio` (incl. confidencial `87------`) |
| FAOSTAT | — | `serie_indicador_agricola` (loader listo, sin Excel aún) |

Migraciones de ajuste creadas: `000001` (amplía tipo_flujo), `000002` (tiempo.mes ≥ 0),
`000003` (tiempo.trimestre/semestre ≥ 0; ranking_comercio.flujo_id/fila_excel/ordinal nullable).
Config: añadido alias `VALOR → valor_fob_usd`.

---

## FASE 3 — API DE CHARTS COMPLETADA ✅ (2026-06-19)

Objetivo: exponer los datos como JSON listo para ApexCharts.

### Hallazgo crítico de esquema
Los servicios de portal (`ResumenPortal`, `RankingPortal`) y el comando
`comexhub:refrescar-vistas` referenciaban vistas `resumen_*` que **NO existen** en
`ovxel_db`. Las vistas materializadas REALES son:
`mv_resumen_anual_ine`, `mv_resumen_mensual_ine`, `mv_resumen_mercosur_zona`.
- Se **corrigió** `RefrescarVistasPortal` para refrescar las 3 vistas reales (antes los
  loaders refrescaban nombres inexistentes que fallaban en silencio → las MV nunca se
  poblaban automáticamente).
- La nueva API se construyó contra el esquema REAL (tablas base + las 3 MV reales).
- ⚠️ Pendiente: `ResumenPortal`/`RankingPortal` (portal Inertia antiguo) siguen apuntando a
  vistas inexistentes; habrá que migrarlos a las MV reales o a `PortalApi` en una próxima fase.

### Implementado
- `app/Servicios/PortalApi.php` — servicio central de consultas + formato ApexCharts.
- Controladores en `app/Http/Controllers/Api/`: `KpiController`, `ChartDataController`,
  `RankingController`, `IndicadoresController`, `FiltroController`.
- `routes/api.php` registrado en `bootstrap/app.php` (`api:` + `apiPrefix: 'api'`).
- Cache `Cache::remember` TTL 600s (charts/kpis) y 1800s (filtros).
- Formato estándar: `{ categorias, series:[{name,data}], meta }`.

### 24 endpoints — todos responden 200 (probados con curl)
- **Con DATOS REALES:** `kpis`, `kpis/{1,2,3}`, `organizaciones`, `organizaciones/{id}`,
  `charts/comercio-mensual`, `charts/evolucion-anual`, `charts/top-productos`,
  `charts/top-paises`, `charts/top-departamentos`, `charts/seccion-arancelaria`,
  `charts/transporte`, `charts/mercosur/{zona,balanza,productos,paises}`,
  `charts/aladi/ranking` (con % acumulado y flag confidencial), `rankings/{productos,paises,departamentos,aladi}`,
  `indicadores` (HHI, cobertura), `filtros/{paises,zonas,secciones,gestiones}`.
- **Estructura vacía documentada (esperan datos):**
  `charts/tnt-evolucion` (el ETL de prueba no mapeó columna TNT),
  `charts/faostat/{poblacion,fertilizantes,subalimentacion,cereales}` (sin Excel FAOSTAT).

### Convenciones API
- Params comunes: `?gestion=YYYY`, `?flujo=exp|imp|ambos`, `?limit=N`, `?zona_id=N`, `?org=N`.
- INE: export = `valor_fob_usd`, import = `valor_cif_frontera_usd`.
- Si no se pasa `gestion`, se usa la más reciente con datos por organización.

---

## FASE 4 — ESQUELETO VISUAL DEL PORTAL COMPLETADA ✅ (2026-06-19)

Objetivo: preparar la estructura visual del portal público (8 secciones), dependencias
de gráficos/mapas y componentes base reutilizables (sin llenar de datos aún → Fase 5).

### Dependencias frontend
- Instaladas: `leaflet`, `@vue-leaflet/vue-leaflet`, `countup.js`.
- Ya estaban: `apexcharts`, `vue3-apexcharts` (registrado global en `app.js` como `<apexchart>`).
- Stack: **Tailwind v4** (config en CSS `@theme`, NO hay `tailwind.config.js`).

### Paleta Ovxel
- Añadida en `resources/css/app.css` (bloque `@theme`) como tokens `--color-ovxel-*`
  (navy, red, red-light, blue, green, gold, orange + grises 50/100/200/600/800) y
  `--color-org-{ine,aladi,mercosur,faostat}`. Generan utilidades `bg-/text-/border-ovxel-*`.
- Helper JS `resources/js/lib/orgColors.js` (COLORES_ORG, PALETA_OVXEL, colorOrg()).

### Navegación (LayoutPublico.vue) — 8 secciones
Inicio · Organizaciones · Rankings · Comparador · Mapa Comercial · Indicadores ·
Línea de Tiempo · Acerca de · [Acceder]. Logo = `public/img/ovxel-mark.svg`.
- **Quitadas** del portal: «Explorar» y «Metodología». Se eliminó `Portal/Explorar.vue`
  y las rutas `/explorar*`. Enlaces viejos repuntados a `/organizaciones`.
- Footer ya traía logo + «Fuente: INE» + «© Ovxel · fines informativos y educativos».

### Rutas (routes/web.php) + páginas Vue
`/`, `/organizaciones`, `/organizaciones/{id}`, `/rankings`, `/comparador`,
`/mapa-comercial`, `/indicadores`, `/linea-de-tiempo`, `/acerca-de` → todas **200**.
Páginas nuevas: `Organizaciones` (consume API real con OrgCard + skeleton),
`OrganizacionDetalle` (banner por color + KPIs reales + ExportModal),
`Comparador`, `MapaComercial` (mapa Leaflet operativo), `Indicadores` (gráfico de prueba
ApexCharts), `LineaDeTiempo`.

### Componentes base (resources/js/Components/)
- `Charts/`: `BaseApexChart`, `BarChart`, `LineChart`, `AreaChart`, `PieChart`,
  `DonutChart`, `TreemapChart`, `RadarChart` (wrappers que reciben `{series, categorias, opciones}`).
- `UI/`: `KPICard` (CountUp + sparkline), `OrgCard`, `RankingTable`, `FilterPanel`,
  `ExportModal` (MAQUETA visual, sin cobro — botón abre «Regístrate para descargar»).
- `Composables/useChartData.js`: fetch a `/api/v1/*` con loading/error y anti-race.

### Fix importante (deuda de Fase 3 saldada)
`ResumenPortal` y `RankingPortal` apuntaban a vistas `resumen_*` inexistentes → el home (`/`)
y `/rankings` daban **500**. Reescritos para leer de `operacion_comercio_exterior` con la
convención de valor (export=`valor_fob_usd`, import=`valor_cif_frontera_usd`). Ahora ambos
devuelven datos reales y todo el portal navega sin errores.

### Verificación
- `npm run build` compila sin errores (Leaflet, ApexCharts y todas las páginas empaquetadas;
  solo warning informativo de tamaño de chunk).
- Las 9 rutas del portal responden 200; `/explorar` responde 404 (removida correctamente).

---

## PENDIENTE (próximas fases)
- FAOSTAT: cargar Excel cuando estén disponibles (loader y endpoints ya listos).
- Fase 7: reactivar verificación granular de permisos en `VerificarPermiso`;
  activar lógica real de `ExportModal` (créditos/descargas).
- Considerar code-splitting del bundle `app.js` (>500 kB) y de ApexCharts.

---

## FASE 5 — PORTAL PÚBLICO CON DATOS REALES COMPLETADA ✅ (2026-06-19)

Objetivo: llenar las páginas públicas con consumo real de `/api/v1/*`, estados de carga,
descarga PNG por gráfico y estados vacíos elegantes cuando una fuente no tiene series.

### Backend adicional para Fase 5
- Nuevos endpoints públicos:
  - `GET /api/v1/charts/mapa-flujos`
  - `GET /api/v1/comparador`
  - `GET /api/v1/timeline`
  - `GET /api/v1/filtros/productos`
- `PortalApi` ampliado con:
  - `mapaFlujos()` para mapa mundial por país (INE real, con exp/imp/saldo)
  - `comparador()` para `modo=anio|pais|producto`
  - `timeline()` con serie anual real + hitos narrativos
  - `filtroProductos()` para selects del comparador
- `topPaises()` y `topDepartamentos()` ahora devuelven también `items` enriquecidos
  (útiles para tablas/mapas, sin romper los gráficos existentes).
- `rankingDinamico()` y `Api\RankingController` ampliados para soportar `aduanas`.

### Páginas del portal conectadas a datos REALES

#### 1. Inicio (`Portal/Inicio.vue`)
- Hero mantenido.
- CTA “Explorar datos”.
- 4 KPIs reales desde `GET /api/v1/kpis` con animación.
- Gráfico principal de comercio mensual desde `GET /api/v1/charts/comercio-mensual`.
- Preview de organizaciones desde `GET /api/v1/organizaciones`.
- Top productos y top destinos desde `GET /api/v1/charts/top-productos` y `top-paises`.
- Evolución histórica anual desde `GET /api/v1/charts/evolucion-anual`.
- Textos editoriales del hero cargados por Inertia desde `contenido_portal`.

#### 2. Organizaciones (`Portal/Organizaciones.vue`)
- Grid real de organizaciones desde `GET /api/v1/organizaciones`.
- `OrgCard` con color, descripción, formatos visuales y botón “Ver detalles”.

#### 3. Detalle de organización (`Portal/OrganizacionDetalle.vue`)
- Cabecera real desde `GET /api/v1/organizaciones/{id}`.
- Panel lateral con `ExportModal` SOLO visual (sin lógica de cobro).
- KPIs reales por organización.
- Gráficos diferenciados por fuente:
  - INE: mensual, sección arancelaria, top productos, top países, top departamentos, transporte, TNT y evolución anual.
  - MERCOSUR: zonas, balanza, productos NCM, países.
  - ALADI: Pareto + tabla completa.
  - FAOSTAT: estados vacíos elegantes cuando no hay series.

#### 4. Rankings (`Portal/Rankings.vue`)
- Tabs/secciones con datos reales de:
  - INE productos exp./imp.
  - INE países destino/origen.
  - INE departamentos.
  - INE aduanas.
  - MERCOSUR productos.
  - MERCOSUR zonas.
  - ALADI.
- FAOSTAT visible con estado vacío elegante mientras no haya carga.
- Tablas + visualizaciones desde `/api/v1/rankings/*`, `/api/v1/charts/mercosur/*`,
  `/api/v1/charts/aladi/ranking` y `/api/v1/charts/faostat/*`.

#### 5. Comparador (`Portal/Comparador.vue`)
- Consumo real de `GET /api/v1/comparador`.
- Modos disponibles:
  - Año A vs Año B
  - País A vs País B
  - Producto A vs Producto B
- Selectores poblados desde `/api/v1/filtros/paises` y `/api/v1/filtros/productos`.

#### 6. Mapa Comercial (`Portal/MapaComercial.vue`)
- Mapa mundial alimentado por `GET /api/v1/charts/mapa-flujos`.
- Mini mapa de Bolivia alimentado por `GET /api/v1/charts/top-departamentos`.
- Popup por país con exp/imp/saldo.
- Tabla de respaldo con los mismos datos reales.

#### 7. Indicadores (`Portal/Indicadores.vue`)
- Scorecards reales desde `GET /api/v1/indicadores`.
- Contexto temporal desde `GET /api/v1/charts/evolucion-anual`.
- Tooltips con fórmula y sparkline en la tarjeta de balanza.

#### 8. Línea de Tiempo (`Portal/LineaDeTiempo.vue`)
- Consumo real de `GET /api/v1/timeline`.
- Fondo con balanza anual real.
- Expansión por año con exportaciones/importaciones/balanza.

#### 9. Acerca de (`Portal/Acerca.vue`)
- Bloque editorial principal desde `contenido_portal` vía Inertia.
- Fuentes reales desde `organizacion_detalle`.
- Glosario, FAQ y equipo servidos como props de Inertia.

### Estados vacíos elegantes (sin inventar datos)
- FAOSTAT en detalle de organización.
- FAOSTAT en Rankings.
- TNT en INE si la columna no fue mapeada/cargada.
- Mapas y comparadores muestran mensajes de “sin datos” si la combinación de filtros no devuelve series.

### Requisitos transversales cumplidos
- Skeletons y estados de carga en páginas clave.
- Responsive en páginas públicas nuevas/actualizadas.
- Descarga PNG por gráfico desde `ChartCard`.
- `ExportModal` visual profesional, sin cobro real.
- `npm run build` ejecutado con éxito.

### Lo que sigue pendiente después de Fase 5
- **FAOSTAT real**: cargar los Excel para activar sus gráficos/rankings.
- **Créditos/descargas reales**: conectar `ExportModal` a autenticación + cobro (Fase 7).
- **Optimización**: el build compila sin errores, pero ApexCharts mantiene chunks grandes (>500 kB);
  conviene aplicar code-splitting posterior.

---

## FASE 6 — PANEL ADMIN ESENCIAL COMPLETADO ✅ (2026-06-19)

Objetivo: dejar operativo el flujo mínimo del panel admin para subir Excel, revisar el
preview canónico, procesar ETL y monitorear el estado real de cargas; mantener el resto de
módulos visibles pero claramente maquetados o en solo lectura.

### 1. Sidebar admin: activo vs maquetado
- `resources/js/Layouts/LayoutAdmin.vue` actualizado con badges visuales por módulo:
  - **Activos:** Inicio, Dashboard, Cargas, Catálogos
  - **Maquetados / próximos:** Explorador, Reportes, Calidad, Organizaciones, Perfiles,
    Usuarios, Roles, Configuración, Bitácora
- No se rompió la navegación existente; los módulos siguen visibles.

### 2. Cargas — flujo principal operativo
- `resources/js/Pages/Cargas/Create.vue` reescrito en 3 pasos:
  - selección de organización + flujo + año + drag & drop del archivo
  - preview del perfil detectado y tabla de mapeo
  - preview de primeras filas **mapeadas a campos canónicos**
  - procesamiento con seguimiento visual del job
- `app/Http/Controllers/CargaController.php` ampliado con:
  - `previsualizar()` acepta detección automática del `tipo_flujo` fuera de INE
  - `store()` devuelve JSON cuando la UI lo necesita (sin salir de la pantalla)
  - `estado()` para polling de `carga_archivo` + `proceso_etl`
  - `refrescarVistas()` para refresco manual de vistas materializadas tras una carga completada
- Nuevas rutas admin:
  - `GET /admin/cargas/{carga}/estado`
  - `POST /admin/cargas/{carga}/refrescar-vistas`
- `resources/js/Pages/Cargas/Index.vue` actualizado:
  - historial con estado real (`PENDIENTE/PROCESANDO/COMPLETADO/FALLIDO`)
  - conteo de filas leídas/válidas/error
  - polling automático si hay cargas vivas
  - acceso a incidencias si hubo errores
  - botón “Refrescar dashboards” cuando la carga terminó

### 3. Dashboard admin con KPIs reales
- `app/Http/Controllers/DashboardController.php` ahora entrega datos operativos reales:
  - total de cargas
  - cargas pendientes/procesando
  - cargas fallidas
  - total de usuarios
  - registros por organización
  - registros por tabla de hechos
  - últimas cargas
  - gráfico de cargas por mes
- `resources/js/Pages/Dashboards/Index.vue` reemplazado por una vista operativa
  enfocada en administración/ETL, no en analytics público.

### 4. Catálogos en solo lectura
- `resources/js/Pages/Admin/Catalogos/Index.vue` simplificado a:
  - navegación entre dimensiones
  - búsqueda
  - paginación
  - tabla de lectura
- El CRUD completo queda postergado; visualmente el módulo deja claro que está en
  **modo solo lectura**.

### 5. Permisos y acceso admin
- `app/Http/Middleware/VerificarPermiso.php` se mantiene en **passthrough** temporal,
  tal como se definió en Fase 1.
- El rol único con todos los permisos sigue habilitando el acceso a todo el panel.

### Verificación
- `php artisan route:list` confirma las nuevas rutas:
  - `cargas.estado`
  - `cargas.refrescar-vistas`
  - `dashboards.index`
  - `catalogos.index`
- `npm run build` compila sin errores. ✅

### Estado funcional al cierre de Fase 6
- **Activos de verdad:**
  - Dashboard admin
  - Cargas (preview, cola, seguimiento, refresco de vistas)
  - Catálogos (solo lectura)
- **Visibles pero maquetados / simplificados:**
  - Explorador
  - Reportes
  - Calidad
  - Organizaciones
  - Perfiles
  - Usuarios
  - Roles
  - Configuración
  - Bitácora

### Pendiente después de Fase 6
- CRUD real de catálogos.
- Flujos completos de escritura para organizaciones, usuarios, roles y configuración.
- Reportes y descargas con lógica final.
- Revisión fina de loaders FAOSTAT cuando lleguen los Excel fuente.
