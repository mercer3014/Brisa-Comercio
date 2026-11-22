-- ============================================================================
--  GEODATA - BASELINE de objetos que antes SOLO existian en basedatos.txt
--  (Partes B y C + Bloque 2: ALADI, MERCOSUR, FAOSTAT, CAN, portal/creditos,
--  semillas y la vista materializada mv_resumen_mercosur_zona).
--
--  Es IDEMPOTENTE: CREATE ... IF NOT EXISTS y semillas que solo se insertan si
--  la tabla esta vacia. En una BD ya creada con basedatos.txt no cambia nada;
--  en una BD vacia permite que `php artisan migrate` reconstruya todo.
--  Lo ejecuta la migracion 2026_06_05_000005_baseline_objetos_basedatos.
--  Las vistas mv_resumen_anual_ine / mv_resumen_mensual_ine NO van aqui: las
--  define la migracion 2026_11_21_000004 como vistas sobre resumen_mensual.
-- ============================================================================

-- ############################################################################
-- ##  PARTE B - INTEGRACION DE FUENTES ALADI, MERCOSUR y FAOSTAT            ##
-- ############################################################################
-- En la PARTE A, la unica tabla de hechos es operacion_comercio_exterior, que
-- guarda MICRODATO (cada fila es una operacion individual de comercio, como las
-- del INE). Pero ALADI, MERCOSUR y FAOSTAT NO entregan microdato: entregan datos
-- ya agregados, y cada uno con una forma distinta. Por eso aqui se agregan tres
-- tablas de hechos nuevas (una por forma) y las tablas que las conectan con las
-- dimensiones compartidas de la PARTE A. NINGUNA tabla de la PARTE A se modifica.


-- ============================================================================
--  B.1  EQUIVALENCIA DE CODIGOS ENTRE ORGANIZACIONES
-- ============================================================================
-- PROBLEMA QUE RESUELVEN: el INE llama a un pais con un codigo (ej. 32), ALADI
-- lo llama por nombre, MERCOSUR con un ISO numerico y FAOSTAT con el codigo M49.
-- Si cada fuente guardara su propio pais, no podriamos comparar "Brasil del INE"
-- con "Brasil de ALADI". La solucion es: el pais existe UNA sola vez en la tabla
-- "pais" (PARTE A), y estas tablas traducen el codigo propio de cada organizacion
-- hacia ese pais/producto canonico.

CREATE TABLE IF NOT EXISTS pais_codigo_externo (
    pais_codigo_externo_id  INT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    -- A QUE pais canonico equivale (apunta a la dimension compartida de la PARTE A):
    pais_id                 INT NOT NULL REFERENCES pais(pais_id) ON DELETE RESTRICT,
    -- DE QUE organizacion es este codigo (porque "32" puede significar paises
    -- distintos en organizaciones distintas):
    organizacion_id         INT NOT NULL REFERENCES organizacion(organizacion_id) ON DELETE RESTRICT,
    codigo_externo          VARCHAR(20) NOT NULL,   -- el codigo tal como lo usa esa organizacion
    nombre_externo          VARCHAR(150),           -- el nombre tal como lo escribe esa organizacion
    -- Un mismo codigo no puede repetirse dentro de la misma organizacion:
    CONSTRAINT uq_pais_codigo_externo UNIQUE (organizacion_id, codigo_externo)
);
COMMENT ON TABLE  pais_codigo_externo IS 'Traduce el codigo de pais propio de cada organizacion al pais canonico de la tabla pais. Es lo que permite comparar el mismo pais entre fuentes.';
COMMENT ON COLUMN pais_codigo_externo.pais_id IS 'Pais canonico (PARTE A) al que equivale este codigo externo.';

CREATE TABLE IF NOT EXISTS producto_codigo_externo (
    producto_codigo_externo_id  INT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    -- A QUE producto NANDINA equivale. Es NULL-able a proposito: a veces el
    -- producto de ALADI/FAOSTAT todavia no esta homologado a NANDINA, y aun asi
    -- queremos guardar el dato sin perderlo.
    producto_id                 INT REFERENCES producto(producto_id) ON DELETE RESTRICT,
    organizacion_id             INT NOT NULL REFERENCES organizacion(organizacion_id) ON DELETE RESTRICT,
    -- Que nomenclatura usa ese codigo: cada fuente usa una distinta.
    nomenclatura                VARCHAR(20) NOT NULL,   -- 'NANDINA', 'NCM', 'CPC', 'ITEM_ALADI', ...
    codigo_externo              VARCHAR(30) NOT NULL,
    descripcion_externa         TEXT,
    CONSTRAINT uq_producto_codigo_externo UNIQUE (organizacion_id, nomenclatura, codigo_externo)
);
COMMENT ON TABLE producto_codigo_externo IS 'Traduce el codigo de producto de cada organizacion/nomenclatura (NCM, CPC, item) al producto canonico NANDINA cuando existe correspondencia.';


-- ============================================================================
--  B.2  ARCHIVO FUENTE (trazabilidad de cada Excel cargado de estas fuentes)
-- ============================================================================
-- Cada archivo de ALADI/MERCOSUR/FAOSTAT corresponde a un pais reportante, un
-- flujo y un anio. Guardamos su origen para saber de donde salio cada fila y
-- poder auditar o reprocesar. (El microdato del INE ya usa carga_archivo en la
-- PARTE A; esta tabla es para las fuentes agregadas.)

CREATE TABLE IF NOT EXISTS archivo_fuente (
    archivo_id          BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    -- De que organizacion proviene el archivo:
    organizacion_id     INT NOT NULL REFERENCES organizacion(organizacion_id) ON DELETE RESTRICT,
    -- Que pais reporta los datos del archivo (ej. el Excel de exportaciones de
    -- Argentina en ALADI). Apunta a la dimension pais de la PARTE A:
    pais_reportante_id  INT REFERENCES pais(pais_id) ON DELETE RESTRICT,
    -- Si el archivo es de exportaciones o importaciones (dimension de la PARTE A):
    flujo_id            SMALLINT REFERENCES flujo_comercial(flujo_id) ON DELETE RESTRICT,
    gestion             SMALLINT,
    ruta_archivo        TEXT NOT NULL,
    filas_detectadas    INTEGER NOT NULL DEFAULT 0,
    estado_revision     VARCHAR(20) NOT NULL DEFAULT 'OK'
                        CHECK (estado_revision IN ('OK','OBSERVADO','CORREGIDO')),
    observacion         TEXT,
    fecha_carga         TIMESTAMPTZ NOT NULL DEFAULT now(),
    -- No se puede cargar dos veces el mismo archivo fisico:
    CONSTRAINT uq_archivo_fuente UNIQUE (ruta_archivo)
);
COMMENT ON TABLE archivo_fuente IS 'Trazabilidad de cada Excel de fuentes agregadas (ALADI/MERCOSUR/FAOSTAT): de que organizacion, pais, flujo y anio proviene.';


