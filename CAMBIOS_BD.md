# Cambios en la base de datos (21-11-2026)

Corrección de los errores de la revisión de la BD. Todo está como **migraciones de Laravel + cambios de código**, así que se puede reproducir con `php artisan migrate`. No se hizo commit ni push: los cambios se ven con `git status` / `git diff`.

Respaldo previo: `C:\Users\hp\Documents\GEODATA_backup\brisa_pre_fix.sql` (y el esquema solo en `brisa_schema_pre_fix.sql`).
Para volver atrás: `git checkout -- . && git clean -fd` en el proyecto y restaurar el respaldo con `psql -d brisa -f brisa_pre_fix.sql` sobre una BD vacía.

## 1. Migraciones nuevas

| Migración | Qué hace |
|---|---|
| `2026_06_05_000005_baseline_objetos_basedatos` | Crea **con IF NOT EXISTS** los objetos que solo estaban en `basedatos.txt`: ALADI, MERCOSUR, FAOSTAT, CAN, nomenclatura NANDINA, portal y créditos, semillas (organizaciones, tipo_operacion, flujo_comercial, etc.) y `mv_resumen_mercosur_zona`. El SQL está en `database/sql/baseline_basedatos.sql`. En una BD que ya existe no cambia nada. |
| `2026_11_21_000001_organizacion_sigla_unica` | Fusiona las organizaciones que repiten sigla, por ejemplo el INE sin tilde, y agrega `UNIQUE(sigla)`. Avisa si INE, ALADI, MERCOSUR y FAOSTAT no tienen los ids 1 a 4. |
| `2026_11_21_000002_gestion_operacion_comercio_exterior_not_null` | Rellena `gestion` a partir de `tiempo`. Agrega el trigger `trg_oce_gestion`, que la completa si falta, y la deja `NOT NULL`. |
| `2026_11_21_000003_mes_anual_ine` | En el INE, el mes 0 pasa a significar "anual". Un par (gestión, flujo) que tiene todas sus filas en enero se mueve a mes 0. `carga_archivo.mes` ahora acepta valores de 0 a 12. |
| `2026_11_21_000004_unificar_vistas_resumen` | Deja **una sola familia** de vistas materializadas (`resumen_anual_producto/pais/departamento` y `resumen_mensual`) con **una sola regla de valor**: FOB para exportación y reexportación, CIF frontera para importación. `mv_resumen_anual_ine` y `mv_resumen_mensual_ine` pasan a ser vistas normales sobre `resumen_mensual`, con las mismas columnas. La mensual no incluye el mes 0. Agrega un índice único a `mv_resumen_mercosur_zona` y actualiza la función `refrescar_vistas_materializadas()`. |
| `2026_11_21_000005_carga_archivo_filas_omitidas` | Agrega la columna `carga_archivo.total_filas_omitidas`. |
| `2026_11_21_000006_producto_codigo_nandina_txt` | Agrega `producto.codigo_nandina_txt CHAR(10)`, una columna generada con `lpad` que conserva el 0 inicial (por ejemplo 0901…), con su índice. Agrega también `producto.nomenclatura_version_id`, opcional y por ahora NULL. |
| `2026_11_21_000007_podar_indices_operacion_comercio_exterior` | Borra `idx_oce_extras`, un GIN que ninguna consulta usa, e `idx_oce_organizacion`, que es redundante. |

Migraciones existentes que se ajustaron para que no fallen en una BD vacía: `2026_06_19_000003` y `2026_07_16_000001`, que ahora revisan `hasTable`.
Con esto, `php artisan migrate:fresh --seed` funciona sobre una BD vacía, y el esquema que resulta es igual al de `brisa`. Se comprobó comparando columnas, constraints, índices y vistas.
`basedatos.txt` queda solo como referencia histórica.

## 2. Cambios de código

- **Nuevos:**
  - `app/Support/Organizaciones.php`: resuelve el id de una organización por su sigla.
  - `app/Support/ReglaValor.php`: la regla única de valor.
  - `app/Servicios/Concerns/CargaTransaccional.php`: maneja la transacción, los savepoints por lote y la limpieza de cachés cuando hay rollback.
- **`CargadorIne`:**
  - Llena `gestion`.
  - Usa mes 0 cuando no hay mes.
  - El borrado y la inserción van en **una sola transacción**. Si algo falla, se revierte y los datos anteriores quedan intactos.
  - Valida que la **gestión del formulario coincida con la del archivo**; si no coincide, la carga se rechaza sin tocar datos.
  - Registra las filas omitidas por falta de año o de NANDINA en `incidencia_calidad` (hasta 500 por carga, más un resumen) y en `total_filas_omitidas`.
  - Si no encuentra la cabecera, la carga queda FALLIDO en vez de "COMPLETADO con 0 filas".
  - Las cabeceras con tilde y ñ (GESTIÓN, AÑO) ahora se reconocen. Puede ser la causa de las importaciones de 1993 a 2016 con 0 filas.
  - Las dimensiones se crean por una conexión aparte, para que dos cargas en paralelo no se bloqueen.
  - Se corrigió el error `uq_zona` que aparecía al recargar un archivo sin zona.
