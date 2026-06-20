<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Ajustes estructurales para que los loaders no-INE funcionen:
 *
 * 1. tiempo.trimestre y tiempo.semestre: permite 0 para registros anuales.
 *    MERCOSUR/ALADI/FAOSTAT usan mes=0 (anual), trimestre=0, semestre=0.
 *
 * 2. ranking_comercio: flujo_id, fila_excel y ordinal pasan a nullable.
 *    Para ALADI_RANKING el flujo cubre todos los flujos (no aplica uno en particular).
 *    fila_excel y ordinal son opcionales según el formato del archivo.
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1. Tiempo: ampliar trimestre y semestre
        DB::statement('ALTER TABLE tiempo DROP CONSTRAINT IF EXISTS tiempo_trimestre_check');
        DB::statement('ALTER TABLE tiempo ADD CONSTRAINT tiempo_trimestre_check CHECK (trimestre >= 0 AND trimestre <= 4)');

        DB::statement('ALTER TABLE tiempo DROP CONSTRAINT IF EXISTS tiempo_semestre_check');
        DB::statement('ALTER TABLE tiempo ADD CONSTRAINT tiempo_semestre_check CHECK (semestre >= 0 AND semestre <= 2)');

        // 2. ranking_comercio: hacer nullable las columnas opcionales
        DB::statement('ALTER TABLE ranking_comercio ALTER COLUMN flujo_id DROP NOT NULL');
        DB::statement('ALTER TABLE ranking_comercio ALTER COLUMN fila_excel DROP NOT NULL');
        DB::statement('ALTER TABLE ranking_comercio ALTER COLUMN ordinal DROP NOT NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE tiempo DROP CONSTRAINT IF EXISTS tiempo_trimestre_check');
        DB::statement('ALTER TABLE tiempo ADD CONSTRAINT tiempo_trimestre_check CHECK (trimestre >= 1 AND trimestre <= 4)');

        DB::statement('ALTER TABLE tiempo DROP CONSTRAINT IF EXISTS tiempo_semestre_check');
        DB::statement('ALTER TABLE tiempo ADD CONSTRAINT tiempo_semestre_check CHECK (semestre >= 1 AND semestre <= 2)');

        DB::statement('ALTER TABLE ranking_comercio ALTER COLUMN flujo_id SET NOT NULL');
        DB::statement('ALTER TABLE ranking_comercio ALTER COLUMN fila_excel SET NOT NULL');
        DB::statement('ALTER TABLE ranking_comercio ALTER COLUMN ordinal SET NOT NULL');
    }
};