-- ============================================================================
--  B.3  TABLA DE HECHOS - FORMA "RANKING"  (la usa ALADI)
-- ============================================================================
-- POR QUE EXISTE: ALADI no da operaciones individuales, da un RANKING ya armado:
-- "el producto N1 representa el 24% del total, acumulado 24%; el N2 el 18%...".
-- Esa forma no cabe en operacion_comercio_exterior (que es microdato). Por eso
-- tiene su propia tabla. Cada fila = una posicion (ordinal) del ranking de un
-- archivo concreto.

CREATE TABLE IF NOT EXISTS ranking_comercio (
    ranking_id            BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    -- De quien es el dato (permite filtrar y comparar por organizacion):
    organizacion_id       INT NOT NULL REFERENCES organizacion(organizacion_id) ON DELETE RESTRICT,
    -- De que archivo salio (trazabilidad, B.2):
    archivo_id            BIGINT NOT NULL REFERENCES archivo_fuente(archivo_id) ON DELETE RESTRICT,
    -- Pais que reporta el ranking (dimension compartida, PARTE A):
    pais_reportante_id    INT REFERENCES pais(pais_id) ON DELETE RESTRICT,
    -- Exportacion o importacion (dimension compartida):
    flujo_id              SMALLINT NOT NULL REFERENCES flujo_comercial(flujo_id) ON DELETE RESTRICT,
    -- Anio del ranking (dimension compartida); gestion se guarda ademas como
    -- numero suelto por si el periodo aun no esta en la tabla tiempo:
    tiempo_id             INT REFERENCES tiempo(tiempo_id) ON DELETE RESTRICT,
    gestion               SMALLINT NOT NULL,
    -- Equivalencia del producto hacia NANDINA si se homologo (B.1):
    producto_codigo_externo_id INT REFERENCES producto_codigo_externo(producto_codigo_externo_id) ON DELETE RESTRICT,
    -- El codigo y la descripcion tal como vienen en el Excel (ALADI maneja codigos
    -- confidenciales como '87------', por eso se guarda el texto crudo tambien):
    item_codigo           VARCHAR(10) NOT NULL,
    descripcion           TEXT,
    es_confidencial       BOOLEAN NOT NULL DEFAULT FALSE,
    -- Datos propios de la forma ranking:
    fila_excel            INTEGER NOT NULL,
    ordinal               SMALLINT NOT NULL CHECK (ordinal > 0),
    valor                 NUMERIC(20,3) NOT NULL CHECK (valor >= 0),
    porcentaje_total      NUMERIC(9,4) CHECK (porcentaje_total BETWEEN 0 AND 100),
    valor_acumulado       NUMERIC(20,3) CHECK (valor_acumulado >= 0),
    porcentaje_acumulado  NUMERIC(9,4) CHECK (porcentaje_acumulado BETWEEN 0 AND 100),
    -- Columnas no previstas que se quieran conservar, sin romper el modelo:
    atributos_extra       JSONB,
    -- Cada fila del Excel se conserva una sola vez (no se fusionan productos):
    CONSTRAINT uq_ranking_archivo_fila UNIQUE (archivo_id, fila_excel),
    CONSTRAINT chk_ranking_fila CHECK (fila_excel >= 2)
);
COMMENT ON TABLE ranking_comercio IS 'Forma RANKING (ALADI): productos ordenados por valor con porcentaje y acumulado. Comparte las dimensiones pais, flujo, tiempo y organizacion de la PARTE A.';


-- ============================================================================
--  B.4  TABLA DE HECHOS - FORMA "SERIE BILATERAL"  (la usa MERCOSUR)
-- ============================================================================
-- POR QUE EXISTE: MERCOSUR informa comercio ENTRE DOS PAISES: un pais reportante
-- y un pais socio (ej. exportaciones de Brasil HACIA Argentina), por producto,
-- anio y flujo. La particularidad es que necesita DOS referencias a pais en la
-- misma fila (origen y destino del comercio), algo que el microdato no tiene.

CREATE TABLE IF NOT EXISTS serie_comercio_bilateral (
    serie_bilateral_id   BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    organizacion_id      INT NOT NULL REFERENCES organizacion(organizacion_id) ON DELETE RESTRICT,
    archivo_id           BIGINT REFERENCES archivo_fuente(archivo_id) ON DELETE SET NULL,
    -- DOS FK hacia la MISMA dimension pais: quien reporta y su socio comercial.
    -- Es la clave de la forma bilateral:
    pais_reportante_id   INT NOT NULL REFERENCES pais(pais_id) ON DELETE RESTRICT,
    pais_socio_id        INT NOT NULL REFERENCES pais(pais_id) ON DELETE RESTRICT,
    -- Producto: directo a NANDINA si se puede, y/o via equivalencia (NCM):
    producto_id          INT REFERENCES producto(producto_id) ON DELETE RESTRICT,
    producto_codigo_externo_id INT REFERENCES producto_codigo_externo(producto_codigo_externo_id) ON DELETE RESTRICT,
    tiempo_id            INT REFERENCES tiempo(tiempo_id) ON DELETE RESTRICT,
    gestion              SMALLINT NOT NULL,
    flujo_id             SMALLINT NOT NULL REFERENCES flujo_comercial(flujo_id) ON DELETE RESTRICT,
    -- Las metricas que da MERCOSUR:
    valor_usd            NUMERIC(18,2) CHECK (valor_usd >= 0),
    volumen_kg           NUMERIC(18,2) CHECK (volumen_kg >= 0),
    atributos_extra      JSONB,
    -- Un pais no comercia consigo mismo: reportante y socio deben ser distintos:
    CONSTRAINT chk_serie_paises_distintos CHECK (pais_reportante_id <> pais_socio_id)
);
COMMENT ON TABLE serie_comercio_bilateral IS 'Forma SERIE BILATERAL (MERCOSUR): valor y volumen entre pais reportante y pais socio, por producto, anio y flujo. Usa dos veces la dimension pais.';


-- ============================================================================
--  B.5  TABLA DE HECHOS - FORMA "SERIE DE INDICADOR AGRICOLA"  (la usa FAOSTAT)
-- ============================================================================
-- POR QUE EXISTE: FAOSTAT no maneja NANDINA ni operaciones; maneja INDICADORES
-- agricolas (ej. "cantidad importada de trigo", "valor exportado de maiz") por
-- pais y anio, con una unidad y un simbolo de calidad. Necesita catalogos propios
-- que no existen en el resto del sistema, por eso se crean aqui.

