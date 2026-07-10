# Geodata — Estructura de la base de datos

Base PostgreSQL `brisa`, esquema `public`, **39 tablas**. Referencia exacta:
`estructura-db.sql` (raiz del proyecto). NO se modifican; se mapean con modelos Eloquent.

Convenciones del esquema:
- PK surrogadas con `GENERATED ALWAYS AS IDENTITY` (NO se llaman `id`).
- Codigo de negocio como atributo `UNIQUE` separado de la PK.
- `NUMERIC` para dinero y pesos; `BIGINT` para NANDINA (10 digitos).
- `timestamptz` para fechas; `inet` para IP; `jsonb` para auditoria y extras.

## Grupos de tablas

### Catalogo central y mapeo
| Tabla | PK | Notas |
|-------|----|-------|
| organizacion | organizacion_id (INT) | INE Bolivia y futuras |
| fuente_datos | fuente_id (INT) | version/descarga de una organizacion |
| perfil_mapeo | perfil_id (INT) | organizacion + tipo_flujo + etiqueta_version |
| mapeo_columna | mapeo_id (INT) | columna origen -> campo canonico, guardar/a_extra/nota |

### Dimensiones del microdato
| Tabla | PK | Notas |
|-------|----|-------|
| tiempo | tiempo_id (INT) | gestion + mes (UNIQUE), trimestre, semestre |
| tipo_operacion | tipo_operacion_id (SMALLINT) | base_valoracion FOB/CIF |
| flujo_comercial | flujo_id (SMALLINT) | codigo_flujo |
| seccion_arancelaria | seccion_id (SMALLINT) | NANDINA nivel seccion |
| capitulo_arancelario | capitulo_id (INT) | FK seccion_id |
| producto | producto_id (INT) | FK capitulo_id, codigo_nandina BIGINT |
| clasificacion_cuci | cuci_id (INT) | codigo_cuci + revision |
| categoria_economica_gce | gce_id (SMALLINT) | codigo_gce + revision |
| grupo_actividad | grupo_actividad_id (INT) | codigo |
| actividad_ciiu | ciiu_id (INT) | FK grupo_actividad_id |
| clasificacion_tnt | tnt_id (SMALLINT) | codigo_tnt |
| clasificacion_cuode | cuode_id (SMALLINT) | codigo_cuode |
| zona_geoeconomica | zona_id (SMALLINT) | codigo_zona |
| pais | pais_id (INT) | FK zona_id, codigo_pais, iso2/iso3 |
| pais_zona | (pais_id, zona_id) | puente N:M, PK compuesta |
| departamento | departamento_id (SMALLINT) | codigo |
| medio_transporte | medio_id (SMALLINT) | codigo |
| via_comercio | via_id (INT) | codigo |
| aduana | aduana_id (INT) | codigo |
| despachante | despachante_id (INT) | nombre UNIQUE |

### Sistema (usuarios, seguridad, operacion)
| Tabla | PK | Notas |
|-------|----|-------|
| rol | rol_id (SMALLINT) | nombre UNIQUE |
| permiso | permiso_id (INT) | codigo UNIQUE, modulo |
| rol_permiso | (rol_id, permiso_id) | PK compuesta |
| usuario | usuario_id (INT) | hash_contrasena, correo, nombre_usuario |
| usuario_rol | (usuario_id, rol_id) | PK compuesta |
| sesion | sesion_id (UUID) | token_hash, fecha_expiracion, ip_origen |
| intento_acceso | intento_id (BIGINT) | exito, ip_origen, motivo_fallo |
| bitacora_auditoria | bitacora_id (BIGINT) | accion, valores_anteriores/nuevos JSONB |
| regla_validacion | regla_id (INT) | campo_objetivo, expresion, severidad |
| configuracion | clave (VARCHAR) PK | valor, tipo_dato |
| tipo_cambio | tipo_cambio_id (INT) | fecha + monedas + tasa |
| carga_archivo | carga_id (BIGINT) | estado, contadores, tipo_flujo |
| proceso_etl | proceso_id (BIGINT) | FK carga_id, estado, filas_procesadas |
| incidencia_calidad | incidencia_id (BIGINT) | FK carga_id, severidad, estado_tratamiento |

### Tabla de hechos (microdato)
| Tabla | PK | Notas |
|-------|----|-------|
| operacion_comercio_exterior | operacion_id (BIGINT) | organizacion_id, carga_id, todas las FK de dimension, metricas NUMERIC, atributos_extra JSONB |

FK obligatorias de la tabla de hechos: fuente_id, tiempo_id, tipo_operacion_id,
flujo_id, producto_id, cuci_id, gce_id, ciiu_id, pais_id, departamento_id, medio_id, via_id.
FK opcionales: tnt_id, cuode_id, aduana_id, despachante_id.
Metricas: peso_bruto_kg, peso_neto_kg, peso_fino_kg, valor_fob_usd,
valor_cif_frontera_usd, valor_cif_aduana_usd, gravamenes_pagados.

