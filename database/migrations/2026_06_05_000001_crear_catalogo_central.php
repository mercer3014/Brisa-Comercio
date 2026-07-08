<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Catálogo central y mapeo de cabeceras.
 * Reproduce EXACTAMENTE las tablas de estructura-db.sql:
 * organización, fuente_datos, perfil_mapeo, mapeo_columna.
 * Se usa SQL crudo para conservar GENERATED ALWAYS AS IDENTITY, los tipos
 * exactos (INT/SMALLINT) y las restricciones UNIQUE/CHECK del esquema original.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Necesaria para gen_random_uuid() (tabla sesión) en PostgreSQL < 13.
        DB::unprepared('CREATE EXTENSION IF NOT EXISTS pgcrypto;');

        DB::unprepared(<<<'SQL'
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

            CREATE INDEX idx_mapeo_perfil ON mapeo_columna (perfil_id);
        SQL);
    }

    public function down(): void
    {
        DB::unprepared(<<<'SQL'
            DROP TABLE IF EXISTS mapeo_columna CASCADE;
            DROP TABLE IF EXISTS perfil_mapeo CASCADE;
            DROP TABLE IF EXISTS fuente_datos CASCADE;
            DROP TABLE IF EXISTS organizacion CASCADE;
        SQL);
    }
};
