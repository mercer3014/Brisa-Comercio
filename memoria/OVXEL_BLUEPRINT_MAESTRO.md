# OVXEL — BLUEPRINT MAESTRO DE REDISEÑO COMPLETO

> **Proyecto:** Ovxel — Plataforma de Comercio Exterior de Bolivia
> **Stack:** Laravel 11 + Vue 3 (Composition API) + PostgreSQL 16 + Inertia.js
> **Fecha:** Junio 2026
> **Objetivo:** Ganar la competencia inter-universitaria nacional con una plataforma que centralice datos de INE, MERCOSUR, FAOSTAT y ALADI con visualizaciones profesionales, indicadores en tiempo real y exportación de datos.

---

## ÍNDICE

1. [Diagnóstico del Estado Actual](#1-diagnóstico)
2. [Análisis de Datos y Archivos Excel](#2-análisis-datos)
3. [Arquitectura General del Sistema](#3-arquitectura)
4. [Base de Datos — Cambios y Adiciones](#4-base-de-datos)
5. [Módulos del Portal Público (Frontend)](#5-portal-público)
6. [Panel de Administración (Backend)](#6-panel-admin)
7. [Sistema de Autenticación y Pagos](#7-auth-y-pagos)
8. [Motor de Carga Excel (ETL)](#8-etl)
9. [API Endpoints](#9-api)
10. [Plan de Implementación Paso a Paso](#10-implementación)

---

## 1. DIAGNÓSTICO DEL ESTADO ACTUAL {#1-diagnóstico}

### 1.1 Lo que existe y funciona
- Landing page con hero "Comercio exterior de Bolivia de un vistazo" — diseño limpio, se mantiene
- Panel admin con sidebar (Cargas, Reportes, Calidad, Catálogos, Organizaciones, Perfiles, Usuarios, Roles, Configuración, Bitácora)
- Login admin con diseño split-screen
- Explorador con filtros y tabla de resultados
- Base de datos `comercio.sql` con estructura para INE, MERCOSUR, FAOSTAT, ALADI y CAN

### 1.2 Lo que se ELIMINA
- **Sección "Explorar"** completa del portal público — se reemplaza por "Organizaciones" con explorador integrado
- **Sección "Metodología"** — se reemplaza por "Acerca de" con documentación
- **El contenido actual del explorador** (muestra datos crudos con "País 54", "Departamento 3") — inservible

### 1.3 Lo que se REDISEÑA desde cero
- Toda la navegación del portal público
- Todas las páginas de contenido
- Los dashboards y gráficos
- El flujo de exportación/descarga con registro y pago
- El panel admin (mantener estructura sidebar pero agregar módulos)

### 1.4 Problemas detectados en la base de datos actual
1. **Falta tabla para MERCOSUR por zona geoeconómica (por PAÍS):** Los Excel de MERCOSUR vienen en dos formatos — por ITEM (NCM) que ya cubre `serie_comercio_bilateral`, y por PAÍS (ISO 3166) que necesita una tabla agregada.
2. **Falta tabla de serie comercial agregada por zona:** Los Excel como "Exp_e_Imp_2000_2026_Union_Europea.xlsx" traen datos agrupados por zona geoeconómica y país, no bilaterales.
3. **Faltan tablas para el sistema de pagos y descargas**
4. **Faltan tablas para contenido dinámico** (textos, imágenes, descripciones de organizaciones para el portal)
5. **Falta tabla para solicitudes de exportación/descarga**
6. **Falta soporte para OAuth (Google login)**
7. **Los perfiles de mapeo necesitan cubrir los 6 grupos de cabeceras de exportaciones y 2 de importaciones**

---

## 2. ANÁLISIS DE DATOS Y ARCHIVOS EXCEL {#2-análisis-datos}

### 2.1 Datos del INE (Microdato)

#### Exportaciones — 6 grupos de cabeceras detectados:

**Grupo 1 (1992-2017, excepto 1996 y 2018): 34 columnas**
```
GESTION, MES, FLUJO, NANDINA, DESNAN, CAP, DESCAP, SECC, DESSEC, PAIS, DESPAIS, AREA, DESAREA, OTROS, MEDI, DESMEDI, VIASAL, DESVIA, DEPART, DESDEP, CUCI3, DESCUCI3, GCE3, DESGCE3, CIIUR3, DESCIIU3, CLACT, CODACT2, DESACT2, TNT, DESTNT, CLTNT, KILNET, VALOR
```
- Sin aduana, sin peso bruto, sin peso fino

**Grupo 2 (solo 1996): 35 columnas**
```
IDENT, GESTION, MES, FLUJO, ... (igual al Grupo 1 + IDENT al inicio)
```
- Agrega columna IDENT al inicio → mapear a `atributos_extra`

**Grupo 3 (solo 2018): 37 columnas**
```
GESTION, MES, FLUJO, ..., OTROS, TCP, MEDI, ..., KILBRU, KILNET, PFINO, VALOR
```
- Agrega TCP (tipo cambio parcial), KILBRU (peso bruto), PFINO (peso fino)

**Grupo 4 (2020, 2021, 2023, 2024, 2025): 38 columnas**
```
ADUDES, DESADU, GESTION, MES, FLUJO, ..., KILBRU, KILNET, FINO, VALOR
```
- Agrega ADUDES/DESADU (aduana) al inicio, KILBRU, FINO

**Grupo 5 (solo 2022): 38 columnas**
```
ADUDES, DESADU, ..., VIASAL2, DESVIA2, ... (VIASAL→VIASAL2, DESVIA→DESVIA2)
```
- Misma estructura que Grupo 4 pero VIASAL2/DESVIA2 en vez de VIASAL/DESVIA

**Grupo 6 (solo 2026): 38 columnas**
```
ADUDES, DESADU, ..., CUCIR3 (no CUCI3), GCE (no GCE3), CIIU3 (no CIIUR3), ...
```
- Renombra CUCI3→CUCIR3, GCE3→GCE, CIIUR3→CIIU3

#### Importaciones — 2 grupos de cabeceras:

**Grupo 1 (1992-2020): 29 columnas**
```
GESTION, MES, ADUANA, DESADU, DEPTO, DESDEPTO, VIA, DESVIA, MEDIO, DESMED, PAIS, DESPAI, DESZON, OTROS, NANDINA, DESNAN, GCER3, DESGCE, CUODE, DESCUO, CIIUR3, DESCIIU, CUCIR3, DESCUCI, KILBRU, FRO, FOB, ADU, PAG
```

**Grupo 2 (2021-2026): 29 columnas**
```
..., KILOS (reemplaza KILBRU), FRO, FOB, ADU, PAG
```
- Solo cambia KILBRU → KILOS

#### Datos de ejemplo reales (Exportaciones 1993):
- 5,696 filas de microdato
- Gestión: 1993, Mes: 1-12
- Flujo: "1EXPORTACIONES"
- NANDINA: código de 10 dígitos (ej. 2710006000)
- País: código numérico + nombre (ej. 23="ALEMANIA", 63="ARGENTINA")
- Área: código + nombre zona (ej. 40="UNION EUROPEA", 10="ALADI")
- Valores: KILNET (peso neto kg), VALOR (USD FOB)

#### Datos de ejemplo reales (Exportaciones 2025p):
- 14,999 filas
- Incluye ADUDES (211="Aeropuerto El Alto"), peso bruto, peso fino
- FLUJO: "1 EXPORTACIONES" (con espacio — diferente al 1993)

### 2.2 Datos del MERCOSUR

#### Formato 1: Por PAÍS (ISO 3166) — Serie agregada por zona
```
ISO 3166 | País | Año | Exportaciones | Importaciones (FOB) | Importaciones (CIF) | Volumen Exports | Volumen Imports
```
**Archivos que usan este formato:**
- Mercado Comun del Sur Mercosur (109 filas / 136 filas para M5)
- America del Sur (352 filas)
- America del Norte (7 filas)
- Union Europea (730 filas)
- Asia Menos Oriente Medio (725 filas)
- Oceania (715 filas)
- Europa Oriental (352 filas)
- Oriente Medio (352 filas)
- AELC (109 filas)
- Extrazona (6,822 filas)
- Extrazona Mercosur 5 (6,795 filas)
- Sur America Excepto Mercosur (244 filas)
- Alianza del Pacifico pequeño (9 filas)
- Africa Menos Oriente Medio pequeño (108 filas)

#### Formato 2: Por ITEM (NCM) — Serie por producto
```
NCM | Descripción | Año | Exportaciones | Importaciones (FOB) | Importaciones (CIF) | Volumen Exports | Volumen Imports
```
**Archivos que usan este formato:**
- Alianza del Pacifico grande (171,255 filas)
- America Excepto Mercosur (173,388 filas)
- Africa Menos Oriente Medio grande (9,312 filas)

### 2.3 Datos de FAOSTAT (del documento)
Datos contenidos en el documento Word con tablas:
- Población rural/urbana (1990-2023)
- Consumo de fertilizantes (1961-2023)
- Subalimentación (períodos 2000-2024)
- Producción de cereales (1961-2023)

### 2.4 Datos de ALADI (del documento)
Formato ranking con 10 productos principales:
```
N° | ÍTEM (Código SA) | DESCRIPCIÓN | VALOR (USD) | % TOTAL | VALOR ACUM. | % ACUM.
```

---

## 3. ARQUITECTURA GENERAL DEL SISTEMA {#3-arquitectura}

### 3.1 Stack Tecnológico
```
Frontend:  Vue 3 (Composition API) + Inertia.js + TailwindCSS + Chart.js/ApexCharts
Backend:   Laravel 11 + PHP 8.2+
Database:  PostgreSQL 16 (pgAdmin)
Auth:      Laravel Socialite (Google OAuth) + Sanctum (tokens)
Pagos:     Sistema de créditos interno (fase 1), Stripe/PayPal (fase 2)
Charts:    ApexCharts (interactivos) + Chart.js (estáticos para exportación)
Maps:      Leaflet.js (mapa de flujos comerciales)
Export:    PhpSpreadsheet (XLSX), DOMPDF (PDF), League\Csv (CSV)
ETL:       PhpSpreadsheet + Laravel Jobs/Queues
Cache:     Redis (para dashboards pesados)
```

### 3.2 Estructura de Directorios Laravel
```
app/
├── Http/
│   ├── Controllers/
│   │   ├── Portal/              # Controladores del portal público
│   │   │   ├── HomeController.php
│   │   │   ├── OrganizacionController.php
│   │   │   ├── RankingController.php
│   │   │   ├── ComparadorController.php
│   │   │   ├── MapaController.php
│   │   │   ├── IndicadoresController.php
│   │   │   ├── TimelineController.php
│   │   │   └── AcercaDeController.php
│   │   ├── Admin/               # Controladores del panel admin
│   │   │   ├── DashboardController.php
│   │   │   ├── CargaController.php
│   │   │   ├── ReporteController.php
│   │   │   ├── CalidadController.php
│   │   │   ├── CatalogoController.php
│   │   │   ├── OrganizacionAdminController.php
│   │   │   ├── PerfilMapeoController.php
│   │   │   ├── UsuarioController.php
│   │   │   ├── RolController.php
│   │   │   ├── ConfiguracionController.php
│   │   │   ├── BitacoraController.php
│   │   │   ├── ContenidoController.php
│   │   │   └── DescargaAdminController.php
│   │   ├── Api/                 # API para gráficos dinámicos
│   │   │   ├── ChartDataController.php
│   │   │   ├── FilterController.php
│   │   │   └── ExportController.php
│   │   └── Auth/
│   │       ├── LoginController.php
│   │       ├── RegisterController.php
│   │       └── SocialAuthController.php
│   ├── Middleware/
│   │   ├── AdminMiddleware.php
│   │   └── ThrottleExports.php
│   └── Requests/               # Form Requests para validación
├── Models/                     # Modelos Eloquent (1 por tabla)
├── Services/
│   ├── ETL/
│   │   ├── ExcelParserService.php
│   │   ├── HeaderMapperService.php
│   │   ├── INELoaderService.php
│   │   ├── MercosurPaisLoaderService.php
│   │   ├── MercosurItemLoaderService.php
│   │   ├── FaostatLoaderService.php
│   │   └── AladiLoaderService.php
│   ├── Charts/
│   │   ├── ComercioExteriorChartService.php
│   │   ├── RankingChartService.php
│   │   ├── IndicadoresService.php
│   │   └── TimeSeriesService.php
│   ├── Export/
│   │   ├── ExcelExportService.php
│   │   ├── CsvExportService.php
│   │   └── PdfExportService.php
│   └── Dashboard/
│       └── KPIService.php
├── Jobs/
│   ├── ProcessExcelUpload.php
│   └── GenerateExportFile.php
└── Events/ + Listeners/

resources/js/
├── Pages/
│   ├── Portal/
│   │   ├── Home.vue
│   │   ├── Organizaciones/
│   │   │   ├── Index.vue         # Grid de todas las organizaciones
│   │   │   └── Show.vue          # Detalle de 1 organización con gráficos
│   │   ├── Rankings/
│   │   │   └── Index.vue
│   │   ├── Comparador/
│   │   │   └── Index.vue
│   │   ├── MapaComercial/
│   │   │   └── Index.vue
│   │   ├── Indicadores/
│   │   │   └── Index.vue
│   │   ├── TimeLine/
│   │   │   └── Index.vue
│   │   └── AcercaDe.vue
│   ├── Admin/
│   │   ├── Dashboard.vue
│   │   ├── Cargas/
│   │   ├── Reportes/
│   │   ├── Calidad/
│   │   ├── Catalogos/
│   │   ├── Organizaciones/
│   │   ├── Perfiles/
│   │   ├── Usuarios/
│   │   ├── Roles/
│   │   ├── Configuracion/
│   │   ├── Bitacora/
│   │   ├── Contenido/
│   │   └── Descargas/
│   └── Auth/
│       ├── Login.vue
│       └── Register.vue
├── Components/
│   ├── Charts/
│   │   ├── BarChart.vue
│   │   ├── LineChart.vue
│   │   ├── PieChart.vue
│   │   ├── AreaChart.vue
│   │   ├── TreemapChart.vue
│   │   ├── RadarChart.vue
│   │   ├── HeatmapChart.vue
│   │   ├── SankeyChart.vue
│   │   └── MapChart.vue
│   ├── UI/
│   │   ├── KPICard.vue
│   │   ├── FilterPanel.vue
│   │   ├── DataTable.vue
│   │   ├── ExportModal.vue
│   │   ├── OrgCard.vue
│   │   └── RankingTable.vue
│   └── Layout/
│       ├── PortalLayout.vue
│       ├── AdminLayout.vue
│       ├── Navbar.vue
│       └── Footer.vue
└── Composables/
    ├── useChartData.js
    ├── useFilters.js
    └── useExport.js
```

---

## 4. BASE DE DATOS — CAMBIOS Y ADICIONES {#4-base-de-datos}

### 4.1 TABLAS NUEVAS A AGREGAR (no modificar las existentes de comercio.sql)

#### 4.1.1 Serie comercial agregada por zona (para Excel MERCOSUR por PAÍS)
```sql
-- Los Excel de MERCOSUR por zona/país (Union Europea, Asia, etc.) NO son bilaterales
-- (no tienen pais_reportante vs pais_socio). Son datos agregados del MERCOSUR completo
-- hacia cada país destino. Necesitan su propia tabla.
CREATE TABLE serie_comercio_zona (
    serie_zona_id           BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    organizacion_id         INT NOT NULL REFERENCES organizacion(organizacion_id) ON DELETE RESTRICT,
    archivo_id              BIGINT REFERENCES archivo_fuente(archivo_id) ON DELETE SET NULL,
    -- Zona geoeconómica del archivo (Unión Europea, Asia, etc.)
    zona_id                 SMALLINT REFERENCES zona_geoeconomica(zona_id) ON DELETE RESTRICT,
    -- País destino/origen del comercio
    pais_id                 INT NOT NULL REFERENCES pais(pais_id) ON DELETE RESTRICT,
    pais_iso3166            VARCHAR(10),  -- código ISO original del Excel
    pais_nombre_original    VARCHAR(150), -- nombre tal como viene en el Excel
    -- Tiempo
    tiempo_id               INT REFERENCES tiempo(tiempo_id) ON DELETE RESTRICT,
    gestion                 SMALLINT NOT NULL,
    -- Métricas
    exportaciones_usd       NUMERIC(20,2) CHECK (exportaciones_usd >= 0),
    importaciones_fob_usd   NUMERIC(20,2) CHECK (importaciones_fob_usd >= 0),
    importaciones_cif_usd   NUMERIC(20,2) CHECK (importaciones_cif_usd >= 0),
    volumen_export_kg       NUMERIC(20,2) CHECK (volumen_export_kg >= 0),
    volumen_import_kg       NUMERIC(20,2) CHECK (volumen_import_kg >= 0),
    -- Calculados
    balanza_comercial_usd   NUMERIC(20,2) GENERATED ALWAYS AS (exportaciones_usd - importaciones_cif_usd) STORED,
    atributos_extra         JSONB
);

CREATE INDEX idx_scz_org ON serie_comercio_zona (organizacion_id);
CREATE INDEX idx_scz_zona ON serie_comercio_zona (zona_id);
CREATE INDEX idx_scz_pais ON serie_comercio_zona (pais_id);
CREATE INDEX idx_scz_gestion ON serie_comercio_zona (gestion);
```

#### 4.1.2 Serie comercial agregada por producto NCM (para Excel MERCOSUR por ITEM)
```sql
CREATE TABLE serie_comercio_producto_zona (
    serie_prod_zona_id      BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    organizacion_id         INT NOT NULL REFERENCES organizacion(organizacion_id) ON DELETE RESTRICT,
    archivo_id              BIGINT REFERENCES archivo_fuente(archivo_id) ON DELETE SET NULL,
    zona_id                 SMALLINT REFERENCES zona_geoeconomica(zona_id) ON DELETE RESTRICT,
    -- Producto NCM
    producto_codigo_externo_id INT REFERENCES producto_codigo_externo(producto_codigo_externo_id) ON DELETE RESTRICT,
    ncm_codigo              VARCHAR(12) NOT NULL,
    ncm_descripcion         TEXT,
    -- Tiempo
    tiempo_id               INT REFERENCES tiempo(tiempo_id) ON DELETE RESTRICT,
    gestion                 SMALLINT NOT NULL,
    -- Métricas
    exportaciones_usd       NUMERIC(20,2) CHECK (exportaciones_usd >= 0),
    importaciones_fob_usd   NUMERIC(20,2) CHECK (importaciones_fob_usd >= 0),
    importaciones_cif_usd   NUMERIC(20,2) CHECK (importaciones_cif_usd >= 0),
    volumen_export_kg       NUMERIC(20,2) CHECK (volumen_export_kg >= 0),
    volumen_import_kg       NUMERIC(20,2) CHECK (volumen_import_kg >= 0),
    atributos_extra         JSONB
);

CREATE INDEX idx_scpz_org ON serie_comercio_producto_zona (organizacion_id);
CREATE INDEX idx_scpz_zona ON serie_comercio_producto_zona (zona_id);
CREATE INDEX idx_scpz_ncm ON serie_comercio_producto_zona (ncm_codigo);
CREATE INDEX idx_scpz_gestion ON serie_comercio_producto_zona (gestion);
```

#### 4.1.3 Sistema de contenido dinámico (para que el admin edite textos/imágenes del portal)
```sql
CREATE TABLE contenido_portal (
    contenido_id    INT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    seccion         VARCHAR(60) NOT NULL,   -- 'home_hero', 'home_stats', 'org_ine_desc', etc.
    clave           VARCHAR(80) NOT NULL,
    tipo            VARCHAR(20) NOT NULL CHECK (tipo IN ('texto','html','imagen','numero','json')),
    valor           TEXT,
    orden           SMALLINT NOT NULL DEFAULT 0,
    activo          BOOLEAN NOT NULL DEFAULT TRUE,
    updated_at      TIMESTAMPTZ NOT NULL DEFAULT now(),
    CONSTRAINT uq_contenido UNIQUE (seccion, clave)
);

-- Galería de imágenes de organizaciones
CREATE TABLE organizacion_media (
    media_id        INT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    organizacion_id INT NOT NULL REFERENCES organizacion(organizacion_id) ON DELETE CASCADE,
    tipo            VARCHAR(20) NOT NULL CHECK (tipo IN ('logo','banner','icono','grafico_ejemplo')),
    ruta_archivo    VARCHAR(500) NOT NULL,
    titulo          VARCHAR(150),
    orden           SMALLINT NOT NULL DEFAULT 0,
    activo          BOOLEAN NOT NULL DEFAULT TRUE,
    created_at      TIMESTAMPTZ NOT NULL DEFAULT now()
);

-- Descripción extendida de cada organización para el portal
CREATE TABLE organizacion_detalle (
    detalle_id          INT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    organizacion_id     INT NOT NULL REFERENCES organizacion(organizacion_id) ON DELETE CASCADE,
    descripcion_corta   VARCHAR(500),
    descripcion_larga   TEXT,
    metodologia         TEXT,
    cobertura_temporal  VARCHAR(100),  -- ej: "1992-2026"
    cobertura_geografica VARCHAR(200), -- ej: "Bolivia y socios comerciales"
    tipos_datos         TEXT,          -- ej: "Microdato de operaciones, exportaciones FOB, importaciones CIF"
    url_fuente_oficial  VARCHAR(500),
    color_primario      CHAR(7),       -- hex ej: #1F3864
    color_secundario    CHAR(7),
    icono_clase         VARCHAR(60),   -- clase CSS del icono
    updated_at          TIMESTAMPTZ NOT NULL DEFAULT now(),
    CONSTRAINT uq_org_detalle UNIQUE (organizacion_id)
);
```

#### 4.1.4 Sistema de usuarios públicos, descargas y pagos
```sql
-- Extender la tabla usuario existente con campos para OAuth
-- NO modificar la tabla existente; agregar tabla separada para perfil público
CREATE TABLE usuario_perfil_publico (
    perfil_id           INT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    usuario_id          INT NOT NULL REFERENCES usuario(usuario_id) ON DELETE CASCADE,
    google_id           VARCHAR(100),
    avatar_url          VARCHAR(500),
    institucion         VARCHAR(200),
    pais                VARCHAR(60),
    motivo_uso          VARCHAR(100),  -- 'investigacion', 'academico', 'gobierno', 'empresa', 'otro'
    creditos_disponibles INT NOT NULL DEFAULT 0,
    CONSTRAINT uq_perfil_usuario UNIQUE (usuario_id),
    CONSTRAINT uq_google_id UNIQUE (google_id)
);

-- Paquetes de créditos para comprar
CREATE TABLE paquete_creditos (
    paquete_id      INT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    nombre          VARCHAR(80) NOT NULL,
    creditos        INT NOT NULL CHECK (creditos > 0),
    precio_usd      NUMERIC(8,2) NOT NULL CHECK (precio_usd >= 0),
    activo          BOOLEAN NOT NULL DEFAULT TRUE,
    descripcion     VARCHAR(255)
);

-- Registro de compras de créditos
CREATE TABLE compra_creditos (
    compra_id           BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    usuario_id          INT NOT NULL REFERENCES usuario(usuario_id) ON DELETE RESTRICT,
    paquete_id          INT REFERENCES paquete_creditos(paquete_id) ON DELETE SET NULL,
    creditos_comprados  INT NOT NULL,
    monto_usd           NUMERIC(8,2) NOT NULL,
    metodo_pago         VARCHAR(40),  -- 'stripe', 'paypal', 'transferencia', 'gratuito'
    referencia_externa  VARCHAR(200), -- ID de transacción externa
    estado              VARCHAR(20) NOT NULL DEFAULT 'COMPLETADO'
                        CHECK (estado IN ('PENDIENTE','COMPLETADO','FALLIDO','REEMBOLSADO')),
    fecha               TIMESTAMPTZ NOT NULL DEFAULT now()
);

-- Solicitudes de descarga/exportación
CREATE TABLE solicitud_descarga (
    solicitud_id        BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    usuario_id          INT NOT NULL REFERENCES usuario(usuario_id) ON DELETE RESTRICT,
    organizacion_id     INT REFERENCES organizacion(organizacion_id) ON DELETE SET NULL,
    tipo_reporte        VARCHAR(60) NOT NULL,  -- 'comercio_seccion', 'ranking_productos', 'serie_temporal', etc.
    formato             VARCHAR(20) NOT NULL CHECK (formato IN ('csv','xlsx_plana','xlsx_dinamica','xlsx_informe','pdf')),
    parametros          JSONB NOT NULL,  -- filtros aplicados: {gestion_desde, gestion_hasta, flujo, pais, zona, etc.}
    creditos_cobrados   INT NOT NULL DEFAULT 0,
    estado              VARCHAR(20) NOT NULL DEFAULT 'PENDIENTE'
                        CHECK (estado IN ('PENDIENTE','GENERANDO','LISTO','ERROR','EXPIRADO')),
    ruta_archivo        VARCHAR(500),
    tamano_bytes        BIGINT,
    fecha_solicitud     TIMESTAMPTZ NOT NULL DEFAULT now(),
    fecha_listo         TIMESTAMPTZ,
    fecha_expiracion    TIMESTAMPTZ  -- el archivo se borra después de X días
);

CREATE INDEX idx_descarga_usuario ON solicitud_descarga (usuario_id);
CREATE INDEX idx_descarga_estado ON solicitud_descarga (estado);
```

#### 4.1.5 Vistas materializadas para dashboards rápidos
```sql
-- KPIs del home: totales por gestión y flujo
CREATE MATERIALIZED VIEW mv_resumen_anual_ine AS
SELECT
    t.gestion,
    tp.nombre AS tipo_operacion,
    COUNT(*) AS total_operaciones,
    SUM(o.valor_fob_usd) AS total_fob_usd,
    SUM(o.valor_cif_frontera_usd) AS total_cif_usd,
    SUM(o.peso_neto_kg) AS total_peso_neto_kg,
    COUNT(DISTINCT o.pais_id) AS paises_distintos,
    COUNT(DISTINCT o.producto_id) AS productos_distintos
FROM operacion_comercio_exterior o
JOIN tiempo t ON o.tiempo_id = t.tiempo_id
JOIN tipo_operacion tp ON o.tipo_operacion_id = tp.tipo_operacion_id
WHERE o.organizacion_id = 1  -- INE
GROUP BY t.gestion, tp.nombre
WITH DATA;

CREATE UNIQUE INDEX idx_mv_resumen_anual ON mv_resumen_anual_ine (gestion, tipo_operacion);

-- Top productos por gestión
CREATE MATERIALIZED VIEW mv_top_productos_ine AS
SELECT
    t.gestion,
    tp.nombre AS tipo_operacion,
    sa.descripcion AS seccion,
    ca.descripcion AS capitulo,
    SUM(o.valor_fob_usd) AS total_fob_usd,
    SUM(o.peso_neto_kg) AS total_peso_neto_kg,
    COUNT(*) AS operaciones
FROM operacion_comercio_exterior o
JOIN tiempo t ON o.tiempo_id = t.tiempo_id
JOIN tipo_operacion tp ON o.tipo_operacion_id = tp.tipo_operacion_id
JOIN producto p ON o.producto_id = p.producto_id
JOIN capitulo_arancelario ca ON p.capitulo_id = ca.capitulo_id
JOIN seccion_arancelaria sa ON ca.seccion_id = sa.seccion_id
WHERE o.organizacion_id = 1
GROUP BY t.gestion, tp.nombre, sa.descripcion, ca.descripcion
WITH DATA;

-- Top países por gestión
CREATE MATERIALIZED VIEW mv_top_paises_ine AS
SELECT
    t.gestion,
    tp.nombre AS tipo_operacion,
    p.nombre AS pais,
    z.descripcion AS zona,
    SUM(o.valor_fob_usd) AS total_fob_usd,
    SUM(o.peso_neto_kg) AS total_peso_neto_kg,
    COUNT(*) AS operaciones
FROM operacion_comercio_exterior o
JOIN tiempo t ON o.tiempo_id = t.tiempo_id
JOIN tipo_operacion tp ON o.tipo_operacion_id = tp.tipo_operacion_id
JOIN pais p ON o.pais_id = p.pais_id
JOIN zona_geoeconomica z ON p.zona_id = z.zona_id
WHERE o.organizacion_id = 1
GROUP BY t.gestion, tp.nombre, p.nombre, z.descripcion
WITH DATA;

-- Resumen MERCOSUR por zona y año
CREATE MATERIALIZED VIEW mv_resumen_mercosur_zona AS
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
```

### 4.2 DATOS SEMILLA (INSERT iniciales)

```sql
-- Organizaciones
INSERT INTO organizacion (nombre, sigla, pais_iso3, url, activo) VALUES
('Instituto Nacional de Estadística de Bolivia', 'INE', 'BOL', 'https://www.ine.gob.bo', true),
('Asociación Latinoamericana de Integración', 'ALADI', NULL, 'https://www.aladi.org', true),
('Mercado Común del Sur', 'MERCOSUR', NULL, 'https://www.mercosur.int', true),
('Organización de las Naciones Unidas para la Alimentación y la Agricultura', 'FAOSTAT', NULL, 'https://www.fao.org/faostat', true);

-- Tipo de operación
INSERT INTO tipo_operacion (nombre, base_valoracion) VALUES
('Exportación', 'FOB'),
('Importación', 'CIF');

-- Flujo comercial
INSERT INTO flujo_comercial (codigo_flujo, descripcion) VALUES
('1', 'Exportación'),
('2', 'Importación'),
('3', 'Reexportación');

-- Paquetes de créditos
INSERT INTO paquete_creditos (nombre, creditos, precio_usd, descripcion) VALUES
('Básico', 10, 2.06, 'Descarga de 10 reportes en formato CSV o XLS'),
('Profesional', 50, 8.00, 'Descarga de 50 reportes en cualquier formato'),
('Institucional', 200, 25.00, 'Descarga ilimitada por 200 créditos');

-- Detalles de organizaciones para el portal
INSERT INTO organizacion_detalle (organizacion_id, descripcion_corta, descripcion_larga, cobertura_temporal, cobertura_geografica, tipos_datos, color_primario, color_secundario) VALUES
(1, 'Microdato de comercio exterior de Bolivia con detalle de cada operación de exportación e importación.',
 'El Instituto Nacional de Estadística (INE) de Bolivia provee la información más granular disponible sobre el comercio exterior boliviano. Cada registro representa una operación individual de exportación o importación, con detalle de producto (NANDINA 10 dígitos), país destino/origen, departamento, aduana, medio de transporte, clasificaciones económicas (CUCI, GCE, CIIU, TNT) y valores en USD y kilogramos.',
 '1992-2026', 'Bolivia (origen) hacia todos los países del mundo', 'Microdato por operación, valores FOB/CIF, pesos bruto/neto/fino', '#1F3864', '#C53030'),
(2, 'Rankings de productos y datos agregados de comercio de los países miembros de ALADI.',
 'La Asociación Latinoamericana de Integración (ALADI) provee rankings de los principales productos exportados e importados por sus países miembros, con valor en USD, porcentaje del total y acumulados.',
 '1993-2025', 'Países miembros de ALADI', 'Rankings por producto, valores acumulados', '#2D5F2D', '#4A8C4A'),
(3, 'Flujos comerciales bilaterales y por zona geoeconómica entre los países del MERCOSUR.',
 'El Mercado Común del Sur (MERCOSUR) provee series históricas de comercio entre sus países miembros y con el resto del mundo, desagregadas por zona geoeconómica (Unión Europea, Asia, América del Norte, etc.) y por producto (nomenclatura NCM).',
 '2000-2026', 'MERCOSUR (Argentina, Brasil, Paraguay, Uruguay, Bolivia + Venezuela) con el mundo', 'Series por país, por zona y por producto NCM', '#1A4B8C', '#3B82F6'),
(4, 'Indicadores agrícolas, alimentarios y de población de Bolivia.',
 'La FAO (FAOSTAT) provee indicadores sobre población rural/urbana, consumo de fertilizantes, prevalencia de subalimentación y producción de cereales en Bolivia.',
 '1961-2024', 'Bolivia', 'Indicadores agrícolas, población, seguridad alimentaria', '#D97706', '#F59E0B');
```

---

## 5. MÓDULOS DEL PORTAL PÚBLICO (FRONTEND) {#5-portal-público}

### Navegación principal (Navbar):
```
[Logo Ovxel] | Inicio | Organizaciones | Rankings | Comparador | Mapa Comercial | Indicadores | Línea de Tiempo | Acerca de | [Acceder]
```
**Total: 8 secciones** (antes eran 4, ahora son el doble — se ve completo sin parecer vacío)

### 5.1 INICIO (Home)

**Estructura de la página:**

#### 5.1.1 Hero Section (se mantiene el diseño actual)
- Título: "Comercio exterior de Bolivia de un vistazo"
- Imagen de fondo con barco/avión/contenedores
- Botón CTA: "Explorar datos →"

#### 5.1.2 KPIs en tiempo real (justo debajo del hero)
4 tarjetas grandes con números animados (CountUp.js):
```
┌─────────────────┐ ┌─────────────────┐ ┌─────────────────┐ ┌─────────────────┐
│ EXPORTACIONES   │ │ IMPORTACIONES   │ │ BALANZA         │ │ OPERACIONES     │
│ 2025(p)         │ │ 2025(p)         │ │ COMERCIAL       │ │ REGISTRADAS     │
│ $X,XXX M USD    │ │ $X,XXX M USD    │ │ +$XXX M USD     │ │ XX,XXX          │
│ ▲ +X.X% vs 2024│ │ ▲ +X.X% vs 2024│ │ Superávit ✓     │ │ 4 organizaciones│
└─────────────────┘ └─────────────────┘ └─────────────────┘ └─────────────────┘
```

#### 5.1.3 Gráfico principal: Comercio Exterior de Bolivia (como la imagen 1 del INE)
- Gráfico de líneas (Exportaciones + Importaciones) + barras (Saldo Comercial)
- Rango: últimos 36 meses
- Slider para seleccionar rango temporal
- Fuente: INE — datos reales de la tabla `operacion_comercio_exterior` agregados por mes

#### 5.1.4 Sección "Organizaciones" (preview)
Grid de 4 tarjetas con logo, nombre, descripción corta y número de registros:
```
┌──────────────┐ ┌──────────────┐ ┌──────────────┐ ┌──────────────┐
│    [Logo]    │ │    [Logo]    │ │    [Logo]    │ │    [Logo]    │
│     INE      │ │    ALADI     │ │   MERCOSUR   │ │   FAOSTAT    │
│ 1.2M regist. │ │ 25K regist.  │ │ 180K regist. │ │ 2K regist.   │
│ Ver datos →  │ │ Ver datos →  │ │ Ver datos →  │ │ Ver datos →  │
└──────────────┘ └──────────────┘ └──────────────┘ └──────────────┘
```

#### 5.1.5 Top 5 Productos Exportados (treemap o barras horizontales)
- Datos del INE, último año disponible
- Interactivo: click para ver detalle

#### 5.1.6 Top 5 Destinos de Exportación (mini mapa mundial)
- Países destacados con colores por volumen
- Datos del INE

#### 5.1.7 Gráfico: Evolución histórica (1992-2025)
- Área apilada: exportaciones vs importaciones
- Timeline con eventos marcados: "Crisis 2008", "Boom commodities", "COVID-19"

#### 5.1.8 Footer con logos institucionales y link a fuente INE

---

### 5.2 ORGANIZACIONES

#### 5.2.1 Página Index: Grid de organizaciones
- 4 tarjetas con diseño tipo la imagen 2 (DATAX)
- Cada tarjeta muestra: logo, nombre, descripción corta, badges de formatos disponibles (CSV, XLS, PDF), fecha de última actualización, botón "Ver detalles →"

#### 5.2.2 Página Show: Detalle de UNA organización
**Layout de la página:**

**Cabecera:**
- Banner con color de la organización
- Logo + nombre + descripción larga
- Cobertura temporal y geográfica
- Panel lateral: formatos de descarga disponibles con precios (como imagen 4 de DATAX)
  - CSV — X créditos
  - XLS Tabla plana — X créditos  
  - XLS Tabla dinámica — X créditos
  - XLS Informe — X créditos
  - PDF — X créditos
  - Botón "Descargar" → si no está logueado → modal de registro (Google OAuth)

**Cuerpo — Sección de Gráficos (diferente según la organización):**

##### Para INE (organizacion_id = 1):
1. **Comercio Exterior mensual** (líneas + barras de saldo) — como la imagen 1
2. **Exportaciones por sección arancelaria** (treemap interactivo)
3. **Importaciones por sección arancelaria** (treemap)
4. **Top 10 productos exportados** (barras horizontales)
5. **Top 10 productos importados** (barras horizontales)
6. **Top 10 países destino de exportaciones** (barras)
7. **Top 10 países origen de importaciones** (barras)
8. **Exportaciones por departamento** (mapa de Bolivia con burbujas)
9. **Distribución por medio de transporte** (donut chart)
10. **Distribución por vía de comercio** (donut chart)
11. **Evolución anual 1992-2025** (área apilada)
12. **Clasificación TNT: Tradicional vs No Tradicional** (stacked bar por año)
13. **Tabla de datos filtrable** con paginación y búsqueda

##### Para ALADI (organizacion_id = 2):
1. **Top 10 Productos Exportados** (barras horizontales con % acumulado — Pareto)
2. **Top 10 Productos Importados** (barras con Pareto)
3. **Concentración de exportaciones** (curva de Lorenz)
4. **Tabla ranking completa** con filtros por año

##### Para MERCOSUR (organizacion_id = 3):
1. **Comercio del MERCOSUR por zona geoeconómica** (barras agrupadas: exp vs imp)
2. **Balanza comercial por zona** (barras divergentes)
3. **Evolución temporal por zona** (multi-líneas)
4. **Heatmap: países × años** (intensidad = volumen comercial)
5. **Top 10 productos NCM exportados** (barras horizontales)
6. **Top 10 productos NCM importados** (barras)
7. **Comparativa entre socios MERCOSUR** (radar chart)
8. **Tabla de datos filtrable** por zona, año, país

##### Para FAOSTAT (organizacion_id = 4):
1. **Población rural vs urbana** (líneas con doble eje + gráfico circular para año más reciente) — como describe el documento Word
2. **Consumo de fertilizantes** (áreas apiladas N, P, K)
3. **Subalimentación** (barras + línea con meta del 10%)
4. **Producción de cereales** (barras + líneas área/rendimiento)
5. Cada gráfico con su explicación de fórmula debajo (como en el Word)

**Todos los gráficos** deben tener:
- Título descriptivo
- Fuente citada
- Botón de descarga del gráfico como imagen PNG
- Botón "Ver datos" que expande la tabla debajo
- Filtros relevantes (año, flujo, etc.)

---

### 5.3 RANKINGS

Página con múltiples tabs/secciones de rankings:

1. **Top productos exportados** (por valor FOB, filtrable por año) — INE
2. **Top productos importados** (por valor CIF) — INE
3. **Top países destino de exportación** — INE
4. **Top países origen de importación** — INE
5. **Top departamentos exportadores** — INE
6. **Top aduanas por volumen** — INE
7. **Top productos MERCOSUR por zona** — MERCOSUR
8. **Ranking ALADI** (el ranking propio de ALADI) — ALADI
9. **Top zonas geoeconómicas del MERCOSUR** — MERCOSUR
10. **Ranking de indicadores FAOSTAT** — FAOSTAT

Cada ranking muestra:
- Tabla con posición, nombre, valor, porcentaje, variación vs año anterior
- Mini gráfico sparkline de tendencia
- Filtros: organización, año, flujo
- Opción de descargar el ranking

---

### 5.4 COMPARADOR

Herramienta interactiva para comparar:
- **País A vs País B** (seleccionables): exp/imp/balanza lado a lado
- **Producto A vs Producto B**: evolución temporal
- **Zona A vs Zona B**: MERCOSUR
- **Año A vs Año B**: misma dimensión en dos periodos

Genera gráficos dual-axis y tablas comparativas automáticamente.

---

### 5.5 MAPA COMERCIAL

Mapa mundial interactivo (Leaflet.js) que muestra:
- Flujos comerciales de Bolivia con cada país (líneas de conexión con grosor proporcional al valor)
- Color por tipo: verde=exportación, rojo=importación
- Click en un país → popup con detalles: valor, productos principales, variación
- Filtros: año, flujo, organización
- Mini mapa de Bolivia con departamentos y sus exportaciones

---

### 5.6 INDICADORES

Dashboard tipo scorecard con indicadores macroeconómicos calculados:

**Fila 1: Indicadores de Comercio (INE)**
- Tasa de cobertura: Exportaciones / Importaciones × 100
- Índice de concentración de exportaciones (Herfindahl)
- Diversificación de mercados (número de destinos)
- Valor promedio por operación

**Fila 2: Indicadores MERCOSUR**
- Participación de Bolivia en exportaciones del MERCOSUR
- Balanza bilateral con cada socio
- Tasa de crecimiento interanual

**Fila 3: Indicadores FAOSTAT**
- Prevalencia de subalimentación (último dato)
- Producción de cereales per cápita
- Uso de fertilizantes por hectárea
- % Población rural

Cada indicador es una tarjeta con: valor actual, tendencia (flecha arriba/abajo), mini sparkline, tooltip con explicación de la fórmula.

---

### 5.7 LÍNEA DE TIEMPO

Timeline vertical/horizontal interactivo (1992-2026) que muestra:
- Hitos del comercio exterior boliviano
- Cambios en la nomenclatura NANDINA
- Eventos macroeconómicos (crisis, boom, pandemia)
- Variación anual de exportaciones/importaciones como fondo
- Click en un año → expande con datos clave de ese año

---

### 5.8 ACERCA DE

- Descripción del proyecto Ovxel
- Fuentes de datos y metodología
- Equipo (foto + nombre de cada integrante)
- Contacto
- Glosario de términos de comercio exterior
- FAQ
- Créditos institucionales

---

## 6. PANEL DE ADMINISTRACIÓN {#6-panel-admin}

### Sidebar actualizado:
```
GENERAL
├── Dashboard
├── Explorador (admin)
└── Dashboards Builder

DATOS
├── Cargas (upload de Excel)
├── Reportes (generación)
├── Calidad (validación)
└── Catálogos (dimensiones)

CONTENIDO
├── Organizaciones (editar info del portal)
├── Contenido Portal (textos, imágenes)
└── Descargas (gestionar solicitudes)

ADMINISTRACIÓN
├── Usuarios
├── Roles y Permisos
├── Perfiles de Mapeo
├── Configuración
└── Bitácora de Auditoría

[Ver portal →]
```

### 6.1 Dashboard Admin
- Total operaciones cargadas por organización
- Últimas cargas (estado, fecha, errores)
- Usuarios registrados
- Descargas solicitadas
- Ingresos por créditos
- Gráfico de cargas por mes

### 6.2 Cargas (ETL)
- Upload de Excel con drag & drop
- Seleccionar: Organización, Tipo (INE Export / INE Import / MERCOSUR País / MERCOSUR Item / ALADI / FAOSTAT), Año
- El sistema detecta automáticamente el grupo de cabeceras y selecciona el perfil de mapeo correcto
- Preview de las primeras 10 filas mapeadas
- Botón "Procesar" → cola de jobs
- Log en tiempo real del progreso
- Historial de cargas con estado

### 6.3 Reportes
- Seleccionar tipo de reporte predefinido
- Filtros: organización, flujo, rango de años
- Generar en: pantalla, Excel, PDF, CSV
- Cola de generación para reportes pesados

### 6.4 Calidad
- Resumen de incidencias por carga
- Filtrar por severidad (INFO, ADVERTENCIA, ERROR)
- Detalle de cada incidencia: fila, campo, valor detectado, regla violada
- Acciones: corregir, aceptar, descartar

### 6.5 Catálogos
- CRUD para cada dimensión: países, zonas, productos, secciones, capítulos, departamentos, aduanas, medios de transporte, vías, CUCI, GCE, CIIU, TNT, CUODE
- Búsqueda y filtros en cada catálogo

### 6.6 Contenido Portal
- Editor WYSIWYG para textos del portal
- Upload de imágenes (banners, logos)
- Organizar por sección (home, organizaciones, etc.)

### 6.7 Descargas
- Lista de solicitudes de descarga
- Estado de cada una
- Stats: formato más popular, organización más descargada

---

## 7. SISTEMA DE AUTENTICACIÓN Y PAGOS {#7-auth-y-pagos}

### 7.1 Flujo de registro
1. Usuario público navega el portal
2. Quiere descargar datos → click "Descargar"
3. Modal: "Para descargar, inicia sesión con Google" + botón Google OAuth
4. Primera vez → se crea cuenta con 3 créditos gratuitos de bienvenida
5. Selecciona formato → se cobran créditos
6. Si no tiene créditos → "Comprar créditos" → pasarela de pago
7. Se genera el archivo y se muestra link de descarga (expira en 7 días)

### 7.2 Roles
- **visitante**: ver portal, gráficos, indicadores. Sin descargas.
- **usuario_registrado**: todo lo anterior + descargar con créditos
- **analista**: todo lo anterior + explorador avanzado + API access
- **administrador**: panel admin completo

### 7.3 Precios sugeridos (en créditos)
| Formato | Créditos |
|---------|----------|
| CSV | 1 |
| XLS Tabla plana | 1 |
| XLS Tabla dinámica | 2 |
| XLS Informe | 2 |
| PDF | 2 |

---

## 8. MOTOR DE CARGA EXCEL (ETL) {#8-etl}

### 8.1 Flujo de carga

```
Admin sube Excel → Detectar organización y tipo →
Seleccionar perfil de mapeo → Preview →
Confirmar → Job en cola → Parsear fila a fila →
Validar contra reglas → Insertar en tabla de hechos correspondiente →
Registrar incidencias → Actualizar estado → Refrescar vistas materializadas
```

### 8.2 Perfiles de mapeo necesarios (tabla perfil_mapeo + mapeo_columna)

#### INE Exportaciones — 6 perfiles:
1. `INE_EXP_V1` (1992-2017): 34 columnas estándar
2. `INE_EXP_V2` (1996): 35 columnas con IDENT
3. `INE_EXP_V3` (2018): 37 columnas con TCP, KILBRU, PFINO
4. `INE_EXP_V4` (2020-2025): 38 columnas con ADUDES, DESADU, KILBRU, FINO
5. `INE_EXP_V5` (2022): 38 columnas con VIASAL2, DESVIA2
6. `INE_EXP_V6` (2026): 38 columnas con CUCIR3, GCE, CIIU3

#### INE Importaciones — 2 perfiles:
7. `INE_IMP_V1` (1992-2020): 29 columnas con KILBRU
8. `INE_IMP_V2` (2021-2026): 29 columnas con KILOS

#### MERCOSUR — 2 perfiles:
9. `MERCOSUR_PAIS` (por país/zona): 8 columnas (ISO 3166, País, Año, Exp, Imp FOB, Imp CIF, Vol Exp, Vol Imp)
10. `MERCOSUR_ITEM` (por producto NCM): 8 columnas (NCM, Descripción, Año, Exp, Imp FOB, Imp CIF, Vol Exp, Vol Imp)

#### ALADI — 1 perfil:
11. `ALADI_RANKING`: columnas (N°, ÍTEM, DESCRIPCIÓN, VALOR, % TOTAL, VALOR ACUM., % ACUM.)

#### FAOSTAT — 4 perfiles (uno por tipo de indicador):
12. `FAOSTAT_POBLACION`
13. `FAOSTAT_FERTILIZANTES`
14. `FAOSTAT_SUBALIMENTACION`
15. `FAOSTAT_CEREALES`

### 8.3 Detección automática de perfil

```php
// Lógica de detección en HeaderMapperService.php
public function detectProfile(array $headers, string $organizacion, string $flujo): PerfilMapeo
{
    // Para INE:
    if ($organizacion === 'INE') {
        if ($flujo === 'EXPORTACION') {
            if (in_array('ADUDES', $headers) && in_array('CUCIR3', $headers)) return 'INE_EXP_V6'; // 2026
            if (in_array('ADUDES', $headers) && in_array('VIASAL2', $headers)) return 'INE_EXP_V5'; // 2022
            if (in_array('ADUDES', $headers) && in_array('FINO', $headers)) return 'INE_EXP_V4'; // 2020+
            if (in_array('TCP', $headers)) return 'INE_EXP_V3'; // 2018
            if (in_array('IDENT', $headers)) return 'INE_EXP_V2'; // 1996
            return 'INE_EXP_V1'; // 1992-2017 estándar
        }
        if ($flujo === 'IMPORTACION') {
            if (in_array('KILOS', $headers)) return 'INE_IMP_V2'; // 2021+
            return 'INE_IMP_V1'; // 1992-2020
        }
    }
    // Para MERCOSUR:
    if ($organizacion === 'MERCOSUR') {
        if (in_array('ISO 3166', $headers)) return 'MERCOSUR_PAIS';
        if (in_array('NCM', $headers)) return 'MERCOSUR_ITEM';
    }
    // ...etc
}
```

### 8.4 Mapeo canónico de columnas (ejemplos)

**INE_EXP_V1 (34 cols):**
| Columna Excel | Campo canónico en BD | Tabla destino |
|---|---|---|
| GESTION | tiempo.gestion | tiempo |
| MES | tiempo.mes | tiempo |
| FLUJO | tipo_operacion.nombre | tipo_operacion |
| NANDINA | producto.codigo_nandina | producto |
| DESNAN | producto.descripcion | producto |
| CAP | capitulo_arancelario.codigo_capitulo | capitulo_arancelario |
| DESCAP | capitulo_arancelario.descripcion | capitulo_arancelario |
| SECC | seccion_arancelaria.codigo_seccion | seccion_arancelaria |
| DESSEC | seccion_arancelaria.descripcion | seccion_arancelaria |
| PAIS | pais.codigo_pais | pais |
| DESPAIS | pais.nombre | pais |
| AREA | zona_geoeconomica.codigo_zona | zona_geoeconomica |
| DESAREA | zona_geoeconomica.descripcion | zona_geoeconomica |
| OTROS | pais_zona (asociación múltiple) | pais_zona |
| MEDI | medio_transporte.codigo | medio_transporte |
| DESMEDI | medio_transporte.descripcion | medio_transporte |
| VIASAL | via_comercio.codigo | via_comercio |
| DESVIA | via_comercio.descripcion | via_comercio |
| DEPART | departamento.codigo | departamento |
| DESDEP | departamento.nombre | departamento |
| CUCI3 | clasificacion_cuci.codigo_cuci | clasificacion_cuci |
| DESCUCI3 | clasificacion_cuci.descripcion | clasificacion_cuci |
| GCE3 | categoria_economica_gce.codigo_gce | categoria_economica_gce |
| DESGCE3 | categoria_economica_gce.descripcion | categoria_economica_gce |
| CIIUR3 | actividad_ciiu.codigo_ciiu | actividad_ciiu |
| DESCIIU3 | actividad_ciiu.descripcion | actividad_ciiu |
| CLACT | grupo_actividad.clasificacion_mayor | grupo_actividad |
| CODACT2 | grupo_actividad.codigo | grupo_actividad |
| DESACT2 | grupo_actividad.descripcion | grupo_actividad |
| TNT | clasificacion_tnt.codigo_tnt | clasificacion_tnt |
| DESTNT | clasificacion_tnt.descripcion | clasificacion_tnt |
| CLTNT | clasificacion_tnt.clase | clasificacion_tnt |
| KILNET | operacion.peso_neto_kg | operacion_comercio_exterior |
| VALOR | operacion.valor_fob_usd | operacion_comercio_exterior |

---

## 9. API ENDPOINTS {#9-api}

### 9.1 Portal público (sin autenticación)
```
GET /api/v1/kpis                             # KPIs del home
GET /api/v1/kpis/{organizacion_id}           # KPIs de una organización

GET /api/v1/charts/comercio-mensual          # Gráfico líneas exp/imp/saldo
GET /api/v1/charts/evolucion-anual           # Serie histórica
GET /api/v1/charts/top-productos             # Top N productos
GET /api/v1/charts/top-paises                # Top N países
GET /api/v1/charts/top-departamentos         # Top N departamentos
GET /api/v1/charts/seccion-arancelaria       # Treemap de secciones
GET /api/v1/charts/tnt-evolucion             # Tradicional vs No Tradicional
GET /api/v1/charts/transporte                # Distribución por medio/vía
GET /api/v1/charts/mapa-flujos               # Datos para mapa mundial

GET /api/v1/charts/mercosur/zona             # Comercio MERCOSUR por zona
GET /api/v1/charts/mercosur/paises           # Comercio MERCOSUR por país
GET /api/v1/charts/mercosur/productos        # Comercio MERCOSUR por NCM
GET /api/v1/charts/mercosur/balanza          # Balanza por zona

GET /api/v1/charts/faostat/poblacion         # Datos FAOSTAT población
GET /api/v1/charts/faostat/fertilizantes     # FAOSTAT fertilizantes
GET /api/v1/charts/faostat/subalimentacion   # FAOSTAT subalimentación
GET /api/v1/charts/faostat/cereales          # FAOSTAT cereales

GET /api/v1/charts/aladi/ranking             # Ranking ALADI

GET /api/v1/rankings/{tipo}                  # Rankings dinámicos
GET /api/v1/comparador                       # Datos para comparador
GET /api/v1/indicadores                      # Todos los indicadores calculados
GET /api/v1/timeline                         # Datos de línea de tiempo

GET /api/v1/organizaciones                   # Lista de organizaciones
GET /api/v1/organizaciones/{id}              # Detalle de 1 organización

GET /api/v1/filtros/paises                   # Lista para selects
GET /api/v1/filtros/zonas
GET /api/v1/filtros/productos
GET /api/v1/filtros/secciones
GET /api/v1/filtros/gestiones
```

### 9.2 Requiere autenticación
```
POST /api/v1/descargas                       # Solicitar descarga
GET  /api/v1/descargas/{id}                  # Estado de descarga
GET  /api/v1/descargas/{id}/archivo          # Descargar archivo
GET  /api/v1/perfil                          # Mi perfil
PUT  /api/v1/perfil                          # Actualizar perfil
GET  /api/v1/creditos                        # Mis créditos
POST /api/v1/creditos/comprar                # Comprar créditos
```

### 9.3 Admin (requiere rol admin)
```
POST   /admin/cargas                          # Subir Excel
GET    /admin/cargas                          # Listar cargas
GET    /admin/cargas/{id}                     # Detalle de carga
DELETE /admin/cargas/{id}                     # Eliminar carga

GET    /admin/reportes/generar                # Generar reporte
GET    /admin/calidad                         # Incidencias
PUT    /admin/calidad/{id}                    # Tratar incidencia

# CRUD para cada catálogo
GET/POST/PUT/DELETE /admin/catalogos/{tabla}

GET/POST/PUT/DELETE /admin/organizaciones
GET/POST/PUT/DELETE /admin/contenido
GET/POST/PUT/DELETE /admin/usuarios
GET/POST/PUT/DELETE /admin/roles
GET/PUT             /admin/configuracion
GET                 /admin/bitacora
GET                 /admin/descargas          # Gestionar descargas
```

---

## 10. PLAN DE IMPLEMENTACIÓN PASO A PASO {#10-implementación}

### FASE 0: Preparar base de datos (día 1)

```bash
# 1. Conectarse a pgAdmin con la contraseña: awd78951230
# 2. Crear nueva base de datos: ovxel_db
# 3. Ejecutar comercio.sql completo
# 4. Ejecutar las tablas nuevas de la sección 4.1
# 5. Ejecutar los INSERTs semilla de la sección 4.2
# 6. Crear las vistas materializadas de la sección 4.1.5
```

### FASE 1: Backend — Modelos y Migraciones (día 1-2)
1. Crear modelos Eloquent para TODAS las tablas
2. Configurar relaciones entre modelos
3. Crear seeders con datos semilla
4. Configurar .env con conexión a PostgreSQL

### FASE 2: Backend — ETL y Carga de Datos (día 2-4)
1. Implementar HeaderMapperService
2. Implementar ExcelParserService
3. Implementar loaders para cada organización
4. Crear Jobs para procesamiento en cola
5. Crear endpoint admin para upload
6. **Cargar datos reales de los Excel del proyecto**

### FASE 3: Backend — APIs de Charts (día 4-6)
1. Implementar ChartDataController con todos los endpoints de /api/v1/charts/
2. Implementar KPIService
3. Implementar RankingChartService
4. Implementar IndicadoresService
5. Configurar cache con Redis para consultas pesadas

### FASE 4: Frontend — Layout y Navegación (día 6-7)
1. Crear PortalLayout.vue con nueva navegación de 8 secciones
2. Crear AdminLayout.vue con sidebar actualizado
3. Configurar rutas en Vue Router / Inertia
4. Crear componentes base: KPICard, FilterPanel, DataTable

### FASE 5: Frontend — Portal Público (día 7-12)
1. Home.vue con todas las secciones (KPIs, gráficos, preview de organizaciones)
2. Organizaciones/Index.vue (grid)
3. Organizaciones/Show.vue (detalle con gráficos por organización)
4. Rankings/Index.vue
5. Comparador/Index.vue
6. MapaComercial/Index.vue (Leaflet)
7. Indicadores/Index.vue
8. TimeLine/Index.vue
9. AcercaDe.vue

### FASE 6: Frontend — Panel Admin (día 12-15)
1. Dashboard.vue con KPIs de admin
2. Cargas: upload con drag&drop, preview, log
3. Reportes: generador con filtros
4. Calidad: tabla de incidencias
5. Catálogos: CRUD genérico
6. Contenido: editor WYSIWYG
7. Descargas: gestión de solicitudes

### FASE 7: Auth y Descargas (día 15-17)
1. Configurar Laravel Socialite con Google OAuth
2. Crear flujo de registro/login
3. Implementar sistema de créditos
4. Implementar generación de archivos de exportación
5. Crear ExportModal.vue con selección de formato

### FASE 8: Pulir y Testing (día 17-20)
1. Responsive design
2. Loading states y animaciones
3. Manejo de errores
4. Tests de integración
5. Performance: lazy loading, paginación, cache
6. SEO: meta tags, Open Graph

---

## RESUMEN DE ARCHIVOS A CREAR/MODIFICAR

### Nuevos archivos críticos:
- `database/migrations/xxxx_create_nuevas_tablas.php` (5+ migraciones)
- `app/Services/ETL/*.php` (7 archivos)
- `app/Services/Charts/*.php` (4 archivos)
- `app/Http/Controllers/Portal/*.php` (8 controladores)
- `app/Http/Controllers/Admin/*.php` (13 controladores)
- `app/Http/Controllers/Api/*.php` (3 controladores)
- `resources/js/Pages/Portal/*.vue` (12+ páginas)
- `resources/js/Pages/Admin/*.vue` (15+ páginas)
- `resources/js/Components/Charts/*.vue` (9 componentes de gráficos)
- `resources/js/Components/UI/*.vue` (6 componentes UI)
- `routes/web.php` (reescribir completo)
- `routes/api.php` (reescribir completo)

### Archivos existentes a modificar:
- `config/database.php` (conexión PostgreSQL)
- `config/auth.php` (guards)
- `config/services.php` (Google OAuth)
- `.env` (todas las variables)
- `app/Models/*.php` (agregar nuevos modelos)
- `resources/js/app.js` (nueva estructura)
- `tailwind.config.js` (paleta de colores Ovxel)

---

## PALETA DE COLORES OVXEL

```css
:root {
    --ovxel-navy: #1A2332;
    --ovxel-red: #C53030;
    --ovxel-red-light: #E53E3E;
    --ovxel-gray-50: #F7FAFC;
    --ovxel-gray-100: #EDF2F7;
    --ovxel-gray-200: #E2E8F0;
    --ovxel-gray-600: #718096;
    --ovxel-gray-800: #2D3748;
    --ovxel-blue: #3182CE;
    --ovxel-green: #38A169;
    --ovxel-gold: #D69E2E;
    --ovxel-orange: #DD6B20;
}
```

---

## NOTA FINAL PARA EL AGENTE

Este blueprint es la especificación completa del sistema. Cada sección describe exactamente QUÉ construir y CÓMO. El orden de implementación en la FASE 10 es el que debes seguir.

**Prioridades absolutas:**
1. La base de datos debe poder almacenar TODA la información de los Excel sin perder columnas
2. Los gráficos deben usar datos REALES de la base de datos, no datos de ejemplo hardcodeados
3. Cada organización tiene su propia visualización adaptada a su tipo de datos
4. El sistema de carga Excel debe detectar automáticamente el perfil de mapeo según las cabeceras
5. El portal público debe verse profesional con muchos gráficos e indicadores — el jurado evaluará la riqueza visual y analítica

**Credenciales:**
- PostgreSQL (pgAdmin): contraseña `awd78951230`
- Base de datos: crear como `ovxel_db`

**Tecnologías obligatorias:**
- Laravel 11, Vue 3 (Composition API), PostgreSQL, Inertia.js, TailwindCSS, ApexCharts
