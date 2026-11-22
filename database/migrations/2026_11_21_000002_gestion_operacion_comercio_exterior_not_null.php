<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * operacion_comercio_exterior.gestion (agregada en 2026_07_14) nunca la llenaba
 * CargadorIne: las cargas nuevas quedaban con gestion NULL y desaparecían de las
 * consultas del portal/explorador filtradas por año.
 *
 * 1. Backfill desde tiempo.
 * 2. Trigger de respaldo: si una inserción no trae gestion (o cambia tiempo_id),
 *    se toma de tiempo. CargadorIne ya la envía, así que el trigger casi no trabaja.
 * 3. NOT NULL.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('UPDATE operacion_comercio_exterior o SET gestion = t.gestion
                         FROM tiempo t WHERE t.tiempo_id = o.tiempo_id AND o.gestion IS DISTINCT FROM t.gestion');

        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION oce_set_gestion() RETURNS trigger AS $$
            BEGIN
                IF NEW.gestion IS NULL OR TG_OP = 'UPDATE' THEN
                    SELECT t.gestion INTO NEW.gestion FROM tiempo t WHERE t.tiempo_id = NEW.tiempo_id;
                END IF;
                RETURN NEW;
            END $$ LANGUAGE plpgsql;

            DROP TRIGGER IF EXISTS trg_oce_gestion ON operacion_comercio_exterior;
            CREATE TRIGGER trg_oce_gestion
                BEFORE INSERT OR UPDATE OF tiempo_id ON operacion_comercio_exterior
                FOR EACH ROW EXECUTE FUNCTION oce_set_gestion();
        SQL);

        DB::statement('ALTER TABLE operacion_comercio_exterior ALTER COLUMN gestion SET NOT NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE operacion_comercio_exterior ALTER COLUMN gestion DROP NOT NULL');
        DB::unprepared('DROP TRIGGER IF EXISTS trg_oce_gestion ON operacion_comercio_exterior; DROP FUNCTION IF EXISTS oce_set_gestion();');
    }
};
