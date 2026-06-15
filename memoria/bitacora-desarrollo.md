# ComexHub — Bitacora de desarrollo

Registro cronologico. Al terminar cada tarea: que se hizo, archivos creados/modificados
y decisiones tomadas.

---

## Tarea 1 — Preparacion del entorno, memoria y conexion (2026-06-03)

### Estado: COMPLETADA

### Que se hizo
1. **Extensiones PHP de PostgreSQL**: en XAMPP (`C:\xampp\php\php.ini`) estaban
   comentadas; se habilitaron `extension=pdo_pgsql` y `extension=pgsql`.
2. **Conexion a PostgreSQL** verificada: base `brisa` en `127.0.0.1:5432`, usuario
   `postgres`, las 39 tablas del esquema ya estaban creadas por el equipo. PostgreSQL 18.2.
3. **.env** reconfigurado: `DB_CONNECTION=pgsql`, host/puerto/base/usuario/contrasena,
   `DB_SEARCH_PATH=public`. App: `APP_NAME=ComexHub`, locale `es`, URL `http://localhost:8000`.
   (Queue, session y cache ya venian con driver `database`.)
4. **Migraciones internas de Laravel** ejecutadas contra PostgreSQL: `sessions`,
   `cache`/`cache_locks`, `jobs`/`job_batches`/`failed_jobs`. Se EDITO la migracion
   `0001_01_01_000000_create_users_table.php` para NO crear la tabla `users` (se usa
   la tabla de negocio `usuario`); solo crea `sessions`.
5. **Inertia + Vue 3 + Tailwind v4 sobre Vite 7**:
   - Composer: `inertiajs/inertia-laravel ^2.0`.
   - npm: `vue ^3.5`, `@inertiajs/vue3 ^2.0`, `@vitejs/plugin-vue ^6.0` (la v5 chocaba con Vite 7).
   - `vite.config.js`: plugin de Vue + Tailwind + laravel-vite-plugin.
   - `resources/js/app.js`: `createInertiaApp` con layout persistente por defecto (AppLayout).
   - `resources/views/app.blade.php`: plantilla raiz con `@inertia` y `@vite`.
   - `app/Http/Middleware/HandleInertiaRequests.php`: comparte `auth.usuario`, `flash`, `app`.
   - `bootstrap/app.php`: se registra el middleware de Inertia en el grupo `web`.
6. **Layout base** `resources/js/Layouts/AppLayout.vue`: barra superior azul,
   menu lateral colapsable (Inicio, Explorador, Dashboards, Cargas, Reportes,
   Catalogos, Administracion), area de contenido y mensajes flash. Paleta `marca-*`
   (azul) definida en `resources/css/app.css`.
7. **Pagina Inicio** `resources/js/Pages/Inicio.vue` + ruta `/` (`routes/web.php`):
   muestra "ComexHub", cuenta de organizaciones y estado de la conexion. La ruta
   ejecuta una consulta real `DB::table('organizacion')->count()` como prueba de conexion.

### Verificacion (criterios de aceptacion)
- `npm run build`: OK (759 modulos, sin errores).
- `php artisan serve` + `curl /`: HTTP 200, Inertia renderiza `Inicio`,
  `estadoBd: "conectada"`, `organizaciones: 0` (tabla vacia, consulta exitosa).
- Carpeta `memoria/` creada con los 4 archivos.

### Decisiones
- El proyecto venia con **Laravel 12**, no 11. Se mantiene 12 (compatible con el plan).
- Autenticacion sobre la tabla `usuario` (no `users`); por eso se vacio la migracion default.
- Layout persistente de Inertia asignado por defecto en `app.js`; las paginas sin
  layout (ej. login, Tarea 3) usaran `defineOptions({ layout: null })`.

### Pendiente para siguientes tareas
- Ver `pendientes.md`.

---

## Tarea 2 — Modelos Eloquent del esquema existente (2026-06-03)

### Estado: COMPLETADA

### Que se hizo
- Se elimino `app/Models/User.php` y se crearon **39 modelos Eloquent**, uno por tabla,
  con `$table`, `$primaryKey`, `$incrementing`, `$keyType` y `$timestamps=false`.
- PKs no se llaman `id`: declaradas explicitamente. Casos especiales:
  - `Sesion` (UUID): `$keyType='string'`, `$incrementing=false`.
  - `Configuracion` (PK `clave` varchar): `$keyType='string'`, `$incrementing=false`.
  - Pivots `PaisZona`, `RolPermiso`, `UsuarioRol`: extienden `Pivot`, PK compuesta.
- **Relaciones**: jerarquia producto (seccion->capitulo->producto, + hasOneThrough
  `Producto::seccion()`), actividad (grupo->ciiu), geografia (zona->pais + puente
  `pais_zona`), todas las FK de `operacion_comercio_exterior`, y usuario->roles->permisos.
- **Usuario** implementa `Authenticatable`; `getAuthPassword()`/`getAuthPasswordName()`
  -> `hash_contrasena`. Metodos `codigosPermisos()`, `tienePermiso()`, `tieneRol()`.
- `config/auth.php`: provider `users` ahora usa `App\Models\Usuario`.
- Casts: JSONB->array (atributos_extra, valores auditoria), decimal:2 en metricas, booleanos, fechas.

### Verificacion (criterios de aceptacion)
- Script de prueba (tinker/bootstrap): consulta `Producto::with('capitulo.seccion')` y
  `OperacionComercioExterior::with(['producto','pais.zona','tiempo',...])` SIN error.
- PK correctas confirmadas (producto_id, usuario_id). `getAuthPasswordName=hash_contrasena`.
- `ls app/Models | wc -l` = 39.

### Decisiones
- `$timestamps=false` en todos: ninguna tabla tiene el par created_at/updated_at de Laravel
  (usan creado_en, fecha_creacion, etc., mapeados como atributos casteados a fecha).
- Equivalencia completa tabla->modelo->PK documentada en `estructura-bd.md`.

---

## Tarea 3 — Autenticacion, usuarios, roles y permisos (2026-06-03)

### Estado: COMPLETADA

### Que se hizo
- **Seeders** (Laravel, no SQL): `ConfiguracionSeeder` (5 parametros), `RolPermisoSeeder`
  (27 permisos en 12 modulos + 4 roles base + matriz rol x permiso), `UsuarioAdminSeeder`
  (usuario `admin` con todos los permisos). `DatabaseSeeder` los orquesta. Idempotentes
  (updateOrCreate / sync). UserFactory eliminado.
- **AutenticacionController** (`Auth/`): login/logout con Inertia. Registra cada intento en
  `intento_acceso` (exito, IP, motivo). Bloquea tras N intentos fallidos en una ventana
  (configuracion `max_intentos_login` y `ventana_bloqueo_minutos`). Valida cuenta activa.
  Actualiza `ultimo_acceso`. Usa `Hash::check` contra `hash_contrasena`.
- **VerificarPermiso** middleware (alias `permiso`): `->middleware('permiso:codigo')`,
  deriva del usuario via roles->permisos (`tienePermiso`). 403 si falta.
- **ExpirarSesionInactiva** middleware (grupo web): expira la sesion segun
  `configuracion.sesion_minutos_expira`, guardando `ultima_actividad` en la sesion.
