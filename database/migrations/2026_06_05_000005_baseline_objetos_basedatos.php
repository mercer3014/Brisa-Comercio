<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * BASELINE: crea los objetos que antes SOLO existían en basedatos.txt (no había
 * migración para ellos): tablas de ALADI, MERCOSUR, FAOSTAT y CAN, nomenclatura
 * NANDINA, portal/créditos, semillas (organizaciones, tipo_operacion,
 * flujo_comercial, ...) y la vista mv_resumen_mercosur_zona.
 *
 * Es IDEMPOTENTE (CREATE ... IF NOT EXISTS y semillas solo si la tabla está
 * vacía): en una BD creada con basedatos.txt no cambia nada; en una BD vacía
 * permite que `php artisan migrate:fresh --seed` reconstruya todo el esquema.
 *
 * El SQL está en database/sql/baseline_basedatos.sql. Desde ahora basedatos.txt
 * queda solo como referencia histórica: la fuente de verdad son las migraciones.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(file_get_contents(database_path('sql/baseline_basedatos.sql')));
    }

    public function down(): void
    {
        // No se revierte: borraría tablas con datos de ALADI/MERCOSUR/FAOSTAT.
    }
};