CREATE TABLE IF NOT EXISTS faostat_elemento (
    elemento_id      INT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    codigo_elemento  INT NOT NULL,
    nombre_elemento  VARCHAR(150) NOT NULL,
    tipo_comercio    VARCHAR(100),
    CONSTRAINT uq_faostat_elemento UNIQUE (codigo_elemento)
);
COMMENT ON TABLE faostat_elemento IS 'Catalogo propio de FAOSTAT: el indicador medido (cantidad/valor importado o exportado). No tiene equivalente en el resto del sistema.';

CREATE TABLE IF NOT EXISTS faostat_simbolo (
    simbolo_id   INT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    codigo       VARCHAR(10) NOT NULL,
    descripcion  VARCHAR(100),
    CONSTRAINT uq_faostat_simbolo UNIQUE (codigo)
);
COMMENT ON TABLE faostat_simbolo IS 'Catalogo propio de FAOSTAT: simbolo de calidad del dato (oficial, estimado, etc.).';

CREATE TABLE IF NOT EXISTS serie_indicador_agricola (
    serie_agricola_id    BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    organizacion_id      INT NOT NULL REFERENCES organizacion(organizacion_id) ON DELETE RESTRICT,
    archivo_id           BIGINT REFERENCES archivo_fuente(archivo_id) ON DELETE SET NULL,
    -- Pais del indicador (dimension compartida de la PARTE A):
    pais_id              INT NOT NULL REFERENCES pais(pais_id) ON DELETE RESTRICT,
    -- Producto via equivalencia, porque FAOSTAT usa codigo CPC, no NANDINA:
    producto_codigo_externo_id INT REFERENCES producto_codigo_externo(producto_codigo_externo_id) ON DELETE RESTRICT,
    tiempo_id            INT REFERENCES tiempo(tiempo_id) ON DELETE RESTRICT,
    gestion              SMALLINT NOT NULL,
    -- Que indicador y con que simbolo de calidad (catalogos propios de arriba):
    elemento_id          INT NOT NULL REFERENCES faostat_elemento(elemento_id) ON DELETE RESTRICT,
    simbolo_id           INT REFERENCES faostat_simbolo(simbolo_id) ON DELETE SET NULL,
    valor                NUMERIC(18,4),
    unidad               VARCHAR(50),
    atributos_extra      JSONB
);
COMMENT ON TABLE serie_indicador_agricola IS 'Forma SERIE AGRICOLA (FAOSTAT): valor de un indicador por pais, producto (CPC) y anio. Usa pais y tiempo compartidos, y catalogos propios de FAOSTAT.';


-- ============================================================================
--  B.6  INDICES DE LA PARTE B
-- ============================================================================
-- Se indexan las claves foraneas mas usadas para filtrar y unir, y un indice GIN
-- por cada columna JSONB para poder consultar dentro de los atributos_extra.

CREATE INDEX IF NOT EXISTS idx_pce_pais            ON pais_codigo_externo (pais_id);
CREATE INDEX IF NOT EXISTS idx_pce_org             ON pais_codigo_externo (organizacion_id);
CREATE INDEX IF NOT EXISTS idx_prodce_producto     ON producto_codigo_externo (producto_id);
CREATE INDEX IF NOT EXISTS idx_prodce_org          ON producto_codigo_externo (organizacion_id);

CREATE INDEX IF NOT EXISTS idx_archivo_org         ON archivo_fuente (organizacion_id);
CREATE INDEX IF NOT EXISTS idx_archivo_pais_flujo  ON archivo_fuente (pais_reportante_id, flujo_id, gestion);

CREATE INDEX IF NOT EXISTS idx_ranking_org         ON ranking_comercio (organizacion_id);
CREATE INDEX IF NOT EXISTS idx_ranking_archivo     ON ranking_comercio (archivo_id);
CREATE INDEX IF NOT EXISTS idx_ranking_gestion     ON ranking_comercio (gestion);
CREATE INDEX IF NOT EXISTS idx_ranking_valor       ON ranking_comercio (valor DESC);
CREATE INDEX IF NOT EXISTS idx_ranking_item        ON ranking_comercio (item_codigo);

CREATE INDEX IF NOT EXISTS idx_serie_org           ON serie_comercio_bilateral (organizacion_id);
CREATE INDEX IF NOT EXISTS idx_serie_reportante    ON serie_comercio_bilateral (pais_reportante_id);
CREATE INDEX IF NOT EXISTS idx_serie_socio         ON serie_comercio_bilateral (pais_socio_id);
CREATE INDEX IF NOT EXISTS idx_serie_producto      ON serie_comercio_bilateral (producto_id);
CREATE INDEX IF NOT EXISTS idx_serie_gestion_flujo ON serie_comercio_bilateral (gestion, flujo_id);

CREATE INDEX IF NOT EXISTS idx_agricola_org        ON serie_indicador_agricola (organizacion_id);
CREATE INDEX IF NOT EXISTS idx_agricola_pais       ON serie_indicador_agricola (pais_id);
CREATE INDEX IF NOT EXISTS idx_agricola_gestion    ON serie_indicador_agricola (gestion);
CREATE INDEX IF NOT EXISTS idx_agricola_elemento   ON serie_indicador_agricola (elemento_id);
-- El dashboard de FAOSTAT siempre filtra por elemento_id (indices de valor) + gestion:
-- este compuesto evita el BitmapAnd de los dos indices sueltos de arriba (3x mas rapido).
CREATE INDEX IF NOT EXISTS idx_agricola_elemento_gestion ON serie_indicador_agricola (elemento_id, gestion);

CREATE INDEX IF NOT EXISTS idx_ranking_extra   ON ranking_comercio          USING GIN (atributos_extra);
CREATE INDEX IF NOT EXISTS idx_serie_extra     ON serie_comercio_bilateral  USING GIN (atributos_extra);
CREATE INDEX IF NOT EXISTS idx_agricola_extra  ON serie_indicador_agricola  USING GIN (atributos_extra);

-- ############################################################################
-- ##  PARTE C - INTEGRACION DE LA COMUNIDAD ANDINA (CAN)                    ##
-- ############################################################################
-- Los archivos de la CAN son de DOS naturalezas distintas:
--   GRUPO 1 (7 archivos): cuadros estadisticos pivoteados (PIB, remesas,
--     contenedores, trafico portuario, transporte aereo, agropecuario, comercio
--     historico). Cada cuadro es una SERIE DE INDICADORES: el valor de un
--     indicador para una geografia y un periodo. Es una FORMA nueva, distinta
--     del microdato (INE), el ranking (ALADI), la serie bilateral (MERCOSUR) y
--     la serie agricola (FAOSTAT). Por eso recibe su propia familia de tablas.
--   GRUPO 2 (2 archivos): nomenclatura NANDINA (D812/D885) y sus correlaciones
--     entre Decisiones (SGdt515). NO es estadistica: es el catalogo de productos
--     y su historial de versiones. Alimenta el catalogo "producto" ya existente
--     (PARTE A) y agrega el versionado de la nomenclatura.
--
-- MEJORAS al modelo existente que la CAN hizo evidentes (justificadas abajo):
--   1) Falta geografia subnacional (puertos, departamentos de otros paises):
--      se agrega "unidad_subnacional" ligada a pais. La tabla "departamento" de
--      la PARTE A queda solo para el microdato del INE (Bolivia); no se fuerza a
--      contener puertos extranjeros.
--   2) El "tiempo" de la PARTE A es de grano MENSUAL y no representa bien series
--      anuales o trimestrales. Se agrega "periodo_estadistico" con periodicidad
--      explicita (ANUAL/SEMESTRAL/TRIMESTRAL/MENSUAL) para las series de la CAN.


