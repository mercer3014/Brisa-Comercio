<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pais_panel', function (Blueprint $table) {
            $table->string('iso_alpha2', 2)->nullable()->after('nombre');
        });

        // Poblar iso_alpha2 desde la tabla pais usando match de nombre (case-insensitive)
        DB::statement("
            UPDATE pais_panel pp
            SET iso_alpha2 = (
                SELECT p.iso_alpha2
                FROM pais p
                WHERE LOWER(p.nombre) = LOWER(pp.nombre)
                  AND p.iso_alpha2 IS NOT NULL
                LIMIT 1
            )
        ");

        // Segunda pasada: match sin acentos para los que no empataron
        DB::statement("
            UPDATE pais_panel pp
            SET iso_alpha2 = (
                SELECT p.iso_alpha2
                FROM pais p
                WHERE LOWER(
                    translate(p.nombre,
                        'áàäâéèëêíìïîóòöôúùüûñÁÀÄÂÉÈËÊÍÌÏÎÓÒÖÔÚÙÜÛÑ',
                        'aaaaeeeeiiiioooouuuunAAAAEEEEIIIIOOOOUUUUN'
                    )
                ) = LOWER(
                    translate(pp.nombre,
                        'áàäâéèëêíìïîóòöôúùüûñÁÀÄÂÉÈËÊÍÌÏÎÓÒÖÔÚÙÜÛÑ',
                        'aaaaeeeeiiiioooouuuunAAAAEEEEIIIIOOOOUUUUN'
                    )
                )
                  AND p.iso_alpha2 IS NOT NULL
                LIMIT 1
            )
            WHERE pp.iso_alpha2 IS NULL
        ");
    }

    public function down(): void
    {
        Schema::table('pais_panel', function (Blueprint $table) {
            $table->dropColumn('iso_alpha2');
        });
    }
};
