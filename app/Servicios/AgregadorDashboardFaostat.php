<?php

namespace App\Servicios;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Version FAOSTAT de AgregadorDashboard: mismo contrato de salida (misma
 * forma de arreglos) que consume Dashboards/Index.vue, pero sobre los índices
 * comerciales de serie_indicador_agricola.
 *
 * FAOSTAT NO publica valores en USD ni kg: publica ÍNDICES (base 2014-2016 =
 * 100) de valor y volumen de exportación/importacion por país y producto CPC.
 * Por eso "expo"/"impo" aquí son la MEDIANA del índice de valor (un índice de
 * 200 = el doble que el promedio 2014-2016; se usa mediana porque el promedio
 * se dispara con outliers), y la vista rotula las tarjetas y gráficos como
 * índices para la organización 4. Lo que no existe (peso, departamento,
 * medio de transporte, meses) se omite.
 */
class AgregadorDashboardFaostat
{
    private const ORG_ID = 4;

    /** elemento_id de los "índices de valor" por tipo (memoizado: son ~6 filas). */
    private array $elementosValor = [];

    private function elementosValor(?string $tipo = null): array
    {
        if (empty($this->elementosValor)) {
            $this->elementosValor = DB::table('faostat_elemento')
                ->where('nombre_elemento', 'ilike', '%valor%')
                ->where('nombre_elemento', 'not ilike', '%unidad%')
                ->where('nombre_elemento', 'not ilike', '%volumen%')
                ->get(['elemento_id', 'tipo_comercio'])
                ->groupBy('tipo_comercio')
                ->map(fn ($g) => $g->pluck('elemento_id')->map(fn ($v) => (int) $v)->all())
                ->all();
        }

        if ($tipo !== null) {
            return $this->elementosValor[$tipo] ?? [];
        }

        return array_merge(...array_values($this->elementosValor) ?: [[]]);
    }

    /**
     * Base: solo los elementos "índice de valor" (no valor unitario ni
     * volumen), filtrados por id para que PostgreSQL use los índices de la
     * tabla en vez de evaluar ILIKEs por fila.
     */
    private function base(?int $gestion = null, ?string $tipo = null): Builder
    {
        $q = DB::table('serie_indicador_agricola as s')
            ->join('faostat_elemento as e', 'e.elemento_id', '=', 's.elemento_id')
            ->where('s.organizacion_id', self::ORG_ID)
            ->whereIn('s.elemento_id', $this->elementosValor($tipo))
            ->whereNotNull('s.valor');

        if ($gestion) {
            $q->where('s.gestion', $gestion);
        }

        return $q;
    }

    /**
     * Mediana del índice de valor: más robusta que el promedio, que se
     * dispara con productos casi inexistentes en la base 2014-2016.
     */
    private function mediana(?int $gestion, string $tipo): float
    {
        return (float) $this->base($gestion, $tipo)
            ->selectRaw('percentile_cont(0.5) WITHIN GROUP (ORDER BY s.valor) as m')
            ->value('m');
    }

    public function kpis(?int $gestion = null): array
    {
        $tot = DB::table('serie_indicador_agricola')
            ->where('organizacion_id', self::ORG_ID)
            ->when($gestion, fn ($q) => $q->where('gestion', $gestion))
            ->selectRaw('COUNT(*) as series')
            ->selectRaw('COUNT(DISTINCT pais_id) as paises')
            ->selectRaw('COUNT(DISTINCT producto_codigo_externo_id) as productos')
            ->selectRaw('MIN(gestion) as anio_min')
            ->selectRaw('MAX(gestion) as anio_max')
            ->first();

        $idxExpo = $this->mediana($gestion, 'EXPORTACION');
        $idxImpo = $this->mediana($gestion, 'IMPORTACION');

        $variacion = null;
        if ($gestion) {
            $act = ($idxExpo + $idxImpo) / 2;
            $ant = ($this->mediana($gestion - 1, 'EXPORTACION') + $this->mediana($gestion - 1, 'IMPORTACION')) / 2;
            if ($ant > 0) {
                $variacion = round((($act - $ant) / $ant) * 100, 1);
            }
        }

        return [
            // Contrato comun (la vista rotula estas cifras como índices para org 4).
            'valor_total'          => round(($idxExpo + $idxImpo) / 2, 1),
            'valor_exportacion'    => round($idxExpo, 1),
            'valor_importacion'    => round($idxImpo, 1),
            'balanza_comercial'    => round($idxExpo - $idxImpo, 1),
            'peso_bruto'           => 0.0,
            'peso_neto'            => 0.0,
            'registros'            => (int) $tot->series,
            'precio_implicito'     => null,
            'variacion_interanual' => $variacion,
            // Extras propios de FAOSTAT para las tarjetas de la vista.
            'series'    => (int) $tot->series,
            'paises'    => (int) $tot->paises,
            'productos' => (int) $tot->productos,
            'anio_min'  => $tot->anio_min !== null ? (int) $tot->anio_min : null,
            'anio_max'  => $tot->anio_max !== null ? (int) $tot->anio_max : null,
        ];
    }

