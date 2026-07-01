<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
            ALTER TABLE carga_archivo DROP CONSTRAINT IF EXISTS carga_archivo_tipo_flujo_check;
            ALTER TABLE carga_archivo
                ADD CONSTRAINT carga_archivo_tipo_flujo_check
                CHECK (tipo_flujo IN (
                    'EXPORTACION',
                    'IMPORTACION',
                    'MERCOSUR_PAIS',
                    'MERCOSUR_ITEM',
                    'ALADI_RANKING',
                    'FAOSTAT'
                ));

            ALTER TABLE tiempo DROP CONSTRAINT IF EXISTS tiempo_mes_check;
            ALTER TABLE tiempo DROP CONSTRAINT IF EXISTS tiempo_trimestre_check;
            ALTER TABLE tiempo DROP CONSTRAINT IF EXISTS tiempo_semestre_check;
            ALTER TABLE tiempo ADD CONSTRAINT tiempo_mes_check CHECK (mes BETWEEN 0 AND 12);
            ALTER TABLE tiempo ADD CONSTRAINT tiempo_trimestre_check CHECK (trimestre BETWEEN 0 AND 4);
            ALTER TABLE tiempo ADD CONSTRAINT tiempo_semestre_check CHECK (semestre BETWEEN 0 AND 2);
        SQL);
    }

    public function down(): void
    {
        DB::unprepared(<<<'SQL'
            ALTER TABLE carga_archivo DROP CONSTRAINT IF EXISTS carga_archivo_tipo_flujo_check;
            ALTER TABLE carga_archivo
                ADD CONSTRAINT carga_archivo_tipo_flujo_check
                CHECK (tipo_flujo IN ('EXPORTACION', 'IMPORTACION'));

            ALTER TABLE tiempo DROP CONSTRAINT IF EXISTS tiempo_mes_check;
            ALTER TABLE tiempo DROP CONSTRAINT IF EXISTS tiempo_trimestre_check;
            ALTER TABLE tiempo DROP CONSTRAINT IF EXISTS tiempo_semestre_check;
            ALTER TABLE tiempo ADD CONSTRAINT tiempo_mes_check CHECK (mes BETWEEN 1 AND 12);
            ALTER TABLE tiempo ADD CONSTRAINT tiempo_trimestre_check CHECK (trimestre BETWEEN 1 AND 4);
            ALTER TABLE tiempo ADD CONSTRAINT tiempo_semestre_check CHECK (semestre BETWEEN 1 AND 2);
        SQL);
    }
};