## Campos canonicos del microdato (destino del mapeo de columnas)
gestion, mes, tipo_operacion, flujo, codigo_nandina, descripcion_producto,
codigo_capitulo, codigo_seccion, codigo_pais, codigo_zona, codigo_medio,
codigo_via, codigo_departamento, codigo_aduana, codigo_cuci, codigo_gce,
codigo_ciiu, codigo_grupo_actividad, codigo_tnt, codigo_cuode, peso_bruto_kg,
peso_neto_kg, peso_fino_kg, valor_fob_usd, valor_cif_frontera_usd,
valor_cif_aduana_usd, gravamenes_pagados.

## Equivalencia tabla -> modelo Eloquent -> PK (Tarea 2)

Todos los modelos: `App\Models\*`, `$timestamps = false` (ninguna tabla tiene el par
created_at/updated_at de Laravel). PK identity -> `$incrementing=true, $keyType='int'`.

| Tabla | Modelo | PK | keyType / incrementing |
|-------|--------|----|------------------------|
| organizacion | Organizacion | organizacion_id | int / true |
| fuente_datos | FuenteDatos | fuente_id | int / true |
| perfil_mapeo | PerfilMapeo | perfil_id | int / true |
| mapeo_columna | MapeoColumna | mapeo_id | int / true |
| tiempo | Tiempo | tiempo_id | int / true |
| tipo_operacion | TipoOperacion | tipo_operacion_id | int / true |
| flujo_comercial | FlujoComercial | flujo_id | int / true |
| seccion_arancelaria | SeccionArancelaria | seccion_id | int / true |
| capitulo_arancelario | CapituloArancelario | capitulo_id | int / true |
| producto | Producto | producto_id | int / true |
| clasificacion_cuci | ClasificacionCuci | cuci_id | int / true |
| categoria_economica_gce | CategoriaEconomicaGce | gce_id | int / true |
| grupo_actividad | GrupoActividad | grupo_actividad_id | int / true |
| actividad_ciiu | ActividadCiiu | ciiu_id | int / true |
| clasificacion_tnt | ClasificacionTnt | tnt_id | int / true |
| clasificacion_cuode | ClasificacionCuode | cuode_id | int / true |
| zona_geoeconomica | ZonaGeoeconomica | zona_id | int / true |
| pais | Pais | pais_id | int / true |
| pais_zona | PaisZona (Pivot) | (pais_id, zona_id) | compuesta / false |
| departamento | Departamento | departamento_id | int / true |
| medio_transporte | MedioTransporte | medio_id | int / true |
| via_comercio | ViaComercio | via_id | int / true |
| aduana | Aduana | aduana_id | int / true |
| despachante | Despachante | despachante_id | int / true |
| rol | Rol | rol_id | int / true |
| permiso | Permiso | permiso_id | int / true |
| rol_permiso | RolPermiso (Pivot) | (rol_id, permiso_id) | compuesta / false |
| usuario | Usuario | usuario_id | int / true (Authenticatable) |
| usuario_rol | UsuarioRol (Pivot) | (usuario_id, rol_id) | compuesta / false |
| sesion | Sesion | sesion_id | string / false (UUID) |
| intento_acceso | IntentoAcceso | intento_id | int / true |
| bitacora_auditoria | BitacoraAuditoria | bitacora_id | int / true |
| regla_validacion | ReglaValidacion | regla_id | int / true |
| configuracion | Configuracion | clave | string / false |
| tipo_cambio | TipoCambio | tipo_cambio_id | int / true |
| carga_archivo | CargaArchivo | carga_id | int / true |
| proceso_etl | ProcesoEtl | proceso_id | int / true |
| incidencia_calidad | IncidenciaCalidad | incidencia_id | int / true |
| operacion_comercio_exterior | OperacionComercioExterior | operacion_id | int / true |

### Relaciones clave declaradas
- **Jerarquia producto**: SeccionArancelaria hasMany Capitulo; Capitulo hasMany Producto;
  Producto belongsTo Capitulo + `seccion()` via hasOneThrough.
- **Actividad**: GrupoActividad hasMany ActividadCiiu; ActividadCiiu belongsTo Grupo.
- **Geografia**: Zona hasMany Pais (FK directa); Pais belongsTo Zona + `zonas()` belongsToMany
  via `pais_zona` (modelo PaisZona).
- **Usuario**: belongsToMany Rol (`usuario_rol`); Rol belongsToMany Permiso (`rol_permiso`).
  Metodos `codigosPermisos()`, `tienePermiso()`, `tieneRol()`.
- **Hechos**: OperacionComercioExterior belongsTo de TODAS sus dimensiones (obligatorias y
  opcionales: tnt, cuode, aduana, despachante).

### Modelo Usuario (autenticacion)
- Implementa `Authenticatable`. `getAuthPassword()` y `getAuthPasswordName()` devuelven
  `hash_contrasena`. `$hidden = ['hash_contrasena']`. Configurado en `config/auth.php`
  como provider `users`. Casts de booleanos y fechas.

### Casts destacados
- JSONB -> `array`: `bitacora_auditoria.valores_*`, `operacion_comercio_exterior.atributos_extra`.
- NUMERIC dinero/peso -> `decimal:2` en la tabla de hechos.