- **UsuarioController**: index (busqueda + paginacion server-side), store, update,
  cambiarEstado (activar/desactivar). Contrasenia siempre bcrypt. Asignacion de roles (sync).
- **RolController**: index (roles + permisos agrupados por modulo), store, update con
  matriz rol x permiso (sync).
- **Paginas Vue**: `Auth/Login.vue` (sin layout), `Admin/Usuarios/Index.vue` (tabla +
  modal CRUD + roles), `Admin/Roles/Index.vue` (lista de roles + matriz de permisos).
- **AppLayout**: menu lateral ahora filtra items por permiso (`auth.usuario.permisos`,
  compartidos por HandleInertiaRequests). Enlaces a Usuarios y Roles.
- **Rutas**: `/login` (guest), resto bajo `auth`; admin con `permiso:*` por accion.
  La pagina Inicio ahora esta protegida (requiere auth).
- **config/auth.php**: provider `users` -> `App\Models\Usuario` (hecho en Tarea 2).

### Verificacion (criterios de aceptacion)
- Script contra PostgreSQL real:
  - Login fallido -> ValidationException + fila en `intento_acceso` (exito=false).
  - Login exitoso -> `Auth::check()`=true, usuario=admin, fila exito=true, ultimo_acceso.
  - Bloqueo tras 5 intentos fallidos -> rechazado con mensaje "Cuenta bloqueada".
  - VerificarPermiso: 200 con `usuario.ver`, 403 con permiso inexistente.
- `GET /` sin sesion -> 302 a `/login`. `GET /login` -> 200 (render Inertia).
- `npm run build` OK (Login, Usuarios/Index, Roles/Index compilan).
- `route:list`: rutas admin con middleware `auth` + `permiso:*`.

### Credenciales del administrador inicial
- Usuario: **admin** / Contrasenia: **Admin12345** (hash bcrypt en `usuario.hash_contrasena`).
  `debe_cambiar_pwd=true`. Correo: admin@comexhub.local.

### Modelo de permisos
- 27 permisos con formato `modulo.accion` (ej. `usuario.ver`, `reporte.exportar`).
- 4 roles: administrador (todos), analista, consultor, invitado. Ver `RolPermisoSeeder`.
- Helpers en Usuario: `codigosPermisos()`, `tienePermiso($codigo)`, `tieneRol(...$nombres)`.

### Pendiente
- "debe_cambiar_pwd": el flujo de cambio obligatorio de contrasenia al primer ingreso
  aun no fuerza el redireccion (se trata en Tarea 10 / mejora).

---

## Tarea 4 — Organizaciones y perfiles de mapeo (2026-06-03)

### Estado: COMPLETADA

### Que se hizo
- **config/comexhub.php**: lista unica de **campos canonicos** (27, con etiqueta/grupo/tipo)
  + tabla de **alias conocidos** (KILBRU/KILOS->peso_bruto_kg, PFINO/FINO->peso_fino_kg,
  CUCI3/CUCIR3->codigo_cuci, etc.). Compartida por backend, detector y frontend.
- **App\Servicios\DetectorPerfil**: normaliza nombres (mayusculas, sin acentos/espacios),
  `sugerirCampo()` por alias, y `detectar(cabeceras, org?, flujo?)` que rankea perfiles por
  interseccion de columnas (score = 60% cobertura del perfil + 40% del archivo).
- **OrganizacionController**: index + store + update (CRUD).
- **PerfilMapeoController**: index, store, edit (tabla editable de mapeo_columna),
  update (cabecera), guardarColumnas (reemplazo completo idempotente), detectar (JSON).
- **OrganizacionIneSeeder**: INE como organizacion #1 + 2 perfiles base (INE-EXPO-base 17
  cols, INE-IMPO-base 20 cols) con mapeo de columnas usando los alias documentados.
  NOTA: mapeo base; validar/completar con las cabeceras reales del equipo.
- **Paginas Vue**: `Admin/Organizaciones/Index` (CRUD modal), `Admin/Perfiles/Index`
  (lista + detector interactivo via axios), `Admin/Perfiles/Edit` (tabla editable:
  [origen]->[campo canonico select]+[guardar]+[a extra]+[nota], agregar/eliminar filas).
- **AppLayout**: items Organizaciones y Perfiles en el menu (por permiso).
- **Rutas** bajo auth con `permiso:organizacion.*` y `permiso:perfil.*`.

### Verificacion (criterios de aceptacion)
- organizaciones=1, perfiles=2, mapeos=37 sembrados.
- Detector con cabeceras de exportacion -> perfil#1 (EXPORTACION) score 86.3%, 15/17.
  Identifica columnas extra y `sugerirCampo('KILBRU')=peso_bruto_kg`, `('CUCIR3')=codigo_cuci`.
- `npm run build` OK (Organizaciones, Perfiles Index/Edit compilan).

### Como funciona el mapeo
- Un perfil = organizacion + tipo_flujo + etiqueta_version. Sus filas `mapeo_columna`
  traducen `nombre_columna_origen` -> `campo_canonico`, con flags `guardar` (al campo),
  `a_extra` (al JSONB atributos_extra) y `nota`. El detector compara por NOMBRE normalizado,
  el orden fisico no importa. Los alias permiten reconocer columnas que cambian de nombre por anio.

---

## Tarea 5 — Carga de archivos con seleccion de columnas (2026-06-03)

### Estado: COMPLETADA

### Que se hizo
- **OpenSpout 4.28** instalado (lector streaming XLSX/CSV, bajo consumo de memoria).
- **App\Servicios\LectorArchivo**: `leerCabecerasYMuestra()` (cabeceras + N filas) e
  `iterarAsociativo()` (generator fila->valor para el ETL). Soporta xlsx/xlsm/csv/txt.
- **App\Jobs\ProcesarCargaArchivo** (ShouldQueue, timeout 3600, tries 1): recibe carga_id
  y delega en `ProcesadorEtl` (stub; ETL completo en Tarea 6).
- **CargaController**:
  - `index`: historial de cargas paginado.
  - `create`: pantalla de carga (organizacion, tipo_flujo, archivo).
  - `previsualizar`: guarda el archivo en `cargas_tmp/{uuid}.ext`, lee cabeceras + 20 filas,
    detecta perfil y devuelve propuesta por columna (campo_canonico del perfil o sugerido por
    alias; marca `desconocida` las que no estan en el perfil). JSON.
  - `store`: registra `carga_archivo` (PENDIENTE), mueve el archivo a `cargas/{id}/datos.ext`,
    escribe `cargas/{id}/mapeo.json` con el mapeo resuelto (solo columnas marcadas guardar/extra)
    y despacha el Job. Token validado por regex (anti path traversal).
- **Paginas Vue**: `Cargas/Index` (historial con badges de estado) y `Cargas/Create`
  (paso 1: seleccion; paso 2: deteccion + tabla de mapeo editable + muestra + confirmar).
  Aviso visual para columnas desconocidas.
- **Rutas** `/cargas*` con `permiso:carga.ver` / `carga.crear`.

### Verificacion (criterios de aceptacion)
- LectorArchivo lee 18 cabeceras + muestra del CSV demo; deteccion perfil#1 (EXPO) 97.8%;
  iteracion streaming OK.
