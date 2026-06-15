<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Dimensiones del microdato (forma usada por INE).
 * Reproduce EXACTAMENTE estructura-db.sql: tiempo, tipo_operacion,
 * flujo_comercial, jerarquia de producto (seccion/capitulo/producto),
 * clasificaciones economicas, geografia (zona/pais + puente) y logistica.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
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

            CREATE TABLE tipo_operacion (
                tipo_operacion_id  SMALLINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
                nombre             VARCHAR(20) NOT NULL,
                base_valoracion    CHAR(3)     NOT NULL CHECK (base_valoracion IN ('FOB','CIF')),
                CONSTRAINT uq_tipo_operacion UNIQUE (nombre)
            );

            CREATE TABLE flujo_comercial (
                flujo_id      SMALLINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
                codigo_flujo  VARCHAR(2)  NOT NULL,
                descripcion   VARCHAR(50) NOT NULL,
                CONSTRAINT uq_flujo UNIQUE (codigo_flujo)
            );

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

            CREATE INDEX idx_capitulo_seccion  ON capitulo_arancelario (seccion_id);
            CREATE INDEX idx_producto_capitulo ON producto (capitulo_id);
            CREATE INDEX idx_ciiu_grupo        ON actividad_ciiu (grupo_actividad_id);
            CREATE INDEX idx_pais_zona_zona    ON pais (zona_id);
        SQL);
    }

    public function down(): void
    {
        DB::unprepared(<<<'SQL'
            DROP TABLE IF EXISTS despachante CASCADE;
            DROP TABLE IF EXISTS aduana CASCADE;
            DROP TABLE IF EXISTS via_comercio CASCADE;
            DROP TABLE IF EXISTS medio_transporte CASCADE;
            DROP TABLE IF EXISTS departamento CASCADE;
            DROP TABLE IF EXISTS pais_zona CASCADE;
            DROP TABLE IF EXISTS pais CASCADE;
            DROP TABLE IF EXISTS zona_geoeconomica CASCADE;
            DROP TABLE IF EXISTS clasificacion_cuode CASCADE;
            DROP TABLE IF EXISTS clasificacion_tnt CASCADE;
            DROP TABLE IF EXISTS actividad_ciiu CASCADE;
            DROP TABLE IF EXISTS grupo_actividad CASCADE;
            DROP TABLE IF EXISTS categoria_economica_gce CASCADE;
            DROP TABLE IF EXISTS clasificacion_cuci CASCADE;
            DROP TABLE IF EXISTS producto CASCADE;
            DROP TABLE IF EXISTS capitulo_arancelario CASCADE;
            DROP TABLE IF EXISTS seccion_arancelaria CASCADE;
            DROP TABLE IF EXISTS flujo_comercial CASCADE;
            DROP TABLE IF EXISTS tipo_operacion CASCADE;
            DROP TABLE IF EXISTS tiempo CASCADE;
        SQL);
    }
};
