<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pais_panel', function (Blueprint $table) {
            $table->increments('pais_panel_id');
            $table->string('nombre', 120);
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        // Poblamos desde la tabla país con deduplicacion por acentos
        try {
            $paises = DB::table('pais')->orderBy('nombre')->get(['nombre']);

            $mapa = [];
            foreach ($paises as $p) {
                $formateado = ucfirst(mb_strtolower($p->nombre));
                $clave      = self::normalizar($formateado);

                if (! isset($mapa[$clave])) {
                    $mapa[$clave] = $formateado;
                } else {
                    // Quedarse con el que tenga más acentos (mejor ortografía)
                    if (self::contarAcentos($formateado) > self::contarAcentos($mapa[$clave])) {
                        $mapa[$clave] = $formateado;
                    }
                }
            }

            $filas = collect(array_values($mapa))
                ->sort()
                ->values()
                ->map(fn ($nombre) => [
                    'nombre'     => $nombre,
                    'activo'     => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
                ->toArray();

            DB::table('pais_panel')->insert($filas);
        } catch (\Throwable) {
            // Si la tabla país aún no existe, se deja vacia para poblar después.
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('pais_panel');
    }

    private static function normalizar(string $s): string
    {
        $desde = ['á','à','ä','â','é','è','ë','ê','í','ì','ï','î','ó','ò','ö','ô','ú','ù','ü','û','ñ','ç'];
        $hacia = ['a','a','a','a','e','e','e','e','i','i','i','i','o','o','o','o','u','u','u','u','n','c'];
        return str_replace($desde, $hacia, mb_strtolower($s));
    }

    private static function contarAcentos(string $s): int
    {
        return preg_match_all('/[áàäâéèëêíìïîóòöôúùüûñç]/u', $s);
    }
};
