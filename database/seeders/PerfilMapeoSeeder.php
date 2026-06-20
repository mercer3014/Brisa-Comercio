<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Siembra los 15 perfiles de mapeo de columnas definidos en el blueprint
 * (sección 8.2) y sus respectivas entradas en mapeo_columna.
 *
 * Columnas de la tabla mapeo_columna:
 *   nombre_columna_origen | campo_canonico | guardar | a_extra | nota
 *
 * guardar=true  → el valor va al campo canónico (usado por ProcesadorEtl.construirHecho)
 * a_extra=true  → el valor va a atributos_extra como JSON (referencia, no resolución)
 * ambos false   → columna ignorada (descripciones redundantes)
 *
 * Para MERCOSUR/ALADI/FAOSTAT todas las columnas tienen guardar=false porque sus
 * loaders dedicados extraen directamente del iterador, sin pasar por campo_canonico.
 * Se registran en mapeo_columna solo para que DetectorPerfil pueda puntuar el perfil.
 */
class PerfilMapeoSeeder extends Seeder
{
    public function run(): void
    {
        // Idempotente: borrar perfiles existentes (y sus columnas por CASCADE).
        DB::table('perfil_mapeo')->delete();

        // ===================================================================
        // INE — EXPORTACIONES (organizacion_id = 1)
        // ===================================================================

        // Columnas base exportaciones V1 (34 cols, 1992-2017)
        $baseExp = [
            ['GESTION',    'gestion',            true,  false],
            ['MES',        'mes',                true,  false],
            ['FLUJO',      'flujo',              true,  false],
            ['NANDINA',    'codigo_nandina',     true,  false],
            ['DESNAN',     'descripcion_producto', true, false],
            ['CAP',        'codigo_capitulo',    true,  false],
            ['DESCAP',     null,                 false, false],
            ['SECC',       'codigo_seccion',     true,  false],
            ['DESSEC',     null,                 false, false],
            ['PAIS',       'codigo_pais',        true,  false],
            ['DESPAIS',    null,                 false, true,  'nombre del país (referencia)'],
            ['AREA',       'codigo_zona',        true,  false],
            ['DESAREA',    null,                 false, true,  'nombre de la zona (referencia)'],
            ['OTROS',      null,                 false, true,  'zona alternativa'],
            ['MEDI',       'codigo_medio',       true,  false],
            ['DESMEDI',    null,                 false, false],
            ['VIASAL',     'codigo_via',         true,  false],
            ['DESVIA',     null,                 false, false],
            ['DEPART',     'codigo_departamento', true, false],
            ['DESDEP',     null,                 false, false],
            ['CUCI3',      'codigo_cuci',        true,  false],
            ['DESCUCI3',   null,                 false, false],
            ['GCE3',       'codigo_gce',         true,  false],
            ['DESGCE3',    null,                 false, false],
            ['CIIUR3',     'codigo_ciiu',        true,  false],
            ['DESCIIU3',   null,                 false, false],
            ['CLACT',      null,                 false, true,  'clasificacion mayor CIIU'],
            ['CODACT2',    'codigo_grupo_actividad', true, false],
            ['DESACT2',    null,                 false, false],
            ['TNT',        'codigo_tnt',         true,  false],
            ['DESTNT',     null,                 false, false],
            ['CLTNT',      null,                 false, false],
            ['KILNET',     'peso_neto_kg',       true,  false],
            ['VALOR',      'valor_fob_usd',      true,  false],
        ];

        // V1 — estándar 1992-2017 (34 cols)
        $this->perfil([
            'organizacion_id' => 1,
            'tipo_flujo'      => 'EXPORTACION',
            'etiqueta_version' => 'INE_EXP_V1',
            'descripcion'     => 'INE Exportaciones 1992-2017 (34 columnas estándar)',
        ], $baseExp);

        // V2 — 1996, agrega IDENT al inicio (35 cols)
        $this->perfil([
            'organizacion_id' => 1,
            'tipo_flujo'      => 'EXPORTACION',
            'etiqueta_version' => 'INE_EXP_V2',
            'descripcion'     => 'INE Exportaciones 1996 (35 cols, agrega IDENT)',
        ], array_merge(
            [['IDENT', null, false, true, 'identificador adicional 1996']],
            $baseExp
        ));

        // V3 — 2018, agrega TCP + KILBRU + PFINO (37 cols)
        // TCP va entre OTROS y MEDI; KILBRU/PFINO van antes de VALOR
        $v3 = $baseExp;
        // Insertar TCP después de OTROS (índice 13)
        array_splice($v3, 14, 0, [['TCP', null, false, true, 'tipo cambio parcial 2018']]);
        // Agregar KILBRU/PFINO antes de VALOR (al final): reemplazar los 2 últimos
        array_splice($v3, -1, 0, [
            ['KILBRU', 'peso_bruto_kg', true, false],
            ['PFINO',  'peso_fino_kg',  true, false],
        ]);
        $this->perfil([
            'organizacion_id' => 1,
            'tipo_flujo'      => 'EXPORTACION',
            'etiqueta_version' => 'INE_EXP_V3',
            'descripcion'     => 'INE Exportaciones 2018 (37 cols, agrega TCP/KILBRU/PFINO)',
        ], $v3);

        // V4 — 2020-2025 (38 cols): ADUDES/DESADU al inicio, KILBRU+FINO al final
        $v4Inicio = [
            ['ADUDES', 'codigo_aduana', true,  false],
            ['DESADU', null,            false, true, 'nombre aduana (referencia)'],
        ];
        $v4Fin = [
            ['KILBRU', 'peso_bruto_kg', true, false],
            ['KILNET', 'peso_neto_kg',  true, false],
            ['FINO',   'peso_fino_kg',  true, false],
            ['VALOR',  'valor_fob_usd', true, false],
        ];
        $baseExpSinKil = array_filter($baseExp, fn ($c) => ! in_array($c[0], ['KILNET', 'VALOR']));
        $baseExpSinKil = array_values($baseExpSinKil);
        $this->perfil([
            'organizacion_id' => 1,
            'tipo_flujo'      => 'EXPORTACION',
            'etiqueta_version' => 'INE_EXP_V4',
            'descripcion'     => 'INE Exportaciones 2020-2025 (38 cols, ADUDES/FINO)',
        ], array_merge($v4Inicio, $baseExpSinKil, $v4Fin));

        // V5 — 2022 (38 cols): como V4 pero VIASAL2/DESVIA2 en lugar de VIASAL/DESVIA
        $baseExpV5 = array_map(function ($c) {
            if ($c[0] === 'VIASAL') {
                return ['VIASAL2', 'codigo_via', true, false, 'via salida 2022'];
            }
            if ($c[0] === 'DESVIA') {
                return ['DESVIA2', null, false, false];
            }
            return $c;
        }, $baseExpSinKil);
        $this->perfil([
            'organizacion_id' => 1,
            'tipo_flujo'      => 'EXPORTACION',
            'etiqueta_version' => 'INE_EXP_V5',
            'descripcion'     => 'INE Exportaciones 2022 (38 cols, VIASAL2/DESVIA2)',
        ], array_merge($v4Inicio, $baseExpV5, $v4Fin));

        // V6 — 2026 (38 cols): como V4 pero CUCIR3, GCE, CIIU3 en lugar de CUCI3, GCE3, CIIUR3
        $baseExpV6 = array_map(function ($c) {
            return match ($c[0]) {
                'CUCI3'  => ['CUCIR3', 'codigo_cuci', true, false],
                'GCE3'   => ['GCE',    'codigo_gce',  true, false],
                'CIIUR3' => ['CIIU3',  'codigo_ciiu', true, false],
                default  => $c,
            };
        }, $baseExpSinKil);
        $this->perfil([
            'organizacion_id' => 1,
            'tipo_flujo'      => 'EXPORTACION',
            'etiqueta_version' => 'INE_EXP_V6',
            'descripcion'     => 'INE Exportaciones 2026 (38 cols, CUCIR3/GCE/CIIU3)',
        ], array_merge($v4Inicio, $baseExpV6, $v4Fin));

        // ===================================================================
        // INE — IMPORTACIONES (organizacion_id = 1)
        // ===================================================================

        $baseImp = [
            ['GESTION',   'gestion',              true,  false],
            ['MES',       'mes',                  true,  false],
            ['ADUANA',    'codigo_aduana',         true,  false],
            ['DESADU',    null,                    false, false],
            ['DEPTO',     'codigo_departamento',   true,  false],
            ['DESDEPTO',  null,                    false, false],
            ['VIA',       'codigo_via',            true,  false],
            ['DESVIA',    null,                    false, false],
            ['MEDIO',     'codigo_medio',          true,  false],
            ['DESMED',    null,                    false, false],
            ['PAIS',      'codigo_pais',           true,  false],
            ['DESPAI',    null,                    false, true,  'nombre país importación'],
            ['DESZON',    null,                    false, true,  'nombre zona importación'],
            ['OTROS',     null,                    false, true,  'zona alternativa'],
            ['NANDINA',   'codigo_nandina',        true,  false],
            ['DESNAN',    'descripcion_producto',  true,  false],
            ['GCER3',     'codigo_gce',            true,  false],
            ['DESGCE',    null,                    false, false],
            ['CUODE',     'codigo_cuode',          true,  false],
            ['DESCUO',    null,                    false, false],
            ['CIIUR3',    'codigo_ciiu',           true,  false],
            ['DESCIIU',   null,                    false, false],
            ['CUCIR3',    'codigo_cuci',           true,  false],
            ['DESCUCI',   null,                    false, false],
        ];

        // V1 — 1992-2020 (29 cols, KILBRU)
        $this->perfil([
            'organizacion_id' => 1,
            'tipo_flujo'      => 'IMPORTACION',
            'etiqueta_version' => 'INE_IMP_V1',
            'descripcion'     => 'INE Importaciones 1992-2020 (29 cols, KILBRU)',
        ], array_merge($baseImp, [
            ['KILBRU', 'peso_bruto_kg',          true, false],
            ['FRO',    'valor_cif_frontera_usd',  true, false],
            ['FOB',    'valor_fob_usd',           true, false],
            ['ADU',    'valor_cif_aduana_usd',    true, false],
            ['PAG',    'gravamenes_pagados',       true, false],
        ]));

        // V2 — 2021-2026 (29 cols, KILOS en lugar de KILBRU)
        $this->perfil([
            'organizacion_id' => 1,
            'tipo_flujo'      => 'IMPORTACION',
            'etiqueta_version' => 'INE_IMP_V2',
            'descripcion'     => 'INE Importaciones 2021-2026 (29 cols, KILOS)',
        ], array_merge($baseImp, [
            ['KILOS', 'peso_bruto_kg',           true, false],
            ['FRO',   'valor_cif_frontera_usd',  true, false],
            ['FOB',   'valor_fob_usd',            true, false],
            ['ADU',   'valor_cif_aduana_usd',     true, false],
            ['PAG',   'gravamenes_pagados',        true, false],
        ]));

        // ===================================================================
        // MERCOSUR (organizacion_id = 3)
        // Columnas marcadas como a_extra=false y guardar=false; la detección
        // usa el nombre normalizado para puntuar, el loader extrae directamente.
        // ===================================================================

        $this->perfil([
            'organizacion_id' => 3,
            'tipo_flujo'      => 'MERCOSUR_PAIS',
            'etiqueta_version' => 'MERCOSUR_PAIS',
            'descripcion'     => 'MERCOSUR — Serie agregada por País/ISO 3166',
        ], [
            ['ISO 3166',             null, false, false, 'código ISO 3166 del país'],
            ['País',                 null, false, false, 'nombre del país'],
            ['Año',                  null, false, false, 'gestión/año'],
            ['Exportaciones',        null, false, false, 'exportaciones USD'],
            ['Importaciones (FOB)',  null, false, false, 'importaciones FOB USD'],
            ['Importaciones (CIF)',  null, false, false, 'importaciones CIF USD'],
            ['Volumen Exports',      null, false, false, 'volumen exportaciones kg'],
            ['Volumen Imports',      null, false, false, 'volumen importaciones kg'],
        ]);

        $this->perfil([
            'organizacion_id' => 3,
            'tipo_flujo'      => 'MERCOSUR_ITEM',
            'etiqueta_version' => 'MERCOSUR_ITEM',
            'descripcion'     => 'MERCOSUR — Serie por Producto NCM (Item)',
        ], [
            ['NCM',                  null, false, false, 'código NCM del producto'],
            ['Descripción',          null, false, false, 'descripción del producto'],
            ['Año',                  null, false, false, 'gestión/año'],
            ['Exportaciones',        null, false, false, 'exportaciones USD'],
            ['Importaciones (FOB)',  null, false, false, 'importaciones FOB USD'],
            ['Importaciones (CIF)',  null, false, false, 'importaciones CIF USD'],
            ['Volumen Exports',      null, false, false, 'volumen exportaciones kg'],
            ['Volumen Imports',      null, false, false, 'volumen importaciones kg'],
        ]);

        // ===================================================================
        // ALADI (organizacion_id = 2)
        // ===================================================================

        $this->perfil([
            'organizacion_id' => 2,
            'tipo_flujo'      => 'ALADI_RANKING',
            'etiqueta_version' => 'ALADI_RANKING',
            'descripcion'     => 'ALADI — Ranking de productos por valor (N°, ÍTEM, VALOR, %)',
        ], [
            ['N°',                null, false, false, 'posición en el ranking'],
            ['ÍTEM (Código SA)',  null, false, false, 'código SA del producto'],
            ['DESCRIPCIÓN',       null, false, false, 'descripción del producto'],
            ['VALOR (USD)',       null, false, false, 'valor en dólares USD'],
            ['% TOTAL',           null, false, false, 'porcentaje del total'],
            ['VALOR ACUM.',       null, false, false, 'valor acumulado'],
            ['% ACUM.',           null, false, false, 'porcentaje acumulado'],
        ]);

        // ===================================================================
        // FAOSTAT (organizacion_id = 4) — 4 subtipos
        // Columnas son aproximadas; el loader detecta el subtipo por cabeceras.
        // ===================================================================

        $anioCol = [['Año', null, false, false, 'año del dato']];

        $this->perfil([
            'organizacion_id' => 4,
            'tipo_flujo'      => 'FAOSTAT',
            'etiqueta_version' => 'FAOSTAT_POBLACION',
            'descripcion'     => 'FAOSTAT — Población rural y urbana',
        ], array_merge($anioCol, [
            ['Población rural',  null, false, false],
            ['Población urbana', null, false, false],
            ['Población total',  null, false, false],
        ]));

        $this->perfil([
            'organizacion_id' => 4,
            'tipo_flujo'      => 'FAOSTAT',
            'etiqueta_version' => 'FAOSTAT_FERTILIZANTES',
            'descripcion'     => 'FAOSTAT — Consumo de fertilizantes (N, P, K)',
        ], array_merge($anioCol, [
            ['Nitrógeno',  null, false, false],
            ['Fósforo',    null, false, false],
            ['Potasio',    null, false, false],
            ['Total',      null, false, false],
        ]));

        $this->perfil([
            'organizacion_id' => 4,
            'tipo_flujo'      => 'FAOSTAT',
            'etiqueta_version' => 'FAOSTAT_SUBALIMENTACION',
            'descripcion'     => 'FAOSTAT — Prevalencia de subalimentación',
        ], array_merge([['Período', null, false, false]], [
            ['Prevalencia (%)', null, false, false],
            ['Número (millones)', null, false, false],
        ]));

        $this->perfil([
            'organizacion_id' => 4,
            'tipo_flujo'      => 'FAOSTAT',
            'etiqueta_version' => 'FAOSTAT_CEREALES',
            'descripcion'     => 'FAOSTAT — Producción de cereales',
        ], array_merge($anioCol, [
            ['Producción (toneladas)', null, false, false],
            ['Área cosechada (ha)',    null, false, false],
            ['Rendimiento (kg/ha)',    null, false, false],
        ]));
    }

    private function perfil(array $attrs, array $cols): void
    {
        $perfilId = DB::table('perfil_mapeo')->insertGetId(
            array_merge($attrs, ['activo' => true]),
            'perfil_id'
        );

        foreach ($cols as $c) {
            DB::table('mapeo_columna')->insert([
                'perfil_id'              => $perfilId,
                'nombre_columna_origen'  => $c[0],
                'campo_canonico'         => $c[1] ?? null,
                'guardar'                => $c[2] ?? false,
                'a_extra'                => $c[3] ?? false,
                'nota'                   => $c[4] ?? null,
            ]);
        }
    }
}
