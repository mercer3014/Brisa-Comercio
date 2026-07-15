<?php

namespace App\Servicios;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Servicio central de la API pública de charts (Fase 3).
 *
 * Devuelve SIEMPRE estructuras listas para ApexCharts:
 *   { categorías: [...], series: [ {name, data}, ... ], meta: {...} }
 * o, para rankings/filtros, arreglos simples documentados por método.
 *
 * Fuentes de datos REALES (la BD es la fuente de verdad):
 *  - INE (org 1):      operacion_comercio_exterior + vistas mv_resumen_anual_ine / mv_resumen_mensual_ine
 *  - MERCOSUR (org 3): mv_resumen_mercosur_zona + serie_comercio_zona + serie_comercio_producto_zona
 *  - ALADI (org 2):    ranking_comercio
 *  - FAOSTAT (org 4):  serie_indicador_agricola (aún sin datos → estructura vacía documentada)
 *
 * Convención INE de flujos: el valor de EXPORTACION vive en valor_fob_usd y el de
 * IMPORTACION en valor_cif_frontera_usd (así lo puebla el ETL).
 */
class PortalApi
{
    public const ORG_INE      = 1;
    public const ORG_ALADI    = 2;
    public const ORG_MERCOSUR = 3;
    public const ORG_FAOSTAT  = 4;

    private string $valorExpo = 'COALESCE(o.valor_fob_usd,0)';
    private string $valorImpo = 'COALESCE(o.valor_cif_frontera_usd,0)';

    // =========================================================================
    //  Helpers de formato
    // =========================================================================

    /** Envoltura estándar para ApexCharts. */
    public function serie(array $categorias, array $series, array $meta = []): array
    {
        return [
            'categorias' => array_values($categorias),
            'series'     => array_values($series),
            'meta'       => array_merge([
                'unidad'               => 'USD',
                'ultima_actualizacion' => now()->toIso8601String(),
            ], $meta),
        ];
    }

    /** Gestión (año) más reciente con datos para una organización. */
    public function gestionReciente(int $orgId): ?int
    {
        $g = match ($orgId) {
            self::ORG_MERCOSUR => DB::table('mv_resumen_mercosur_zona')->max('gestion'),
            self::ORG_ALADI    => DB::table('ranking_comercio')->max('gestion'),
            self::ORG_FAOSTAT  => DB::table('serie_indicador_agricola')->max('gestion'),
            default            => DB::table('mv_resumen_anual_ine')->max('gestion'),
        };

        return $g !== null ? (int) $g : null;
    }

    /**
     * Gestión "preferida" para mostrar por defecto: para INE es el último año
     * CON importaciones (para que exp/imp/balanza tengan sentido al entrar);
     * para el resto, la más reciente con datos.
     */
    public function gestionPreferida(int $orgId): ?int
    {
        if ($orgId === self::ORG_INE) {
            $anios = $this->aniosConImportacionIne();
            if (! empty($anios)) {
                return (int) end($anios);
            }
        }

        return $this->gestionReciente($orgId);
    }

    // =========================================================================
    //  Base INE
    // =========================================================================

    private function baseIne(?int $gestion = null): Builder
    {
        $q = DB::table('operacion_comercio_exterior as o')
            ->join('tiempo as t', 't.tiempo_id', '=', 'o.tiempo_id')
            ->where('o.organizacion_id', self::ORG_INE);

        if ($gestion) {
            // o.gestion es una copia indexada de t.gestion (índice idx_oce_org_gestion):
            // filtrar aquí, en vez de en t.gestion, deja que Postgres descarte casi toda
            // la tabla (4M+ filas) antes de llegar al JOIN con tiempo.
            $q->where('o.gestion', $gestion);
        }

        return $q;
    }

    // =========================================================================
    //  KPIs
    // =========================================================================

    /**
     * KPIs de una organización para su último periodo (o el indicado),
     * con variación respecto al año anterior.
     */
    public function kpis(int $orgId, ?int $gestion = null): array
    {
        $gestion ??= $this->gestionReciente($orgId);

        return match ($orgId) {
            self::ORG_MERCOSUR => $this->kpisMercosur($gestion),
            self::ORG_ALADI    => $this->kpisAladi($gestion),
            self::ORG_FAOSTAT  => $this->kpisFaostat($gestion),
            default            => $this->kpisIne($gestion),
        };
    }

    private function kpisIne(?int $gestion): array
    {
        $org = DB::table('organizacion')->where('organizacion_id', self::ORG_INE)->first();

        $fila = function (?int $g) {
            if (! $g) {
                return null;
            }

            return $this->baseIne($g)
                ->selectRaw("SUM({$this->valorExpo}) as expo")
                ->selectRaw("SUM({$this->valorImpo}) as impo")
                ->selectRaw('COUNT(*) as operaciones')
                ->first();
        };

        $act = $fila($gestion);
        $ant = $fila($gestion ? $gestion - 1 : null);

        $expo = (float) ($act->expo ?? 0);
        $impo = (float) ($act->impo ?? 0);
        $expoA = (float) ($ant->expo ?? 0);
        $impoA = (float) ($ant->impo ?? 0);

        return [
            'organizacion'        => $org?->sigla,
            'gestion'             => $gestion,
            'gestion_anterior'    => $gestion ? $gestion - 1 : null,
            'exportaciones'       => $expo,
            'importaciones'       => $impo,
            'balanza_comercial'   => $expo - $impo,
            'operaciones'         => (int) ($act->operaciones ?? 0),
            'variacion_exp_pct'   => $this->variacion($expo, $expoA),
            'variacion_imp_pct'   => $this->variacion($impo, $impoA),
            'unidad'              => 'USD',
            'fuente'              => 'INE',
            'hay_datos'           => $act !== null && ((int) ($act->operaciones ?? 0)) > 0,
        ];
    }

    private function kpisMercosur(?int $gestion): array
    {
        $fila = fn (?int $g) => $g ? DB::table('mv_resumen_mercosur_zona')->where('gestion', $g)
            ->selectRaw('SUM(total_exportaciones) as expo')
            ->selectRaw('SUM(total_importaciones) as impo')
            ->selectRaw('SUM(paises) as paises')
            ->first() : null;

        $act = $fila($gestion);
        $ant = $fila($gestion ? $gestion - 1 : null);

        $expo = (float) ($act->expo ?? 0);
        $impo = (float) ($act->impo ?? 0);

        return [
            'organizacion'      => 'MERCOSUR',
            'gestion'           => $gestion,
            'gestion_anterior'  => $gestion ? $gestion - 1 : null,
            'exportaciones'     => $expo,
            'importaciones'     => $impo,
            'balanza_comercial' => $expo - $impo,
            'operaciones'       => (int) ($act->paises ?? 0),
            'variacion_exp_pct' => $this->variacion($expo, (float) ($ant->expo ?? 0)),
            'variacion_imp_pct' => $this->variacion($impo, (float) ($ant->impo ?? 0)),
            'unidad'            => 'USD',
            'fuente'            => 'MERCOSUR',
            'hay_datos'         => $act !== null && $expo + $impo > 0,
        ];
    }

    private function kpisAladi(?int $gestion): array
    {
        $act = $gestion ? $this->totalesAladi($gestion) : collect();
        $ant = $gestion ? $this->totalesAladi($gestion - 1) : collect();

        $expo = (float) $act->where('flujo', '1')->sum('total');
        $impo = (float) $act->where('flujo', '2')->sum('total');
        $expoAnt = (float) $ant->where('flujo', '1')->sum('total');
        $impoAnt = (float) $ant->where('flujo', '2')->sum('total');

        $items = (int) DB::table('ranking_comercio')
            ->where('organizacion_id', self::ORG_ALADI)
            ->when($gestion, fn ($q) => $q->where('gestion', $gestion))
            ->count();

        return [
            'organizacion'      => 'ALADI',
            'gestion'           => $gestion,
            'gestion_anterior'  => $gestion ? $gestion - 1 : null,
            'exportaciones'     => $expo,
            'importaciones'     => $impo,
            'balanza_comercial' => $expo - $impo,
            'valor_total'       => $expo + $impo,
            'items_ranking'     => $items,
            'operaciones'       => $items,
            'variacion_exp_pct' => $this->variacion($expo, $expoAnt),
            'variacion_imp_pct' => $this->variacion($impo, $impoAnt),
            'unidad'            => 'USD',
            'fuente'            => 'ALADI',
            'hay_datos'         => $expo + $impo > 0,
        ];
    }

