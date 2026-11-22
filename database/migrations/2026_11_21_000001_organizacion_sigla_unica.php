<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Organizaciones: la SIGLA pasa a ser UNIQUE y el código resuelve el id por sigla
 * (App\Support\Organizaciones) en vez de usar 1/2/3/4 fijos.
 *
 * 1. Fusiona duplicados por sigla (p.ej. el "Instituto Nacional de Estadistica de
 *    Bolivia" sin tilde que creaba el seeder): reasigna sus filas hijas al id más
 *    bajo y borra el duplicado. Si hay un conflicto, se detiene y avisa.
 * 2. Normaliza sigla (mayúsculas, sin espacios) y agrega UNIQUE(sigla).
 * 3. Verifica que INE/ALADI/MERCOSUR/FAOSTAT tengan los ids 1/2/3/4 que todavía
 *    usan PortalApi::ORG_* y el frontend; si no coinciden, solo deja un aviso.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("UPDATE organizacion SET sigla = UPPER(TRIM(sigla)) WHERE sigla IS NOT NULL AND sigla <> UPPER(TRIM(sigla))");

        $duplicadas = DB::select('SELECT sigla, array_agg(organizacion_id ORDER BY organizacion_id) AS ids
                                    FROM organizacion WHERE sigla IS NOT NULL GROUP BY sigla HAVING COUNT(*) > 1');
        foreach ($duplicadas as $d) {
            $ids = array_map('intval', explode(',', trim($d->ids, '{}')));
            $conservar = array_shift($ids);
            foreach ($ids as $id) {
                try {
                    DB::transaction(function () use ($id, $conservar) {
                        // Reasignar al registro conservado todo lo que apunte al duplicado.
                        $fks = DB::select("
                            SELECT c.conrelid::regclass::text AS tabla, a.attname AS columna
                              FROM pg_constraint c
                              JOIN pg_attribute a ON a.attrelid = c.conrelid AND a.attnum = c.conkey[1]
                             WHERE c.contype = 'f' AND c.confrelid = 'organizacion'::regclass");
                        foreach ($fks as $fk) {
                            DB::table($fk->tabla)->where($fk->columna, $id)->update([$fk->columna => $conservar]);
                        }
                        DB::table('organizacion')->where('organizacion_id', $id)->delete();
                    });
                } catch (\Throwable $e) {
                    throw new RuntimeException("La organización {$id} duplica la sigla {$d->sigla} (se conserva la {$conservar}) y no se pudo fusionar automáticamente; resuélvalo a mano y vuelva a migrar. Detalle: ".$e->getMessage());
                }
            }
        }

        DB::statement('ALTER TABLE organizacion ADD CONSTRAINT uq_organizacion_sigla UNIQUE (sigla)');

        $esperado = ['INE' => 1, 'ALADI' => 2, 'MERCOSUR' => 3, 'FAOSTAT' => 4];
        foreach ($esperado as $sigla => $id) {
            $real = DB::table('organizacion')->where('sigla', $sigla)->value('organizacion_id');
            if ($real !== null && (int) $real !== $id) {
                fwrite(STDERR, "AVISO: {$sigla} tiene organizacion_id={$real} (el portal asume {$id}). Los cargadores ya resuelven por sigla, pero PortalApi y el frontend aún usan {$id}.\n");
            }
        }
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE organizacion DROP CONSTRAINT IF EXISTS uq_organizacion_sigla');
    }
};