- Flujo previsualizar->store (invocando el controlador real con UploadedFile):
  - previsualizar: 18 cols, perfil#1, `COLUMNA_RARA` marcada desconocida.
  - store: carga #1 PENDIENTE, archivo en `storage/app/private/cargas/1/datos.csv`,
    `mapeo.json` con 18 columnas (1 a_extra), **1 job encolado** en tabla `jobs`.
- `npm run build` OK.

### Donde se guardan los archivos
- Temporal (previsualizacion): `storage/app/private/cargas_tmp/{uuid}.{ext}`.
- Definitivo (al confirmar): `storage/app/private/cargas/{carga_id}/datos.{ext}` +
  `mapeo.json` (mapeo resuelto que usara el ETL).
- Fixture para Tarea 6: existe la **carga #1** (CSV demo de exportacion) lista para procesar.

---

## Tarea 6 — ETL: procesamiento por lotes e insercion del microdato (2026-06-03)

### Estado: COMPLETADA

### Que se hizo
- **ReglaValidacionSeeder**: 8 reglas base. La columna `expresion` lleva un codigo que el
  ETL interpreta: `no_nulo` (gestion, mes, codigo_nandina, codigo_pais -> ERROR),
  `rango_mes` (ERROR), `no_negativo` (fob, cif, peso -> ADVERTENCIA).
- **App\Servicios\ProcesadorEtl** (reemplaza el stub):
  - Lee `cargas/{id}/mapeo.json` (mapeo resuelto) y `datos.{ext}` en streaming (LectorArchivo).
  - Resuelve/crea la `fuente_datos` de la organizacion (todas las dimensiones se asocian a ella).
  - **Idempotencia**: borra hechos e incidencias previas de la carga antes de insertar.
  - Itera por filas: traduce a campos canonicos (segun mapeo) + acumula `a_extra` para JSONB.
  - **Validacion**: aplica regla_validacion; ERROR -> no inserta + incidencia; ADVERTENCIA ->
    inserta + incidencia. Todo en `incidencia_calidad` con numero_fila.
  - **Resolucion de dimensiones** "buscar o crear" con **cache en memoria** (array por clave
    natural) para no repetir consultas: tiempo, tipo_operacion, flujo, producto (con jerarquia
    seccion->capitulo derivada de la NANDINA si falta), cuci, gce, ciiu (con grupo_actividad),
    pais (con zona), departamento, medio, via, tnt, cuode, aduana. Descripciones placeholder
    cuando el archivo solo trae el codigo (corregibles en Tarea 10).
  - **Insercion masiva** por lotes de `lote_etl_filas` (config, def. 1000) con
    `DB::table(...)->insert($lote)`.
  - Actualiza contadores (`total_filas_leidas/validas/error`) y estado COMPLETADO/FALLIDO en
    carga_archivo; registra avance y cierre en `proceso_etl` (EN_EJECUCION->EXITOSO/FALLIDO).
  - Parser numerico tolerante (separadores de miles/decimales).

### Verificacion (criterios de aceptacion)
- Carga #1 (CSV demo): PENDIENTE->COMPLETADO, leidas=3 validas=3 error=0, 3 hechos insertados.
  Dimensiones creadas (pais=2, producto=3, tiempo=2, cuci=3, aduana=2). proceso_etl EXITOSO.
  `atributos_extra` JSONB poblado ({"COLUMNA_RARA":"xx"}).
- **Idempotencia**: reprocesar carga #1 (directo Y via `queue:work`) mantiene 3 hechos.
- **Validaciones** (carga #2): fila sin NANDINA -> ERROR no insertada; fila mes=13 -> ERROR
  no insertada; FOB negativo -> ADVERTENCIA registrada. leidas=3 validas=1 error=2,
  3 incidencias en incidencia_calidad.
- **Cola real**: el Job despachado se ejecuto con `php artisan queue:work --once` (DONE,
  jobs=0, failed=0).

### Nota
- Archivo real del INE aun pendiente (equipo). Probado con CSV sinteticos que replican la
  estructura. El ETL escala por lotes/streaming para ~400.000 filas.

### Dataset demo
- Se generaron cargas #3 (EXPO) y #4 (IMPO) con 288 filas validas c/u (2022-2024, 12 meses,
  5 paises, 5 productos). Total **580 hechos** para probar explorador y dashboards.

---

## Tarea 7 — Explorador con filtros dinamicos (2026-06-03)

### Estado: COMPLETADA

### Que se hizo
- **App\Servicios\ConsultaExplorador**: consulta base sobre `operacion_comercio_exterior`
  (join `tiempo`) filtrada por `organizacion_id`. Metodos:
  - `aplicar()`: aplica filtros (directos por columna de hechos; zona via subquery a pais;
    capitulo/seccion via subquery a producto; gestion/mes via tiempo; busqueda libre ilike
    sobre descripcion de producto/pais/aduana). Parametro `$excepto` para el facetado.
  - `totales()`: COUNT + SUM(valor FOB+CIF) + SUM(peso bruto) agregados en PostgreSQL.
  - `tabla()`: paginacion **del lado servidor** (forPage) con joins de etiquetas.
  - `facetas()`: para cada faceta recalcula conteos por opcion aplicando todos los filtros
    EXCEPTO el propio (faceted search), via GROUP BY.
- **ExploradorController**: `index` (opciones de cada dimension + org por defecto) y
  `consultar` (JSON: totales + tabla + facetas).
- **Componente FacetaFiltro.vue**: multiseleccion colapsable con buscador interno y conteo
  por opcion; atenua opciones con 0 resultados.
- **Pagina Explorador/Index.vue**: panel lateral con 15 facetas + selector de organizacion,
  busqueda libre, 3 KPIs (registros, valor, peso), tabla paginada server-side. Re-consulta
  reactiva (watch) con debounce en la busqueda. Alias `@` -> resources/js anadido a Vite.
- **Rutas** `/explorador` y `/explorador/consultar` con `permiso:explorador.ver`.

### Verificacion (criterios de aceptacion)
- Sin filtros: 580 registros, valor ~880M USD, peso ~147M kg, tabla 24 paginas de 25.
- Filtro combinado (gestion 2024 + Exportacion) -> 100 registros; faceta `gestion` recalculada
  excluyendo su filtro, `mes` recalculada con ambos -> **contadores facetados correctos**.
- Busqueda libre 'oro' -> 112 registros.
- Paginacion 100% server-side (nunca trae las 580/400.000 filas al frontend).

### Consultas de agregacion
- Totales: `SUM(COALESCE(fob,0)+COALESCE(cif_frontera,0))`, `SUM(COALESCE(peso_bruto,0))`.
- Facetas: `GROUP BY <dimension>` con los filtros activos menos el propio. Indices existentes
  (idx_oce_* y el compuesto org/tiempo/pais/producto) sostienen el rendimiento.

---

## Tarea 8 — Dashboards, graficos e indicadores (2026-06-03)

### Estado: COMPLETADA

### Que se hizo
- **ApexCharts** (`apexcharts` + `vue3-apexcharts`) registrado en `app.js`.
- **App\Servicios\AgregadorDashboard**: todas las agregaciones en PostgreSQL (GROUP BY/SUM).
  Convencion clave: valor de EXPORTACION = `valor_fob_usd`, valor de IMPORTACION =
  `valor_cif_frontera_usd` (asi los puebla el ETL), lo que permite separar flujos.
