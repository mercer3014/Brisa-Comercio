# Armonización de países con ISO 3166 (22/11/2026)

Cambios hechos solo en la copia local (`C:\Users\hp\Documents\GEODATA`). Sin commit ni push.
Respaldo previo: `C:\Users\hp\Documents\GEODATA_backup\brisa_pre_iso.sql`.

## Por qué

Cada fuente crea su propia fila en `pais` (`UNIQUE (fuente_id, codigo_pais)`): Bolivia existe una vez
en el INE (código 97), otra en ALADI (68), otra en MERCOSUR (68) y otra en FAOSTAT (68). Solo ALADI
llenaba `iso_alpha3`. Sin un código común no se pueden cruzar fuentes, pintar mapas ni unir datos
externos (PIB, precios, clima) que vienen con ISO.

## Diseño (lo menos invasivo posible)

No se fusionan filas de `pais` (las tablas de hechos siguen apuntando a la fila de su fuente).
Se agrega un **catálogo canónico** y cada fila de `pais` queda **vinculada** a él por `pais.iso_alpha3`.

| Objeto | Qué es |
|---|---|
| `pais_iso` | Catálogo canónico: 249 países/territorios ISO 3166-1 (alpha-3, alpha-2, M49, nombre en español e inglés, región ONU) + `ANT` Antillas Neerlandesas y `SCG` Serbia y Montenegro (históricos, `vigente=false`, aparecen en series viejas) + `XKX` Kosovo. |
| `pais_iso_alias` | 560 nombres normalizados ⇒ ISO3. Variantes del INE, MERCOSUR y FAOSTAT, más las comunes (EE.UU., Holanda, Corea Rep., Rusia, Taiwán, Hong Kong…). Zonas francas y "No declarado" quedan con `iso3 NULL` y categoría `ZONA_FRANCA` / `NO_DETERMINADO`. |
| `pais_iso_codigo` | Códigos propios de una fuente ⇒ ISO3. Esquema `INE`: los 213 códigos de país del INE (p.ej. 249 = USA, 97 = BOL, 990 = zona franca, 999 = no declarado). Esquema `M49`: excepciones (736 y 729 = Sudán). |
| `pais.iso_metodo` | Cómo se obtuvo el ISO: `CODIGO_INE`, `CODIGO_M49`, `M49`, `ISO`, `ALIAS`, `CARGADOR`, `MANUAL`, `ZONA_FRANCA`, `NO_DETERMINADO` o `SIN_MATCH`. |
| FK `pais.iso_alpha3 → pais_iso.iso3` | Ya no puede guardarse un ISO inexistente. `iso_alpha2` se completa solo. |
| `geo_normalizar_nombre(texto)` | Mayúsculas, sin tildes (también corrige `ESPAÐA`), sin puntuación. |
| `geo_resolver_pais_iso(esquema, codigo, nombre)` | Resolver único. Orden: código de la fuente → M49 estándar → alpha-2/3 → nombre (tal cual, sin paréntesis, "CAIMAN, ISLAS" → "ISLAS CAIMAN"). Si el nombre dice zona franca / no determinado, gana sobre el código. |
| Trigger `trg_pais_iso` | Al insertar o editar una fila de `pais` la vincula sola. Por eso **todos** los cargadores (también MERCOSUR) quedan cubiertos sin cambiar cómo crean países. Una edición manual de `iso_alpha3` (Catálogos) se respeta (`MANUAL`) y se valida contra `pais_iso`. |
| `v_pais_canonico` | Una fila por `pais` de cada fuente con su `iso3`, `iso2`, `m49`, nombre canónico, región y categoría (`PAIS`, `ZONA_FRANCA`, `NO_DETERMINADO`, `SIN_ISO`). |
| `v_pais_iso_fuentes` | Una fila por ISO3 con los `pais_id` de cada fuente (`pais_ids_ine`, `pais_ids_aladi`, …). |

Esquema de códigos por fuente: INE ⇒ `INE`; ALADI, MERCOSUR ("ISO 3166" numérico) y FAOSTAT ("Código del área (M49)") ⇒ `M49`.

