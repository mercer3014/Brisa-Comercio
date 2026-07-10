-- ============================================================================
--  GEODATA — Estructura de base de datos (PostgreSQL 14+)
--  Versión inicial: solo INE (microdato de comercio exterior).
--
--  Notas de diseño:
--    - Una sola base de datos. Cada hecho lleva organizacion_id para distinguir
--      y, a futuro, comparar entre organizaciones sin mezclar.
--    - Las tablas de hechos se separan por FORMA del dato, no por organización.
--      Hoy existe solo la forma "microdato" (operacion_comercio_exterior), usada
--      por INE. Cuando entren ALADI, MERCOSUR o FAOSTAT (que traen rankings o
--      series agregadas), se agregarán como nuevas tablas de hechos con su propia
--      forma, sin tocar las existentes.
--    - La tabla de equivalencias de códigos de país entre organizaciones se
--      agregará más adelante (cuando entre la segunda organización).
--    - Mapeo de cabeceras por NOMBRE (no por posición): perfil_mapeo + mapeo_columna.
--    - Solo estructura: sin INSERT ni datos semilla.
--
--  Convenciones:
--    - PK surrogadas con GENERATED ALWAYS AS IDENTITY.
--    - Código de negocio como atributo UNIQUE separado de la PK.
--    - NUMERIC para dinero y pesos (nunca FLOAT). BIGINT para NANDINA (10 dígitos).
--    - timestamptz para fechas; inet para IP; jsonb para auditoría y extras.
--    - Contraseñas SOLO como hash (bcrypt/argon2).
-- ============================================================================
-- contraseña de pgadmin: awd78951230
--nombre de la base de datos: brisa
-- Necesaria para gen_random_uuid() en PostgreSQL anterior a la versión 13.
CREATE EXTENSION IF NOT EXISTS pgcrypto;


-- ############################################################################
-- ##  CATÁLOGO CENTRAL (compartido por todas las organizaciones)            ##
-- ############################################################################

-- Organización emisora (INE Bolivia, y futuras: ALADI, MERCOSUR, FAOSTAT, ...)
CREATE TABLE organizacion (
    organizacion_id   INT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    nombre            VARCHAR(120) NOT NULL,
    sigla             VARCHAR(20),
    pais_iso3         CHAR(3),
    url               VARCHAR(500),
    activo            BOOLEAN      NOT NULL DEFAULT TRUE,
    creado_en         TIMESTAMPTZ  NOT NULL DEFAULT now(),
    CONSTRAINT uq_organizacion UNIQUE (nombre)
);

-- Fuente de datos: una versión/descarga concreta de una organización
CREATE TABLE fuente_datos (
    fuente_id             INT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    organizacion_id       INT NOT NULL REFERENCES organizacion(organizacion_id) ON DELETE RESTRICT,
    url_fuente            VARCHAR(500),
    fecha_descarga        DATE,
    version_nomenclatura  VARCHAR(50),
    observaciones         TEXT,
    creado_en             TIMESTAMPTZ NOT NULL DEFAULT now(),
    CONSTRAINT uq_fuente UNIQUE (organizacion_id, version_nomenclatura)
);


-- ############################################################################
-- ##  MAPEO DE CABECERAS (traduce nombres de columnas a campos canónicos)   ##
-- ############################################################################

-- Un perfil por organización + tipo de flujo + versión de cabecera
CREATE TABLE perfil_mapeo (
    perfil_id        INT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    organizacion_id  INT NOT NULL REFERENCES organizacion(organizacion_id) ON DELETE CASCADE,
    tipo_flujo       VARCHAR(20) NOT NULL CHECK (tipo_flujo IN ('EXPORTACION','IMPORTACION')),
    etiqueta_version VARCHAR(40) NOT NULL,
    descripcion      VARCHAR(255),
    activo           BOOLEAN NOT NULL DEFAULT TRUE,
    creado_en        TIMESTAMPTZ NOT NULL DEFAULT now(),
    CONSTRAINT uq_perfil UNIQUE (organizacion_id, tipo_flujo, etiqueta_version)
);