- **DashboardController**: `index` (org + gestiones) y `datos` (JSON con KPIs + 8 series).
- **Pagina Dashboards/Index.vue**: 7 pestañas tematicas (General, Exportaciones,
  Importaciones, Por pais, Por producto, Balanza comercial, Logistico), 6 tarjetas KPI
  siempre visibles, y graficos ApexCharts (columna+linea, barras horizontales, donut, pie).
  Selector de organizacion y gestion; recarga reactiva.
- **Rutas** `/dashboards` y `/dashboards/datos` con `permiso:dashboard.ver`.

### Indicadores y sus formulas
- **valor_total** = SUM(COALESCE(fob,0) + COALESCE(cif_frontera,0)).
- **valor_exportacion** = SUM(COALESCE(fob,0)); **valor_importacion** = SUM(COALESCE(cif_frontera,0)).
- **balanza_comercial** = valor_exportacion - valor_importacion.
- **peso_bruto/neto** = SUM(COALESCE(peso_*_kg,0)).
- **precio_implicito** ($/kg) = valor_total / peso_neto (si peso_neto > 0).
- **variacion_interanual** (%) = (valor_total[g] - valor_total[g-1]) / valor_total[g-1] * 100.
- **participacion_pais** (%) = valor_pais / SUM(valor top-N) * 100.

### Series (todas GROUP BY en SQL)
- evolucion_mensual (gestion+mes), evolucion_anual (expo/impo/balanza por gestion),
  top_paises (10), top_productos (10), distribucion_zona, distribucion_departamento,
  participacion_pais, distribucion_medio (logistico).

### Verificacion (criterios de aceptacion)
- KPIs 2024: valor 292,5M, expo 156,6M, impo 135,9M, balanza +20,75M, precio 6.09 $/kg,
  var. interanual -1.0%. **Balanza KPI coincide con calculo manual** (SI).
- participacion_pais suma 100%. evolucion_mensual 12 filas, anual 3 gestiones.
- Graficos se sincronizan con el selector de organizacion/gestion.

### Nota
- Las etiquetas de pais/producto muestran placeholders ("Pais 724") porque el dataset demo
  solo traia codigos; con datos reales o correccion de catalogo (Tarea 10) muestran el nombre.

---

## Tarea 9 — Reportes y exportacion (2026-06-03)

### Estado: COMPLETADA

### Que se hizo
- **barryvdh/laravel-dompdf** instalado (PDF). XLSX/CSV via OpenSpout (ya instalado).
- **App\Servicios\GeneradorReporte**: catalogo de 5 reportes predefinidos con sus columnas:
  `por_seccion`, `por_capitulo`, `por_pais`, `por_departamento`, `balanza_mensual`.
  Cada uno agrega con GROUP BY respetando parametros (organizacion, flujo, rango de gestiones)
  y devuelve titulo + columnas + filas + resumen (totales).
- **App\Servicios\ExportadorReporte**: `descargar($reporte,$formato)`:
  - xlsx/csv -> OpenSpout Writer en streaming a `php://output` (StreamedResponse).
  - pdf -> dompdf con la vista `resources/views/reportes/pdf.blade.php` (A4 horizontal).
- **ReporteController**: index (catalogo + opciones), generar (vista previa JSON),
  exportar (GET, descarga el archivo).
- **Pagina Reportes/Index.vue**: selector de reporte + parametros (organizacion, flujo,
  gestion desde/hasta), tabla con resumen y botones Excel/CSV/PDF.
- **Rutas**: `/reportes` (reporte.ver), `/reportes/generar` (reporte.ver),
  `/reportes/exportar` (reporte.exportar).

### Verificacion (criterios de aceptacion)
- 5 reportes generan filas correctas: por_pais (5), por_capitulo (4), balanza_mensual (36).
  Valor total reportes = 879.779.063 (coincide con el explorador, consistencia cruzada).
- Exportacion: PDF generado (magic `%PDF`, ~878KB), XLSX generado (4.6KB). CSV analogo.

### Nota sobre volumenes grandes
- Los reportes predefinidos son AGREGADOS (decenas/cientos de filas), por lo que la
  exportacion es sincrona. La exportacion a nivel microdato (cientos de miles de filas)
  deberia despacharse a un Job en cola (pendiente, ver pendientes.md); el patron de Job ya
  existe (ProcesarCargaArchivo) y se reutilizaria.

---

## Tarea 10 — Bitacora, configuracion, catalogos y calidad (2026-06-03)

### Estado: COMPLETADA

### Que se hizo
- **App\Servicios\Auditoria**: `registrar(accion, entidad, registro, anteriores, nuevos)` que
  inserta en `bitacora_auditoria` con usuario (Auth::id), IP y valores JSONB. Integrada en:
  login/logout, carga registrada, ETL completado, configuracion, catalogos, exportacion de
  reportes e incidencias tratadas.
- **BitacoraController** + `Admin/Bitacora/Index.vue`: consulta con filtros (accion, entidad,
  rango de fechas), paginada (solo `bitacora.ver`).
- **ConfiguracionController** + `Admin/Configuracion/Index.vue`: edicion de parametros del
  sistema (audita los cambios).
- **CatalogoController** + `Admin/Catalogos/Index.vue`: CRUD/edicion con busqueda para 7
  catalogos (pais, producto, aduana, departamento, zona, medio, CUCI). Audita cada edicion.
- **CalidadController** + `Admin/Calidad/Index.vue` y `Detalle.vue`: tablero por carga con
  conteo de incidencias; detalle con marcado de tratamiento (CORREGIDO/ACEPTADO/DESCARTADO).
- **Menu** completo con todos los modulos (filtrados por permiso).
- **Revision final de seguridad**: las 35 rutas de negocio tienen `auth` + `permiso:*`.
  Publicas solo login (guest); auth-sin-permiso solo `/` (inicio) y logout (correcto).

### Verificacion (criterios de aceptacion)
- Bitacora registra y se consulta (acciones: LOGIN, ETL_COMPLETADO, CONFIG_ACTUALIZADA,
  CATALOGO_EDITADO, INCIDENCIA_TRATADA, REPORTE_EXPORTADO...). Valores JSONB ok.
- Configuracion editada (sesion_minutos_expira 30->45) con auditoria.
- Catalogo editado (pais renombrado) con auditoria.
- Calidad: incidencia marcada ACEPTADO con auditoria.
- `route:list`: 35/35 rutas de negocio protegidas con auth+permiso.

---

## ESTADO FINAL DEL PROTOTIPO (Tareas 1-10 COMPLETADAS)

ComexHub queda como prototipo demostrable funcional end-to-end:
- Entorno Laravel 12 + Inertia/Vue 3 + Tailwind v4 sobre PostgreSQL 18 (base `brisa`).
- 39 modelos Eloquent mapeando el esquema existente.
- Autenticacion con roles/permisos granulares (4 roles, 27 permisos), bloqueo por intentos,
  expiracion por inactividad.
- Organizaciones + perfiles de mapeo de columnas con detector por cabeceras.
- Carga de archivos con previsualizacion y seleccion de columnas; ETL por lotes/streaming con
  resolucion de dimensiones, validaciones e idempotencia (soporta ~400k filas).
