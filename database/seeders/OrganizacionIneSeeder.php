<?php

namespace Database\Seeders;

use App\Models\MapeoColumna;
use App\Models\Organizacion;
use App\Models\PerfilMapeo;
use Illuminate\Database\Seeder;

class OrganizacionIneSeeder extends Seeder
{
    public function run(): void
    {
        // 1) INE como primera organización (organizacion_id = 1 esperado).
        $ine = Organizacion::updateOrCreate(
            ['nombre' => 'Instituto Nacional de Estadistica de Bolivia'],
            [
                'sigla'     => 'INE',
                'pais_iso3' => 'BOL',
                'url'       => 'https://www.ine.gob.bo',
                'activo'    => true,
            ]
        );

        // 2) Perfiles iniciales: exportación e importación.
        // NOTA: las cabeceras reales completas las entrega el equipo; este mapeo es una
        // base operativa con los alias conocidos. Editable desde la interfaz (Tarea 4)
        // y validable con archivos reales en la Tarea 5/6.
        $this->perfil($ine->organizacion_id, 'EXPORTACION', 'INE-EXPO-base', $this->columnasExportacion());
        $this->perfil($ine->organizacion_id, 'IMPORTACION', 'INE-IMPO-base', $this->columnasImportacion());
    }

    /**
     * Crea (o recrea) un perfil y su mapeo de columnas.
     * Cada columna: [nombre_origen, campo_canonico, guardar, a_extra, nota].
     */
    private function perfil(int $orgId, string $flujo, string $etiqueta, array $columnas): void
    {
        $perfil = PerfilMapeo::updateOrCreate(
            ['organizacion_id' => $orgId, 'tipo_flujo' => $flujo, 'etiqueta_version' => $etiqueta],
            ['descripcion' => "Perfil base INE ($flujo). Validar con archivos reales.", 'activo' => true]
        );

        // Reemplaza el set de columnas del perfil.
        MapeoColumna::where('perfil_id', $perfil->perfil_id)->delete();

        foreach ($columnas as [$origen, $canonico, $guardar, $extra, $nota]) {
            MapeoColumna::create([
                'perfil_id'             => $perfil->perfil_id,
                'nombre_columna_origen' => $origen,
                'campo_canonico'        => $canonico,
                'guardar'               => $guardar,
                'a_extra'               => $extra,
                'nota'                  => $nota,
            ]);
        }
    }

    private function columnasExportacion(): array
    {
        return [
            ['GESTION',     'gestion',                true,  false, null],
            ['MES',         'mes',                    true,  false, null],
            ['NANDINA',     'codigo_nandina',         true,  false, 'Partida arancelaria 10 digitos'],
            ['DESCRIPCION', 'descripcion_producto',   true,  false, null],
            ['PAIS',        'codigo_pais',            true,  false, 'Pais de destino'],
            ['ZONA',        'codigo_zona',            true,  false, null],
            ['DEPTO',       'codigo_departamento',    true,  false, 'Departamento de origen'],
            ['ADUANA',      'codigo_aduana',          true,  false, null],
            ['VIA',         'codigo_via',             true,  false, null],
            ['MEDIO',       'codigo_medio',           true,  false, null],
            ['CUCI3',       'codigo_cuci',            true,  false, 'Alias CUCI Rev.3'],
            ['GCE',         'codigo_gce',             true,  false, null],
            ['CIIU',        'codigo_ciiu',            true,  false, null],
            ['KILBRU',      'peso_bruto_kg',          true,  false, 'Alias kilos brutos'],
            ['KILNET',      'peso_neto_kg',           true,  false, null],
            ['PFINO',       'peso_fino_kg',           true,  false, 'Alias peso fino (minerales)'],
            ['FOB',         'valor_fob_usd',          true,  false, 'Valor FOB exportacion'],
        ];
    }

    private function columnasImportacion(): array
    {
        return [
            ['GESTION',     'gestion',                true,  false, null],
            ['MES',         'mes',                    true,  false, null],
            ['NANDINA',     'codigo_nandina',         true,  false, null],
            ['DESCRIPCION', 'descripcion_producto',   true,  false, null],
            ['PAIS',        'codigo_pais',            true,  false, 'Pais de origen'],
            ['ZONA',        'codigo_zona',            true,  false, null],
            ['DEPTO',       'codigo_departamento',    true,  false, 'Departamento de destino'],
            ['ADUANA',      'codigo_aduana',          true,  false, null],
            ['VIA',         'codigo_via',             true,  false, null],
            ['MEDIO',       'codigo_medio',           true,  false, null],
            ['CUCI3',       'codigo_cuci',            true,  false, 'Alias CUCI Rev.3'],
            ['GCE',         'codigo_gce',             true,  false, null],
            ['CIIU',        'codigo_ciiu',            true,  false, null],
            ['CUODE',       'codigo_cuode',           true,  false, null],
            ['TNT',         'codigo_tnt',             true,  false, null],
            ['KILBRU',      'peso_bruto_kg',          true,  false, null],
            ['KILNET',      'peso_neto_kg',           true,  false, null],
            ['CIF',         'valor_cif_frontera_usd', true,  false, 'CIF frontera'],
            ['CIFADUANA',   'valor_cif_aduana_usd',   true,  false, null],
            ['GRAVAMEN',    'gravamenes_pagados',     true,  false, null],
        ];
    }
}