-- Cada fila traduce un nombre de columna del archivo a un campo canónico
CREATE TABLE mapeo_columna (
    mapeo_id              INT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    perfil_id             INT NOT NULL REFERENCES perfil_mapeo(perfil_id) ON DELETE CASCADE,
    nombre_columna_origen VARCHAR(80) NOT NULL,
    campo_canonico        VARCHAR(60),
    guardar               BOOLEAN NOT NULL DEFAULT TRUE,
    a_extra               BOOLEAN NOT NULL DEFAULT FALSE,
    nota                  VARCHAR(255),
    CONSTRAINT uq_mapeo UNIQUE (perfil_id, nombre_columna_origen)
);


-- ############################################################################
-- ##  DIMENSIONES DEL MICRODATO (forma usada por INE)                       ##
-- ############################################################################

-- Tiempo (calendario analítico)
CREATE TABLE tiempo (
    tiempo_id          INT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    gestion            SMALLINT  NOT NULL,
    mes                SMALLINT  NOT NULL CHECK (mes BETWEEN 1 AND 12),
    nombre_mes         VARCHAR(15) NOT NULL,
    trimestre          SMALLINT  NOT NULL CHECK (trimestre BETWEEN 1 AND 4),
    semestre           SMALLINT  NOT NULL CHECK (semestre BETWEEN 1 AND 2),
    fecha_inicio_mes   DATE      NOT NULL,
    CONSTRAINT uq_tiempo UNIQUE (gestion, mes)
);

-- Tipo de operación (exportación / importación)
CREATE TABLE tipo_operacion (
    tipo_operacion_id  SMALLINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    nombre             VARCHAR(20) NOT NULL,
    base_valoracion    CHAR(3)     NOT NULL CHECK (base_valoracion IN ('FOB','CIF')),
    CONSTRAINT uq_tipo_operacion UNIQUE (nombre)
);

-- Flujo comercial
CREATE TABLE flujo_comercial (
    flujo_id      SMALLINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    codigo_flujo  VARCHAR(2)  NOT NULL,
    descripcion   VARCHAR(50) NOT NULL,
    CONSTRAINT uq_flujo UNIQUE (codigo_flujo)
);

-- Jerarquía de producto NANDINA: sección -> capítulo -> producto
CREATE TABLE seccion_arancelaria (
    seccion_id      SMALLINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    fuente_id       INT NOT NULL REFERENCES fuente_datos(fuente_id) ON DELETE RESTRICT,
    codigo_seccion  SMALLINT     NOT NULL,
    descripcion     VARCHAR(255) NOT NULL,
    CONSTRAINT uq_seccion UNIQUE (fuente_id, codigo_seccion)
);

CREATE TABLE capitulo_arancelario (
    capitulo_id      INT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    seccion_id       SMALLINT NOT NULL REFERENCES seccion_arancelaria(seccion_id) ON DELETE RESTRICT,
    codigo_capitulo  SMALLINT     NOT NULL,
    descripcion      VARCHAR(255) NOT NULL,
    CONSTRAINT uq_capitulo UNIQUE (seccion_id, codigo_capitulo)
);

CREATE TABLE producto (
    producto_id     INT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    capitulo_id     INT NOT NULL REFERENCES capitulo_arancelario(capitulo_id) ON DELETE RESTRICT,
    codigo_nandina  BIGINT  NOT NULL,
    descripcion     TEXT    NOT NULL,
    vigente         BOOLEAN NOT NULL DEFAULT TRUE,
    CONSTRAINT uq_producto UNIQUE (capitulo_id, codigo_nandina)
);

-- Clasificaciones económicas
CREATE TABLE clasificacion_cuci (
    cuci_id      INT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    fuente_id    INT NOT NULL REFERENCES fuente_datos(fuente_id) ON DELETE RESTRICT,
    codigo_cuci  VARCHAR(5)  NOT NULL,
    descripcion  TEXT        NOT NULL,
    revision     VARCHAR(10) NOT NULL DEFAULT 'Rev.3',
    CONSTRAINT uq_cuci UNIQUE (fuente_id, codigo_cuci, revision)
);