- Explorador facetado, dashboards con ApexCharts e indicadores, reportes con exportacion
  XLSX/CSV/PDF, y administracion (bitacora, configuracion, catalogos, calidad).

Datos: dataset demo de 580+ hechos (2022-2024). Falta cargar archivos reales del INE y
validar los perfiles/cabeceras reales (ver pendientes.md).

Admin inicial: usuario **admin** / **Admin12345**.

---

# SEGUNDA TANDA — PORTAL PUBLICO (Tareas 11-16)

Agrega la cara publica del sistema (portal informativo abierto) sin tocar el panel privado,
que pasa a vivir bajo `/admin` detras del login.

---

## Tarea 11 — Separar portal publico de panel privado (ruteo y layouts) (2026-06-08)

### Estado: COMPLETADA

### Que se hizo
- **Ruteo reorganizado** en `routes/web.php` en dos caras:
  - **PUBLICAS** (sin auth, LayoutPublico): `/` (portal.inicio), `/explorar` (portal.explorar),
    `/rankings` (portal.rankings), `/acerca` (portal.acerca) -> `PortalController`.
  - **ACCESO** (guest): el login se movio de `/login` a **`/acceder`** (name `login` / `login.intento`).
  - **PRIVADAS** (auth): `/logout` + todo el panel bajo `Route::prefix('admin')`. La portada del
    panel quedo en **`/admin`** (name `admin.inicio`). Los nombres de ruta de los modulos se
    mantuvieron (usuarios.index, etc.), solo cambio el path: explorador/dashboards/cargas/reportes/
    calidad ahora cuelgan de `/admin/...`; los que ya estaban en `/admin/*` no cambiaron.
- **PortalController** nuevo: metodos `inicio/explorar/rankings/acerca`, todos comparten
  `opcionesBase()` (organizaciones activas, gestiones, organizacion por defecto INE) para el
  selector del portal. Tolerante a fallo de BD (try/catch -> portal carga con mensaje amable).
- **Dos layouts Vue** (en `resources/js/Layouts/`):
  - **LayoutPublico.vue** (nuevo): barra superior con logo, nav (Inicio/Explorar/Rankings/Acerca),
    boton discreto **"Acceder"** -> `/acceder` (o "Ir al panel" si ya hay sesion), menu movil
    desplegable (responsive), pie con la fuente. 
  - **LayoutAdmin.vue**: renombrado desde `AppLayout.vue` (eliminado); menu lateral con rutas
    actualizadas a `/admin/*`, Inicio -> `/admin`, enlace "Ver portal" -> `/`. Logout en `/logout`.
- **app.js**: el layout por defecto se asigna por nombre de pagina -> paginas en `Pages/Portal/*`
  usan `LayoutPublico`, el resto `LayoutAdmin`. Login mantiene `layout: null`.
- **Paginas nuevas** `Pages/Portal/`: `Inicio.vue` (hero + selector org/gestion + accesos; los
  titulares/indicadores reales llegan en Tarea 12), `Explorar.vue` y `Rankings.vue` (placeholders
  para Tareas 15 y 13), `Acerca.vue` (informativa, completa).
- **AutenticacionController**: login -> `redirect()->intended(route('admin.inicio'))`;
  logout -> `redirect()->route('portal.inicio')`. `Login.vue` postea a `/acceder` y enlaza "Volver al portal".
- **bootstrap/app.php**: `redirectGuestsTo(login)` y `redirectUsersTo('/admin')` (un usuario
  autenticado que toca `/acceder` va al panel).
- **Paths hardcodeados** actualizados en las paginas del panel que se movieron (axios/router/Link):
  Reportes, Cargas (Index/Create), Explorador, Dashboards, Calidad (Index/Detalle) -> `/admin/...`.

### Verificacion (criterios de aceptacion)
- `npm run build` OK (compilan Portal/Inicio, Explorar, Rankings, Acerca y todo el panel).
- Servidor real: `GET /` -> **200** renderizando `component: Portal/Inicio` (sin pedir login).
  `GET /admin` sin sesion -> **302 -> /acceder** (panel protegido). `GET /acceder` -> 200.
  `/explorar`, `/rankings`, `/acerca` -> 200.
- `route:list`: 46 rutas; publicas sin middleware auth, todo `/admin/*` bajo auth(+permiso).

### Decisiones
- Login en `/acceder` (el plan permitia `/acceder` o `/login`).
- Layout asignado por convencion de carpeta (`Portal/`) en `app.js`, evitando declarar layout en
  cada pagina del panel (16 paginas).
- Logout se mantuvo en `/logout` (auth, fuera del prefijo) para no tocar el LayoutAdmin existente.

### Estructura de rutas (resumen)
| Cara | Middleware | Rutas |
|------|-----------|-------|
| Portal publico | (ninguno) | `/`, `/portal/datos`, `/explorar`, `/rankings`, `/acerca` |
| Acceso | guest | `/acceder` (GET/POST) |
| Panel privado | auth + prefix `admin` | `/admin` y `/admin/<modulo>...`; `/logout` (auth) |

---

## Tarea 12 — Portada publica: titulares automaticos e indicadores grandes (2026-06-08)

### Estado: COMPLETADA

### Que se hizo
- **App\Servicios\ResumenPortal**: arma toda la portada con agregaciones en PostgreSQL,
  SIEMPRE filtradas por `organizacion_id` y normalmente por gestion. Convencion de flujo:
  EXPORTACION = `flujo_id=1` (valor en `valor_fob_usd`), IMPORTACION = `flujo_id=2`
  (valor en `valor_cif_frontera_usd`). Metodos: `gestionMasReciente()`, `portada()` y privados
  por bloque. Maneja el caso "sin datos" (meta.hay_datos=false). Preparado para leer de las
  vistas materializadas de la Tarea 14 cuando existan.
- **PortalController**: `inicio()` renderiza `Portal/Inicio` con `opcionesBase` + `gestionInicial`
  (la mas reciente con datos de la org por defecto) + `portada` inicial. `datos()` = endpoint
  JSON publico `GET /portal/datos?organizacion_id=&gestion=` para refrescar al cambiar el selector.
- **Ruta** publica `portal.datos`.
- **Pagina Portal/Inicio.vue** (reescrita): hero con selector organizacion+gestion (reactivo, refresca
  via axios a `/portal/datos`), titulares en tarjetas grandes con icono, 5 KPIs, 2 rankings
  destacados con mini-grafico de barras horizontales ApexCharts + enlace "ver ranking completo"
  (-> /rankings), grafico de area de evolucion mensual (expo vs impo). Cada bloque muestra la
  fuente; mensaje amable si no hay datos. Responsive.

### Titulares automaticos (consulta que genera cada uno)
Todos sobre `operacion_comercio_exterior o JOIN tiempo t`, WHERE org + gestion + flujo, GROUP BY, ORDER BY valor DESC, LIMIT 1:
- **Producto mas exportado**: flujo=1, JOIN producto, SUM(valor_fob_usd) por `p.descripcion`.
- **Producto mas importado**: flujo=2, SUM(valor_cif_frontera_usd) por `p.descripcion`.
- **Principal destino de exportaciones**: flujo=1, JOIN pais, SUM(valor_fob_usd) por `pa.nombre`.
- **Principal origen de importaciones**: flujo=2, SUM(valor_cif_frontera_usd) por `pa.nombre`.
- **Departamento que mas exporto**: flujo=1, JOIN departamento, SUM(valor_fob_usd) por `d.nombre`.

