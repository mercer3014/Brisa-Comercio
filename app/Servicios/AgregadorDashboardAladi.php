<?php

namespace App\Servicios;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Version ALADI de AgregadorDashboard: mismo contrato de salida (misma forma
 * de arreglos) que consume Dashboards/Index.vue, pero leyendo de la tabla
 * propia de ALADI (ranking_comercio: top-50 de productos por país miembro,
 * flujo y gestión) en vez del microdato del INE o las series de MERCOSUR.
 *
 * Los rankings solo traen el top-50 de cada país, pero incluyen el
 * "% acumulado" sobre el total del país: de ahi se deriva el total real
 * (total = suma_top50 * 100 / pct_acumulado). No se inventa nada, es
 * aritmetica del propio archivo publicado.
 *
 * Lo que no existe en ALADI (volumen físico, departamento, medio de
 * transporte, granularidad mensual) devuelve series vacias o nulos en vez
 * de inventar datos.
 */
class AgregadorDashboardAladi
{
    private const ORG_ID = 2;

    /**
     * Total derivado por (país, flujo, gestión): la suma del top-50 escalada
     * por el % acumulado que ese top representa del total del país.
     *
     * @return Collection<int, object{pais_id:int|null, flujo:string|null, gestión:int, total:float}>
     */
    private function totales(?int $gestion = null): Collection
    {
        return DB::table('ranking_comercio as rc')
            ->leftJoin('flujo_comercial as fl', 'fl.flujo_id', '=', 'rc.flujo_id')
            ->where('rc.organizacion_id', self::ORG_ID)
            ->when($gestion, fn ($q) => $q->where('rc.gestion', $gestion))
            ->selectRaw('rc.pais_reportante_id as pais_id, fl.codigo_flujo as flujo, rc.gestion')
            ->selectRaw('SUM(rc.valor) as suma_top')
            ->selectRaw('MAX(rc.porcentaje_acumulado) as pct')
            ->groupBy('rc.pais_reportante_id', 'fl.codigo_flujo', 'rc.gestion')
            ->get()
            ->map(function ($r) {
                $suma = (float) $r->suma_top;
                $pct  = (float) $r->pct;
                $r->total = $pct > 0 ? $suma * 100 / $pct : $suma;

                return $r;
            });
    }

    public function kpis(?int $gestion = null): array
    {
        $tot  = $this->totales($gestion);
        $expo = (float) $tot->where('flujo', '1')->sum('total');
        $impo = (float) $tot->where('flujo', '2')->sum('total');
        $valorTotal = $expo + $impo;

        $registros = (int) DB::table('ranking_comercio')
            ->where('organizacion_id', self::ORG_ID)
            ->when($gestion, fn ($q) => $q->where('gestion', $gestion))
            ->count();

        $variacion = null;
        if ($gestion) {
            $ant = (float) $this->totales($gestion - 1)->sum('total');
            if ($ant > 0) {
                $variacion = round((($valorTotal - $ant) / $ant) * 100, 1);
            }
        }

        return [
            'valor_total'          => $valorTotal,
            'valor_exportacion'    => $expo,
            'valor_importacion'    => $impo,
            'balanza_comercial'    => $expo - $impo,
            'peso_bruto'           => 0.0, // ALADI no publica volumen físico
            'peso_neto'            => 0.0,
            'registros'            => $registros,
            'precio_implicito'     => null,
            'variacion_interanual' => $variacion,
        ];
    }

    /**
     * ALADI no trae desglose mensual (rankings anuales). Se devuelve un punto
     * por gestión, IGNORANDO el filtro de gestión: filtrando a un solo año
     * quedaria un gráfico de una sola barra sin poder ver evolución.
     */
    public function evolucionMensual(?int $gestion = null): array
    {
        return $this->totales(null)
            ->groupBy('gestion')
            ->map(fn ($grupo, $g) => [
                'periodo' => (string) $g,
                'valor'   => (float) $grupo->sum('total'),
                'peso'    => 0.0,
            ])
            ->sortKeys()
            ->values()
            ->all();
    }

    public function evolucionAnual(): array
    {
        return $this->totales(null)
            ->groupBy('gestion')
            ->map(function ($grupo, $g) {
                $expo = (float) $grupo->where('flujo', '1')->sum('total');
                $impo = (float) $grupo->where('flujo', '2')->sum('total');

                return [
                    'gestion' => (int) $g,
                    'expo'    => $expo,
                    'impo'    => $impo,
                    'balanza' => $expo - $impo,
                ];
            })
            ->sortKeys()
            ->values()
            ->all();
    }

    public function topPaises(?int $gestion = null, int $n = 10): array
    {
        $nombres = $this->nombresPais();

        return $this->totales($gestion)
            ->groupBy('pais_id')
            ->map(fn ($grupo, $paisId) => [
                'label' => $nombres[$paisId] ?? "País {$paisId}",
                'valor' => (float) $grupo->sum('total'),
            ])
            ->sortByDesc('valor')
            ->take($n)
            ->values()
            ->all();
    }

    public function topProductos(?int $gestion = null, int $n = 10): array
    {
        return DB::table('ranking_comercio')
            ->where('organizacion_id', self::ORG_ID)
            ->when($gestion, fn ($q) => $q->where('gestion', $gestion))
            ->selectRaw('item_codigo, MAX(descripcion) as label')
            ->selectRaw('SUM(COALESCE(valor,0)) as valor')
            ->groupBy('item_codigo')->orderByDesc('valor')->limit($n)
            ->get()->map(fn ($r) => [
                'label'   => mb_strimwidth((string) ($r->label ?: $r->item_codigo), 0, 40, '...'),
                'nandina' => $r->item_codigo,
                'valor'   => (float) $r->valor,
            ])->all();
    }

    /**
     * ALADI no maneja zonas geoeconomicas: su dimension geográfica es el país
     * miembro, así que la "distribución por zona" muestra los 13 miembros.
     */
    public function distribucionZona(?int $gestion = null): array
    {
        return $this->topPaises($gestion, 13);
    }

    /** ALADI no tiene desglose por departamento (eso es específico del microdato del INE). */
    public function distribucionDepartamento(): array
    {
        return [];
    }

    public function participacionPais(?int $gestion = null, int $n = 8): array
    {
        $top = $this->topPaises($gestion, $n);
        $total = array_sum(array_column($top, 'valor'));

        return array_map(fn ($r) => [
            'label'      => $r['label'],
            'valor'      => $r['valor'],
            'porcentaje' => $total > 0 ? round($r['valor'] / $total * 100, 1) : 0,
        ], $top);
    }

    /** ALADI no tiene desglose por medio de transporte (eso es específico del microdato del INE). */
    public function distribucionMedio(): array
    {
        return [];
    }

    /** @return array<int, string> pais_id => nombre */
    private function nombresPais(): array
    {
        return DB::table('pais')
            ->join('fuente_datos as f', 'f.fuente_id', '=', 'pais.fuente_id')
            ->where('f.organizacion_id', self::ORG_ID)
            ->pluck('pais.nombre', 'pais.pais_id')
            ->all();
    }
}
