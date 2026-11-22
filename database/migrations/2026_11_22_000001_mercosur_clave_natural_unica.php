<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * MERCOSUR: la recarga del mismo archivo/zona/año duplicaba filas porque la
 * idempotencia era por archivo (y cada subida desde el panel crea una ruta nueva).
 *
 * 1) Elimina duplicados existentes por CLAVE NATURAL, conservando la fila más
 *    reciente (id mayor = última carga):
 *      - serie_comercio_zona:          (organizacion_id, zona_id, pais_id, gestion)
 *      - serie_comercio_producto_zona: (organizacion_id, zona_id, ncm_codigo, gestion)
 * 2) Crea índices UNIQUE (NULLS NOT DISTINCT) sobre esas claves para que no
 *    vuelvan a aparecer; los cargadores ahora reemplazan por (zona, gestión) y
 *    hacen UPSERT sobre la clave.
 * 3) Refresca mv_resumen_mercosur_zona si se borró algo.
 */
return new class extends Migration
{
    public function up(): void
    {
        $borradasZona = DB::affectingStatement(<<<'SQL'
            DELETE FROM serie_comercio_zona s
            USING (
                SELECT serie_zona_id,
                       ROW_NUMBER() OVER (PARTITION BY organizacion_id, zona_id, pais_id, gestion
                                          ORDER BY serie_zona_id DESC) AS rn
                FROM serie_comercio_zona
            ) d
            WHERE s.serie_zona_id = d.serie_zona_id AND d.rn > 1
        SQL);

        $borradasItem = DB::affectingStatement(<<<'SQL'
            DELETE FROM serie_comercio_producto_zona s
            USING (
                SELECT serie_prod_zona_id,
                       ROW_NUMBER() OVER (PARTITION BY organizacion_id, zona_id, ncm_codigo, gestion
                                          ORDER BY serie_prod_zona_id DESC) AS rn
                FROM serie_comercio_producto_zona
            ) d
            WHERE s.serie_prod_zona_id = d.serie_prod_zona_id AND d.rn > 1
        SQL);

        DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS uq_scz_clave_natural
            ON serie_comercio_zona (organizacion_id, zona_id, pais_id, gestion) NULLS NOT DISTINCT');
        DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS uq_scpz_clave_natural
            ON serie_comercio_producto_zona (organizacion_id, zona_id, ncm_codigo, gestion) NULLS NOT DISTINCT');

        if ($borradasZona + $borradasItem > 0) {
            DB::statement('REFRESH MATERIALIZED VIEW mv_resumen_mercosur_zona');
        }

        if (app()->runningInConsole()) {
            fwrite(STDOUT, "  MERCOSUR: {$borradasZona} duplicado(s) eliminados en serie_comercio_zona, {$borradasItem} en serie_comercio_producto_zona.\n");
        }
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS uq_scz_clave_natural');
        DB::statement('DROP INDEX IF EXISTS uq_scpz_clave_natural');
    }
};
