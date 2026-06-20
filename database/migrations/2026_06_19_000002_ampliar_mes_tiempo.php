<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Expande el CHECK de tiempo.mes para permitir mes=0 (registro anual).
 * MERCOSUR, ALADI y FAOSTAT usan mes=0 para series anuales sin desglose mensual.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE tiempo DROP CONSTRAINT IF EXISTS tiempo_mes_check');
        DB::statement('ALTER TABLE tiempo ADD CONSTRAINT tiempo_mes_check CHECK (mes >= 0 AND mes <= 12)');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE tiempo DROP CONSTRAINT IF EXISTS tiempo_mes_check');
        DB::statement('ALTER TABLE tiempo ADD CONSTRAINT tiempo_mes_check CHECK (mes >= 1 AND mes <= 12)');
    }
};