- **ALADI, FAOSTAT y MERCOSUR (País e Ítem):** la transacción y los lotes usan savepoint. La organización se resuelve por sigla.
- **Comandos por lote:**
  - `geodata:cargar-aladi`, `geodata:cargar-faostat` y `geodata:cargar-mercosur` con `--fresh` corren todo en una sola transacción. Si algún archivo falla, se revierte todo, salvo que se use `--permitir-fallos`.
  - `geodata:cargar-ine` refresca las vistas nuevas.
  - `geodata:refrescar-vistas` acepta `--solo=ine|mercosur` y ahora incluye MERCOSUR.
- **Job `ProcesarCargaArchivo`:** elige el cargador por sigla. Cuando una carga del INE o de MERCOSUR termina bien, refresca las vistas y después calienta el caché.
- **Regla de valor única** en `PortalApi`, `AgregadorDashboard`, `GeneradorReporte` y `ConsultaExplorador`. Antes "exportaciones" sumaba también el FOB de las importaciones, y el reporte de EXPORTACION incluía las importaciones.
- **NANDINA:** el explorador, el top de productos y el buscador de productos muestran el código con el 0 inicial. Además, el buscador ya no falla al hacer `LOWER()` sobre un número.
- **Mes 0:** aparece como "Anual" en el explorador y hay un filtro "Anual (sin mes)". La curva mensual del portal no lo incluye, y en el dashboard y los reportes se muestra como "AAAA (anual)".
- **Seeders:**
  - `OrganizacionIneSeeder` busca el INE por sigla y usa el nombre con tilde, así que ya no se duplica.
  - `PerfilMapeoSeeder` usa la sigla en vez de ids fijos.

## 3. Efecto en el panel admin

- **Cargas, listado:** hay una columna nueva, **Omitidas**. Al pasar el mouse muestra cuántas incidencias hay en Calidad. Las cargas en FALLIDO muestran el motivo debajo del estado, por ejemplo "la gestión no coincide" o "no se encontró la cabecera".
- **Cargas, nueva:** debajo de "Gestión" hay una nota que explica que debe coincidir con el archivo y que reemplaza esa gestión y ese flujo. El campo mes acepta 0.
- **Calidad:** ahora aparecen las incidencias de las filas omitidas y de las cargas revertidas. La pantalla no cambió.
- **Dashboards, Reportes y Explorador:** las cifras de exportación ya no incluyen el FOB de las importaciones. Las cifras pueden bajar respecto de antes; es la corrección. Los datos anuales se ven como "Anual" o "(anual)".
- **Portal:** después de cada carga INE o MERCOSUR desde el panel, los resúmenes y rankings se actualizan solos.

## 4. Pruebas hechas

- `migrate` sobre `brisa` y `migrate:fresh --seed` sobre una BD de prueba (`brisa_test`, que después se borró).
- Una carga INE sintética con 6 casos, borrada al final:
  1. Anual: 3 filas válidas, 2 omitidas con incidencia, mes 0 y gestión llena.
  2. Recarga: reemplaza las filas, no las duplica.
  3. Gestión equivocada: rechazada, los datos quedan iguales.
  4. Falla a mitad del archivo: se hace rollback.
  5. Archivo sin cabecera: FALLIDO.
  6. Archivo mensual: aparece en `mv_resumen_mensual_ine`.
- Prueba de humo: las páginas del portal, la API y todas las de `/admin` responden 200.
- `php artisan test`: falla `ExampleTest`, igual que antes de los cambios. Corre en SQLite en memoria y el esquema es solo para PostgreSQL. No es una regresión.

## 5. Pendiente (no se hizo)

- **Rotar contraseñas:** la de PostgreSQL y la del admin demo están en el repo público (`estructura-db.sql`, `memoria/`). Hay que cambiarlas en producción. No se tocó el historial de git.
- **País canónico por ISO3 y `pais_codigo_externo` en todos los cargadores:** cambia los cuatro cargadores y los datos de producción, así que queda para una etapa aparte.
- `PortalApi::ORG_*` y el frontend todavía usan 1, 2, 3 y 4 como constantes. La migración de siglas verifica que coincidan.
- Particionado de `operacion_comercio_exterior`, PostGIS y las tablas del simulador del PDF: todavía no se pidieron.
- `idx_oce_tipo_op`, `idx_oce_flujo`, `idx_oce_medio`, `idx_oce_via` e `idx_oce_tnt` se mantienen, porque el Explorador filtra por esas columnas. Antes de borrarlos conviene revisar `pg_stat_user_indexes` en producción.
- `CargadorMercosurPais` borra lo anterior por ruta de archivo, así que recargar el mismo Excel con otra carga duplica los datos. Es un problema que ya existía; habría que agregar un UNIQUE de clave natural a las series.
- Si se aplica en producción, las migraciones 000002, 000003 y 000004 recorren la tabla grande de más de 4 millones de filas y pueden tardar varios minutos.