### Indicadores grandes (formula)
- **valor_exportado** = SUM(valor_fob_usd) WHERE flujo=1, gestion.
- **valor_importado** = SUM(valor_cif_frontera_usd) WHERE flujo=2, gestion.
- **variacion_expo/impo** (%) = (valor[g] - valor[g-1]) / valor[g-1] * 100 (null si g-1 = 0).
- **balanza_comercial** = valor_exportado - valor_importado (superavit/deficit segun signo).
- **paises_destino** = COUNT(DISTINCT pais_id) WHERE flujo=1, gestion.
- **productos_distintos** = COUNT(DISTINCT producto_id) en la gestion (ambos flujos).
- **Rankings destacados**: top 5 productos exportados y top 5 paises destino (flujo=1, SUM fob, LIMIT 5).
- **Evolucion mensual**: por `t.mes`, SUM con CASE por flujo -> expo e impo.

### Verificacion (criterios de aceptacion)
- `npm run build` OK. `GET /` -> renderiza `Portal/Inicio` con datos reales sin login.
- Servicio (org 1, 2024): producto mas exportado "Habas de soya" USD 38,351,304; mas importado
  "Minerales de plata"; 5 titulares. KPIs: expo 156,6M (+11,5% vs 2023), impo 135,9M (-12,3%),
  **balanza +20,75M** (coincide con los dashboards de la Tarea 8). paises_destino=7, productos=8.
- `GET /portal/datos?organizacion_id=1&gestion=2023` -> JSON, expo 140,470,992 (coherente con la
  variacion +11,5% mostrada para 2024). Sin `gestion` usa la mas reciente. Cambiar org/gestion
  en el selector refresca todo (watch reactivo).

### Nota
- Nombres de pais/departamento muestran placeholders ("Pais 54", "Departamento 9") por el dataset
  demo (solo trae codigos); los productos si tienen nombre. Con catalogos/datos reales del INE se
  completan (ver pendientes.md). El rendimiento se apoya en las vistas materializadas (Tarea 14).

---

## Tarea 14 — Vistas materializadas para rendimiento del portal (2026-06-08)

### Estado: COMPLETADA

### Que se hizo
- **Migracion** `2026_06_08_000001_create_vistas_materializadas_portal.php`: crea 4 vistas
  materializadas de SOLO LECTURA (no toca tablas base) con `CREATE MATERIALIZED VIEW ... WITH DATA`:
  - `resumen_anual_producto`: (organizacion_id, gestion, flujo_id, producto_id) -> valor, peso_bruto,
    peso_neto, n_operaciones.
  - `resumen_anual_pais`: idem por pais_id.
  - `resumen_anual_departamento`: idem por departamento_id.
  - `resumen_mensual`: (organizacion_id, gestion, mes, flujo_id) -> valor, pesos, n_operaciones (para evolucion).
  - Campo `valor` = SUM(CASE flujo 1 -> valor_fob_usd, si no -> valor_cif_frontera_usd), coherente con
    AgregadorDashboard/ResumenPortal.
  - **Indices**: por cada vista, UNIQUE (org, gestion, flujo, dimension) -> permite REFRESH CONCURRENTLY,
    + indice por (organizacion_id, gestion).
- **Comando** `php artisan comexhub:refrescar-vistas` (`App\Console\Commands\RefrescarVistasPortal`):
  REFRESH MATERIALIZED VIEW CONCURRENTLY de las 4 (cae a REFRESH normal si falla). Opcion `--sin-concurrencia`.
- **Enganche al ETL**: `ProcesadorEtl::refrescarVistasPortal()` se llama tras el cierre exitoso del ETL
  (despues de la bitacora), tolerante a fallos (no rompe la carga si las vistas no existen).
- **ResumenPortal refactorizado**: ahora lee de las vistas (resumen_mensual para totales/evolucion,
  resumen_anual_* para titulares, rankings y conteos). Misma API publica y mismos resultados.

### Verificacion (criterios de aceptacion)
- Migracion aplicada; vistas pobladas (resumen_anual_producto=33, resumen_anual_pais=32, resumen_mensual=72 filas).
- **Totales identicos a la consulta directa**: EXPO 2024 vista = directo = 156,613,442.75. paises_destino=7,
  productos_distintos=8.
- `comexhub:refrescar-vistas` ejecuta OK (CONCURRENTLY). Se dispara tras cada ETL exitoso.
- Portada (Tarea 12) ahora servida desde las vistas: mismos 5 titulares, KPIs y 12 meses de evolucion.

### Decisiones
- Indices UNIQUE para habilitar `REFRESH ... CONCURRENTLY` (sin bloquear lecturas del portal).
- Las vistas se pueblan al crearse (WITH DATA), por lo que el portal tiene datos sin esperar al primer ETL.

---

## Tarea 13 — Rankings y comparadores (2026-06-08)

### Estado: COMPLETADA

### Que se hizo
- **App\Servicios\RankingPortal** (lee de las vistas materializadas, filtra por organizacion):
  - `ranking(org, gestion, flujo, dimension, metrica, limite)`: dimension producto/pais/departamento,
    metrica valor (USD) o peso (kg, "volumen"). Devuelve posicion, label, valor, **% del total**
    (sobre el total GENERAL de la dimension) y **% acumulado** (Pareto). Limite 10/20/50.
  - `compararAnios(org, dimension, flujo, anioA, anioB, limite)`: valor[A], valor[B], variacion abs y %.
  - `compararFlujos(org, dimension, gestion, limite)`: expo, impo y balance por item.
- **RankingController** (publico): `datos` (JSON), `comparar` (JSON, modo anios|flujos), `exportar`
  (XLSX/CSV reutilizando `ExportadorReporte`). Validacion estricta de parametros (flujo 1|2,
  dimension/ metrica/limite en listas blancas).
- **Rutas** publicas: `/rankings/datos`, `/rankings/comparar`, `/rankings/exportar`.
- **Pagina Portal/Rankings.vue**: 2 pestanias. *Rankings*: filtros (org, gestion, flujo, dimension,
  metrica, posiciones) + tabla (posicion/nombre/valor/% total/% acum) + grafico de barras
  horizontales ApexCharts + botones Excel/CSV. *Comparadores*: modo "dos anios" (variacion por
  producto/pais) o "expo vs impo" (balance), con su tabla. Reactivo (watch) en el ranking.

### Verificacion (criterios de aceptacion)
- Ranking productos exportados 2024: total 156,613,442.75 (= total exportado del portal).
  **La suma de los % de todas las posiciones da 100 y el ultimo acumulado = 100** (SI).
- Comparador 2023 vs 2024 (productos expo): Habas de soya 33,1M -> 38,4M (+15.9%), Minerales de
  cobre +131.3% — variaciones correctas.
- Comparador expo vs impo 2024 (paises): balance correcto por pais (ej. Pais 54: +18,4M).
- Endpoints HTTP 200; `/rankings/exportar?formato=xlsx` descarga XLSX valido (magic 504b, ~4.7KB)
  con Content-Disposition correcto. CSV analogo.

---

## Tarea 15 — Explorador publico con detalle (2026-06-08)