## Archivos

Nuevos:
- `database/migrations/2026_11_22_100001_crear_pais_iso.php`
- `database/data/paises/pais_iso.csv`, `pais_iso_alias.csv`, `pais_iso_codigo.csv` (datos semilla; la migración los carga)
- `app/Servicios/ResolverPaisIso.php`
- `app/Console/Commands/PaisesIso.php` (`php artisan geodata:paises-iso`)

Modificados (cambios mínimos):
- `app/Servicios/CargadorIne.php` → `resolverPais()`: si el país quedó `SIN_MATCH`, registra una incidencia de calidad.
- `app/Servicios/CargadorFaostat.php` → guarda el `carga_id` y en `resolverPais()` registra incidencia si quedó sin ISO.
- `app/Servicios/CargadorAladi.php` → `resolverPaisBolivia()` busca Bolivia **solo en la fuente ALADI** (ahora la Bolivia del INE/MERCOSUR/FAOSTAT también tiene `iso_alpha3 = 'BOL'`; sin esto la carga manual de ALADI podía colgarse de la Bolivia del INE).

## Fuentes de los datos semilla
- ISO 3166-1 con nombres en español: dataset `stefangabos/world_countries` (249 entradas) y regiones M49 de `lukes/ISO-3166-Countries-with-Regional-Codes`.
- Códigos y nombres reales leídos de los Excel del Drive: INE Exportaciones 2005 y 2025p, Importaciones 2010 y ene-mar 2026p (213 códigos de país distintos); los 16 archivos MERCOSUR "Por Paises" de Argentina (233 pares código/nombre); 197 archivos FAOSTAT (M49 + Área).

## Cobertura (prueba con los 643 países reales de las fuentes)
Se insertaron en una transacción (luego revertida) todas las combinaciones código/nombre encontradas:

| Fuente | Con ISO | Zona franca | No determinado | Sin ISO |
|---|---|---|---|---|
| INE | 211 | 1 (990) | 1 (999) | 0 |
| MERCOSUR | 211 | 20 | 2 (Desconocido, Indeterminado) | 0 |
| FAOSTAT | 197 | – | – | 0 |
| ALADI | 13 miembros (ya traían ISO) | – | – | 0 |

643/643 resueltos como se esperaba. La BD local hoy tiene 0 filas en `pais`, así que el backfill no tuvo nada que vincular; al cargar datos el trigger los vincula solo.

## Cómo se usa
- Ver cobertura y países sin ISO: `php artisan geodata:paises-iso`
- Probar el resolver: `php artisan geodata:paises-iso --probar="INE:249:" --probar="M49::Corea, Rep."`
- Si aparece un país sin ISO (incidencia en Calidad): agregar el alias normalizado en `pais_iso_alias` (o el código en `pais_iso_codigo`) y ejecutar `php artisan geodata:paises-iso --revincular`.
- Cruzar fuentes: `JOIN v_pais_canonico c ON c.pais_id = hechos.pais_id` y agrupar por `c.iso3`.

## Para los cargadores MERCOSUR (pendiente de integrar)
No necesitan cambiar para vincular: el trigger ya vincula cada fila nueva de `pais`. Opcional:
- Incidencia de calidad: después de obtener `$paisId`, llamar
  `app(\App\Servicios\ResolverPaisIso::class)->registrarIncidenciaSiSinIso($paisId, $carga->carga_id);`
- ISO directo (p.ej. para guardar un iso3 en `serie_comercio_zona`):
  `app(ResolverPaisIso::class)->resolver('M49', $isoNumerico, $nombre)` ⇒ `['iso3','iso2','m49','nombre','categoria','metodo']`.
- Ojo: para filas sin código (zonas francas) `CargadorMercosurPais` calcula `codigo_pais` con `crc32(...)`, que no cabe en `SMALLINT` y haría fallar el INSERT.

## Revertir
`php artisan migrate:rollback --step=1` (si es el último lote) o restaurar `brisa_pre_iso.sql`.
