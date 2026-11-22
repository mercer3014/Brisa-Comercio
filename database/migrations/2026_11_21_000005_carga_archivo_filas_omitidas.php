<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Las filas que CargadorIne descartaba (sin año o sin NANDINA) desaparecían sin
 * rastro: así las importaciones 1993-2016 terminaban "COMPLETADO, 0 filas".
 * Ahora se cuentan en carga_archivo.total_filas_omitidas y se detallan (hasta 500
 * por carga, más un resumen) en incidencia_calidad.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE carga_archivo ADD COLUMN IF NOT EXISTS total_filas_omitidas INT NOT NULL DEFAULT 0');
        DB::statement("COMMENT ON COLUMN carga_archivo.total_filas_omitidas IS 'Filas descartadas por faltar año (GESTION) o NANDINA; detalle en incidencia_calidad.'");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE carga_archivo DROP COLUMN IF EXISTS total_filas_omitidas');
    }
};
