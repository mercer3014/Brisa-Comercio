<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Tarea 14 — Vistas materializadas para el rendimiento del portal publico.
 *
 * Son objetos de SOLO LECTURA derivados de operacion_comercio_exterior; NO modifican
 * ni borran las tablas base. Precalculan los resumenes mas usados por la portada
 * (Tarea 12) y los rankings (Tarea 13).
 *
 * Convencion de valor: por cada fila de la vista (que ya esta separada por flujo_id),
 * `valor` = SUM(valor_fob_usd) para exportacion (flujo 1) y SUM(valor_cif_frontera_usd)
 * para importacion (flujo 2), coherente con AgregadorDashboard y ResumenPortal.
 *
 * Se refrescan con el comando `comexhub:refrescar-vistas` (tras cada ETL exitoso).
 */
return new class extends Migration
{
    /** Expresion de valor segun flujo, reutilizada en las vistas. */
    private string $valor = 'SUM(CASE WHEN o.flujo_id = 1 THEN COALESCE(o.valor_fob_usd,0) ELSE COALESCE(o.valor_cif_frontera_usd,0) END)';

    public function up(): void
    {
        $valor = $this->valor;

        // 1) Resumen anual por producto
        DB::statement("
            CREATE MATERIALIZED VIEW resumen_anual_producto AS
            SELECT
                o.organizacion_id,
                t.gestion,
                o.flujo_id,
                o.producto_id,
                {$valor} AS valor,
                SUM(COALESCE(o.peso_bruto_kg,0)) AS peso_bruto,
                SUM(COALESCE(o.peso_neto_kg,0))  AS peso_neto,
                COUNT(*) AS n_operaciones
            FROM operacion_comercio_exterior o
            JOIN tiempo t ON t.tiempo_id = o.tiempo_id
            GROUP BY o.organizacion_id, t.gestion, o.flujo_id, o.producto_id
            WITH DATA
        ");

        // 2) Resumen anual por pais
        DB::statement("
            CREATE MATERIALIZED VIEW resumen_anual_pais AS
            SELECT
                o.organizacion_id,
                t.gestion,
                o.flujo_id,
                o.pais_id,
                {$valor} AS valor,
                SUM(COALESCE(o.peso_bruto_kg,0)) AS peso_bruto,
                SUM(COALESCE(o.peso_neto_kg,0))  AS peso_neto,
                COUNT(*) AS n_operaciones
            FROM operacion_comercio_exterior o
            JOIN tiempo t ON t.tiempo_id = o.tiempo_id
            GROUP BY o.organizacion_id, t.gestion, o.flujo_id, o.pais_id
            WITH DATA
        ");

        // 3) Resumen anual por departamento
        DB::statement("
            CREATE MATERIALIZED VIEW resumen_anual_departamento AS
            SELECT
                o.organizacion_id,
                t.gestion,
                o.flujo_id,
                o.departamento_id,
                {$valor} AS valor,
                SUM(COALESCE(o.peso_bruto_kg,0)) AS peso_bruto,
                SUM(COALESCE(o.peso_neto_kg,0))  AS peso_neto,
                COUNT(*) AS n_operaciones
            FROM operacion_comercio_exterior o
            JOIN tiempo t ON t.tiempo_id = o.tiempo_id
            GROUP BY o.organizacion_id, t.gestion, o.flujo_id, o.departamento_id
            WITH DATA
        ");

        // 4) Resumen mensual (para graficos de evolucion)
        DB::statement("
            CREATE MATERIALIZED VIEW resumen_mensual AS
            SELECT
                o.organizacion_id,
                t.gestion,
                t.mes,
                o.flujo_id,
                {$valor} AS valor,
                SUM(COALESCE(o.peso_bruto_kg,0)) AS peso_bruto,
                SUM(COALESCE(o.peso_neto_kg,0))  AS peso_neto,
                COUNT(*) AS n_operaciones
            FROM operacion_comercio_exterior o
            JOIN tiempo t ON t.tiempo_id = o.tiempo_id
            GROUP BY o.organizacion_id, t.gestion, t.mes, o.flujo_id
            WITH DATA
        ");

        // Indices por organizacion y anio (y unicos para permitir REFRESH CONCURRENTLY).
        DB::statement('CREATE UNIQUE INDEX ux_rap_org_gestion_flujo_producto ON resumen_anual_producto (organizacion_id, gestion, flujo_id, producto_id)');
        DB::statement('CREATE INDEX ix_rap_org_gestion ON resumen_anual_producto (organizacion_id, gestion)');

        DB::statement('CREATE UNIQUE INDEX ux_rapa_org_gestion_flujo_pais ON resumen_anual_pais (organizacion_id, gestion, flujo_id, pais_id)');
        DB::statement('CREATE INDEX ix_rapa_org_gestion ON resumen_anual_pais (organizacion_id, gestion)');

        DB::statement('CREATE UNIQUE INDEX ux_rad_org_gestion_flujo_depto ON resumen_anual_departamento (organizacion_id, gestion, flujo_id, departamento_id)');
        DB::statement('CREATE INDEX ix_rad_org_gestion ON resumen_anual_departamento (organizacion_id, gestion)');

        DB::statement('CREATE UNIQUE INDEX ux_rm_org_gestion_mes_flujo ON resumen_mensual (organizacion_id, gestion, mes, flujo_id)');
        DB::statement('CREATE INDEX ix_rm_org_gestion ON resumen_mensual (organizacion_id, gestion)');
    }

    public function down(): void
    {
        DB::statement('DROP MATERIALIZED VIEW IF EXISTS resumen_anual_producto');
        DB::statement('DROP MATERIALIZED VIEW IF EXISTS resumen_anual_pais');
        DB::statement('DROP MATERIALIZED VIEW IF EXISTS resumen_anual_departamento');
        DB::statement('DROP MATERIALIZED VIEW IF EXISTS resumen_mensual');
    }
};
