<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * operacion_comercio_exterior (4M+ filas) solo tenía tiempo_id: filtrar por
 * gestión obligaba a un JOIN contra "tiempo" antes de poder descartar filas,
 * lo que forzaba un seq scan de casi toda la tabla en cada consulta del
 * portal público (medido: ~830ms por consulta con caché fría). Se agrega la
 * gestión desnormalizada + índice compuesto con organizacion_id, que es el
 * mismo par de columnas que ya filtra PortalApi::baseIne() en cada llamada.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('operacion_comercio_exterior', function (Blueprint $table) {
            $table->integer('gestion')->nullable()->after('tiempo_id');
        });

        DB::statement(
            'UPDATE operacion_comercio_exterior o SET gestion = t.gestion FROM tiempo t WHERE t.tiempo_id = o.tiempo_id'
        );

        Schema::table('operacion_comercio_exterior', function (Blueprint $table) {
            $table->index(['organizacion_id', 'gestion'], 'idx_oce_org_gestion');
        });
    }

    public function down(): void
    {
        Schema::table('operacion_comercio_exterior', function (Blueprint $table) {
            $table->dropIndex('idx_oce_org_gestion');
            $table->dropColumn('gestion');
        });
    }
};