-- ============================================================================
--  C.1  GEOGRAFIA SUBNACIONAL (puertos, departamentos, regiones de cualquier pais)
-- ============================================================================
-- POR QUE: la CAN reporta por puerto (Buenaventura, Cartagena) y por departamento
-- de paises distintos a Bolivia. "departamento" (PARTE A) es solo del INE. Esta
-- tabla generaliza la geografia interna de CUALQUIER pais, sin duplicar "pais".
CREATE TABLE IF NOT EXISTS unidad_subnacional (
    unidad_subnacional_id  INT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    pais_id                INT NOT NULL REFERENCES pais(pais_id) ON DELETE RESTRICT,
    tipo                   VARCHAR(20) NOT NULL CHECK (tipo IN ('PUERTO','DEPARTAMENTO','REGION','CIUDAD','OTRO')),
    nombre                 VARCHAR(120) NOT NULL,
    CONSTRAINT uq_unidad_subnacional UNIQUE (pais_id, tipo, nombre)
);
COMMENT ON TABLE unidad_subnacional IS 'Geografia interna de cualquier pais (puerto, departamento, region). Reutiliza la dimension pais; no duplica departamento del INE.';


-- ============================================================================
--  C.2  PERIODO ESTADISTICO (anual / trimestral / mensual)
-- ============================================================================
-- POR QUE: la dimension "tiempo" (PARTE A) es (gestion, mes), grano mensual. Las
-- series de la CAN son anuales (PIB historico, comercio 1969-2024) y trimestrales
-- (remesas, trafico portuario, PIB). Forzar un trimestre dentro de un mes seria
-- incorrecto. Esta dimension representa el periodo con su periodicidad explicita.
CREATE TABLE IF NOT EXISTS periodo_estadistico (
    periodo_estadistico_id  INT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    gestion                 SMALLINT NOT NULL,
    periodicidad            VARCHAR(12) NOT NULL CHECK (periodicidad IN ('ANUAL','SEMESTRAL','TRIMESTRAL','MENSUAL')),
    numero                  SMALLINT NOT NULL DEFAULT 0,   -- 0=anual; 1-2 semestre; 1-4 trimestre; 1-12 mes
    etiqueta                VARCHAR(20) NOT NULL,          -- '2024', 'I 2020', 'Ene 2015', etc.
    CONSTRAINT uq_periodo_estadistico UNIQUE (gestion, periodicidad, numero),
    CONSTRAINT chk_periodo_numero CHECK (numero BETWEEN 0 AND 12)
);
COMMENT ON TABLE periodo_estadistico IS 'Periodo con periodicidad explicita (anual/semestral/trimestral/mensual) para las series agregadas de la CAN. Complementa a tiempo (mensual) sin reemplazarlo.';


-- ============================================================================
--  C.3  CATALOGOS DE LA SERIE DE INDICADORES
-- ============================================================================
-- Un TEMA agrupa indicadores afines (Cuentas Nacionales, Remesas, Logistica
-- Portuaria, Transporte Aereo, Agropecuario, Comercio). Sirve para navegar el
-- portal por dominio.
CREATE TABLE IF NOT EXISTS tema_estadistico (
    tema_id      SMALLINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    nombre       VARCHAR(80) NOT NULL,
    descripcion  VARCHAR(255),
    CONSTRAINT uq_tema_estadistico UNIQUE (nombre)
);
COMMENT ON TABLE tema_estadistico IS 'Dominio tematico de los indicadores de la CAN (cuentas nacionales, remesas, logistica, transporte aereo, agropecuario, comercio).';

-- Un INDICADOR es lo que se mide (PIB, remesas recibidas, TEUS movilizados,
-- toneladas de carga, vuelos, superficie cosechada, etc.). Pertenece a un tema.
CREATE TABLE IF NOT EXISTS indicador (
    indicador_id   INT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    tema_id        SMALLINT NOT NULL REFERENCES tema_estadistico(tema_id) ON DELETE RESTRICT,
    nombre         VARCHAR(150) NOT NULL,
    descripcion    VARCHAR(255),
    CONSTRAINT uq_indicador UNIQUE (tema_id, nombre)
);
COMMENT ON TABLE indicador IS 'Concepto medido por la CAN (PIB, remesas, TEUS, toneladas, vuelos, superficie, etc.), agrupado por tema.';

-- La UNIDAD DE MEDIDA del valor. Catalogo reutilizable (Millones USD, %, TEUS,
-- Toneladas, Hectareas, Pasajeros, Vuelos...).
CREATE TABLE IF NOT EXISTS unidad_medida (
    unidad_medida_id  SMALLINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    codigo            VARCHAR(20) NOT NULL,
    nombre            VARCHAR(80) NOT NULL,
    CONSTRAINT uq_unidad_medida UNIQUE (codigo)
);
COMMENT ON TABLE unidad_medida IS 'Unidad en que se expresa el valor del indicador (Millones USD, %, TEUS, Toneladas, Hectareas, etc.).';


