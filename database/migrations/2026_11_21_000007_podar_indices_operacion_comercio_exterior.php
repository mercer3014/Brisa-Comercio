<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Poda de índices inútiles en operacion_comercio_exterior (4M+ filas, cada INSERT
 * mantenía 19 índices). Solo se borran los que ninguna consulta puede usar:
 *   - idx_oce_extras (GIN sobre atributos_extra): ninguna consulta filtra por
 *     atributos_extra y CargadorIne siempre lo deja NULL.
 *   - idx_oce_organizacion: redundante; organizacion_id ya es la primera columna
 *     de idx_oce_org_gestion y de idx_oce_org_tiempo_pais_prod.
 * Se CONSERVAN idx_oce_tipo_op, idx_oce_flujo, idx_oce_medio, idx_oce_via e
 * idx_oce_tnt porque el Explorador filtra por esas columnas (ConsultaExplorador).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('DROP INDEX IF EXISTS idx_oce_extras');
        DB::statement('DROP INDEX IF EXISTS idx_oce_organizacion');
    }

    public function down(): void
    {
        DB::statement('CREATE INDEX IF NOT EXISTS idx_oce_organizacion ON operacion_comercio_exterior (organizacion_id)');
        DB::statement('CREATE INDEX IF NOT EXISTS idx_oce_extras ON operacion_comercio_exterior USING GIN (atributos_extra)');
    }
};