CREATE TABLE categoria_economica_gce (
    gce_id       SMALLINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    fuente_id    INT NOT NULL REFERENCES fuente_datos(fuente_id) ON DELETE RESTRICT,
    codigo_gce   VARCHAR(5)   NOT NULL,
    descripcion  VARCHAR(255) NOT NULL,
    revision     VARCHAR(10)  NOT NULL DEFAULT 'Rev.3',
    CONSTRAINT uq_gce UNIQUE (fuente_id, codigo_gce, revision)
);

CREATE TABLE grupo_actividad (
    grupo_actividad_id  INT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    fuente_id           INT NOT NULL REFERENCES fuente_datos(fuente_id) ON DELETE RESTRICT,
    codigo              VARCHAR(5)   NOT NULL,
    descripcion         VARCHAR(255) NOT NULL,
    clasificacion_mayor VARCHAR(60),
    CONSTRAINT uq_grupo_actividad UNIQUE (fuente_id, codigo)
);

CREATE TABLE actividad_ciiu (
    ciiu_id             INT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    grupo_actividad_id  INT NOT NULL REFERENCES grupo_actividad(grupo_actividad_id) ON DELETE RESTRICT,
    codigo_ciiu         VARCHAR(5)   NOT NULL,
    descripcion         VARCHAR(255) NOT NULL,
    revision            VARCHAR(10)  NOT NULL DEFAULT 'Rev.3',
    CONSTRAINT uq_ciiu UNIQUE (grupo_actividad_id, codigo_ciiu, revision)
);

CREATE TABLE clasificacion_tnt (
    tnt_id       SMALLINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    codigo_tnt   SMALLINT     NOT NULL,
    descripcion  VARCHAR(60)  NOT NULL,
    clase        VARCHAR(30)  NOT NULL,
    CONSTRAINT uq_tnt UNIQUE (codigo_tnt)
);

CREATE TABLE clasificacion_cuode (
    cuode_id     SMALLINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    fuente_id    INT NOT NULL REFERENCES fuente_datos(fuente_id) ON DELETE RESTRICT,
    codigo_cuode VARCHAR(5)   NOT NULL,
    descripcion  VARCHAR(255) NOT NULL,
    CONSTRAINT uq_cuode UNIQUE (fuente_id, codigo_cuode)
);

-- Geografía internacional: zona -> país (+ puente N:M)
CREATE TABLE zona_geoeconomica (
    zona_id      SMALLINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    fuente_id    INT NOT NULL REFERENCES fuente_datos(fuente_id) ON DELETE RESTRICT,
    codigo_zona  SMALLINT     NOT NULL,
    descripcion  VARCHAR(100) NOT NULL,
    CONSTRAINT uq_zona UNIQUE (fuente_id, codigo_zona)
);

CREATE TABLE pais (
    pais_id      INT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    fuente_id    INT NOT NULL REFERENCES fuente_datos(fuente_id) ON DELETE RESTRICT,
    zona_id      SMALLINT NOT NULL REFERENCES zona_geoeconomica(zona_id) ON DELETE RESTRICT,
    codigo_pais  SMALLINT     NOT NULL,
    nombre       VARCHAR(100) NOT NULL,
    iso_alpha2   CHAR(2),
    iso_alpha3   CHAR(3),
    CONSTRAINT uq_pais UNIQUE (fuente_id, codigo_pais)
);

CREATE TABLE pais_zona (
    pais_id  INT      NOT NULL REFERENCES pais(pais_id) ON DELETE CASCADE,
    zona_id  SMALLINT NOT NULL REFERENCES zona_geoeconomica(zona_id) ON DELETE CASCADE,
    CONSTRAINT pk_pais_zona PRIMARY KEY (pais_id, zona_id)
);

-- Geografía nacional y logística
CREATE TABLE departamento (
    departamento_id  SMALLINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    fuente_id        INT NOT NULL REFERENCES fuente_datos(fuente_id) ON DELETE RESTRICT,
    codigo           SMALLINT    NOT NULL,
    nombre           VARCHAR(50) NOT NULL,
    CONSTRAINT uq_departamento UNIQUE (fuente_id, codigo)
);