### Estado: COMPLETADA

### Que se hizo
- **ConsultaExplorador ampliado** (reutiliza la logica facetada de la Tarea 7):
  - Agregada la dimension **flujo** (`o.flujo_id`) a filtros y facetas (aditivo; el explorador
    privado no la envia, sigue igual).
  - `detalleQuery(orgId, f)`: query de detalle con joins de etiqueta (producto, pais, departamento,
    medio, via, tipo). `tabla()` ahora la reutiliza.
  - `graficos(orgId, f, n)`: top N paises y top N productos por valor del **subconjunto filtrado**.
- **ExploradorPublicoController** (publico): `index` (render `Portal/Explorar` con opciones de cada
  faceta, incluido flujo), `consultar` (POST JSON: totales + tabla paginada + facetas + graficos),
  `exportar` (GET, **streaming** XLSX/CSV del detalle filtrado via OpenSpout + `cursor()`, apto para
  grandes volumenes).
- **Rutas** publicas: `/explorar` (pagina), `/explorar/consultar`, `/explorar/exportar`. (Se quito
  `explorar()` de PortalController.)
- **Pagina Portal/Explorar.vue**: panel de filtros facetados (16 facetas + organizacion + busqueda
  libre) reutilizando `FacetaFiltro`; RESUMEN VISUAL arriba (3 KPIs: operaciones, valor, peso + 2
  graficos de barras top paises/productos del filtro); TABLA DETALLADA paginada server-side con
  botones Excel/CSV. **Filtros reflejados en la URL** (querystring, leidos al montar -> enlaces
  compartibles). **Responsive**: en movil los filtros se colapsan en panel desplegable.

### Verificacion (criterios de aceptacion)
- `npm run build` OK. `/explorar` renderiza `Portal/Explorar`.
- Servicio sin filtros: 580 registros, valor 879,779,063, peso 147,401,459 (coincide con el
  explorador privado / Tarea 7). Faceta flujo: {1:292, 2:288}.
- Filtro flujo=1 (Exportacion): 292 registros, valor 443,257,842. Tabla gestion 2024: 196 filas,
  8 paginas; etiquetas de departamento/medio/via presentes.
- Exportacion CSV (gestion 2024): 196 filas + cabecera, Content-Disposition correcto. XLSX analogo.
- Paginacion 100% server-side; resumenes por agregacion. URL sincronizada para compartir.

---

## Tarea 16 — Carga con arrastrar y soltar + correccion de errores (2026-06-08)

### Estado: COMPLETADA (drag&drop y contadores); correccion de errores PENDIENTE de lista del equipo

### Que se hizo
- **Arrastrar y soltar** en `Cargas/Create.vue` (paso 1):
  - Zona drop con estados de arrastre (`@dragover/@dragenter/@dragleave/@drop`), ademas del boton
    "selecciona uno" (input file oculto). Icono y textos de ayuda.
  - Muestra **nombre, tamanio** (formateado B/KB/MB/GB) y **barra de progreso** de la subida
    (axios `onUploadProgress`). Boton "Quitar".
  - **Validacion previa** de tipo (.xlsx/.xlsm/.csv/.txt) y tamanio (<=500 MB) con mensajes claros,
    coherente con la validacion del backend (`mimes` + `max:512000`). Boton Previsualizar deshabilitado
    sin archivo.
  - **Flujo posterior intacto**: misma deteccion de perfil, previsualizacion, tabla de mapeo y
    confirmacion/encolado del ETL (no se toco `previsualizar`/`confirmar` salvo el progreso).
- **Contadores de Cargas** (`Cargas/Index.vue`): leidas/validas/error ahora muestran `0` en lugar de
  vacio cuando aun no hay datos (carga PENDIENTE). El controlador ya entregaba bien los contadores,
  estado, organizacion y flujo.

### Verificacion
- `npm run build` OK. Flujo de previsualizacion/confirmacion sin cambios (validado en Tareas 5-6).
- La validacion de tipo/tamanio en el cliente refleja la del servidor.

### Pendiente (requiere al equipo)
- **Lista de errores del panel**: el Scrum Master no entrego aun el detalle de errores a corregir.
  Cuando llegue, cada uno se reproduce, corrige y se documenta aqui (causa + solucion). Ver pendientes.md.

---

## ESTADO FINAL — SEGUNDA TANDA (Tareas 11-16)

Portal publico de ComexHub completo y funcional, separado del panel privado:
- **T11**: portal publico (LayoutPublico) en `/`, panel privado bajo `/admin` (LayoutAdmin), login en `/acceder`.
- **T12**: portada con titulares automaticos, KPIs (con variacion), rankings destacados y evolucion mensual.
- **T14**: 4 vistas materializadas (resumen anual producto/pais/departamento + mensual) con indices,
  comando `comexhub:refrescar-vistas` y refresco automatico tras cada ETL; el portal lee de ellas.
- **T13**: pagina de rankings (por valor/peso, top 10/20/50) y comparadores (dos anios / expo vs impo),
  con exportacion XLSX/CSV.
- **T15**: explorador publico facetado con resumen visual (KPIs + 2 graficos), tabla detallada paginada,
  exportacion en streaming del resultado filtrado, filtros en URL (compartibles) y responsive.
- **T16**: carga con arrastrar y soltar (progreso + validacion); contadores de Cargas endurecidos.

Datos: dataset demo (580 hechos, 2022-2024). Falta cargar archivos reales del INE y validar nombres de
catalogos (placeholders "Pais 54"). Lista de errores del panel pendiente del equipo (T16).

---

# TERCERA TANDA (tarea02) — REPRODUCIBILIDAD + REDISENIO VISUAL

## Tarea02 PARTE B — Migrations y seeders (reproducibilidad) (2026-06-09)

### Estado: COMPLETADA (B1, B2, B3)

### B1 — Migraciones de todo el esquema
- 4 migraciones nuevas, agrupadas por modulo, con SQL crudo (`DB::unprepared`) copiado
  EXACTAMENTE de `estructura-db.sql` para conservar `GENERATED ALWAYS AS IDENTITY`, los
  tipos exactos (INT/SMALLINT/BIGINT), UNIQUE, CHECK, FK, JSONB e indice GIN:
  - `2026_06_05_000001_crear_catalogo_central.php` (pgcrypto + organizacion, fuente_datos,
    perfil_mapeo, mapeo_columna).
  - `2026_06_05_000002_crear_dimensiones_microdato.php` (tiempo..despachante, 20 tablas + indices).
  - `2026_06_05_000003_crear_modulo_sistema.php` (rol..incidencia_calidad, 14 tablas + indices).
  - `2026_06_05_000004_crear_tabla_hechos_microdato.php` (operacion_comercio_exterior + 18 indices, incl. GIN).
- Timestamps `2026_06_05_*` para que corran ANTES de la migracion de vistas materializadas
  (`2026_06_08_000001`), que depende de la tabla de hechos.
- Las tablas internas de Laravel siguen en sus propias migraciones (sessions/cache/jobs).
  La de `users` se mantiene vaciada (la auth usa la tabla de negocio `usuario`).