    /**
     * Totales ALADI derivados por (país miembro, flujo): los rankings solo
     * traen el top-50 de cada país, pero incluyen el % acumulado sobre el
     * total del país, del que se deriva el total real
     * (total = suma_top50 * 100 / pct_acumulado).
     */
    private function totalesAladi(?int $gestion, ?int $paisId = null): \Illuminate\Support\Collection
    {
        return DB::table('ranking_comercio as rc')
            ->leftJoin('flujo_comercial as fl', 'fl.flujo_id', '=', 'rc.flujo_id')
            ->leftJoin('pais as pa', 'pa.pais_id', '=', 'rc.pais_reportante_id')
            ->where('rc.organizacion_id', self::ORG_ALADI)
            ->when($gestion, fn ($q) => $q->where('rc.gestion', $gestion))
            ->when($paisId, fn ($q) => $q->where('rc.pais_reportante_id', $paisId))
            ->selectRaw('rc.pais_reportante_id as pais_id, pa.nombre as pais, fl.codigo_flujo as flujo, rc.gestion')
            ->selectRaw('SUM(rc.valor) as suma_top')
            ->selectRaw('MAX(rc.porcentaje_acumulado) as pct')
            ->groupBy('rc.pais_reportante_id', 'pa.nombre', 'fl.codigo_flujo', 'rc.gestion')
            ->get()
            ->map(function ($r) {
                $suma = (float) $r->suma_top;
                $pct  = (float) $r->pct;
                $r->total = $pct > 0 ? $suma * 100 / $pct : $suma;

                return $r;
            });
    }

    private function kpisFaostat(?int $gestion): array
    {
        $base = DB::table('serie_indicador_agricola')->where('organizacion_id', self::ORG_FAOSTAT);

        $tot = (clone $base)
            ->selectRaw('COUNT(*) as series')
            ->selectRaw('COUNT(DISTINCT pais_id) as paises')
            ->selectRaw('COUNT(DISTINCT producto_codigo_externo_id) as productos')
            ->selectRaw('MIN(gestion) as anio_min')
            ->selectRaw('MAX(gestion) as anio_max')
            ->first();

        return [
            'organizacion' => 'FAOSTAT',
            'gestion'      => $gestion ?? ($tot->anio_max !== null ? (int) $tot->anio_max : null),
            'series'       => (int) $tot->series,
            'paises'       => (int) $tot->paises,
            'productos'    => (int) $tot->productos,
            'anio_min'     => $tot->anio_min !== null ? (int) $tot->anio_min : null,
            'anio_max'     => $tot->anio_max !== null ? (int) $tot->anio_max : null,
            'unidad'       => 'índice (2014-2016 = 100)',
            'fuente'       => 'FAOSTAT',
            'hay_datos'    => (int) $tot->series > 0,
        ];
    }

    // =========================================================================
    //  Charts INE
    // =========================================================================

