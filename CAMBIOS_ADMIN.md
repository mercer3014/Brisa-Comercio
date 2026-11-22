# Cambios en el panel de administración: Gestión de datos

Fecha: 22 de noviembre de 2026 (America/La_Paz). Copia local, sin commit ni push.

## 1. Qué se construyó

Una sección nueva, **Gestión de datos**, con el diseño del mockup HTML: título «Gestión de datos de GEODATA» y 4 pestañas, en Vue/Inertia + Tailwind. Usa datos **reales** de PostgreSQL; las cifras de ejemplo del mockup no se usaron. Se conservan el login (`/acceder`), los roles y permisos, y las demás secciones (usuarios, roles, perfiles, calidad, bitácora, reportes, dashboards, catálogos, explorador de microdatos).

En el menú lateral, «Cargas» pasa a llamarse **«Gestión de datos»**.

| Pestaña | Ruta | Permiso | Contenido |
|---|---|---|---|
| Cargar datos | `/admin/cargas/nueva` | `carga.crear` | Fuente (INE, ALADI, MERCOSUR, FAOSTAT), flujo según la fuente, gestión y mes cuando aplican, arrastrar o elegir archivo, enlaces oficiales y **descarga de la plantilla** de esa fuente/flujo. Al elegir el archivo, el servidor lee las cabeceras, marca las columnas que reconoce el cargador y avisa si falta una obligatoria. Luego muestra una vista previa de 6 filas y el botón **Importar datos**: registra `carga_archivo` y encola el job `ProcesarCargaArchivo` (mismo ETL de siempre). |
| Datos cargados | `/admin/cargas` | `carga.ver` | Filas por fuente y tabla, con rango de gestiones; totales INE por gestión (exportación FOB, importación CIF, saldo); descarga Excel. Historial de lotes con estado, último mensaje del ETL, omitidas (con enlace a Calidad), y botones **Reintentar** y **Eliminar**. |
| Explorador de gráficos | `/admin/gestion/graficos` | `explorador.ver` | Fuente (INE microdatos, MERCOSUR por país y por ítem, ALADI), flujo (exp / imp / ambos / saldo), variable (USD o kg), agrupación (gestión, mes, sección, capítulo, partida, país, departamento, medio, vía, aduana, zona, NCM, ítem), rango de años, top N, filtro de texto, gráfico (columnas, barras, líneas, anillo), KPIs (total, líder, concentración de las 5 primeras), tabla y descarga CSV. |
| Configuración | `/admin/gestion/configuracion` | `configuracion.ver` | Estado real: última migración, corte (última carga completada), filas, gestiones disponibles, cargas en cola o fallidas, trabajos en la cola, datos por fuente y parámetros. Botones: refrescar vistas (`configuracion.editar`), exportar resumen, parámetros del sistema, cerrar sesión. |

El explorador de microdatos de siempre sigue en `/admin/explorador`.

## 2. Plantillas Excel por entidad y flujo

Ruta: `GET /admin/cargas/plantilla/{entidad}/{flujo}` (permiso `carga.ver`). Las plantillas se generan al vuelo con OpenSpout.

| Entidad/flujo | Archivo | Columnas (exactas de cada cargador) |
|---|---|---|
| `ine/exportacion` | plantilla_INE_exportaciones.xlsx | GESTION, MES, NANDINA, DESNAN, SECC, DESSEC, CAP, DESCAP, PAIS, DESPAIS, AREA, DESAREA, DEPART, DESDEP, ADUANA, DESADU, MEDI, DESMEDI, VIASAL, DESVIA, CUCI3, DESCUCI3, GCE3, DESGCE3, CIIUR3, DESCIIU3, TNT, DESTNT, CLTNT, CUODE, DESCUO, KILBRU, KILNET, FINO, VALOR |
| `ine/importacion` | plantilla_INE_importaciones.xlsx | Las mismas, pero VIA en lugar de VIASAL y KILBRU, FOB, FRO, ADU, PAG |
| `aladi/exportacion`, `aladi/importacion` | plantilla_ALADI_ranking_*.xlsx | Ordinal, Item, Descripcion, Valor, % / Total, Valor acumulado, % Acumulado |
| `mercosur/pais` | plantilla_MERCOSUR_por_pais.xlsx | ISO 3166, País, Año, Exportaciones, Importaciones (FOB), Importaciones (CIF), Volumen Exports, Volumen Imports |
| `mercosur/item` | plantilla_MERCOSUR_por_item.xlsx | NCM, Descripción, Año, Exportaciones, Importaciones (FOB), Importaciones (CIF), Volumen Exports, Volumen Imports |
| `faostat/indices` | plantilla_FAOSTAT_indices.xlsx | Código del ámbito, Ámbito, Código del área (M49), Área, Código del elemento, Elemento, Código del producto (CPC), Producto, Código del año, Año, Unidad, Valor, Símbolo, Descripción del Símbolo |