### Verificacion B1 (contra base NUEVA y vacia `brisa_test`, sin tocar `brisa`)
- `php artisan migrate` levanta las 9 migraciones en orden sin error.
- Comparacion estructural `brisa` (real) vs `brisa_test` via information_schema:
  **39 tablas de negocio identicas**, 0 diferencias de columnas (nombre/tipo/longitud/nullable),
  constraints (PK/UNIQUE/FK/CHECK) coinciden por tabla, 34 indices `idx_*`, indice GIN
  `idx_oce_extras` presente, y 34 columnas `IDENTITY ALWAYS` en ambas. Estructura IDENTICA.

### B2 — Seeders de datos base
- Los seeders ya existian (Tareas 3/4/6): `ConfiguracionSeeder` (5 params), `RolPermisoSeeder`
  (27 permisos / 4 roles / matriz), `UsuarioAdminSeeder`, `OrganizacionIneSeeder` (INE + 2
  perfiles + 37 mapeos), `ReglaValidacionSeeder` (8 reglas), orquestados por `DatabaseSeeder`.
- Mejora: `UsuarioAdminSeeder` ahora lee `ADMIN_USUARIO` / `ADMIN_CORREO` / `ADMIN_PASSWORD`
  desde `.env` (con defaults documentados). Hash bcrypt, `debe_cambiar_pwd=true`.

### Verificacion B2 (`migrate:fresh --seed` sobre `brisa_test`)
- 4 roles, 27 permisos, 43 filas rol_permiso (admin = 27, todos), 1 usuario admin,
  1 organizacion (INE), 2 perfiles, 37 mapeos, 5 config, 8 reglas.
- `password_verify('Admin12345', hash)` = SI; `debe_cambiar_pwd=true`. Sistema operativo desde cero.

### B3 — Documentacion de arranque
- `README.md` reescrito (era el de Laravel) con pasos de arranque desde cero: requisitos,
  composer/npm install, `.env`, `key:generate`, `migrate --seed`, `composer dev` / serve+queue+vite,
  usuario admin inicial y obligacion de cambiar contrasenia, cola/ETL, refresco de vistas, rutas.
- `.env.example` actualizado: PostgreSQL (pgsql/brisa/search_path), APP_NAME=ComexHub, locale es,
  y variables ADMIN_*.

### Decisiones
- SQL crudo en las migraciones (en vez del schema builder) para garantizar reproduccion
  byte a byte del esquema original (identity ALWAYS, tipos SMALLINT/INT exactos, CHECK, GIN).
- La validacion se hizo en `brisa_test` (creada y eliminada via PDO); la base `brisa` de
  desarrollo quedo intacta, como pedia la tarea.

---

## Tarea02 PARTE A — Redisenio visual editorial e institucional (2026-06-09)

### Estado: COMPLETADA (A1..A5)

### Identidad (regla de oro: el AZUL manda, el ROJO es acento puntual)
- Azul institucional `#193153` (dominante), hero `#10203a`; rojo `#e10f1c` (acento).

### A1 — Sistema de disenio (tokens y tipografia)
- Tokens centralizados en `resources/css/app.css` via `@theme` (Tailwind v4; NO hay
  `tailwind.config.js`, la config vive en CSS). Escalas `institucional-*`, `rojo-*`,
  `gris-*`, semanticos `positivo`/`negativo`; radios `rounded-tarjeta/panel`; sombras
  `shadow-tarjeta/flotante`. Se conserva `marca-*` solo por compatibilidad.
- Tipografias: **Inter** (cuerpo, `font-sans`) y **Fraunces** (titulares, `font-display`),
  cargadas desde bunny.net en `app.blade.php`. Helpers `.titular-editorial`, `.subrayado-rojo`.
- Componentes en `@layer components`: `.btn` + variantes (`btn-primario` rojo, `btn-secundario`
  azul, `btn-contorno`, `btn-contorno-claro`), `.pildora`, `.tarjeta`, badges (`.badge` + ok/error/
  info/neutro). Documentado en `memoria/sistema-disenio.md`.

### A2 — Header y footer del portal (LayoutPublico.vue)
- Utility bar (azul muy oscuro): izq "PROYECTO ACADEMICO · FCEE · UAGRM", der "FUENTE: INE ·
  ACTUAL. [fecha]" con punto rojo. Header amplio: logo + subtitulo + placeholder de escudo FCEE,
  nav con LINEA/acento ROJO en el item activo, boton "Acceder" (primario rojo) / "Ir al panel".
  Menu hamburguesa en movil (acento rojo en borde izq). Footer institucional en azul oscuro:
  descripcion, navegacion, datos FCEE/UAGRM, enlace al INE, leyenda academica, ultima actualizacion,
  espacio para logos.

### A3 — Portada editorial (Portal/Inicio.vue)
- HERO oscuro a dos columnas: pildora con periodo (+ punto rojo), TITULAR grande editorial
  (Fraunces) con "claro y al alcance" subrayado en rojo, bajada, selectores org/gestion integrados
  + boton rojo "Explorar datos"; a la derecha PANEL "RESUMEN DEL PERIODO" flotante translucido
  (`shadow-flotante`) con 4 cifras: Exportaciones, Importaciones, Balanza, Volumen exportado (cada
  una con variacion verde sube / rojo baja).
- Debajo (fondo claro): KPIs, "Lo mas destacado" con set de iconos outline coherente (reemplaza
  emojis), rankings top-5 (barras azules, 1er puesto en rojo, enlace a /rankings), evolucion mensual.
- Backend: se anadio en `ResumenPortal::indicadores()` el campo read-only `volumen_exportado`
  (+`variacion_volumen`) = SUM(peso_bruto) flujo=1 desde `resumen_mensual`. Cambio ADITIVO, no
  rompe nada. Verificado: 2024 volumen=24,001,567.5 kg, variacion=-3.8%.

### A4 — Explorar, Rankings, Acerca + FacetaFiltro
- Explorar: encabezado oscuro, panel de filtros con cabecera azul, chip de acento rojo en
  seleccionados (FacetaFiltro), tabla con ENCABEZADO AZUL y filas alternas gris claro, botones de
  descarga secundarios. Rankings: tablas/graficos con identidad, 1er puesto resaltado en rojo
  (fila + barra), barras azules; comparadores con verde/rojo de variacion. Acerca: rehecha editorial
  (hero oscuro + bloques). Toda la LOGICA y endpoints intactos (solo clases y colores de chart).

### A5 — Panel admin y login
- LayoutAdmin: barra superior azul `institucional-900`, menu lateral `institucional-950` con acento
  ROJO en el activo, boton Salir rojo, flash con badges semanticos. Login: split-screen (panel
  lateral azul oscuro con logo + placeholder de escudo + titular editorial; formulario limpio a la
  derecha), boton primario rojo, "Volver al portal publico". Logica del form intacta.

### Verificacion A (build + servidor real)
- `npm run build` OK en cada etapa (compilan portal, login y panel).
- Servidor `php artisan serve`: `/`, `/explorar`, `/rankings`, `/acerca`, `/acceder` -> 200;
  `/admin` sin sesion -> 302 a `/acceder` (proteccion intacta); `/` renderiza `Portal/Inicio` con
  datos reales del INE; `/portal/datos` devuelve JSON correcto.

### Nota
- Imagenes y escudo FCEE quedan como placeholders (cajas con borde punteado), como pedia la tarea;
  el equipo los reemplazara por los reales.