    /** Comercio mensual: líneas exp/imp + barras saldo. Usa mv_resumen_mensual_ine. */
    public function comercioMensual(?int $gestion): array
    {
        $gestion ??= $this->gestionReciente(self::ORG_INE);

        $rows = DB::table('mv_resumen_mensual_ine')
            ->when($gestion, fn ($q) => $q->where('gestion', $gestion))
            ->orderBy('gestion')->orderBy('mes')
            ->get();

        // Acumular por periodo: la MV separa por tipo_operacion (Exportación/Importacion).
        $meses = ['', 'Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
        $cat = [];
        $exp = [];
        $imp = [];
        $saldo = [];
        $idx = [];

        foreach ($rows as $r) {
            $key = $r->gestion . '-' . str_pad((string) $r->mes, 2, '0', STR_PAD_LEFT);
            if (! isset($idx[$key])) {
                $idx[$key] = count($cat);
                $cat[] = ($meses[$r->mes] ?? $r->mes) . '-' . substr((string) $r->gestion, 2);
                $exp[$idx[$key]] = 0.0;
                $imp[$idx[$key]] = 0.0;
            }
            $i = $idx[$key];
            $esExpo = stripos($r->tipo_operacion, 'export') !== false;
            if ($esExpo) {
                $exp[$i] += (float) $r->total_fob_usd;
            } else {
                $imp[$i] += (float) $r->total_cif_usd;
            }
        }

        foreach ($cat as $i => $_) {
            $saldo[$i] = ($exp[$i] ?? 0) - ($imp[$i] ?? 0);
        }

        return $this->serie($cat, [
            ['name' => 'Exportaciones', 'type' => 'line', 'data' => array_map('round', $exp)],
            ['name' => 'Importaciones', 'type' => 'line', 'data' => array_map('round', $imp)],
            ['name' => 'Saldo comercial', 'type' => 'column', 'data' => array_map('round', $saldo)],
        ], ['fuente' => 'INE', 'gestion' => $gestion]);
    }

    /** Evolución anual histórica (exp/imp/balanza). Usa mv_resumen_anual_ine. */
    public function evolucionAnual(): array
    {
        $rows = DB::table('mv_resumen_anual_ine')->orderBy('gestion')->get();

        $cat = [];
        $exp = [];
        $imp = [];
        $bal = [];
        $idx = [];

        foreach ($rows as $r) {
            $key = (int) $r->gestion;
            if (! isset($idx[$key])) {
                $idx[$key] = count($cat);
                $cat[] = (string) $key;
                $exp[$idx[$key]] = 0.0;
                $imp[$idx[$key]] = 0.0;
            }
            $i = $idx[$key];
            if (stripos($r->tipo_operacion, 'export') !== false) {
                $exp[$i] += (float) $r->total_fob_usd;
            } else {
                $imp[$i] += (float) $r->total_cif_usd;
            }
        }
        foreach ($cat as $i => $_) {
            $bal[$i] = ($exp[$i] ?? 0) - ($imp[$i] ?? 0);
        }

        return $this->serie($cat, [
            ['name' => 'Exportaciones', 'data' => array_map('round', $exp)],
            ['name' => 'Importaciones', 'data' => array_map('round', $imp)],
            ['name' => 'Balanza', 'data' => array_map('round', $bal)],
        ], ['fuente' => 'INE']);
    }

    /** Top N productos por valor (param flujo: exp|imp, gestión, limit). */
    public function topProductos(string $flujo, ?int $gestion, int $limit): array
    {
        $gestion ??= $this->gestionReciente(self::ORG_INE);
        $valor = $this->exprFlujo($flujo);

        $rows = $this->baseIne($gestion)
            ->join('producto as p', 'p.producto_id', '=', 'o.producto_id')
            ->selectRaw('p.codigo_nandina, p.descripcion as label')
            ->selectRaw("SUM($valor) as valor")
            ->groupBy('p.codigo_nandina', 'p.descripcion')
            ->orderByDesc('valor')->limit($limit)
            ->get();

        $serie = $this->serie(
            $rows->map(fn ($r) => mb_strimwidth((string) $r->label, 0, 45, '…'))->all(),
            [['name' => 'Valor', 'data' => $rows->map(fn ($r) => round((float) $r->valor))->all()]],
            ['fuente' => 'INE', 'flujo' => $flujo, 'gestion' => $gestion]
        );

        return $serie;
    }

    /** Top N países por valor (param flujo, gestión, limit). */
    public function topPaises(string $flujo, ?int $gestion, int $limit): array
    {
        $gestion ??= $this->gestionReciente(self::ORG_INE);
        $valor = $this->exprFlujo($flujo);

        $rows = $this->baseIne($gestion)
            ->join('pais as pa', 'pa.pais_id', '=', 'o.pais_id')
            ->selectRaw('pa.nombre as label, pa.iso_alpha3 as iso_alpha3')
            ->selectRaw("SUM($valor) as valor")
            ->groupBy('pa.nombre', 'pa.iso_alpha3')
            ->orderByDesc('valor')->limit($limit)
            ->get();

        $serie = $this->serie(
            $rows->pluck('label')->all(),
            [['name' => 'Valor', 'data' => $rows->map(fn ($r) => round((float) $r->valor))->all()]],
            ['fuente' => 'INE', 'flujo' => $flujo, 'gestion' => $gestion]
        );

        $serie['items'] = $rows->map(fn ($r) => [
            'label' => $r->label,
            'iso'   => $r->iso_alpha3,
            'valor' => round((float) $r->valor),
        ])->all();

        return $serie;
    }

    /** Top N departamentos exportadores. */
    public function topDepartamentos(?int $gestion, int $limit): array
    {
        $gestion ??= $this->gestionReciente(self::ORG_INE);

        $rows = $this->baseIne($gestion)
            ->join('departamento as d', 'd.departamento_id', '=', 'o.departamento_id')
            ->selectRaw('d.nombre as label')
            ->selectRaw("SUM({$this->valorExpo}) as valor")
            ->groupBy('d.nombre')
            ->orderByDesc('valor')->limit($limit)
            ->get();

        $serie = $this->serie(
            $rows->pluck('label')->all(),
            [['name' => 'Exportaciones', 'data' => $rows->map(fn ($r) => round((float) $r->valor))->all()]],
            ['fuente' => 'INE', 'flujo' => 'exp', 'gestion' => $gestion]
        );

        $serie['items'] = $rows->map(fn ($r) => [
            'label' => $r->label,
            'valor' => round((float) $r->valor),
        ])->all();

        return $serie;
    }

    /** Sección arancelaria para treemap (label + value). */
    public function seccionArancelaria(string $flujo, ?int $gestion): array
    {
        $gestion ??= $this->gestionReciente(self::ORG_INE);
        $valor = $this->exprFlujo($flujo);

        $rows = $this->baseIne($gestion)
            ->join('producto as p', 'p.producto_id', '=', 'o.producto_id')
            ->join('capitulo_arancelario as c', 'c.capitulo_id', '=', 'p.capitulo_id')
            ->join('seccion_arancelaria as s', 's.seccion_id', '=', 'c.seccion_id')
            ->selectRaw('s.descripcion as label')
            ->selectRaw("SUM($valor) as valor")
            ->groupBy('s.descripcion')
            ->orderByDesc('valor')
            ->get();

        // Treemap: una serie con data [{x,y}]
        return $this->serie(
            $rows->pluck('label')->all(),
            [['name' => 'Secciones', 'data' => $rows->map(fn ($r) => [
                'x' => mb_strimwidth((string) $r->label, 0, 40, '…'),
                'y' => round((float) $r->valor),
            ])->all()]],
            ['fuente' => 'INE', 'flujo' => $flujo, 'gestion' => $gestion, 'tipo' => 'treemap']
        );
    }

    /** Distribución por medio de transporte y por vía (donut). */
    public function transporte(?int $gestion): array
    {
        $gestion ??= $this->gestionReciente(self::ORG_INE);
        $valExpImp = "{$this->valorExpo} + {$this->valorImpo}";

        $medios = $this->baseIne($gestion)
            ->join('medio_transporte as m', 'm.medio_id', '=', 'o.medio_id')
            ->selectRaw('m.descripcion as label')
            ->selectRaw("SUM($valExpImp) as valor")
            ->groupBy('m.descripcion')->orderByDesc('valor')->get();

        $vias = $this->baseIne($gestion)
            ->join('via_comercio as v', 'v.via_id', '=', 'o.via_id')
            ->selectRaw('v.descripcion as label')
            ->selectRaw("SUM($valExpImp) as valor")
            ->groupBy('v.descripcion')->orderByDesc('valor')->get();

        return [
            'medio' => $this->serie(
                $medios->pluck('label')->all(),
                [['name' => 'Valor', 'data' => $medios->map(fn ($r) => round((float) $r->valor))->all()]],
                ['fuente' => 'INE', 'gestion' => $gestion, 'dimension' => 'medio_transporte']
            ),
            'via' => $this->serie(
                $vias->pluck('label')->all(),
                [['name' => 'Valor', 'data' => $vias->map(fn ($r) => round((float) $r->valor))->all()]],
                ['fuente' => 'INE', 'gestion' => $gestion, 'dimension' => 'via_comercio']
            ),
        ];
    }

    /** Agrupación de medio_transporte en las 4 vías que muestra el portal público. */
    private const GRUPOS_VIA = [
        'maritimo'  => 'Marítimo',
        'terrestre' => 'Terrestre',
        'aereo'     => 'Aéreo',
        'otros'     => 'Otros',
    ];

    /**
     * Bolivia no tiene costa: lo que el publico entiende como "comercio
     * maritimo" queda registrado en medio_transporte como intermodal
     * (CARRETERO-MARITIMO, FERROVIARIO-MARITIMO: camion/tren hasta un puerto
     * vecino y de ahi por barco). CARRETERA/FERROVIARIA sin ese tramo son
     * "terrestre" puro; AEREA es aparte; el resto (fluvial, lacustre, postal,
     * ductos, courier) cae en "otros".
     */
    private function grupoVia(string $medio): string
    {
        $m = mb_strtoupper($medio);

        return match (true) {
            str_contains($m, 'AEREA')    => 'aereo',
            str_contains($m, 'MARITIMO') => 'maritimo',
            in_array($m, ['CARRETERA', 'FERROVIARIA'], true) => 'terrestre',
            default => 'otros',
        };
    }

    /**
     * Exportaciones e importaciones (separadas, en USD) agrupadas por vía de
     * transporte: lo que muestran los paneles Marítimo/Terrestre/Aéreo/Otros
     * de la portada publica.
     *
     * Solo el microdato del INE guarda medio de transporte por operación;
     * ALADI/MERCOSUR/FAOSTAT llegan pre-agregados por país/zona/producto y no
     * tienen esa columna en ninguna parte (verificado en su esquema). Para
     * esas organizaciones se devuelve la estructura vacía documentada en vez
     * de inventar un desglose que la fuente no publica.
     */
    public function comercioPorVia(?int $gestion, int $orgId = self::ORG_INE): array
    {
        $org = DB::table('organizacion')->where('organizacion_id', $orgId)->first();

        if ($orgId !== self::ORG_INE) {
            return [
                'categorias' => [],
                'series'     => [],
                'items'      => [],
                'meta'       => [
                    'fuente'     => $org?->sigla ?? 'N/D',
                    'gestion'    => $gestion,
                    'unidad'     => 'USD',
                    'disponible' => false,
                    'nota'       => ($org?->sigla ?? 'Esta organización')." no publica desglose por vía de transporte (solo llegan totales por país/zona/producto).",
                ],
            ];
        }

        $gestion ??= $this->gestionReciente(self::ORG_INE);

        $rows = $this->baseIne($gestion)
            ->join('medio_transporte as m', 'm.medio_id', '=', 'o.medio_id')
            ->selectRaw('m.descripcion as medio')
            ->selectRaw("SUM({$this->valorExpo}) as expo")
            ->selectRaw("SUM({$this->valorImpo}) as impo")
            ->groupBy('m.descripcion')
            ->get();

        $totales = array_fill_keys(array_keys(self::GRUPOS_VIA), ['expo' => 0.0, 'impo' => 0.0]);
        foreach ($rows as $r) {
            $grupo = $this->grupoVia((string) $r->medio);
            $totales[$grupo]['expo'] += (float) $r->expo;
            $totales[$grupo]['impo'] += (float) $r->impo;
        }

        $items = [];
        foreach (self::GRUPOS_VIA as $clave => $etiqueta) {
            $expo = $totales[$clave]['expo'];
            $impo = $totales[$clave]['impo'];
            $items[] = [
                'clave'   => $clave,
                'label'   => $etiqueta,
                'expo'    => round($expo),
                'impo'    => round($impo),
                'balanza' => round($expo - $impo),
                'total'   => round($expo + $impo),
            ];
        }

        return [
            'categorias' => array_column($items, 'label'),
            'series'     => [
                ['name' => 'Exportaciones', 'data' => array_column($items, 'expo')],
                ['name' => 'Importaciones', 'data' => array_column($items, 'impo')],
            ],
            'items' => $items,
            'meta'  => [
                'fuente'     => 'INE',
                'gestion'    => $gestion,
                'unidad'     => 'USD',
                'disponible' => true,
                'ultima_actualizacion' => now()->toIso8601String(),
            ],
        ];
    }

    /** Tradicional vs No tradicional por año (clasificacion_tnt). */
    public function tntEvolucion(): array
    {
        $rows = DB::table('operacion_comercio_exterior as o')
            ->join('tiempo as t', 't.tiempo_id', '=', 'o.tiempo_id')
            ->join('clasificacion_tnt as tn', 'tn.tnt_id', '=', 'o.tnt_id')
            ->where('o.organizacion_id', self::ORG_INE)
            ->selectRaw('t.gestion, tn.descripcion as clase')
            ->selectRaw("SUM({$this->valorExpo}) as valor")
            ->groupBy('t.gestion', 'tn.descripcion')
            ->orderBy('t.gestion')
            ->get();

        $cat = $rows->pluck('gestion')->unique()->sort()->values()->map(fn ($g) => (string) $g)->all();
        $clases = $rows->pluck('clase')->unique()->values();
        $catIdx = array_flip($cat);

        $series = [];
        foreach ($clases as $clase) {
            $data = array_fill(0, count($cat), 0.0);
            foreach ($rows->where('clase', $clase) as $r) {
                $data[$catIdx[(string) $r->gestion]] = round((float) $r->valor);
            }
            $series[] = ['name' => $clase, 'data' => $data];
        }

        return $this->serie($cat, $series, ['fuente' => 'INE', 'flujo' => 'exp']);
    }

    // =========================================================================
    //  Charts MERCOSUR
    // =========================================================================

    /** Comercio MERCOSUR por zona (mv_resumen_mercosur_zona). */
    public function mercosurZona(?int $gestion): array
    {
        $gestion ??= $this->gestionReciente(self::ORG_MERCOSUR);

        $rows = DB::table('mv_resumen_mercosur_zona')
            ->when($gestion, fn ($q) => $q->where('gestion', $gestion))
            ->orderByDesc('total_exportaciones')->get();

        return $this->serie(
            $rows->pluck('zona_nombre')->all(),
            [
                ['name' => 'Exportaciones', 'data' => $rows->map(fn ($r) => round((float) $r->total_exportaciones))->all()],
                ['name' => 'Importaciones', 'data' => $rows->map(fn ($r) => round((float) $r->total_importaciones))->all()],
            ],
            ['fuente' => 'MERCOSUR', 'gestion' => $gestion]
        );
    }

    /** Balanza por zona (mv_resumen_mercosur_zona). */
    public function mercosurBalanza(?int $gestion): array
    {
        $gestion ??= $this->gestionReciente(self::ORG_MERCOSUR);

        $rows = DB::table('mv_resumen_mercosur_zona')
            ->when($gestion, fn ($q) => $q->where('gestion', $gestion))
            ->orderByDesc('balanza')->get();

        return $this->serie(
            $rows->pluck('zona_nombre')->all(),
            [['name' => 'Balanza', 'data' => $rows->map(fn ($r) => round((float) $r->balanza))->all()]],
            ['fuente' => 'MERCOSUR', 'gestion' => $gestion]
        );
    }

    /** Top productos NCM (serie_comercio_producto_zona). */
    public function mercosurProductos(?int $gestion, int $limit): array
    {
        $gestion ??= $this->gestionReciente(self::ORG_MERCOSUR);

        $rows = DB::table('serie_comercio_producto_zona')
            ->when($gestion, fn ($q) => $q->where('gestion', $gestion))
            ->selectRaw('ncm_codigo, MAX(ncm_descripcion) as descripcion')
            ->selectRaw('SUM(COALESCE(exportaciones_usd,0)) as valor')
            ->groupBy('ncm_codigo')
            ->orderByDesc('valor')->limit($limit)
            ->get();

        return $this->serie(
            $rows->map(fn ($r) => $r->ncm_codigo . ' · ' . mb_strimwidth((string) $r->descripcion, 0, 35, '…'))->all(),
            [['name' => 'Exportaciones', 'data' => $rows->map(fn ($r) => round((float) $r->valor))->all()]],
            ['fuente' => 'MERCOSUR', 'gestion' => $gestion]
        );
    }

    /** Comercio por país dentro de una zona (serie_comercio_zona). */
    public function mercosurPaises(?int $gestion, ?int $zonaId, int $limit): array
    {
        $gestion ??= $this->gestionReciente(self::ORG_MERCOSUR);

        $rows = DB::table('serie_comercio_zona')
            ->when($gestion, fn ($q) => $q->where('gestion', $gestion))
            ->when($zonaId, fn ($q) => $q->where('zona_id', $zonaId))
            ->selectRaw('COALESCE(pais_nombre_original, pais_iso3166) as label')
            ->selectRaw('SUM(COALESCE(exportaciones_usd,0)) as expo')
            ->selectRaw('SUM(COALESCE(importaciones_fob_usd,0)) as impo')
            ->groupBy('label')
            ->orderByDesc('expo')->limit($limit)
            ->get();

        return $this->serie(
            $rows->pluck('label')->all(),
            [
                ['name' => 'Exportaciones', 'data' => $rows->map(fn ($r) => round((float) $r->expo))->all()],
                ['name' => 'Importaciones', 'data' => $rows->map(fn ($r) => round((float) $r->impo))->all()],
            ],
            ['fuente' => 'MERCOSUR', 'gestion' => $gestion, 'zona_id' => $zonaId]
        );
    }

    // =========================================================================
    //  ALADI
    // =========================================================================

    /** Ranking de productos ALADI con % acumulado (ranking_comercio). */
    public function aladiRanking(?int $gestion, ?string $flujo, int $limit, ?int $paisId = null): array
    {
        $gestion ??= $this->gestionReciente(self::ORG_ALADI);

        $flujoId = null;
        if ($flujo === 'exp' || $flujo === 'imp') {
            $codigo = $flujo === 'exp' ? '1' : '2';
            $flujoId = DB::table('flujo_comercial')->where('codigo_flujo', $codigo)->value('flujo_id');
        }

        $filtrado = fn () => DB::table('ranking_comercio')
            ->where('organizacion_id', self::ORG_ALADI)
            ->when($gestion, fn ($qq) => $qq->where('gestion', $gestion))
            ->when($flujoId, fn ($qq) => $qq->where('flujo_id', $flujoId))
            ->when($paisId, fn ($qq) => $qq->where('pais_reportante_id', $paisId));

        $rows = $filtrado()->orderByDesc('valor')->limit($limit)->get();

        $total = (float) $filtrado()->sum('valor');

        $acum = 0.0;
        $items = $rows->map(function ($r) use ($total, &$acum) {
            $valor = (float) $r->valor;
            $pct = $total > 0 ? $valor / $total * 100 : 0;
            $acum += $pct;

            return [
                'item_codigo'    => $r->item_codigo,
                'descripcion'    => $r->descripcion,
                'valor'          => round($valor),
                'porcentaje'     => round($pct, 2),
                'acumulado'      => round($acum, 2),
                'es_confidencial' => (bool) $r->es_confidencial,
            ];
        });

        return [
            'categorias' => $items->pluck('item_codigo')->all(),
            'series'     => [
                ['name' => 'Valor', 'type' => 'bar', 'data' => $items->pluck('valor')->all()],
                ['name' => '% Acumulado', 'type' => 'line', 'data' => $items->pluck('acumulado')->all()],
            ],
            'items' => $items->all(),
            'meta'  => [
                'fuente'   => 'ALADI',
                'gestion'  => $gestion,
                'flujo'    => $flujo ?? 'todos',
                'pais_id'  => $paisId,
                'unidad'   => 'USD',
                'total'    => round($total),
                'ultima_actualizacion' => now()->toIso8601String(),
            ],
        ];
    }

    /**
     * Evolución anual ALADI: exportaciones e importaciones derivadas de los
     * rankings (total = suma_top50 * 100 / pct_acumulado), del bloque completo
     * o de un país miembro.
     */
    public function aladiEvolucion(?int $paisId = null): array
    {
        $porAnio = $this->totalesAladi(null, $paisId)
            ->groupBy('gestion')
            ->map(fn ($grupo, $g) => [
                'gestion' => (int) $g,
                'expo'    => (float) $grupo->where('flujo', '1')->sum('total'),
                'impo'    => (float) $grupo->where('flujo', '2')->sum('total'),
            ])
            ->sortKeys()
            ->values();

        return $this->serie(
            $porAnio->pluck('gestion')->all(),
            [
                ['name' => 'Exportaciones', 'type' => 'line', 'data' => $porAnio->map(fn ($r) => round($r['expo']))->all()],
                ['name' => 'Importaciones', 'type' => 'line', 'data' => $porAnio->map(fn ($r) => round($r['impo']))->all()],
                ['name' => 'Balanza', 'type' => 'column', 'data' => $porAnio->map(fn ($r) => round($r['expo'] - $r['impo']))->all()],
            ],
            ['fuente' => 'ALADI', 'pais_id' => $paisId]
        );
    }

    /**
     * Comercio por país miembro de ALADI en una gestión (totales derivados).
     * Incluye en meta la lista de países para el selector del panel.
     */
    public function aladiPaises(?int $gestion = null, ?string $flujo = null): array
    {
        $gestion ??= $this->gestionReciente(self::ORG_ALADI);

        $tot = $this->totalesAladi($gestion);

        $porPais = $tot
            ->groupBy('pais_id')
            ->map(fn ($grupo) => [
                'pais_id' => (int) $grupo->first()->pais_id,
                'label'   => $grupo->first()->pais ?? '—',
                'expo'    => (float) $grupo->where('flujo', '1')->sum('total'),
                'impo'    => (float) $grupo->where('flujo', '2')->sum('total'),
            ])
            ->sortByDesc(fn ($r) => $flujo === 'imp' ? $r['impo'] : ($flujo === 'exp' ? $r['expo'] : $r['expo'] + $r['impo']))
            ->values();

        $paises = DB::table('pais as pa')
            ->join('fuente_datos as f', 'f.fuente_id', '=', 'pa.fuente_id')
            ->where('f.organizacion_id', self::ORG_ALADI)
            ->orderBy('pa.nombre')
            ->get(['pa.pais_id', 'pa.nombre'])
            ->map(fn ($p) => ['id' => (int) $p->pais_id, 'nombre' => $p->nombre])
            ->all();

        return $this->serie(
            $porPais->pluck('label')->all(),
            [
                ['name' => 'Exportaciones', 'data' => $porPais->map(fn ($r) => round($r['expo']))->all()],
                ['name' => 'Importaciones', 'data' => $porPais->map(fn ($r) => round($r['impo']))->all()],
            ],
            ['fuente' => 'ALADI', 'gestion' => $gestion, 'flujo' => $flujo ?? 'todos', 'paises' => $paises]
        );
    }

    /**
     * Ranking dinámico INE por dimensión (productos|países|departamentos),
     * con posición, valor, % del total y % acumulado.
     */
    public function rankingDinamico(string $tipo, string $flujo, ?int $gestion, int $limit): array
    {
        $gestion ??= $this->gestionReciente(self::ORG_INE);
        $valor = $this->exprFlujo($flujo);

        $cfg = match ($tipo) {
            'paises'        => ['tabla' => 'pais as x', 'pk' => 'x.pais_id', 'fk' => 'o.pais_id', 'label' => 'x.nombre'],
            'departamentos' => ['tabla' => 'departamento as x', 'pk' => 'x.departamento_id', 'fk' => 'o.departamento_id', 'label' => 'x.nombre'],
            'aduanas'       => ['tabla' => 'aduana as x', 'pk' => 'x.aduana_id', 'fk' => 'o.aduana_id', 'label' => 'x.descripcion'],
            default         => ['tabla' => 'producto as x', 'pk' => 'x.producto_id', 'fk' => 'o.producto_id', 'label' => 'x.descripcion'],
        };

        $total = (float) $this->baseIne($gestion)->selectRaw("SUM($valor) as v")->value('v');

        $rows = $this->baseIne($gestion)
            ->join($cfg['tabla'], $cfg['pk'], '=', $cfg['fk'])
            ->selectRaw("{$cfg['label']} as label")
            ->selectRaw("SUM($valor) as valor")
            ->groupBy(DB::raw($cfg['label']))
            ->orderByDesc('valor')->limit($limit)
            ->get();

        $acum = 0.0;
        $filas = $rows->values()->map(function ($r, $i) use ($total, &$acum) {
            $valor = (float) $r->valor;
            $pct = $total > 0 ? $valor / $total * 100 : 0;
            $acum += $pct;

            return [
                'posicion'   => $i + 1,
                'label'      => $r->label,
                'valor'      => round($valor),
                'porcentaje' => round($pct, 2),
                'acumulado'  => round($acum, 2),
            ];
        })->all();

        return [
            'tipo'    => $tipo,
            'titulo'  => 'Ranking de ' . $tipo . ' — ' . ($flujo === 'imp' ? 'Importación' : 'Exportación') . ' ' . $gestion,
            'filas'   => $filas,
            'meta'    => [
                'fuente'  => 'INE',
                'flujo'   => $flujo,
                'gestion' => $gestion,
                'unidad'  => 'USD',
                'total'   => round($total),
                'ultima_actualizacion' => now()->toIso8601String(),
            ],
        ];
    }

    // =========================================================================
    //  FAOSTAT (estructura vacía documentada hasta tener datos)
    // =========================================================================

    public function faostat(string $subtipo): array
    {
        $tipoMap = [
            'poblacion'       => 'FAOSTAT_POBLACION',
            'fertilizantes'   => 'FAOSTAT_FERTILIZANTES',
            'subalimentacion' => 'FAOSTAT_SUBALIMENTACION',
            'cereales'        => 'FAOSTAT_CEREALES',
        ];
        $tipo = $tipoMap[$subtipo] ?? null;

        $rows = collect();
        if ($tipo) {
            $rows = DB::table('serie_indicador_agricola as s')
                ->join('faostat_elemento as e', 'e.elemento_id', '=', 's.elemento_id')
                ->where('e.tipo_comercio', $tipo)
                ->orderBy('s.gestion')
                ->get(['s.gestion', 's.valor', 's.unidad', 'e.nombre_elemento as elemento']);
        }

        if ($rows->isEmpty()) {
            return $this->serie([], [], [
                'fuente'    => 'FAOSTAT',
                'subtipo'   => $subtipo,
                'hay_datos' => false,
                'nota'      => 'Sin datos FAOSTAT cargados aún. Estructura lista para cuando se entreguen los Excel.',
            ]);
        }

        $cat = $rows->pluck('gestion')->unique()->sort()->values()->map(fn ($g) => (string) $g)->all();
        $catIdx = array_flip($cat);
        $series = [];
        foreach ($rows->groupBy('elemento') as $elemento => $grupo) {
            $data = array_fill(0, count($cat), null);
            foreach ($grupo as $r) {
                $data[$catIdx[(string) $r->gestion]] = (float) $r->valor;
            }
            $series[] = ['name' => $elemento, 'data' => $data];
        }

        return $this->serie($cat, $series, [
            'fuente'    => 'FAOSTAT',
            'subtipo'   => $subtipo,
            'hay_datos' => true,
            'unidad'    => $rows->first()->unidad,
        ]);
    }

    /** País FAOSTAT por defecto: Bolivia (M49 = 68) o el primero con datos. */
    private function paisFaostatDefecto(): ?int
    {
        $id = DB::table('pais as p')
            ->join('fuente_datos as f', 'f.fuente_id', '=', 'p.fuente_id')
            ->where('f.organizacion_id', self::ORG_FAOSTAT)
            ->where('p.codigo_pais', 68)
            ->value('p.pais_id');

        if ($id) {
            return (int) $id;
        }

        $min = DB::table('serie_indicador_agricola')
            ->where('organizacion_id', self::ORG_FAOSTAT)
            ->min('pais_id');

        return $min !== null ? (int) $min : null;
    }

    /**
     * Evolución anual de los índices comerciales FAOSTAT de un país y un
     * producto CPC (una línea por elemento: valor/volumen de exp/imp).
     * Sin producto se usa el que más serie histórica tiene en ese país.
     */
    public function faostatEvolucion(?int $paisId = null, ?int $productoId = null): array
    {
        $paisId ??= $this->paisFaostatDefecto();

        $productoId ??= (int) DB::table('serie_indicador_agricola')
            ->where('organizacion_id', self::ORG_FAOSTAT)
            ->where('pais_id', $paisId)
            ->whereNotNull('producto_codigo_externo_id')
            ->selectRaw('producto_codigo_externo_id')
            ->groupBy('producto_codigo_externo_id')
            ->orderByRaw('COUNT(*) DESC')
            ->value('producto_codigo_externo_id') ?: null;

        $rows = DB::table('serie_indicador_agricola as s')
            ->join('faostat_elemento as e', 'e.elemento_id', '=', 's.elemento_id')
            ->where('s.organizacion_id', self::ORG_FAOSTAT)
            ->where('s.pais_id', $paisId)
            ->when($productoId, fn ($q) => $q->where('s.producto_codigo_externo_id', $productoId))
            ->orderBy('s.gestion')
            ->get(['s.gestion', 's.valor', 'e.nombre_elemento as elemento']);

        $producto = $productoId ? DB::table('producto_codigo_externo')
            ->where('producto_codigo_externo_id', $productoId)
            ->value('descripcion_externa') : null;
        $pais = DB::table('pais')->where('pais_id', $paisId)->value('nombre');

        $cat = $rows->pluck('gestion')->unique()->sort()->values()->map(fn ($g) => (string) $g)->all();
        $catIdx = array_flip($cat);
        $series = [];
        foreach ($rows->groupBy('elemento') as $elemento => $grupo) {
            $data = array_fill(0, count($cat), null);
            foreach ($grupo as $r) {
                $data[$catIdx[(string) $r->gestion]] = $r->valor !== null ? (float) $r->valor : null;
            }
            $series[] = ['name' => mb_strimwidth((string) $elemento, 0, 45, '…'), 'data' => $data];
        }

        return $this->serie($cat, $series, [
            'fuente'    => 'FAOSTAT',
            'unidad'    => 'índice (2014-2016 = 100)',
            'pais_id'   => $paisId,
            'pais'      => $pais,
            'producto_id' => $productoId,
            'producto'  => $producto,
            'hay_datos' => ! empty($series),
        ]);
    }

    /**
     * Top productos de un país por índice de valor de exportación o
     * importación en una gestión (los que más crecieron frente a la base
     * 2014-2016 = 100).
     */
    public function faostatProductos(?int $paisId = null, string $flujo = 'exp', ?int $gestion = null, int $limit = 10): array
    {
        $paisId ??= $this->paisFaostatDefecto();
        $tipo = $flujo === 'imp' ? 'IMPORTACION' : 'EXPORTACION';

        $base = DB::table('serie_indicador_agricola as s')
            ->join('faostat_elemento as e', 'e.elemento_id', '=', 's.elemento_id')
            ->join('producto_codigo_externo as pc', 'pc.producto_codigo_externo_id', '=', 's.producto_codigo_externo_id')
            ->where('s.organizacion_id', self::ORG_FAOSTAT)
            ->where('s.pais_id', $paisId)
            ->where('e.tipo_comercio', $tipo)
            // Solo el "índice de valor" (no el de volumen ni el de valor unitario).
            ->where('e.nombre_elemento', 'ilike', '%valor%')
            ->where('e.nombre_elemento', 'not ilike', '%unidad%')
            ->where('e.nombre_elemento', 'not ilike', '%volumen%');

        $gestion ??= (int) (clone $base)->max('s.gestion') ?: null;

        $rows = (clone $base)
            ->when($gestion, fn ($q) => $q->where('s.gestion', $gestion))
            ->whereNotNull('s.valor')
            ->selectRaw('pc.descripcion_externa as label')
            ->selectRaw('MAX(s.valor) as valor')
            ->groupBy('pc.descripcion_externa')
            ->orderByDesc('valor')
            ->limit($limit)
            ->get();

        $pais = DB::table('pais')->where('pais_id', $paisId)->value('nombre');

        return $this->serie(
            $rows->pluck('label')->map(fn ($l) => mb_strimwidth((string) $l, 0, 40, '…'))->all(),
            [['name' => 'Índice de valor ('.($flujo === 'imp' ? 'importación' : 'exportación').')', 'data' => $rows->pluck('valor')->map(fn ($v) => round((float) $v, 1))->all()]],
            [
                'fuente'    => 'FAOSTAT',
                'unidad'    => 'índice (2014-2016 = 100)',
                'gestion'   => $gestion,
                'flujo'     => $flujo,
                'pais_id'   => $paisId,
                'pais'      => $pais,
                'hay_datos' => $rows->isNotEmpty(),
            ]
        );
    }

    /** Países y productos disponibles en FAOSTAT para los selectores del panel. */
    public function faostatFiltros(?int $paisId = null): array
    {
        $paises = DB::table('serie_indicador_agricola as s')
            ->join('pais as p', 'p.pais_id', '=', 's.pais_id')
            ->where('s.organizacion_id', self::ORG_FAOSTAT)
            ->selectRaw('p.pais_id as id, p.nombre')
            ->groupBy('p.pais_id', 'p.nombre')
            ->orderBy('p.nombre')
            ->get()
            ->map(fn ($p) => ['id' => (int) $p->id, 'nombre' => $p->nombre])
            ->all();

        $paisId ??= $this->paisFaostatDefecto();

        $productos = DB::table('serie_indicador_agricola as s')
            ->join('producto_codigo_externo as pc', 'pc.producto_codigo_externo_id', '=', 's.producto_codigo_externo_id')
            ->where('s.organizacion_id', self::ORG_FAOSTAT)
            ->where('s.pais_id', $paisId)
            ->selectRaw('pc.producto_codigo_externo_id as id, pc.descripcion_externa as nombre')
            ->groupBy('pc.producto_codigo_externo_id', 'pc.descripcion_externa')
            ->orderBy('pc.descripcion_externa')
            ->get()
            ->map(fn ($p) => ['id' => (int) $p->id, 'nombre' => $p->nombre])
            ->all();

        return ['paises' => $paises, 'pais_id' => $paisId, 'productos' => $productos];
    }

    // =========================================================================
    //  Indicadores
    // =========================================================================

    /**
     * Indicadores y ratios calculados por organización.
     *
     * Dispatch por fuente: cada organización tiene sus propios indicadores según
     * los datos realmente disponibles. Para INE, el año por defecto es el más
     * reciente CON importaciones, para que los ratios de cobertura tengan sentido
     * (las importaciones solo están cargadas en 2018-2020); si se pide un año sin
     * importaciones, los ratios que las requieren devuelven null (no se inventan).
     */
    public function indicadores(?int $gestion = null, int $orgId = self::ORG_INE): array
    {
        return match ($orgId) {
            self::ORG_MERCOSUR => $this->indicadoresMercosur($gestion),
            self::ORG_ALADI    => $this->indicadoresAladi($gestion),
            self::ORG_FAOSTAT  => $this->indicadoresFaostat($gestion),
            default            => $this->indicadoresIne($gestion),
        };
    }

    /** Años (gestiones) INE que tienen importaciones cargadas (cif > 0). */
    public function aniosConImportacionIne(): array
    {
        return DB::table('operacion_comercio_exterior as o')
            ->join('tiempo as t', 't.tiempo_id', '=', 'o.tiempo_id')
            ->where('o.organizacion_id', self::ORG_INE)
            ->where('o.valor_cif_frontera_usd', '>', 0)
            ->distinct()->orderBy('t.gestion')
            ->pluck('t.gestion')->map(fn ($g) => (int) $g)->all();
    }

    /** Gestión INE más reciente con importaciones (para que los ratios cuadren). */
    private function gestionCompletaIne(): ?int
    {
        $anios = $this->aniosConImportacionIne();

        return ! empty($anios) ? (int) end($anios) : $this->gestionReciente(self::ORG_INE);
    }

    private function indicadoresIne(?int $gestion): array
    {
        // Si no se pide año, usar el más reciente con importaciones (ratios válidos).
        $gestion ??= $this->gestionCompletaIne();

        $expo = (float) $this->baseIne($gestion)->selectRaw("SUM({$this->valorExpo}) as v")->value('v');
        $impo = (float) $this->baseIne($gestion)->selectRaw("SUM({$this->valorImpo}) as v")->value('v');
        $operaciones = (int) $this->baseIne($gestion)->count('o.operacion_id');

        // Índice de concentración HHI por país (exportaciones).
        $paises = $this->baseIne($gestion)
            ->selectRaw("SUM({$this->valorExpo}) as valor")
            ->groupBy('o.pais_id')->having(DB::raw("SUM({$this->valorExpo})"), '>', 0)
            ->pluck('valor')->map(fn ($v) => (float) $v);
        $totalExp = $paises->sum();
        $hhi = $totalExp > 0
            ? round($paises->reduce(fn ($c, $v) => $c + (($v / $totalExp * 100) ** 2), 0.0), 1)
            : null;

        return [
            'organizacion'       => 'INE',
            'gestion'            => $gestion,
            'hay_importaciones'  => $impo > 0,
            'exportaciones'      => $expo,
            'importaciones'      => $impo,
            'balanza_comercial'  => $expo - $impo,
            'cobertura_exportaciones' => ($expo + $impo) > 0 ? round($expo / ($expo + $impo) * 100, 1) : null,
            'indice_cobertura'   => $impo > 0 ? round($expo / $impo * 100, 1) : null,
            'concentracion_hhi'  => $hhi,
            'paises_destino'     => (int) $this->baseIne($gestion)->distinct()->count('o.pais_id'),
            'productos_distintos' => (int) $this->baseIne($gestion)->distinct()->count('o.producto_id'),
            'valor_promedio_operacion' => $operaciones > 0 ? round(($expo + $impo) / $operaciones, 2) : null,
            'meta' => [
                'fuente'  => 'INE',
                'unidad'  => 'USD',
                'anios_con_importacion'  => $this->aniosConImportacionIne(),
                'ultima_actualizacion' => now()->toIso8601String(),
            ],
        ];
    }

    private function indicadoresMercosur(?int $gestion): array
    {
        $gestion ??= $this->gestionReciente(self::ORG_MERCOSUR);

        $tot = DB::table('serie_comercio_zona')
            ->when($gestion, fn ($q) => $q->where('gestion', $gestion))
            ->selectRaw('SUM(COALESCE(exportaciones_usd,0)) as expo')
            ->selectRaw('SUM(COALESCE(importaciones_fob_usd,0)) as impo')
            ->selectRaw('COUNT(DISTINCT zona_id) as zonas')
            ->selectRaw('COUNT(*) as registros')
            ->first();

        $expo = (float) ($tot->expo ?? 0);
        $impo = (float) ($tot->impo ?? 0);

        // HHI por zona (exportaciones).
        $zonas = DB::table('mv_resumen_mercosur_zona')
            ->when($gestion, fn ($q) => $q->where('gestion', $gestion))
            ->pluck('total_exportaciones')->map(fn ($v) => (float) $v);
        $totZ = $zonas->sum();
        $hhi = $totZ > 0 ? round($zonas->reduce(fn ($c, $v) => $c + (($v / $totZ * 100) ** 2), 0.0), 1) : null;

        return [
            'organizacion'      => 'MERCOSUR',
            'gestion'           => $gestion,
            'hay_importaciones' => $impo > 0,
            'exportaciones'     => $expo,
            'importaciones'     => $impo,
            'balanza_comercial' => $expo - $impo,
            'cobertura_exportaciones' => ($expo + $impo) > 0 ? round($expo / ($expo + $impo) * 100, 1) : null,
            'indice_cobertura'  => $impo > 0 ? round($expo / $impo * 100, 1) : null,
            'concentracion_hhi' => $hhi,
            'zonas'             => (int) ($tot->zonas ?? 0),
            'meta' => ['fuente' => 'MERCOSUR', 'unidad' => 'USD', 'ultima_actualizacion' => now()->toIso8601String()],
        ];
    }

    private function indicadoresAladi(?int $gestion): array
    {
        $gestion ??= $this->gestionReciente(self::ORG_ALADI);

        $tot  = $this->totalesAladi($gestion);
        $expo = (float) $tot->where('flujo', '1')->sum('total');
        $impo = (float) $tot->where('flujo', '2')->sum('total');

        $base = DB::table('ranking_comercio')
            ->where('organizacion_id', self::ORG_ALADI)
            ->when($gestion, fn ($q) => $q->where('gestion', $gestion));

        $sumaTop = (float) (clone $base)->sum('valor');
        $items = (int) (clone $base)->count();
        $confidenciales = (int) (clone $base)->where('es_confidencial', true)->count();

        // HHI por país miembro (exportaciones derivadas): que tan concentrado
        // esta el comercio del bloque entre sus miembros.
        $expoPais = $tot->where('flujo', '1')->pluck('total')->map(fn ($v) => (float) $v);
        $hhi = $expo > 0 ? round($expoPais->reduce(fn ($c, $v) => $c + (($v / $expo * 100) ** 2), 0.0), 1) : null;

        // Participación top 5 productos sobre la suma de los rankings.
        $valores = (clone $base)->orderByDesc('valor')->limit(5)->pluck('valor')->map(fn ($v) => (float) $v);
        $top5 = $sumaTop > 0 ? round($valores->sum() / $sumaTop * 100, 1) : null;

        return [
            'organizacion'      => 'ALADI',
            'gestion'           => $gestion,
            'hay_importaciones' => $impo > 0,
            'exportaciones'     => $expo,
            'importaciones'     => $impo,
            'balanza_comercial' => $expo - $impo,
            'cobertura_exportaciones' => ($expo + $impo) > 0 ? round($expo / ($expo + $impo) * 100, 1) : null,
            'indice_cobertura'  => $impo > 0 ? round($expo / $impo * 100, 1) : null,
            'paises_destino'    => $tot->pluck('pais_id')->unique()->count(),
            'items_ranking'     => $items,
            'items_confidenciales' => $confidenciales,
            'concentracion_hhi' => $hhi,
            'participacion_top5' => $top5,
            'meta' => ['fuente' => 'ALADI', 'unidad' => 'USD', 'ultima_actualizacion' => now()->toIso8601String()],
        ];
    }

    private function indicadoresFaostat(?int $gestion): array
    {
        $base = DB::table('serie_indicador_agricola')->where('organizacion_id', self::ORG_FAOSTAT);
        $gestion ??= (int) (clone $base)->max('gestion') ?: null;

        $delAnio = (clone $base)->when($gestion, fn ($q) => $q->where('gestion', $gestion));
        $series = (int) (clone $delAnio)->count();

        return [
            'organizacion'        => 'FAOSTAT',
            'gestion'             => $gestion,
            'hay_datos'           => $series > 0,
            'series'              => $series,
            'paises_destino'      => (int) (clone $delAnio)->distinct()->count('pais_id'),
            'productos_distintos' => (int) (clone $delAnio)->distinct()->count('producto_codigo_externo_id'),
            'meta' => [
                'fuente' => 'FAOSTAT',
                'nota'   => 'FAOSTAT publica índices comerciales (base 2014-2016 = 100), no valores en USD.',
                'unidad' => 'índice (2014-2016 = 100)',
                'ultima_actualizacion' => now()->toIso8601String(),
            ],
        ];
    }

    // =========================================================================
    //  Comparador / mapa / línea de tiempo
    // =========================================================================

    public function comparador(array $filtros): array
    {
        $modo = $filtros['modo'] ?? 'anio';
        $flujo = $filtros['flujo'] ?? 'exp';

        return match ($modo) {
            'pais'     => $this->compararPaises($filtros, $flujo),
            'producto' => $this->compararProductos($filtros, $flujo),
            default    => $this->compararAnios($filtros, $flujo),
        };
    }

    private function compararAnios(array $filtros, string $flujo): array
    {
        $dimension = $filtros['dimension'] ?? 'top-productos';
        $anioA = isset($filtros['anio_a']) ? (int) $filtros['anio_a'] : null;
        $anioB = isset($filtros['anio_b']) ? (int) $filtros['anio_b'] : null;
        $limit = isset($filtros['limit']) ? (int) $filtros['limit'] : 12;

        $serieA = match ($dimension) {
            'top-paises' => $this->topPaises($flujo, $anioA, $limit),
            'top-departamentos' => $this->topDepartamentos($anioA, $limit),
            default => $this->topProductos($flujo, $anioA, $limit),
        };
        $serieB = match ($dimension) {
            'top-paises' => $this->topPaises($flujo, $anioB, $limit),
            'top-departamentos' => $this->topDepartamentos($anioB, $limit),
            default => $this->topProductos($flujo, $anioB, $limit),
        };

        $mapA = collect($serieA['categorias'] ?? [])->mapWithKeys(fn ($label, $i) => [$label => (float) ($serieA['series'][0]['data'][$i] ?? 0)]);
        $mapB = collect($serieB['categorias'] ?? [])->mapWithKeys(fn ($label, $i) => [$label => (float) ($serieB['series'][0]['data'][$i] ?? 0)]);
        $labels = $mapA->keys()->merge($mapB->keys())->unique()->values();

        $filas = $labels->map(function ($label) use ($mapA, $mapB) {
            $a = (float) ($mapA[$label] ?? 0);
            $b = (float) ($mapB[$label] ?? 0);

            return [
                'label' => $label,
                'valor_a' => round($a),
                'valor_b' => round($b),
                'variacion' => $this->variacion($b, $a),
            ];
        })->sortByDesc('valor_b')->values()->all();

        return [
            'modo' => 'anio',
            'categorias' => array_column($filas, 'label'),
            'series' => [
                ['name' => (string) $anioA, 'data' => array_column($filas, 'valor_a')],
                ['name' => (string) $anioB, 'data' => array_column($filas, 'valor_b')],
            ],
            'filas' => $filas,
            'meta' => [
                'dimension' => $dimension,
                'flujo' => $flujo,
                'fuente' => 'INE',
            ],
        ];
    }

    private function compararPaises(array $filtros, string $flujo): array
    {
        $paisA = isset($filtros['pais_a']) ? (int) $filtros['pais_a'] : null;
        $paisB = isset($filtros['pais_b']) ? (int) $filtros['pais_b'] : null;
        $valor = $this->exprFlujo($flujo);

        $rows = $this->baseIne(null)
            ->join('pais as p', 'p.pais_id', '=', 'o.pais_id')
            ->selectRaw('t.gestion, o.pais_id, p.nombre as label')
            ->selectRaw("SUM($valor) as valor")
            ->whereIn('o.pais_id', array_filter([$paisA, $paisB]))
            ->groupBy('t.gestion', 'o.pais_id', 'p.nombre')
            ->orderBy('t.gestion')
            ->get();

        return $this->comparacionTemporal($rows, 'pais', $flujo);
    }

    private function compararProductos(array $filtros, string $flujo): array
    {
        $productoA = isset($filtros['producto_a']) ? (int) $filtros['producto_a'] : null;
        $productoB = isset($filtros['producto_b']) ? (int) $filtros['producto_b'] : null;
        $valor = $this->exprFlujo($flujo);

        $rows = $this->baseIne(null)
            ->join('producto as p', 'p.producto_id', '=', 'o.producto_id')
            ->selectRaw('t.gestion, o.producto_id, p.descripcion as label')
            ->selectRaw("SUM($valor) as valor")
            ->whereIn('o.producto_id', array_filter([$productoA, $productoB]))
            ->groupBy('t.gestion', 'o.producto_id', 'p.descripcion')
            ->orderBy('t.gestion')
            ->get();

        return $this->comparacionTemporal($rows, 'producto', $flujo);
    }

    private function comparacionTemporal($rows, string $modo, string $flujo): array
    {
        $categorias = $rows->pluck('gestion')->unique()->sort()->values()->map(fn ($g) => (string) $g)->all();
        $ids = $rows->pluck($modo . '_id')->unique()->values()->all();
        $catIdx = array_flip($categorias);
        $series = [];
        $filas = [];

        foreach ($ids as $id) {
            $grupo = $rows->where($modo . '_id', $id)->values();
            $nombre = (string) ($grupo->first()->label ?? ('Serie ' . $id));
            $data = array_fill(0, count($categorias), 0);

            foreach ($grupo as $r) {
                $data[$catIdx[(string) $r->gestion]] = round((float) $r->valor);
            }

            $series[] = ['name' => $nombre, 'data' => $data];
        }

        foreach ($categorias as $i => $gestion) {
            $fila = ['gestion' => (int) $gestion];
            foreach ($series as $serie) {
                $fila[$serie['name']] = $serie['data'][$i] ?? 0;
            }
            $filas[] = $fila;
        }

        return [
            'modo' => $modo,
            'categorias' => $categorias,
            'series' => $series,
            'filas' => $filas,
            'meta' => ['flujo' => $flujo, 'fuente' => 'INE'],
        ];
    }

    public function mapaFlujos(?int $gestion, string $flujo = 'ambos', int $limit = 30): array
    {
        $gestion ??= $this->gestionReciente(self::ORG_INE);

        $rows = $this->baseIne($gestion)
            ->join('pais as p', 'p.pais_id', '=', 'o.pais_id')
            ->selectRaw('o.pais_id, p.nombre as label, p.iso_alpha3 as iso')
            ->selectRaw("SUM({$this->valorExpo}) as expo")
            ->selectRaw("SUM({$this->valorImpo}) as impo")
            ->groupBy('o.pais_id', 'p.nombre', 'p.iso_alpha3')
            ->get()
            ->map(function ($r) {
                $expo = (float) $r->expo;
                $impo = (float) $r->impo;

                return [
                    'pais_id' => (int) $r->pais_id,
                    'label'   => $r->label,
                    'iso'     => $r->iso,
                    'expo'    => round($expo),
                    'impo'    => round($impo),
                    'saldo'   => round($expo - $impo),
                    'total'   => round($expo + $impo),
                ];
            });

        $ordenados = (match ($flujo) {
            'exp' => $rows->sortByDesc('expo'),
            'imp' => $rows->sortByDesc('impo'),
            default => $rows->sortByDesc('total'),
        })->take($limit)->values();

        return [
            'items' => $ordenados->all(),
            'meta' => [
                'fuente' => 'INE',
                'gestion' => $gestion,
                'flujo' => $flujo,
                'hay_datos' => $ordenados->isNotEmpty(),
            ],
        ];
    }

    public function timeline(): array
    {
        $evol = $this->evolucionAnual();
        $cats = $evol['categorias'] ?? [];
        $exp = $evol['series'][0]['data'] ?? [];
        $imp = $evol['series'][1]['data'] ?? [];

        $hitos = [
            2008 => ['titulo' => 'Crisis financiera global', 'descripcion' => 'Caída de la demanda y de los precios internacionales.'],
            2011 => ['titulo' => 'Boom de commodities', 'descripcion' => 'Máximos de precios para varias materias primas exportadas.'],
            2020 => ['titulo' => 'Pandemia COVID-19', 'descripcion' => 'Shock simultáneo en logística, demanda y actividad económica.'],
        ];

        $items = collect($cats)->map(function ($anio, $i) use ($exp, $imp, $hitos) {
            $year = (int) $anio;
            $expo = (float) ($exp[$i] ?? 0);
            $impo = (float) ($imp[$i] ?? 0);

            return [
                'anio' => $year,
                'exportaciones' => round($expo),
                'importaciones' => round($impo),
                'balanza' => round($expo - $impo),
                'hito' => $hitos[$year] ?? null,
            ];
        })->all();

        return [
            'items' => $items,
            'categorias' => $cats,
            'series' => $evol['series'],
            'meta' => ['fuente' => 'INE', 'hay_datos' => ! empty($items)],
        ];
    }

    // =========================================================================
    //  Filtros
    // =========================================================================

    /**
     * Países para el Comparador (que solo compara microdato INE): se restringe
     * a los países con al menos un registro en operacion_comercio_exterior y se
     * deduplica por nombre normalizado, porque el catálogo `pais` puede tener
     * más de una fila para el mismo país con distinta grafía/mayúsculas (cada
     * organización carga sus propios códigos de país; la tabla de equivalencias
     * entre organizaciones queda pendiente, ver memoria/pendientes.md).
     */
    public function filtroPaises(): array
    {
        return DB::table('operacion_comercio_exterior as o')
            ->join('pais as p', 'p.pais_id', '=', 'o.pais_id')
            ->selectRaw('MIN(p.pais_id) as id, MAX(p.nombre) as nombre, MAX(p.iso_alpha3) as iso')
            ->groupBy(DB::raw('UPPER(TRIM(p.nombre))'))
            ->orderByRaw('MAX(p.nombre)')
            ->get()
            ->map(fn ($r) => ['id' => (int) $r->id, 'nombre' => $r->nombre, 'iso' => $r->iso])->all();
    }

    public function filtroZonas(): array
    {
        return DB::table('zona_geoeconomica')->orderBy('descripcion')
            ->get(['zona_id as id', 'descripcion', 'fuente_id'])
            ->map(fn ($r) => ['id' => (int) $r->id, 'nombre' => $r->descripcion])->all();
    }

    public function filtroSecciones(): array
    {
        return DB::table('seccion_arancelaria')->orderBy('codigo_seccion')
            ->get(['seccion_id as id', 'codigo_seccion', 'descripcion'])
            ->map(fn ($r) => ['id' => (int) $r->id, 'codigo' => $r->codigo_seccion, 'nombre' => $r->descripcion])->all();
    }

    public function filtroProductos(?string $buscar = null, int $limit = 50): array
    {
        return DB::table('producto')
            ->when($buscar, fn ($q) => $q->where(function ($qq) use ($buscar) {
                $qq->whereRaw('LOWER(descripcion) LIKE ?', ['%' . mb_strtolower($buscar) . '%'])
                    ->orWhereRaw('LOWER(codigo_nandina) LIKE ?', ['%' . mb_strtolower($buscar) . '%']);
            }))
            ->orderBy('descripcion')
            ->limit($limit)
            ->get(['producto_id as id', 'codigo_nandina', 'descripcion'])
            ->map(fn ($r) => [
                'id' => (int) $r->id,
                'codigo' => $r->codigo_nandina,
                'nombre' => $r->descripcion,
                'label' => trim(($r->codigo_nandina ? $r->codigo_nandina . ' · ' : '') . $r->descripcion),
            ])->all();
    }

    /** Años disponibles, opcionalmente por organización. */
    public function filtroGestiones(?int $orgId = null): array
    {
        if ($orgId === self::ORG_MERCOSUR) {
            return DB::table('mv_resumen_mercosur_zona')->distinct()->orderByDesc('gestion')
                ->pluck('gestion')->map(fn ($g) => (int) $g)->all();
        }
        if ($orgId === self::ORG_ALADI) {
            return DB::table('ranking_comercio')->distinct()->orderByDesc('gestion')
                ->pluck('gestion')->map(fn ($g) => (int) $g)->all();
        }
        if ($orgId === self::ORG_FAOSTAT) {
            return DB::table('serie_indicador_agricola')->distinct()->orderByDesc('gestion')
                ->pluck('gestion')->map(fn ($g) => (int) $g)->all();
        }
        if ($orgId === self::ORG_INE) {
            return DB::table('mv_resumen_anual_ine')->distinct()->orderByDesc('gestion')
                ->pluck('gestion')->map(fn ($g) => (int) $g)->all();
        }

        return DB::table('tiempo')->distinct()->orderByDesc('gestion')
            ->pluck('gestion')->map(fn ($g) => (int) $g)->all();
    }

    // =========================================================================
    //  Organizaciones
    // =========================================================================

    public function organizaciones(): array
    {
        $conteos = [
            self::ORG_INE      => DB::table('operacion_comercio_exterior')->count(),
            self::ORG_ALADI    => DB::table('ranking_comercio')->count(),
            self::ORG_MERCOSUR => DB::table('serie_comercio_zona')->count()
                + DB::table('serie_comercio_producto_zona')->count(),
            self::ORG_FAOSTAT  => DB::table('serie_indicador_agricola')->count(),
        ];

        return DB::table('organizacion as o')
            ->leftJoin('organizacion_detalle as d', 'd.organizacion_id', '=', 'o.organizacion_id')
            ->where('o.activo', true)
            ->orderBy('o.organizacion_id')
            ->get([
                'o.organizacion_id', 'o.nombre', 'o.sigla', 'o.url',
                'd.descripcion_corta', 'd.color_primario', 'd.color_secundario', 'd.icono_clase',
            ])
            ->map(fn ($r) => [
                'id'               => (int) $r->organizacion_id,
                'nombre'           => $r->nombre,
                'sigla'            => $r->sigla,
                'url'              => $r->url,
                'descripcion'      => $r->descripcion_corta,
                'color_primario'   => $r->color_primario,
                'color_secundario' => $r->color_secundario,
                'icono'            => $r->icono_clase,
                'registros'        => $conteos[$r->organizacion_id] ?? 0,
                'gestion_reciente' => $this->gestionReciente((int) $r->organizacion_id),
            ])->all();
    }

    public function organizacion(int $id): ?array
    {
        $r = DB::table('organizacion as o')
            ->leftJoin('organizacion_detalle as d', 'd.organizacion_id', '=', 'o.organizacion_id')
            ->where('o.organizacion_id', $id)
            ->first();

        if (! $r) {
            return null;
        }

        return [
            'id'                  => (int) $r->organizacion_id,
            'nombre'              => $r->nombre,
            'sigla'               => $r->sigla,
            'url'                 => $r->url,
            'descripcion_corta'   => $r->descripcion_corta ?? null,
            'descripcion_larga'   => $r->descripcion_larga ?? null,
            'metodologia'         => $r->metodologia ?? null,
            'cobertura_temporal'  => $r->cobertura_temporal ?? null,
            'cobertura_geografica' => $r->cobertura_geografica ?? null,
            'tipos_datos'         => $r->tipos_datos ?? null,
            'url_fuente_oficial'  => $r->url_fuente_oficial ?? null,
            'color_primario'      => $r->color_primario ?? null,
            'color_secundario'    => $r->color_secundario ?? null,
            'icono'               => $r->icono_clase ?? null,
            'gestion_reciente'    => $this->gestionPreferida($id),
            'kpis'                => $this->kpis($id, $this->gestionPreferida($id)),
        ];
    }

    // =========================================================================
    //  Utilidades
    // =========================================================================

    /** Expresión SQL del valor según flujo solicitado (exp|imp|ambos). */
    private function exprFlujo(string $flujo): string
    {
        return match ($flujo) {
            'imp'   => $this->valorImpo,
            'ambos' => "{$this->valorExpo} + {$this->valorImpo}",
            default => $this->valorExpo, // exp
        };
    }

    private function variacion(float $actual, float $anterior): ?float
    {
        if ($anterior <= 0) {
            return null;
        }

        return round((($actual - $anterior) / $anterior) * 100, 1);
    }
}