-- ============================================================================
--  C.4  TABLA DE HECHOS - FORMA "SERIE DE INDICADOR ECONOMICO" (CAN)
-- ============================================================================
-- POR QUE EXISTE: cubre los 7 cuadros estadisticos de la CAN con UNA sola forma.
-- Cada fila = el valor de un indicador, para una geografia (el bloque CAN via
-- zona, o un pais, o una unidad subnacional), en un periodo, con su unidad y su
-- tipo de medida (valor absoluto o variacion porcentual). La geografia se expresa
-- con FK nullables: se usa la que corresponda al nivel del dato.
CREATE TABLE IF NOT EXISTS serie_indicador_economico (
    serie_economico_id     BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    organizacion_id        INT NOT NULL REFERENCES organizacion(organizacion_id) ON DELETE RESTRICT,
    archivo_id             BIGINT REFERENCES archivo_fuente(archivo_id) ON DELETE SET NULL,
    indicador_id           INT NOT NULL REFERENCES indicador(indicador_id) ON DELETE RESTRICT,
    -- Geografia: se usa UNA de las tres segun el nivel del dato.
    zona_id                SMALLINT REFERENCES zona_geoeconomica(zona_id) ON DELETE RESTRICT,      -- bloque CAN (agregado)
    pais_id                INT REFERENCES pais(pais_id) ON DELETE RESTRICT,                          -- pais miembro
    unidad_subnacional_id  INT REFERENCES unidad_subnacional(unidad_subnacional_id) ON DELETE RESTRICT, -- puerto/departamento
    nivel_geografico       VARCHAR(15) NOT NULL CHECK (nivel_geografico IN ('BLOQUE','PAIS','SUBNACIONAL')),
    -- Tiempo: periodo con periodicidad explicita.
    periodo_id             INT NOT NULL REFERENCES periodo_estadistico(periodo_estadistico_id) ON DELETE RESTRICT,
    gestion                SMALLINT NOT NULL,
    -- Medida.
    unidad_medida_id       SMALLINT NOT NULL REFERENCES unidad_medida(unidad_medida_id) ON DELETE RESTRICT,
    tipo_medida            VARCHAR(15) NOT NULL DEFAULT 'VALOR' CHECK (tipo_medida IN ('VALOR','VARIACION_PCT')),
    valor                  NUMERIC(20,4),
    atributos_extra        JSONB
);
COMMENT ON TABLE serie_indicador_economico IS 'Forma SERIE DE INDICADORES (CAN): un valor de indicador por geografia (bloque/pais/subnacional), periodo y unidad. Cubre PIB, remesas, contenedores, trafico portuario, transporte aereo, agropecuario y comercio historico.';
COMMENT ON COLUMN serie_indicador_economico.nivel_geografico IS 'Indica cual de las tres FK de geografia esta en uso: BLOQUE (zona_id), PAIS (pais_id) o SUBNACIONAL (unidad_subnacional_id).';


-- ============================================================================
--  C.5  NOMENCLATURA NANDINA: VERSIONES Y CORRELACIONES
-- ============================================================================
-- POR QUE: los archivos D812/D885 y SGdt515 traen el catalogo NANDINA en varias
-- versiones (Decisiones D249 ... D885) y las correlaciones entre ellas (que codigo
-- de una version equivale a que codigo de otra). Esto NO duplica "producto" (PARTE
-- A): lo NUTRE (es la fuente oficial de los codigos) y agrega la trazabilidad de
-- como evolucionan los codigos al cambiar de version.
CREATE TABLE IF NOT EXISTS nomenclatura_version (
    version_id   SMALLINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    codigo       VARCHAR(10) NOT NULL,        -- 'D812', 'D885', 'D270', ...
    anio         SMALLINT,
    descripcion  VARCHAR(150),
    CONSTRAINT uq_nomenclatura_version UNIQUE (codigo)
);
COMMENT ON TABLE nomenclatura_version IS 'Version de la nomenclatura NANDINA segun la Decision de la CAN (D249 ... D885).';

CREATE TABLE IF NOT EXISTS correlacion_nandina (
    correlacion_id      BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    version_origen_id   SMALLINT NOT NULL REFERENCES nomenclatura_version(version_id) ON DELETE RESTRICT,
    version_destino_id  SMALLINT NOT NULL REFERENCES nomenclatura_version(version_id) ON DELETE RESTRICT,
    codigo_origen       VARCHAR(12) NOT NULL,
    codigo_destino      VARCHAR(12),
    designacion         TEXT,
    producto_id         INT REFERENCES producto(producto_id) ON DELETE SET NULL,   -- homologacion al catalogo canonico, si aplica
    CONSTRAINT chk_correlacion_versiones CHECK (version_origen_id <> version_destino_id)
);
COMMENT ON TABLE correlacion_nandina IS 'Correlacion de codigos NANDINA entre dos versiones (Decisiones). Permite seguir un producto cuando cambia de codigo al actualizarse la nomenclatura.';


-- ============================================================================
--  C.6  INDICES DE LA PARTE C
-- ============================================================================
CREATE INDEX IF NOT EXISTS idx_unidad_subnac_pais   ON unidad_subnacional (pais_id);
CREATE INDEX IF NOT EXISTS idx_periodo_gestion      ON periodo_estadistico (gestion, periodicidad);
CREATE INDEX IF NOT EXISTS idx_indicador_tema       ON indicador (tema_id);

CREATE INDEX IF NOT EXISTS idx_serie_eco_org        ON serie_indicador_economico (organizacion_id);
CREATE INDEX IF NOT EXISTS idx_serie_eco_indicador  ON serie_indicador_economico (indicador_id);
CREATE INDEX IF NOT EXISTS idx_serie_eco_pais       ON serie_indicador_economico (pais_id);
CREATE INDEX IF NOT EXISTS idx_serie_eco_zona       ON serie_indicador_economico (zona_id);
CREATE INDEX IF NOT EXISTS idx_serie_eco_subnac     ON serie_indicador_economico (unidad_subnacional_id);
CREATE INDEX IF NOT EXISTS idx_serie_eco_periodo    ON serie_indicador_economico (periodo_id);
CREATE INDEX IF NOT EXISTS idx_serie_eco_gestion    ON serie_indicador_economico (gestion);
CREATE INDEX IF NOT EXISTS idx_serie_eco_extra      ON serie_indicador_economico USING GIN (atributos_extra);

CREATE INDEX IF NOT EXISTS idx_correlacion_origen   ON correlacion_nandina (version_origen_id, codigo_origen);
CREATE INDEX IF NOT EXISTS idx_correlacion_destino  ON correlacion_nandina (version_destino_id, codigo_destino);
CREATE INDEX IF NOT EXISTS idx_correlacion_producto ON correlacion_nandina (producto_id);

-- ##  BLOQUE 2 DE 2 : ADICIONES GEODATA (tablas nuevas + semilla + vistas)    ##
-- ############################################################################

-- ============================================================================
--  GEODATA — ADICIONES A LA BASE DE DATOS (ejecutar DESPUÉS de comercio.sql)
--  Tablas nuevas para: serie por zona, serie por producto/zona, contenido
--  dinámico, sistema de pagos/descargas, perfiles OAuth y vistas materializadas.
-- ============================================================================

-- ############################################################################
-- ##  SERIE COMERCIAL AGREGADA POR ZONA (MERCOSUR — Excel por PAÍS)         ##
-- ############################################################################
-- Los Excel como "Exp_e_Imp_2000_2026_Union_Europea.xlsx" traen datos
-- del MERCOSUR agregados por zona geoeconómica con formato:
-- ISO 3166 | País | Año | Exportaciones | Imp FOB | Imp CIF | Vol Exp | Vol Imp