Cada plantilla trae 3 hojas:

1. **Datos**: solo la cabecera. Las obligatorias van en azul marino y el resto en vino. Es la hoja que lee el sistema, porque los lectores leen la primera hoja.
2. **Ejemplo**: 2 filas de ejemplo, marcadas como tales, que **no se cargan**.
3. **Instrucciones**: significado, obligatoriedad, formato y unidad de cada columna, más sinónimos aceptados y notas. Por ejemplo: ALADI va en **miles de USD**; INE va en USD con **mes 0 o vacío = anual**; NANDINA, NCM e Item van como **texto con ceros a la izquierda**; en MERCOSUR la **zona sale del nombre del archivo**; FAOSTAT son índices, no USD.

Las definiciones están en `app/Support/PlantillasCarga.php` y se derivan del código de los cargadores. Se probó que cada plantilla, llenada con sus filas de ejemplo, carga bien con su cargador real (INE, ALADI, MERCOSUR país e ítem, FAOSTAT).

### Formatos aceptados (según el lector real)

- **INE**: .xlsx y .xlsm. El lector del INE (OpenSpout XLSX) no lee .xls ni .csv; si llega un .xls, se muestra: «INE no admite archivos .xls… Abra el archivo en Excel y use Guardar como > Libro de Excel (.xlsx)».
- **ALADI**: .xlsx, .xlsm y .csv.
- **MERCOSUR**: .xlsx, .xlsm, .xls y .csv. Se agregó .xls a la búsqueda del archivo en sus cargadores; LectorArchivo ya lo leía con PhpSpreadsheet.
- **FAOSTAT**: .xlsx, .xlsm, .xls y .csv.
- **Validaciones**: el servidor rechaza un flujo que no corresponde a la organización (por ejemplo MERCOSUR + EXPORTACION). La **gestión es obligatoria para ALADI**, porque su archivo no la trae.

## 3. Reintentar y eliminar cargas (permiso `carga.procesar`)

- **Reintentar**: solo para cargas FALLIDO cuyo archivo sigue guardado en `storage/app/cargas/{id}`. La carga vuelve a PENDIENTE, se borran sus incidencias y se reencola el job. Los cargadores son idempotentes.
- **Eliminar**: para FALLIDO o COMPLETADO. En **una transacción** borra:
  - las filas de la carga: INE por `carga_id`; ALADI, MERCOSUR y FAOSTAT por el `archivo_fuente` del archivo guardado;
  - su `archivo_fuente`;
  - la carga; las incidencias y procesos caen en cascada.

  Después borra el archivo, refresca las vistas INE o MERCOSUR, limpia y vuelve a calentar el cache, y registra `CARGA_ELIMINADA` en la bitácora.

  Si una carga COMPLETADA de ALADI, MERCOSUR o FAOSTAT vino de un comando por lote (sin archivo guardado), no se pueden identificar sus filas: se rechaza con un mensaje y no se borra nada.

## 4. MERCOSUR: recargas sin duplicados

- **Problema**: la idempotencia era por archivo. Cada subida desde el panel crea una ruta nueva, así que volver a subir el mismo archivo, zona y año duplicaba filas. Además, el borrado comparaba una ruta relativa con la absoluta guardada, así que nunca borraba nada.
- **Migración `2026_11_22_000001_mercosur_clave_natural_unica`**:
  - elimina duplicados existentes por clave natural, conservando la fila más reciente;
  - crea índices UNIQUE (NULLS NOT DISTINCT): `uq_scz_clave_natural` sobre `serie_comercio_zona (organizacion_id, zona_id, pais_id, gestion)` y `uq_scpz_clave_natural` sobre `serie_comercio_producto_zona (organizacion_id, zona_id, ncm_codigo, gestion)`;
  - refresca `mv_resumen_mercosur_zona` si borró algo.

  En la BD de la PC había 0 duplicados (no había datos MERCOSUR).
- **`CargadorMercosurPais` y `CargadorMercosurItem`**, dentro de la transacción de la carga:
  - borran las filas existentes de la misma organización, zona y gestión al encontrar cada gestión en el archivo;
  - insertan con **UPSERT** sobre la clave natural (`upsertLote` en `CargaTransaccional`). Si el archivo repite una clave, gana la última y se informa en el log;
  - reutilizan `archivo_fuente` si la ruta ya existe, para evitar el error de UNIQUE al recargar por lote;
  - el mensaje del ETL indica las gestiones reemplazadas.

### Países de MERCOSUR y armonización ISO (ver `CAMBIOS_ISO.md`)

