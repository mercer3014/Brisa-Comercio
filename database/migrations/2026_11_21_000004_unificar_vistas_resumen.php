<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Una sola familia de resúmenes del INE con UNA sola regla de valor.
 *
 * Antes había dos familias de vistas materializadas sobre operacion_comercio_exterior:
 *   - basedatos.txt: mv_resumen_anual_ine, mv_resumen_mensual_ine (las lee PortalApi)
 *   - 2026_06_08:    resumen_anual_producto/pais/departamento, resumen_mensual
 *                    (las leen ResumenPortal y RankingPortal)
 * y el panel solo refrescaba la segunda.
 *
 * Ahora:
 *   - Las 4 resumen_* son las ÚNICAS materializadas. Regla de valor:
 *       exportación/reexportación (codigo_flujo 1 y 3) -> FOB
 *       importación (codigo_flujo 2)                   -> CIF frontera
 *     resumen_mensual guarda además FOB, CIF frontera y CIF aduana por separado.
 *   - mv_resumen_anual_ine y mv_resumen_mensual_ine pasan a ser VISTAS NORMALES
 *     sobre resumen_mensual, con las mismas columnas de antes (PortalApi no
 *     cambia y siempre ve lo mismo que ResumenPortal). La mensual excluye el
 *     mes 0 (registros anuales); la anual los incluye. Las columnas
 *     paises_distintos/productos_distintos de la anual se quitaron (nadie las leía).
 *   - mv_resumen_mercosur_zona recibe índice único (permite REFRESH CONCURRENTLY).
 *   - refrescar_vistas_materializadas() refresca la familia nueva.
 */
