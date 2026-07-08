<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Tabla de hechos: microdato de comercio exterior (forma usada por INE).
 * Reproduce EXACTAMENTE estructura-db.sql, incluida la columna JSONB
 * atributos_extra, todas las FK (obligatorias y opcionales), las restricciones
 * CHECK de metricas y todos los índices (incluido el GIN sobre atributos_extra).
 * Va al final por depender de todas las dimensiones y de carga_archivo.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
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

                -- Dimensiones opcionales (segun el flujo)
                tnt_id                SMALLINT REFERENCES clasificacion_tnt(tnt_id) ON DELETE RESTRICT,
                cuode_id              SMALLINT REFERENCES clasificacion_cuode(cuode_id) ON DELETE RESTRICT,
                aduana_id             INT      REFERENCES aduana(aduana_id) ON DELETE RESTRICT,
                despachante_id        INT      REFERENCES despachante(despachante_id) ON DELETE RESTRICT,

                -- Metricas de peso
                peso_bruto_kg            NUMERIC(18,2) CHECK (peso_bruto_kg >= 0),
                peso_neto_kg             NUMERIC(18,2) CHECK (peso_neto_kg  >= 0),
                peso_fino_kg             NUMERIC(18,2) CHECK (peso_fino_kg  >= 0),

                -- Metricas de valor (segun el flujo)
                valor_fob_usd            NUMERIC(18,2) CHECK (valor_fob_usd >= 0),
                valor_cif_frontera_usd   NUMERIC(18,2) CHECK (valor_cif_frontera_usd >= 0),
                valor_cif_aduana_usd     NUMERIC(18,2) CHECK (valor_cif_aduana_usd >= 0),
                gravamenes_pagados       NUMERIC(18,2) CHECK (gravamenes_pagados >= 0),

                -- Columnas no canonicas conservadas (clave-valor JSON)
                atributos_extra          JSONB
            );

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
        SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS operacion_comercio_exterior CASCADE;');
    }
};
