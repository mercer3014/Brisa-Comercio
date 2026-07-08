<?php

namespace Database\Seeders;

use App\Models\ReglaValidacion;
use Illuminate\Database\Seeder;

class ReglaValidacionSeeder extends Seeder
{
    /**
     * Reglas base de calidad. La columna `expresion` lleva un código que el
     * ProcesadorEtl sabe interpretar (no_nulo, no_negativo, rango_mes).
     */
    public function run(): void
    {
        $reglas = [
            ['nombre' => 'gestion_requerida',  'descripcion' => 'La gestion (anio) es obligatoria.',          'campo_objetivo' => 'gestion',         'expresion' => 'no_nulo',     'severidad' => 'ERROR'],
            ['nombre' => 'mes_requerido',      'descripcion' => 'El mes es obligatorio.',                     'campo_objetivo' => 'mes',             'expresion' => 'no_nulo',     'severidad' => 'ERROR'],
            ['nombre' => 'mes_valido',         'descripcion' => 'El mes debe estar entre 1 y 12.',            'campo_objetivo' => 'mes',             'expresion' => 'rango_mes',   'severidad' => 'ERROR'],
            ['nombre' => 'nandina_requerida',  'descripcion' => 'El codigo NANDINA es obligatorio.',          'campo_objetivo' => 'codigo_nandina',  'expresion' => 'no_nulo',     'severidad' => 'ERROR'],
            ['nombre' => 'pais_requerido',     'descripcion' => 'El codigo de pais es obligatorio.',          'campo_objetivo' => 'codigo_pais',     'expresion' => 'no_nulo',     'severidad' => 'ERROR'],
            ['nombre' => 'fob_no_negativo',    'descripcion' => 'El valor FOB no puede ser negativo.',        'campo_objetivo' => 'valor_fob_usd',   'expresion' => 'no_negativo', 'severidad' => 'ADVERTENCIA'],
            ['nombre' => 'cif_no_negativo',    'descripcion' => 'El valor CIF frontera no puede ser negativo.','campo_objetivo' => 'valor_cif_frontera_usd', 'expresion' => 'no_negativo', 'severidad' => 'ADVERTENCIA'],
            ['nombre' => 'peso_bruto_no_neg',  'descripcion' => 'El peso bruto no puede ser negativo.',       'campo_objetivo' => 'peso_bruto_kg',   'expresion' => 'no_negativo', 'severidad' => 'ADVERTENCIA'],
        ];

        foreach ($reglas as $r) {
            ReglaValidacion::updateOrCreate(['nombre' => $r['nombre']], $r + ['activa' => true]);
        }
    }
}