    /**
     * FAOSTAT es anual: un punto por gestión (índice de valor promedio),
     * IGNORANDO el filtro de gestión para poder ver la evolución completa.
     */
    public function evolucionMensual(?int $gestion = null): array
    {
        // No depende de la gestión (siempre la serie completa): se cachea por
        // version de datos para que cambiar de año no repita el escaneo.
        return \Illuminate\Support\Facades\Cache::remember(
            'faostat.evo.mensual.'.ClavesCache::version(), ClavesCache::TTL,
            fn () => $this->base(null)
                ->selectRaw('s.gestion')
                ->selectRaw('percentile_cont(0.5) WITHIN GROUP (ORDER BY s.valor) as valor')
                ->groupBy('s.gestion')->orderBy('s.gestion')
                ->get()
                ->map(fn ($r) => [
                    'periodo' => (string) $r->gestion,
                    'valor'   => round((float) $r->valor, 1),
                    'peso'    => 0.0,
                ])->all()
        );
    }

    public function evolucionAnual(): array
    {
        return \Illuminate\Support\Facades\Cache::remember(
            'faostat.evo.anual.'.ClavesCache::version(), ClavesCache::TTL,
            fn () => $this->base(null)
                ->selectRaw('s.gestion')
                ->selectRaw("percentile_cont(0.5) WITHIN GROUP (ORDER BY s.valor) FILTER (WHERE e.tipo_comercio = 'EXPORTACION') as expo")
                ->selectRaw("percentile_cont(0.5) WITHIN GROUP (ORDER BY s.valor) FILTER (WHERE e.tipo_comercio = 'IMPORTACION') as impo")
                ->groupBy('s.gestion')->orderBy('s.gestion')
                ->get()
                ->map(fn ($r) => [
                    'gestion' => (int) $r->gestion,
                    'expo'    => round((float) $r->expo, 1),
                    'impo'    => round((float) $r->impo, 1),
                    'balanza' => round((float) $r->expo - (float) $r->impo, 1),
                ])->all()
        );
    }

    public function topPaises(?int $gestion = null, int $n = 10): array
    {
        return $this->base($gestion)
            ->join('pais as pa', 'pa.pais_id', '=', 's.pais_id')
            ->selectRaw('pa.nombre as label')
            ->selectRaw('percentile_cont(0.5) WITHIN GROUP (ORDER BY s.valor) as valor')
            ->groupBy('pa.nombre')->orderByDesc('valor')->limit($n)
            ->get()->map(fn ($r) => ['label' => $r->label, 'valor' => round((float) $r->valor, 1)])->all();
    }

    public function topProductos(?int $gestion = null, int $n = 10): array
    {
        return $this->base($gestion)
            ->join('producto_codigo_externo as pc', 'pc.producto_codigo_externo_id', '=', 's.producto_codigo_externo_id')
            ->selectRaw('pc.codigo_externo, MAX(pc.descripcion_externa) as label')
            ->selectRaw('percentile_cont(0.5) WITHIN GROUP (ORDER BY s.valor) as valor')
            ->groupBy('pc.codigo_externo')->orderByDesc('valor')->limit($n)
            ->get()->map(fn ($r) => [
                'label'   => mb_strimwidth((string) ($r->label ?: $r->codigo_externo), 0, 40, '...'),
                'nandina' => $r->codigo_externo,
                'valor'   => round((float) $r->valor, 1),
            ])->all();
    }

    /** FAOSTAT no maneja zonas: su dimension geográfica es el país. */
    public function distribucionZona(?int $gestion = null): array
    {
        return $this->topPaises($gestion, 8);
    }

    /** FAOSTAT no tiene desglose por departamento. */
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

    /** FAOSTAT no tiene desglose por medio de transporte. */
    public function distribucionMedio(): array
    {
        return [];
    }
}