return new class extends Migration
{
    private string $valor = "SUM(CASE WHEN fc.codigo_flujo IN ('1','3') THEN COALESCE(o.valor_fob_usd,0) ELSE COALESCE(o.valor_cif_frontera_usd,0) END)";

    public function up(): void
    {
        DB::statement('DROP MATERIALIZED VIEW IF EXISTS mv_resumen_anual_ine');
        DB::statement('DROP MATERIALIZED VIEW IF EXISTS mv_resumen_mensual_ine');
        foreach (['resumen_anual_producto', 'resumen_anual_pais', 'resumen_anual_departamento', 'resumen_mensual'] as $mv) {
            DB::statement("DROP MATERIALIZED VIEW IF EXISTS {$mv}");
        }

        $valor = $this->valor;
        $base = 'FROM operacion_comercio_exterior o
                 JOIN tiempo t ON t.tiempo_id = o.tiempo_id
                 JOIN flujo_comercial fc ON fc.flujo_id = o.flujo_id';
        $medidas = "{$valor} AS valor,
                SUM(COALESCE(o.peso_bruto_kg,0)) AS peso_bruto,
                SUM(COALESCE(o.peso_neto_kg,0))  AS peso_neto,
                COUNT(*) AS n_operaciones";

        foreach (['producto' => 'producto_id', 'pais' => 'pais_id', 'departamento' => 'departamento_id'] as $nombre => $col) {
            DB::statement("
                CREATE MATERIALIZED VIEW resumen_anual_{$nombre} AS
                SELECT o.organizacion_id, t.gestion, o.flujo_id, o.{$col}, {$medidas}
                {$base}
                GROUP BY o.organizacion_id, t.gestion, o.flujo_id, o.{$col}
                WITH DATA");
        }

        DB::statement("
            CREATE MATERIALIZED VIEW resumen_mensual AS
            SELECT o.organizacion_id, t.gestion, t.mes, o.flujo_id, {$medidas},
                   SUM(COALESCE(o.valor_fob_usd,0))          AS valor_fob_usd,
                   SUM(COALESCE(o.valor_cif_frontera_usd,0)) AS valor_cif_frontera_usd,
                   SUM(COALESCE(o.valor_cif_aduana_usd,0))   AS valor_cif_aduana_usd
            {$base}
            GROUP BY o.organizacion_id, t.gestion, t.mes, o.flujo_id
            WITH DATA");

        DB::statement('CREATE UNIQUE INDEX ux_rap_org_gestion_flujo_producto ON resumen_anual_producto (organizacion_id, gestion, flujo_id, producto_id)');
        DB::statement('CREATE INDEX ix_rap_org_gestion ON resumen_anual_producto (organizacion_id, gestion)');
        DB::statement('CREATE UNIQUE INDEX ux_rapa_org_gestion_flujo_pais ON resumen_anual_pais (organizacion_id, gestion, flujo_id, pais_id)');
        DB::statement('CREATE INDEX ix_rapa_org_gestion ON resumen_anual_pais (organizacion_id, gestion)');
        DB::statement('CREATE UNIQUE INDEX ux_rad_org_gestion_flujo_depto ON resumen_anual_departamento (organizacion_id, gestion, flujo_id, departamento_id)');
        DB::statement('CREATE INDEX ix_rad_org_gestion ON resumen_anual_departamento (organizacion_id, gestion)');
        DB::statement('CREATE UNIQUE INDEX ux_rm_org_gestion_mes_flujo ON resumen_mensual (organizacion_id, gestion, mes, flujo_id)');
        DB::statement('CREATE INDEX ix_rm_org_gestion ON resumen_mensual (organizacion_id, gestion)');

        // Compatibilidad: mismas columnas que las antiguas mv_*_ine (las lee PortalApi).
        $tipo = "CASE WHEN fc.codigo_flujo = '2' THEN 'Importación' ELSE 'Exportación' END";
        $ine  = "(SELECT organizacion_id FROM organizacion WHERE sigla = 'INE')";

        DB::statement("
            CREATE VIEW mv_resumen_anual_ine AS
            SELECT r.gestion,
                   {$tipo} AS tipo_operacion,
                   SUM(r.n_operaciones)::bigint AS total_operaciones,
                   SUM(r.valor_fob_usd)          AS total_fob_usd,
                   SUM(r.valor_cif_frontera_usd) AS total_cif_usd,
                   SUM(r.peso_neto)              AS total_peso_neto_kg
              FROM resumen_mensual r
              JOIN flujo_comercial fc ON fc.flujo_id = r.flujo_id
             WHERE r.organizacion_id = {$ine}
             GROUP BY r.gestion, {$tipo}");

        DB::statement("
            CREATE VIEW mv_resumen_mensual_ine AS
            SELECT r.gestion,
                   r.mes,
                   make_date(r.gestion::int, r.mes::int, 1) AS fecha_inicio_mes,
                   {$tipo} AS tipo_operacion,
                   SUM(r.n_operaciones)::bigint AS total_operaciones,
                   SUM(r.valor_fob_usd)          AS total_fob_usd,
                   SUM(r.valor_cif_frontera_usd) AS total_cif_usd,
                   SUM(r.peso_neto)              AS total_peso_neto_kg
              FROM resumen_mensual r
              JOIN flujo_comercial fc ON fc.flujo_id = r.flujo_id
             WHERE r.organizacion_id = {$ine} AND r.mes BETWEEN 1 AND 12
             GROUP BY r.gestion, r.mes, {$tipo}");

        // MERCOSUR: índice único para REFRESH CONCURRENTLY y dejarla poblada.
        DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS ux_mv_mercosur_zona_gestion ON mv_resumen_mercosur_zona (zona_id, gestion)');
        DB::statement('REFRESH MATERIALIZED VIEW mv_resumen_mercosur_zona');

        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION refrescar_vistas_materializadas() RETURNS VOID AS $$
            BEGIN
                REFRESH MATERIALIZED VIEW CONCURRENTLY resumen_anual_producto;
                REFRESH MATERIALIZED VIEW CONCURRENTLY resumen_anual_pais;
                REFRESH MATERIALIZED VIEW CONCURRENTLY resumen_anual_departamento;
                REFRESH MATERIALIZED VIEW CONCURRENTLY resumen_mensual;
                REFRESH MATERIALIZED VIEW CONCURRENTLY mv_resumen_mercosur_zona;
            END;
            $$ LANGUAGE plpgsql;
            COMMENT ON FUNCTION refrescar_vistas_materializadas IS 'Refresca resumen_* (INE) y mv_resumen_mercosur_zona. Equivale a php artisan geodata:refrescar-vistas.';
        SQL);
    }

    public function down(): void
    {
        // Vuelta a la versión previa de las resumen_* (regla flujo_id = 1) no se
        // implementa: las vistas son derivadas; usar migrate:fresh si hace falta.
        DB::statement('DROP VIEW IF EXISTS mv_resumen_anual_ine');
        DB::statement('DROP VIEW IF EXISTS mv_resumen_mensual_ine');
    }
};
