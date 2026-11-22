<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Mes "anual" del INE. Antes CargadorIne convertía el mes ausente en ENERO
 * (max(1, ...)), mientras ALADI/MERCOSUR/FAOSTAT usan mes = 0 para "Anual".
 * Desde ahora el INE también usa mes = 0 (tiempo.mes ya admite 0..12).
 *
 * Corrección de datos existentes (heurística conservadora): un (gestión, flujo)
 * del INE cuyas filas están TODAS en enero, sin ningún otro mes, proviene de un
 * archivo anual → se mueve a mes 0. Si hay al menos otro mes, no se toca.
 * Además se carga 'carga_archivo.mes' que permitía solo 1..12 → ahora 0..12.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE carga_archivo DROP CONSTRAINT IF EXISTS carga_archivo_mes_check');
        DB::statement('ALTER TABLE carga_archivo ADD CONSTRAINT carga_archivo_mes_check CHECK (mes >= 0 AND mes <= 12)');

        $ine = DB::table('organizacion')->where('sigla', 'INE')->value('organizacion_id');
        if (! $ine) {
            return;
        }

        $grupos = DB::select('
            SELECT o.gestion, o.flujo_id
              FROM operacion_comercio_exterior o
              JOIN tiempo t ON t.tiempo_id = o.tiempo_id
             WHERE o.organizacion_id = ?
             GROUP BY o.gestion, o.flujo_id
            HAVING bool_and(t.mes = 1)', [$ine]);

        foreach ($grupos as $g) {
            $anual = DB::table('tiempo')->where('gestion', $g->gestion)->where('mes', 0)->value('tiempo_id');
            if (! $anual) {
                $anual = DB::table('tiempo')->insertGetId([
                    'gestion' => $g->gestion, 'mes' => 0, 'nombre_mes' => 'Anual',
                    'trimestre' => 0, 'semestre' => 0, 'fecha_inicio_mes' => sprintf('%04d-01-01', $g->gestion),
                ], 'tiempo_id');
            }
            DB::table('operacion_comercio_exterior')
                ->where('organizacion_id', $ine)->where('flujo_id', $g->flujo_id)->where('gestion', $g->gestion)
                ->update(['tiempo_id' => $anual]);
        }
    }

    public function down(): void
    {
        // Irreversible a nivel de datos (no se sabe qué filas eran enero real).
        DB::statement('ALTER TABLE carga_archivo DROP CONSTRAINT IF EXISTS carga_archivo_mes_check');
        DB::statement('ALTER TABLE carga_archivo ADD CONSTRAINT carga_archivo_mes_check CHECK (mes >= 1 AND mes <= 12)');
    }
};