CREATE TABLE medio_transporte (
    medio_id     SMALLINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    codigo       SMALLINT    NOT NULL,
    descripcion  VARCHAR(50) NOT NULL,
    CONSTRAINT uq_medio UNIQUE (codigo)
);

CREATE TABLE via_comercio (
    via_id       INT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    codigo       SMALLINT     NOT NULL,
    descripcion  VARCHAR(100) NOT NULL,
    CONSTRAINT uq_via UNIQUE (codigo)
);

CREATE TABLE aduana (
    aduana_id    INT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    fuente_id    INT NOT NULL REFERENCES fuente_datos(fuente_id) ON DELETE RESTRICT,
    codigo       SMALLINT     NOT NULL,
    descripcion  VARCHAR(100) NOT NULL,
    CONSTRAINT uq_aduana UNIQUE (fuente_id, codigo)
);

CREATE TABLE despachante (
    despachante_id  INT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    nombre          VARCHAR(150) NOT NULL,
    CONSTRAINT uq_despachante UNIQUE (nombre)
);


-- ############################################################################
-- ##  MÓDULO DE SISTEMA (usuarios, seguridad, operación)                    ##
-- ############################################################################

CREATE TABLE rol (
    rol_id       SMALLINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    nombre       VARCHAR(40) NOT NULL,
    descripcion  VARCHAR(255),
    CONSTRAINT uq_rol UNIQUE (nombre)
);

CREATE TABLE permiso (
    permiso_id   INT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    codigo       VARCHAR(60)  NOT NULL,
    descripcion  VARCHAR(255) NOT NULL,
    modulo       VARCHAR(40)  NOT NULL,
    CONSTRAINT uq_permiso UNIQUE (codigo)
);

CREATE TABLE rol_permiso (
    rol_id      SMALLINT NOT NULL REFERENCES rol(rol_id) ON DELETE CASCADE,
    permiso_id  INT      NOT NULL REFERENCES permiso(permiso_id) ON DELETE CASCADE,
    CONSTRAINT pk_rol_permiso PRIMARY KEY (rol_id, permiso_id)
);

CREATE TABLE usuario (
    usuario_id        INT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    nombre_usuario    VARCHAR(50)  NOT NULL,
    correo            VARCHAR(150) NOT NULL,
    hash_contrasena   VARCHAR(255) NOT NULL,
    nombre_completo   VARCHAR(150) NOT NULL,
    activo            BOOLEAN      NOT NULL DEFAULT TRUE,
    debe_cambiar_pwd  BOOLEAN      NOT NULL DEFAULT TRUE,
    fecha_creacion    TIMESTAMPTZ  NOT NULL DEFAULT now(),
    ultimo_acceso     TIMESTAMPTZ,
    CONSTRAINT uq_usuario_nombre UNIQUE (nombre_usuario),
    CONSTRAINT uq_usuario_correo UNIQUE (correo)
);

CREATE TABLE usuario_rol (
    usuario_id   INT      NOT NULL REFERENCES usuario(usuario_id) ON DELETE CASCADE,
    rol_id       SMALLINT NOT NULL REFERENCES rol(rol_id) ON DELETE CASCADE,
    asignado_en  TIMESTAMPTZ NOT NULL DEFAULT now(),
    CONSTRAINT pk_usuario_rol PRIMARY KEY (usuario_id, rol_id)
);

CREATE TABLE sesion (
    sesion_id         UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    usuario_id        INT NOT NULL REFERENCES usuario(usuario_id) ON DELETE CASCADE,
    token_hash        VARCHAR(255) NOT NULL,
    fecha_inicio      TIMESTAMPTZ  NOT NULL DEFAULT now(),
    fecha_expiracion  TIMESTAMPTZ  NOT NULL,
    ip_origen         INET,
    user_agent        VARCHAR(255),
    activa            BOOLEAN      NOT NULL DEFAULT TRUE
);

