<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * producto.codigo_nandina es BIGINT y pierde el cero inicial de los capítulos
 * 01-09 (café 0901..., banano 0803..., cítricos 0805...): 0901110000 se guarda
 * como 901110000.
 *
 * Sin romper el código existente (que sigue escribiendo/leyendo el BIGINT):
 *   - codigo_nandina_txt CHAR(10): columna GENERADA = lpad(codigo_nandina, 10, '0'),
 *     indexada; se usa para mostrar y buscar el código.
 *   - nomenclatura_version_id: referencia opcional a la versión NANDINA (Decisión
 *     CAN) del código; por ahora NULL (no hay cargador de nomenclatura).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE producto ADD COLUMN IF NOT EXISTS codigo_nandina_txt CHAR(10)
                       GENERATED ALWAYS AS (lpad(codigo_nandina::text, 10, '0')) STORED");
        DB::statement('CREATE INDEX IF NOT EXISTS idx_producto_nandina_txt ON producto (codigo_nandina_txt)');
        DB::statement("COMMENT ON COLUMN producto.codigo_nandina_txt IS 'NANDINA de 10 dígitos con ceros a la izquierda (derivada de codigo_nandina).'");

        if (\Illuminate\Support\Facades\Schema::hasTable('nomenclatura_version')) {
            DB::statement('ALTER TABLE producto ADD COLUMN IF NOT EXISTS nomenclatura_version_id SMALLINT
                           REFERENCES nomenclatura_version(version_id) ON DELETE SET NULL');
        }
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE producto DROP COLUMN IF EXISTS nomenclatura_version_id');
        DB::statement('DROP INDEX IF EXISTS idx_producto_nandina_txt');
        DB::statement('ALTER TABLE producto DROP COLUMN IF EXISTS codigo_nandina_txt');
    }
};