CREATE TABLE IF NOT EXISTS serie_comercio_zona (
    serie_zona_id           BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    organizacion_id         INT NOT NULL REFERENCES organizacion(organizacion_id) ON DELETE RESTRICT,
    archivo_id              BIGINT REFERENCES archivo_fuente(archivo_id) ON DELETE SET NULL,
    zona_id                 SMALLINT REFERENCES zona_geoeconomica(zona_id) ON DELETE RESTRICT,
    pais_id                 INT REFERENCES pais(pais_id) ON DELETE RESTRICT,
    pais_iso3166            VARCHAR(10),
    pais_nombre_original    VARCHAR(150),
    tiempo_id               INT REFERENCES tiempo(tiempo_id) ON DELETE RESTRICT,
    gestion                 SMALLINT NOT NULL,
    exportaciones_usd       NUMERIC(20,2) CHECK (exportaciones_usd >= 0),
    importaciones_fob_usd   NUMERIC(20,2) CHECK (importaciones_fob_usd >= 0),
    importaciones_cif_usd   NUMERIC(20,2) CHECK (importaciones_cif_usd >= 0),
    volumen_export_kg       NUMERIC(20,2) CHECK (volumen_export_kg >= 0),
    volumen_import_kg       NUMERIC(20,2) CHECK (volumen_import_kg >= 0),
    balanza_comercial_usd   NUMERIC(20,2) GENERATED ALWAYS AS (exportaciones_usd - importaciones_cif_usd) STORED,
    atributos_extra         JSONB
);

CREATE INDEX IF NOT EXISTS idx_scz_org      ON serie_comercio_zona (organizacion_id);
CREATE INDEX IF NOT EXISTS idx_scz_zona     ON serie_comercio_zona (zona_id);
CREATE INDEX IF NOT EXISTS idx_scz_pais     ON serie_comercio_zona (pais_id);
CREATE INDEX IF NOT EXISTS idx_scz_gestion  ON serie_comercio_zona (gestion);
CREATE INDEX IF NOT EXISTS idx_scz_extra    ON serie_comercio_zona USING GIN (atributos_extra);

COMMENT ON TABLE serie_comercio_zona IS 'Serie comercial agregada del MERCOSUR por zona geoeconómica y país destino. Cada fila = un país en un año con exportaciones, importaciones y volúmenes.';


-- ############################################################################
-- ##  SERIE COMERCIAL POR PRODUCTO NCM POR ZONA (MERCOSUR — Excel por ITEM) ##
-- ############################################################################
-- Los Excel como "Exp_e_Imp_2000_2026_Alianza_del_pacifico.xlsx" (170K+ filas)
-- traen datos por producto NCM con formato:
-- NCM | Descripción | Año | Exportaciones | Imp FOB | Imp CIF | Vol Exp | Vol Imp

CREATE TABLE IF NOT EXISTS serie_comercio_producto_zona (
    serie_prod_zona_id      BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    organizacion_id         INT NOT NULL REFERENCES organizacion(organizacion_id) ON DELETE RESTRICT,
    archivo_id              BIGINT REFERENCES archivo_fuente(archivo_id) ON DELETE SET NULL,
    zona_id                 SMALLINT REFERENCES zona_geoeconomica(zona_id) ON DELETE RESTRICT,
    producto_codigo_externo_id INT REFERENCES producto_codigo_externo(producto_codigo_externo_id) ON DELETE RESTRICT,
    ncm_codigo              VARCHAR(12) NOT NULL,
    ncm_descripcion         TEXT,
    tiempo_id               INT REFERENCES tiempo(tiempo_id) ON DELETE RESTRICT,
    gestion                 SMALLINT NOT NULL,
    exportaciones_usd       NUMERIC(20,2) CHECK (exportaciones_usd >= 0),
    importaciones_fob_usd   NUMERIC(20,2) CHECK (importaciones_fob_usd >= 0),
    importaciones_cif_usd   NUMERIC(20,2) CHECK (importaciones_cif_usd >= 0),
    volumen_export_kg       NUMERIC(20,2) CHECK (volumen_export_kg >= 0),
    volumen_import_kg       NUMERIC(20,2) CHECK (volumen_import_kg >= 0),
    atributos_extra         JSONB
);

CREATE INDEX IF NOT EXISTS idx_scpz_org      ON serie_comercio_producto_zona (organizacion_id);
CREATE INDEX IF NOT EXISTS idx_scpz_zona     ON serie_comercio_producto_zona (zona_id);
CREATE INDEX IF NOT EXISTS idx_scpz_ncm      ON serie_comercio_producto_zona (ncm_codigo);
CREATE INDEX IF NOT EXISTS idx_scpz_gestion  ON serie_comercio_producto_zona (gestion);
CREATE INDEX IF NOT EXISTS idx_scpz_extra    ON serie_comercio_producto_zona USING GIN (atributos_extra);

COMMENT ON TABLE serie_comercio_producto_zona IS 'Serie por producto NCM del MERCOSUR dentro de una zona geoeconómica. Cada fila = un producto en un año con exportaciones, importaciones y volúmenes.';


-- ############################################################################
-- ##  CONTENIDO DINÁMICO DEL PORTAL                                         ##
-- ############################################################################

CREATE TABLE IF NOT EXISTS contenido_portal (
    contenido_id    INT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    seccion         VARCHAR(60) NOT NULL,
    clave           VARCHAR(80) NOT NULL,
    tipo            VARCHAR(20) NOT NULL CHECK (tipo IN ('texto','html','imagen','numero','json')),
    valor           TEXT,
    orden           SMALLINT NOT NULL DEFAULT 0,
    activo          BOOLEAN NOT NULL DEFAULT TRUE,
    updated_at      TIMESTAMPTZ NOT NULL DEFAULT now(),
    CONSTRAINT uq_contenido UNIQUE (seccion, clave)
);

COMMENT ON TABLE contenido_portal IS 'Textos, imágenes y números editables desde el panel admin para el portal público.';

