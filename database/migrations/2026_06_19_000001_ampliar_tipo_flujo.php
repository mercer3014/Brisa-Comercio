<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Amplía el CHECK constraint de tipo_flujo en perfil_mapeo y carga_archivo
 * para soportar MERCOSUR_PAIS, MERCOSUR_ITEM, ALADI_RANKING y FAOSTAT,
 * además de los valores originales EXPORTACION e IMPORTACION.
 *
 * No toca ninguna tabla de dominio ni datos existentes.
 */
return new class extends Migration
{
    private array $tipos = [
        'EXPORTACION', 'IMPORTACION',
        'MERCOSUR_PAIS', 'MERCOSUR_ITEM',
        'ALADI_RANKING', 'FAOSTAT',
    ];

    public function up(): void
    {
        $lista = implode(', ', array_map(fn ($t) => "'{$t}'", $this->tipos));

        // perfil_mapeo
        DB::statement('ALTER TABLE perfil_mapeo DROP CONSTRAINT IF EXISTS perfil_mapeo_tipo_flujo_check');
        DB::statement("ALTER TABLE perfil_mapeo ADD CONSTRAINT perfil_mapeo_tipo_flujo_check CHECK (tipo_flujo IN ({$lista}))");

        // carga_archivo
        DB::statement('ALTER TABLE carga_archivo DROP CONSTRAINT IF EXISTS carga_archivo_tipo_flujo_check');
        DB::statement("ALTER TABLE carga_archivo ADD CONSTRAINT carga_archivo_tipo_flujo_check CHECK (tipo_flujo IN ({$lista}))");
    }

    public function down(): void
    {
        $original = "'EXPORTACION', 'IMPORTACION'";

        DB::statement('ALTER TABLE perfil_mapeo DROP CONSTRAINT IF EXISTS perfil_mapeo_tipo_flujo_check');
        DB::statement("ALTER TABLE perfil_mapeo ADD CONSTRAINT perfil_mapeo_tipo_flujo_check CHECK (tipo_flujo IN ({$original}))");

        DB::statement('ALTER TABLE carga_archivo DROP CONSTRAINT IF EXISTS carga_archivo_tipo_flujo_check');
        DB::statement("ALTER TABLE carga_archivo ADD CONSTRAINT carga_archivo_tipo_flujo_check CHECK (tipo_flujo IN ({$original}))");
    }
};