CREATE TABLE intento_acceso (
    intento_id              BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    nombre_usuario_intento  VARCHAR(50) NOT NULL,
    exito                   BOOLEAN     NOT NULL,
    ip_origen               INET,
    motivo_fallo            VARCHAR(100),
    fecha_hora              TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE TABLE bitacora_auditoria (
    bitacora_id          BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    usuario_id           INT REFERENCES usuario(usuario_id) ON DELETE SET NULL,
    accion               VARCHAR(40)  NOT NULL,
    entidad_afectada     VARCHAR(80),
    registro_afectado    VARCHAR(80),
    valores_anteriores   JSONB,
    valores_nuevos       JSONB,
    ip_origen            INET,
    fecha_hora           TIMESTAMPTZ  NOT NULL DEFAULT now()
);

CREATE TABLE regla_validacion (
    regla_id        INT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    nombre          VARCHAR(80)  NOT NULL,
    descripcion     VARCHAR(255) NOT NULL,
    campo_objetivo  VARCHAR(60),
    expresion       VARCHAR(255),
    severidad       VARCHAR(20) NOT NULL DEFAULT 'ADVERTENCIA'
                    CHECK (severidad IN ('INFO','ADVERTENCIA','ERROR')),
    activa          BOOLEAN NOT NULL DEFAULT TRUE,
    CONSTRAINT uq_regla UNIQUE (nombre)
);

CREATE TABLE configuracion (
    clave              VARCHAR(60) PRIMARY KEY,
    valor              VARCHAR(255) NOT NULL,
    tipo_dato          VARCHAR(20)  NOT NULL DEFAULT 'texto',
    descripcion        VARCHAR(255),
    fecha_modificacion TIMESTAMPTZ  NOT NULL DEFAULT now(),
    usuario_modifico   INT REFERENCES usuario(usuario_id) ON DELETE SET NULL
);

CREATE TABLE tipo_cambio (
    tipo_cambio_id   INT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    fecha            DATE        NOT NULL,
    moneda_origen    CHAR(3)     NOT NULL,
    moneda_destino   CHAR(3)     NOT NULL,
    tasa             NUMERIC(12,6) NOT NULL CHECK (tasa > 0),
    CONSTRAINT uq_tipo_cambio UNIQUE (fecha, moneda_origen, moneda_destino)
);

-- Carga de archivos
CREATE TABLE carga_archivo (
    carga_id              BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    organizacion_id       INT NOT NULL REFERENCES organizacion(organizacion_id) ON DELETE RESTRICT,
    fuente_id             INT REFERENCES fuente_datos(fuente_id) ON DELETE SET NULL,
    perfil_id             INT REFERENCES perfil_mapeo(perfil_id) ON DELETE SET NULL,
    usuario_id            INT REFERENCES usuario(usuario_id) ON DELETE SET NULL,
    nombre_archivo        VARCHAR(255) NOT NULL,
    tipo_flujo            VARCHAR(20)  NOT NULL CHECK (tipo_flujo IN ('EXPORTACION','IMPORTACION')),
    gestion               SMALLINT,
    mes                   SMALLINT CHECK (mes BETWEEN 1 AND 12),
    total_filas_leidas    INT NOT NULL DEFAULT 0,
    total_filas_validas   INT NOT NULL DEFAULT 0,
    total_filas_error     INT NOT NULL DEFAULT 0,
    estado                VARCHAR(20) NOT NULL DEFAULT 'PENDIENTE'
                          CHECK (estado IN ('PENDIENTE','PROCESANDO','COMPLETADO','FALLIDO')),
    fecha_carga           TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE TABLE proceso_etl (
    proceso_id        BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    carga_id          BIGINT NOT NULL REFERENCES carga_archivo(carga_id) ON DELETE CASCADE,
    estado            VARCHAR(20) NOT NULL DEFAULT 'EN_COLA'
                      CHECK (estado IN ('EN_COLA','EN_EJECUCION','EXITOSO','FALLIDO')),
    fecha_inicio      TIMESTAMPTZ,
    fecha_fin         TIMESTAMPTZ,
    filas_procesadas  INT NOT NULL DEFAULT 0,
    mensaje_log       TEXT
);

CREATE TABLE incidencia_calidad (
    incidencia_id       BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    carga_id            BIGINT NOT NULL REFERENCES carga_archivo(carga_id) ON DELETE CASCADE,
    regla_id            INT REFERENCES regla_validacion(regla_id) ON DELETE SET NULL,
    descripcion         VARCHAR(255) NOT NULL,
    severidad           VARCHAR(20) NOT NULL CHECK (severidad IN ('INFO','ADVERTENCIA','ERROR')),
    numero_fila         INT,
    valor_detectado     VARCHAR(255),
    estado_tratamiento  VARCHAR(20) NOT NULL DEFAULT 'PENDIENTE'
                        CHECK (estado_tratamiento IN ('PENDIENTE','CORREGIDO','ACEPTADO','DESCARTADO')),
    fecha_deteccion     TIMESTAMPTZ NOT NULL DEFAULT now()
);


-- ############################################################################
-- ##  TABLA DE HECHOS — MICRODATO DE COMERCIO EXTERIOR (forma usada por INE)##
-- ##  Lleva organizacion_id: cualquier organización con microdato entra aquí.##
-- ############################################################################
CREATE TABLE operacion_comercio_exterior (
    operacion_id          BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,

    organizacion_id       INT    NOT NULL REFERENCES organizacion(organizacion_id) ON DELETE RESTRICT,
    carga_id              BIGINT REFERENCES carga_archivo(carga_id) ON DELETE SET NULL,

    -- Dimensiones obligatorias
    fuente_id             INT      NOT NULL REFERENCES fuente_datos(fuente_id) ON DELETE RESTRICT,
    tiempo_id             INT      NOT NULL REFERENCES tiempo(tiempo_id) ON DELETE RESTRICT,
    tipo_operacion_id     SMALLINT NOT NULL REFERENCES tipo_operacion(tipo_operacion_id) ON DELETE RESTRICT,
    flujo_id              SMALLINT NOT NULL REFERENCES flujo_comercial(flujo_id) ON DELETE RESTRICT,
    producto_id           INT      NOT NULL REFERENCES producto(producto_id) ON DELETE RESTRICT,
    cuci_id               INT      NOT NULL REFERENCES clasificacion_cuci(cuci_id) ON DELETE RESTRICT,
    gce_id                SMALLINT NOT NULL REFERENCES categoria_economica_gce(gce_id) ON DELETE RESTRICT,
    ciiu_id               INT      NOT NULL REFERENCES actividad_ciiu(ciiu_id) ON DELETE RESTRICT,
    pais_id               INT      NOT NULL REFERENCES pais(pais_id) ON DELETE RESTRICT,
    departamento_id       SMALLINT NOT NULL REFERENCES departamento(departamento_id) ON DELETE RESTRICT,
    medio_id              SMALLINT NOT NULL REFERENCES medio_transporte(medio_id) ON DELETE RESTRICT,
    via_id                INT      NOT NULL REFERENCES via_comercio(via_id) ON DELETE RESTRICT,

    -- Dimensiones opcionales (según el flujo)
    tnt_id                SMALLINT REFERENCES clasificacion_tnt(tnt_id) ON DELETE RESTRICT,
    cuode_id              SMALLINT REFERENCES clasificacion_cuode(cuode_id) ON DELETE RESTRICT,
    aduana_id             INT      REFERENCES aduana(aduana_id) ON DELETE RESTRICT,
    despachante_id        INT      REFERENCES despachante(despachante_id) ON DELETE RESTRICT,

    -- Métricas de peso
    peso_bruto_kg            NUMERIC(18,2) CHECK (peso_bruto_kg >= 0),
    peso_neto_kg             NUMERIC(18,2) CHECK (peso_neto_kg  >= 0),
    peso_fino_kg             NUMERIC(18,2) CHECK (peso_fino_kg  >= 0),

    -- Métricas de valor (según el flujo)
    valor_fob_usd            NUMERIC(18,2) CHECK (valor_fob_usd >= 0),
    valor_cif_frontera_usd   NUMERIC(18,2) CHECK (valor_cif_frontera_usd >= 0),
    valor_cif_aduana_usd     NUMERIC(18,2) CHECK (valor_cif_aduana_usd >= 0),
    gravamenes_pagados       NUMERIC(18,2) CHECK (gravamenes_pagados >= 0),

    -- Columnas no canónicas conservadas (clave-valor JSON)
    atributos_extra          JSONB
);


-- ############################################################################
-- ##  ÍNDICES                                                               ##
-- ############################################################################

CREATE INDEX idx_oce_organizacion  ON operacion_comercio_exterior (organizacion_id);
CREATE INDEX idx_oce_tiempo        ON operacion_comercio_exterior (tiempo_id);
CREATE INDEX idx_oce_tipo_op       ON operacion_comercio_exterior (tipo_operacion_id);
CREATE INDEX idx_oce_flujo         ON operacion_comercio_exterior (flujo_id);
CREATE INDEX idx_oce_producto      ON operacion_comercio_exterior (producto_id);
CREATE INDEX idx_oce_cuci          ON operacion_comercio_exterior (cuci_id);
CREATE INDEX idx_oce_gce           ON operacion_comercio_exterior (gce_id);
CREATE INDEX idx_oce_ciiu          ON operacion_comercio_exterior (ciiu_id);
CREATE INDEX idx_oce_pais          ON operacion_comercio_exterior (pais_id);
CREATE INDEX idx_oce_departamento  ON operacion_comercio_exterior (departamento_id);
CREATE INDEX idx_oce_medio         ON operacion_comercio_exterior (medio_id);
CREATE INDEX idx_oce_via           ON operacion_comercio_exterior (via_id);
CREATE INDEX idx_oce_tnt           ON operacion_comercio_exterior (tnt_id);
CREATE INDEX idx_oce_cuode         ON operacion_comercio_exterior (cuode_id);
CREATE INDEX idx_oce_aduana        ON operacion_comercio_exterior (aduana_id);
CREATE INDEX idx_oce_carga         ON operacion_comercio_exterior (carga_id);
CREATE INDEX idx_oce_org_tiempo_pais_prod
    ON operacion_comercio_exterior (organizacion_id, tiempo_id, pais_id, producto_id);
CREATE INDEX idx_oce_extras ON operacion_comercio_exterior USING GIN (atributos_extra);

CREATE INDEX idx_capitulo_seccion  ON capitulo_arancelario (seccion_id);
CREATE INDEX idx_producto_capitulo ON producto (capitulo_id);
CREATE INDEX idx_ciiu_grupo        ON actividad_ciiu (grupo_actividad_id);
CREATE INDEX idx_pais_zona_zona    ON pais (zona_id);
CREATE INDEX idx_mapeo_perfil      ON mapeo_columna (perfil_id);

CREATE INDEX idx_usuario_rol_usuario   ON usuario_rol (usuario_id);
CREATE INDEX idx_usuario_rol_rol       ON usuario_rol (rol_id);
CREATE INDEX idx_rol_permiso_rol       ON rol_permiso (rol_id);
CREATE INDEX idx_sesion_usuario        ON sesion (usuario_id);
CREATE INDEX idx_sesion_activa         ON sesion (activa) WHERE activa = TRUE;
CREATE INDEX idx_bitacora_usuario      ON bitacora_auditoria (usuario_id);
CREATE INDEX idx_bitacora_fecha        ON bitacora_auditoria (fecha_hora);
CREATE INDEX idx_carga_organizacion    ON carga_archivo (organizacion_id);
CREATE INDEX idx_carga_estado          ON carga_archivo (estado);
CREATE INDEX idx_incidencia_carga      ON incidencia_calidad (carga_id);
CREATE INDEX idx_intento_fecha         ON intento_acceso (fecha_hora);

-- ============================================================================
--  FIN DEL SCRIPT
-- ============================================================================