CREATE TABLE IF NOT EXISTS organizacion_media (
    media_id        INT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    organizacion_id INT NOT NULL REFERENCES organizacion(organizacion_id) ON DELETE CASCADE,
    tipo            VARCHAR(20) NOT NULL CHECK (tipo IN ('logo','banner','icono','grafico_ejemplo')),
    ruta_archivo    VARCHAR(500) NOT NULL,
    titulo          VARCHAR(150),
    orden           SMALLINT NOT NULL DEFAULT 0,
    activo          BOOLEAN NOT NULL DEFAULT TRUE,
    created_at      TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE INDEX IF NOT EXISTS idx_org_media_org ON organizacion_media (organizacion_id);

CREATE TABLE IF NOT EXISTS organizacion_detalle (
    detalle_id           INT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    organizacion_id      INT NOT NULL REFERENCES organizacion(organizacion_id) ON DELETE CASCADE,
    descripcion_corta    VARCHAR(500),
    descripcion_larga    TEXT,
    metodologia          TEXT,
    cobertura_temporal   VARCHAR(100),
    cobertura_geografica VARCHAR(200),
    tipos_datos          TEXT,
    url_fuente_oficial   VARCHAR(500),
    color_primario       CHAR(7),
    color_secundario     CHAR(7),
    icono_clase          VARCHAR(60),
    updated_at           TIMESTAMPTZ NOT NULL DEFAULT now(),
    CONSTRAINT uq_org_detalle UNIQUE (organizacion_id)
);

COMMENT ON TABLE organizacion_detalle IS 'Información extendida de cada organización para el portal público: descripción, colores, metodología, etc.';


-- ############################################################################
-- ##  PERFILES PÚBLICOS, CRÉDITOS Y DESCARGAS                               ##
-- ############################################################################

CREATE TABLE IF NOT EXISTS usuario_perfil_publico (
    perfil_id            INT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    usuario_id           INT NOT NULL REFERENCES usuario(usuario_id) ON DELETE CASCADE,
    google_id            VARCHAR(100),
    avatar_url           VARCHAR(500),
    institucion          VARCHAR(200),
    pais                 VARCHAR(60),
    motivo_uso           VARCHAR(100),
    creditos_disponibles INT NOT NULL DEFAULT 0,
    CONSTRAINT uq_perfil_usuario UNIQUE (usuario_id),
    CONSTRAINT uq_google_id UNIQUE (google_id)
);

CREATE TABLE IF NOT EXISTS paquete_creditos (
    paquete_id   INT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    nombre       VARCHAR(80) NOT NULL,
    creditos     INT NOT NULL CHECK (creditos > 0),
    precio_usd   NUMERIC(8,2) NOT NULL CHECK (precio_usd >= 0),
    activo       BOOLEAN NOT NULL DEFAULT TRUE,
    descripcion  VARCHAR(255)
);

CREATE TABLE IF NOT EXISTS compra_creditos (
    compra_id          BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    usuario_id         INT NOT NULL REFERENCES usuario(usuario_id) ON DELETE RESTRICT,
    paquete_id         INT REFERENCES paquete_creditos(paquete_id) ON DELETE SET NULL,
    creditos_comprados INT NOT NULL,
    monto_usd          NUMERIC(8,2) NOT NULL,
    metodo_pago        VARCHAR(40),
    referencia_externa VARCHAR(200),
    estado             VARCHAR(20) NOT NULL DEFAULT 'COMPLETADO'
                       CHECK (estado IN ('PENDIENTE','COMPLETADO','FALLIDO','REEMBOLSADO')),
    fecha              TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE INDEX IF NOT EXISTS idx_compra_usuario ON compra_creditos (usuario_id);

CREATE TABLE IF NOT EXISTS solicitud_descarga (
    solicitud_id      BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    usuario_id        INT NOT NULL REFERENCES usuario(usuario_id) ON DELETE RESTRICT,
    organizacion_id   INT REFERENCES organizacion(organizacion_id) ON DELETE SET NULL,
    tipo_reporte      VARCHAR(60) NOT NULL,
    formato           VARCHAR(20) NOT NULL CHECK (formato IN ('csv','xlsx_plana','xlsx_dinamica','xlsx_informe','pdf')),
    parametros        JSONB NOT NULL,
    creditos_cobrados INT NOT NULL DEFAULT 0,
    estado            VARCHAR(20) NOT NULL DEFAULT 'PENDIENTE'
                      CHECK (estado IN ('PENDIENTE','GENERANDO','LISTO','ERROR','EXPIRADO')),
    ruta_archivo      VARCHAR(500),
    tamano_bytes      BIGINT,
    fecha_solicitud   TIMESTAMPTZ NOT NULL DEFAULT now(),
    fecha_listo       TIMESTAMPTZ,
    fecha_expiracion  TIMESTAMPTZ
);

CREATE INDEX IF NOT EXISTS idx_descarga_usuario ON solicitud_descarga (usuario_id);
CREATE INDEX IF NOT EXISTS idx_descarga_estado  ON solicitud_descarga (estado);
CREATE INDEX IF NOT EXISTS idx_descarga_org     ON solicitud_descarga (organizacion_id);


-- SEMILLAS (solo si la tabla esta vacia)
DO $seed$ BEGIN
  IF NOT EXISTS (SELECT 1 FROM organizacion) THEN
INSERT INTO organizacion (nombre, sigla, pais_iso3, url, activo) VALUES
('Instituto Nacional de Estadística de Bolivia', 'INE', 'BOL', 'https://www.ine.gob.bo', true),
('Asociación Latinoamericana de Integración', 'ALADI', NULL, 'https://www.aladi.org', true),
('Mercado Común del Sur', 'MERCOSUR', NULL, 'https://www.mercosur.int', true),
('Organización de las Naciones Unidas para la Alimentación y la Agricultura', 'FAOSTAT', NULL, 'https://www.fao.org/faostat', true);
  END IF;
END $seed$;

DO $seed$ BEGIN
  IF NOT EXISTS (SELECT 1 FROM tipo_operacion) THEN
INSERT INTO tipo_operacion (nombre, base_valoracion) VALUES
('Exportación', 'FOB'),
('Importación', 'CIF');
  END IF;
END $seed$;

DO $seed$ BEGIN
  IF NOT EXISTS (SELECT 1 FROM flujo_comercial) THEN
INSERT INTO flujo_comercial (codigo_flujo, descripcion) VALUES
('1', 'Exportación'),
('2', 'Importación'),
('3', 'Reexportación');
  END IF;
END $seed$;

DO $seed$ BEGIN
  IF NOT EXISTS (SELECT 1 FROM organizacion_detalle) THEN
INSERT INTO organizacion_detalle (organizacion_id, descripcion_corta, descripcion_larga, cobertura_temporal, cobertura_geografica, tipos_datos, url_fuente_oficial, color_primario, color_secundario) VALUES
(1,
 'Microdato de comercio exterior de Bolivia con detalle de cada operación de exportación e importación.',
 'El Instituto Nacional de Estadística (INE) de Bolivia provee la información más granular disponible sobre el comercio exterior boliviano. Cada registro representa una operación individual con detalle de producto (NANDINA 10 dígitos), país destino/origen, departamento, aduana, medio de transporte, clasificaciones económicas (CUCI, GCE, CIIU, TNT) y valores en USD y kilogramos. La cobertura temporal abarca desde 1992 hasta 2026, con variaciones en las cabeceras según el periodo.',
 '1992-2026', 'Bolivia → Mundo (todos los países destino/origen)',
 'Microdato por operación individual. Valores FOB (exportaciones) y CIF (importaciones). Pesos bruto, neto y fino en kilogramos. Clasificaciones NANDINA, CUCI Rev.3, GCE Rev.3, CIIU Rev.3 y TNT.',
 'https://www.ine.gob.bo/index.php/censos-y-banco-de-datos/censos/', '#1F3864', '#C53030'),
(2,
 'Rankings de productos exportados e importados por los países miembros de ALADI.',
 'La Asociación Latinoamericana de Integración (ALADI) provee rankings de los principales productos del comercio exterior de sus 13 países miembros. Los datos incluyen valor en USD, porcentaje del total, valor acumulado y porcentaje acumulado, ordenados por importancia del producto en el comercio del país.',
 '1993-2025', 'Países miembros de ALADI (13 países latinoamericanos)',
 'Rankings por producto (código SA), valores acumulados, porcentajes de participación.',
 'https://www.aladi.org', '#2D5F2D', '#4A8C4A'),
(3,
 'Flujos comerciales entre los países del MERCOSUR y con el resto del mundo, por zona geoeconómica y por producto.',
 'El Mercado Común del Sur (MERCOSUR) provee series históricas del comercio entre sus países miembros y con zonas geoeconómicas del mundo (Unión Europea, Asia, América del Norte, etc.). Los datos se presentan en dos formatos: agregados por país/zona (exportaciones, importaciones FOB/CIF y volúmenes) y desagregados por producto (nomenclatura NCM).',
 '2000-2026', 'MERCOSUR (Argentina, Brasil, Paraguay, Uruguay, Bolivia, Venezuela) ↔ Mundo',
 'Series anuales por país y zona geoeconómica. Series por producto NCM. Exportaciones, importaciones FOB y CIF, volúmenes en kg.',
 'https://www.mercosur.int', '#1A4B8C', '#3B82F6'),
(4,
 'Indicadores agrícolas, alimentarios y de población de Bolivia según la FAO.',
 'La Organización de las Naciones Unidas para la Alimentación y la Agricultura (FAO) a través de FAOSTAT provee indicadores clave sobre la situación agrícola y alimentaria de Bolivia: distribución de la población rural/urbana, consumo de fertilizantes por tipo de nutriente, prevalencia de subalimentación y producción de cereales.',
 '1961-2024', 'Bolivia',
 'Indicadores de población (miles de personas), fertilizantes (toneladas de nutriente), subalimentación (prevalencia % y millones de personas), producción de cereales (hectáreas, kg/ha, toneladas).',
 'https://www.fao.org/faostat/es/', '#D97706', '#F59E0B');
  END IF;
END $seed$;

DO $seed$ BEGIN
  IF NOT EXISTS (SELECT 1 FROM paquete_creditos) THEN
INSERT INTO paquete_creditos (nombre, creditos, precio_usd, descripcion) VALUES
('Básico', 10, 2.06, '10 descargas en formato CSV o XLS Tabla Plana'),
('Profesional', 50, 8.00, '50 descargas en cualquier formato disponible'),
('Institucional', 200, 25.00, '200 descargas con acceso a todos los formatos y reportes');
  END IF;
END $seed$;

DO $seed$ BEGIN
  IF NOT EXISTS (SELECT 1 FROM contenido_portal) THEN
INSERT INTO contenido_portal (seccion, clave, tipo, valor, orden) VALUES
('home', 'hero_titulo', 'texto', 'Comercio exterior de Bolivia de un vistazo', 1),
('home', 'hero_subtitulo', 'texto', 'Datos oficiales del INE, ALADI, MERCOSUR y FAOSTAT centralizados en una sola plataforma.', 2),
('home', 'hero_cta', 'texto', 'Explorar datos', 3),
('home', 'kpi_nota', 'texto', 'Datos actualizados al último periodo disponible. Fuente: INE Bolivia.', 10),
('acerca_de', 'titulo', 'texto', 'Acerca de Geodata', 1),
('acerca_de', 'descripcion', 'html', '<p>Geodata es una plataforma de análisis de comercio exterior de Bolivia que centraliza información de múltiples organizaciones estadísticas internacionales.</p>', 2),
('acerca_de', 'mision', 'texto', 'Facilitar el acceso, análisis y comprensión de los datos de comercio exterior de Bolivia mediante herramientas visuales e interactivas.', 3);
  END IF;
END $seed$;

DO $seed$ BEGIN
  IF NOT EXISTS (SELECT 1 FROM fuente_datos) THEN
INSERT INTO fuente_datos (organizacion_id, url_fuente, fecha_descarga, version_nomenclatura, observaciones) VALUES
(1, 'https://www.ine.gob.bo', '2026-06-01', 'INE_2026', 'Microdato de exportaciones e importaciones 1992-2026'),
(2, 'https://www.aladi.org', '2026-06-01', 'ALADI_2025', 'Rankings de productos exportados e importados'),
(3, 'https://www.mercosur.int', '2026-06-01', 'MERCOSUR_2026', 'Series comerciales por zona y por producto NCM'),
(4, 'https://www.fao.org/faostat', '2026-06-01', 'FAOSTAT_2024', 'Indicadores agrícolas y alimentarios de Bolivia');
  END IF;
END $seed$;

DO $seed$ BEGIN
  IF NOT EXISTS (SELECT 1 FROM zona_geoeconomica) THEN
INSERT INTO zona_geoeconomica (fuente_id, codigo_zona, descripcion) VALUES
(3, 1, 'Mercado Común del Sur (MERCOSUR 4)'),
(3, 2, 'Mercado Común del Sur (MERCOSUR 5)'),
(3, 3, 'América del Sur'),
(3, 4, 'América del Norte'),
(3, 5, 'América Excepto MERCOSUR'),
(3, 6, 'Sur América Excepto MERCOSUR'),
(3, 7, 'Alianza del Pacífico'),
(3, 8, 'Unión Europea'),
(3, 9, 'Asociación Europea de Libre Comercio (AELC)'),
(3, 10, 'Europa Oriental'),
(3, 11, 'Asia Menos Oriente Medio'),
(3, 12, 'Oriente Medio'),
(3, 13, 'Oceanía'),
(3, 14, 'África Menos Oriente Medio'),
(3, 15, 'Extrazona'),
(3, 16, 'Extrazona MERCOSUR 5');
  END IF;
END $seed$;



-- Resumen MERCOSUR por zona y año
CREATE MATERIALIZED VIEW IF NOT EXISTS mv_resumen_mercosur_zona AS
SELECT
    sz.zona_id,
    zg.descripcion AS zona_nombre,
    sz.gestion,
    SUM(sz.exportaciones_usd) AS total_exportaciones,
    SUM(sz.importaciones_cif_usd) AS total_importaciones,
    SUM(sz.exportaciones_usd) - SUM(sz.importaciones_cif_usd) AS balanza,
    COUNT(DISTINCT sz.pais_id) AS paises,
    SUM(sz.volumen_export_kg) AS vol_export,
    SUM(sz.volumen_import_kg) AS vol_import
FROM serie_comercio_zona sz
JOIN zona_geoeconomica zg ON sz.zona_id = zg.zona_id
GROUP BY sz.zona_id, zg.descripcion, sz.gestion
WITH DATA;

