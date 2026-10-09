<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * serie_indicador_agricola (FAOSTAT) tiene ~4.5M filas y el dashboard
 * siempre filtra por elemento_id (los pocos "indice de valor") + gestion.
 * Con solo idx_agricola_elemento e idx_agricola_gestion por separado,
 * Postgres tenia que combinarlos con un BitmapAnd (~1.1s por consulta);
 * un indice compuesto deja que use un solo Bitmap Index Scan (~0.35s).
 * El dashboard de FAOSTAT llegaba a tardar mas de 100s en frio con esto.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE INDEX IF NOT EXISTS idx_agricola_elemento_gestion ON serie_indicador_agricola (elemento_id, gestion)');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS idx_agricola_elemento_gestion');
    }
};