- **Bug corregido**: para filas sin código (zonas francas, «Desconocido») `CargadorMercosurPais` usaba `crc32(nombre) & 0x7FFFFFFF` como `codigo_pais`, que no cabe en `SMALLINT` y hacía fallar el INSERT. Ahora:
  - se busca primero un país existente de la fuente MERCOSUR por **nombre normalizado** (`geo_normalizar_nombre`), así una recarga reutiliza la misma fila;
  - si no existe, se crea con un **código interno negativo correlativo** (-1, -2, …): nunca choca con un M49 (1..999) y siempre cabe en `SMALLINT`;
  - un código numérico fuera de `SMALLINT` (p. ej. 99999) se trata igual que «sin código»;
  - los alfa-2/alfa-3 se pasan en mayúsculas y el trigger `trg_pais_iso` los valida.
- **Incidencia de calidad**: después de resolver el país se llama a `ResolverPaisIso::registrarIncidenciaSiSinIso($paisId, $cargaId)`. Si el país quedó `SIN_MATCH`, se registra una advertencia en Calidad, una por carga y país.
- **MERCOSUR por ítem**: no usa la tabla `pais`, así que no aplica.
- **Probado** con dos subidas del mismo archivo: Brasil, Argentina, Paraguay, Uruguay (URY), Zona Franca de Manaos y Desconocido (sin código), un código 99999 y un nombre inventado. Resultado: 8 filas, no 16; 0 duplicados; los códigos -1 a -5 se crean una sola vez; zona franca y no determinado quedan como `ZONA_FRANCA` y `NO_DETERMINADO`; el nombre inventado queda `SIN_MATCH` y deja una advertencia por carga.

## 5. Archivos

- **Nuevos**:
  - `app/Support/PlantillasCarga.php`
  - `app/Servicios/GeneradorPlantilla.php`
  - `app/Servicios/ResumenGestionDatos.php`
  - `app/Http/Controllers/GestionDatosController.php`
  - `database/migrations/2026_11_22_000001_mercosur_clave_natural_unica.php`
  - `resources/js/Components/Gestion/CabeceraGestion.vue`
  - `resources/js/Pages/Gestion/{Cargar,Datos,Graficos,Configuracion}.vue`
  - `CAMBIOS_ADMIN.md`
- **Modificados**:
  - `app/Http/Controllers/CargaController.php`: páginas nuevas, validación por organización, flujo y formato, columnas reconocidas, zona detectada, plantilla, exportar resumen, reintentar y eliminar.
  - `app/Servicios/CargadorMercosurPais.php` y `CargadorMercosurItem.php`
  - `app/Servicios/Concerns/CargaTransaccional.php`: `upsertLote`.
  - `routes/web.php`
  - `resources/js/Layouts/LayoutAdmin.vue`: ítem «Gestión de datos».
- **Eliminados** (reemplazados por `Pages/Gestion/*`): `resources/js/Pages/Cargas/Create.vue` e `Index.vue`.
- **No se tocaron** `CargadorIne.php`, `CargadorAladi.php` ni `CargadorFaostat.php` (son parte de la armonización ISO, `CAMBIOS_ISO.md`).

## 6. Cómo usarlo

```
composer dev
```

Levanta el servidor, el **worker de colas** (`queue:listen`), los logs y Vite. Sin worker, las cargas quedan en PENDIENTE; como alternativa, ejecutar `php artisan queue:work`. Para producción: `npm run build` y un `php artisan queue:work` permanente.

## 7. Lo que queda del mockup

- **Portal público (fuera de esta fase)**: Inicio/panorama, Productos (módulo nuevo), Transporte con fotos, Organizaciones con descargas, Rankings + Comparador unificados, Mapa (departamentos + globo 360°), Producción agro FAOSTAT, Indicadores, Línea de tiempo con hitos editables y Acerca de.
- **Admin**:
  - Selector «Unidad del valor» (USD, miles, millones): no se implementó, porque la unidad la fija cada cargador (ALADI siempre en miles).
  - Mapeo libre de columnas y formato ancho: no se implementaron, porque los cargadores leen por nombre de cabecera.
  - Respaldo e importación JSON y «borrar datos del navegador»: no aplican a un servidor; en su lugar quedaron refrescar vistas, exportar resumen y eliminar carga.
  - Explorador de gráficos: no incluye FAOSTAT, porque sus valores son índices y sumarlos no tiene sentido. MERCOSUR advierte que las zonas se solapan.
  - Descarga del explorador: es CSV (abre en Excel), no .xlsx.
- **Pendientes técnicos**:
  - ALADI y FAOSTAT subidos **desde el panel** todavía pueden duplicarse si se suben dos veces: su idempotencia es por archivo y no se tocaron por la armonización ISO en curso.
  - La zona de MERCOSUR depende del nombre del archivo; no hay campo para elegirla.
