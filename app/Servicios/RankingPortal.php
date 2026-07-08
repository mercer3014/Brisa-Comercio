<?php

namespace App\Servicios;

use Illuminate\Support\Facades\DB;

/**
 * Rankings y comparadores del portal público (Tarea 13).
 *
 * Lee de las vistas materializadas de la Tarea 14 (resumen_anual_producto/pais/departamento),
 * SIEMPRE filtradas por organización. Cada ranking devuelve posición, nombre, valor, % del total
 * y % acumulado; los comparadores cruzan dos años o los dos flujos por dimension.
 */
class RankingPortal
{
    public const FLUJO_EXPORTACION = 1;
    public const FLUJO_IMPORTACION = 2;

    /**
     * ALADI y MERCOSUR no están en las vistas del microdato: sus datos viven
     * en ranking_comercio y en las series serie_comercio_* respectivamente, y
     * se resuelven con ramas propias con la misma forma de salida.
     */
    private const ORG_ALADI    = 2;
    private const ORG_MERCOSUR = 3;
    private const ORG_FAOSTAT  = 4;

    /**
     * Configuración por dimension: vista, tabla de nombre y columnas de join/etiqueta.
     */
    private function dim(string $dimension): array
    {
        return ['dimension' => $dimension] + match ($dimension) {
            'pais' => [
                'vista' => 'resumen_anual_pais', 'tabla' => 'pais', 'fk' => 'pais_id',
                'pk' => 'pais_id', 'label' => 'nombre',
            ],
            'departamento' => [
                'vista' => 'resumen_anual_departamento', 'tabla' => 'departamento', 'fk' => 'departamento_id',
                'pk' => 'departamento_id', 'label' => 'nombre',
            ],
            default => [ // producto
                'vista' => 'resumen_anual_producto', 'tabla' => 'producto', 'fk' => 'producto_id',
                'pk' => 'producto_id', 'label' => 'descripcion',
            ],
        };
    }

    private function colMetrica(string $metrica): string
    {
        return $metrica === 'peso' ? 'peso_bruto' : 'valor';
    }

    /**
     * Ranking de una dimension por valor o por peso.
     *
     * @return array{título:string, metrica:string, total:float, unidad:string, filas:array}
     */
    public function ranking(int $orgId, int $gestion, int $flujo, string $dimension, string $metrica, int $limite): array
    {
        if ($orgId === self::ORG_ALADI) {
            return $this->rankingAladi($gestion, $flujo, $dimension, $metrica, $limite);
        }
        if ($orgId === self::ORG_MERCOSUR) {
            return $this->rankingMercosur($gestion, $flujo, $dimension, $metrica, $limite);
        }
        if ($orgId === self::ORG_FAOSTAT) {
            return $this->rankingFaostat($gestion, $flujo, $dimension, $metrica, $limite);
        }

        $cfg = $this->dim($dimension);
        $col = $this->colMetrica($metrica);

        // Total general (todas las posiciones) para los porcentajes.
        $total = (float) DB::table($cfg['vista'])
            ->where('organizacion_id', $orgId)->where('gestion', $gestion)->where('flujo_id', $flujo)
            ->sum($col);

        $rows = DB::table($cfg['vista'] . ' as r')
            ->join($cfg['tabla'] . ' as x', "x.{$cfg['pk']}", '=', "r.{$cfg['fk']}")
            ->where('r.organizacion_id', $orgId)->where('r.gestion', $gestion)->where('r.flujo_id', $flujo)
            ->selectRaw("x.{$cfg['label']} as label")
            ->selectRaw("SUM(r.{$col}) as valor")
            ->groupBy("x.{$cfg['label']}")
            ->orderByDesc('valor')
            ->limit($limite)
            ->get();

        $filas = [];
        $acum = 0.0;
        foreach ($rows as $i => $r) {
            $valor = (float) $r->valor;
            $pct = $total > 0 ? $valor / $total * 100 : 0;
            $acum += $pct;
            $filas[] = [
                'posicion'   => $i + 1,
                'label'      => $r->label,
                'valor'      => $valor,
                'porcentaje' => round($pct, 2),
                'acumulado'  => round($acum, 2),
            ];
        }

        return [
            'titulo'  => $this->titulo($dimension, $flujo, $metrica, $gestion),
            'metrica' => $metrica,
            'unidad'  => $metrica === 'peso' ? 'kg' : 'USD',
            'total'   => $total,
            'filas'   => $filas,
        ];
    }

    /**
     * Comparador de dos años para una dimension y flujo: variación por item.
     */
    public function compararAnios(int $orgId, string $dimension, int $flujo, int $anioA, int $anioB, int $limite): array
    {
        $cfg = $this->dim($dimension);

        $valoresA = $this->valoresPorItem($orgId, $anioA, $flujo, $cfg);
        $valoresB = $this->valoresPorItem($orgId, $anioB, $flujo, $cfg);

        $labels = $valoresA + $valoresB; // union de claves
        $filas = [];
        foreach (array_keys($labels) as $label) {
            $vA = $valoresA[$label] ?? 0.0;
            $vB = $valoresB[$label] ?? 0.0;
            $filas[] = [
                'label'        => $label,
                'valor_a'      => $vA,
                'valor_b'      => $vB,
                'variacion'    => $vB - $vA,
                'variacion_pct' => $vA > 0 ? round(($vB - $vA) / $vA * 100, 1) : null,
            ];
        }

        // Ordenar por el valor del año más reciente (B) descendente.
        usort($filas, fn ($x, $y) => $y['valor_b'] <=> $x['valor_b']);
        $filas = array_slice($filas, 0, $limite);

        return [
            'titulo'  => 'Comparación ' . $anioA . ' vs ' . $anioB . ' — ' . $this->nombreDim($dimension)
                . ' (' . $this->nombreFlujo($flujo) . ')',
            'anio_a'  => $anioA,
            'anio_b'  => $anioB,
            'filas'   => $filas,
        ];
    }

    /**
     * Comparador exportación vs importación de una dimension en una gestión.
     */
    public function compararFlujos(int $orgId, string $dimension, int $gestion, int $limite): array
    {
        $cfg = $this->dim($dimension);

        $expo = $this->valoresPorItem($orgId, $gestion, self::FLUJO_EXPORTACION, $cfg);
        $impo = $this->valoresPorItem($orgId, $gestion, self::FLUJO_IMPORTACION, $cfg);

        $labels = $expo + $impo;
        $filas = [];
        foreach (array_keys($labels) as $label) {
            $e = $expo[$label] ?? 0.0;
            $i = $impo[$label] ?? 0.0;
            $filas[] = [
                'label'    => $label,
                'expo'     => $e,
                'impo'     => $i,
                'balance'  => $e - $i,
            ];
        }

        usort($filas, fn ($x, $y) => ($y['expo'] + $y['impo']) <=> ($x['expo'] + $x['impo']));
        $filas = array_slice($filas, 0, $limite);

        return [
            'titulo'  => 'Exportación vs Importación ' . $gestion . ' — ' . $this->nombreDim($dimension),
            'gestion' => $gestion,
            'filas'   => $filas,
        ];
    }

    /**
     * Valor por item (label => valor) para una gestión y flujo.
     */
    private function valoresPorItem(int $orgId, int $gestion, int $flujo, array $cfg): array
    {
        if ($orgId === self::ORG_ALADI) {
            return $this->valoresAladi($gestion, $flujo, $cfg['dimension'] ?? 'producto');
        }
        if ($orgId === self::ORG_MERCOSUR) {
            return $this->valoresMercosur($gestion, $flujo, $cfg['dimension'] ?? 'producto', 'valor');
        }
        if ($orgId === self::ORG_FAOSTAT) {
            return $this->valoresFaostat($gestion, $flujo, $cfg['dimension'] ?? 'producto', 'valor');
        }

        return DB::table($cfg['vista'] . ' as r')
            ->join($cfg['tabla'] . ' as x', "x.{$cfg['pk']}", '=', "r.{$cfg['fk']}")
            ->where('r.organizacion_id', $orgId)->where('r.gestion', $gestion)->where('r.flujo_id', $flujo)
            ->selectRaw("x.{$cfg['label']} as label")
            ->selectRaw('SUM(r.valor) as valor')
            ->groupBy("x.{$cfg['label']}")
            ->pluck('valor', 'label')
            ->map(fn ($v) => (float) $v)
            ->all();
    }

    private function titulo(string $dimension, int $flujo, string $metrica, int $gestion): string
    {
        $base = 'Ranking de ' . $this->nombreDim($dimension);
        $por = $metrica === 'peso' ? ' por volumen (peso)' : ' por valor';

        return $base . $por . ' — ' . $this->nombreFlujo($flujo) . ' ' . $gestion;
    }

    private function nombreDim(string $dimension): string
    {
        return match ($dimension) {
            'pais' => 'países',
            'departamento' => 'departamentos',
            default => 'productos',
        };
    }

    private function nombreFlujo(int $flujo): string
    {
        return $flujo === self::FLUJO_EXPORTACION ? 'Exportación' : 'Importación';
    }

    // =========================================================================
    //  Rama ALADI (ranking_comercio)
    // =========================================================================

    /**
     * Ranking ALADI por producto (suma de los top-50 de los miembros) o por
     * país miembro (totales derivados del % acumulado). Sin datos de peso ni
     * de departamento: esas combinaciones devuelven filas vacias.
     */
    private function rankingAladi(int $gestion, int $flujo, string $dimension, string $metrica, int $limite): array
    {
        $titulo = $this->titulo($dimension, $flujo, $metrica, $gestion) . ' — ALADI';

        if ($metrica === 'peso' || $dimension === 'departamento') {
            return ['titulo' => $titulo, 'metrica' => $metrica, 'unidad' => $metrica === 'peso' ? 'kg' : 'USD', 'total' => 0.0, 'filas' => []];
        }

        return $this->armarRanking($this->valoresAladi($gestion, $flujo, $dimension), $titulo, $metrica, 'USD', $limite);
    }

    /**
     * Ranking MERCOSUR por producto (serie_comercio_producto_zona) o por país
     * socio (serie_comercio_zona), por valor o por volumen. Sin desglose por
     * departamento (eso es del microdato del INE).
     */
    private function rankingMercosur(int $gestion, int $flujo, string $dimension, string $metrica, int $limite): array
    {
        $titulo = $this->titulo($dimension, $flujo, $metrica, $gestion) . ' — MERCOSUR';
        $unidad = $metrica === 'peso' ? 'kg' : 'USD';

        if ($dimension === 'departamento') {
            return ['titulo' => $titulo, 'metrica' => $metrica, 'unidad' => $unidad, 'total' => 0.0, 'filas' => []];
        }

        return $this->armarRanking($this->valoresMercosur($gestion, $flujo, $dimension, $metrica), $titulo, $metrica, $unidad, $limite);
    }

    /** Arma la respuesta estandar (posición, %, % acumulado) desde label => valor. */
    private function armarRanking(array $valores, string $titulo, string $metrica, string $unidad, int $limite): array
    {
        $valores = array_filter($valores, fn ($v) => $v > 0);
        arsort($valores);
        $total = array_sum($valores);

        $filas = [];
        $acum = 0.0;
        $pos = 0;
        foreach (array_slice($valores, 0, $limite, true) as $label => $valor) {
            $pct = $total > 0 ? $valor / $total * 100 : 0;
            $acum += $pct;
            $filas[] = [
                'posicion'   => ++$pos,
                'label'      => $label,
                'valor'      => $valor,
                'porcentaje' => round($pct, 2),
                'acumulado'  => round($acum, 2),
            ];
        }

        return [
            'titulo'  => $titulo,
            'metrica' => $metrica,
            'unidad'  => $unidad,
            'total'   => $total,
            'filas'   => $filas,
        ];
    }

    /** Columna MERCOSUR según flujo y metrica (valor USD o volumen kg). */
    private function colMercosur(int $flujo, string $metrica): string
    {
        if ($metrica === 'peso') {
            return $flujo === self::FLUJO_EXPORTACION ? 'volumen_export_kg' : 'volumen_import_kg';
        }

        return $flujo === self::FLUJO_EXPORTACION ? 'exportaciones_usd' : 'importaciones_cif_usd';
    }

    // =========================================================================
    //  Rama FAOSTAT (serie_indicador_agricola)
    // =========================================================================

    /**
     * FAOSTAT publica ÍNDICES (base 2014-2016 = 100), no USD ni kg: el ranking
     * ordena por el índice promedio (de valor si la metrica es "valor", de
     * volumen físico si es "peso"). Un índice alto = lo que más crecio frente
     * a la base. Sin desglose por departamento.
     */
    private function rankingFaostat(int $gestion, int $flujo, string $dimension, string $metrica, int $limite): array
    {
        $titulo = 'Ranking de '.$this->nombreDim($dimension).' por índice de '
            .($metrica === 'peso' ? 'volumen' : 'valor').' — '.$this->nombreFlujo($flujo).' '.$gestion
            .' — FAOSTAT (índice mediano, 2014-2016 = 100)';

        if ($dimension === 'departamento') {
            return ['titulo' => $titulo, 'metrica' => $metrica, 'unidad' => 'índice', 'total' => 0.0, 'filas' => []];
        }

        return $this->armarRanking($this->valoresFaostat($gestion, $flujo, $dimension, $metrica), $titulo, $metrica, 'índice', $limite);
    }

    /** Índice promedio por item (label => índice) para FAOSTAT. */
    private function valoresFaostat(int $gestion, int $flujo, string $dimension, string $metrica): array
    {
        $tipo = $flujo === self::FLUJO_IMPORTACION ? 'IMPORTACION' : 'EXPORTACION';

        $q = DB::table('serie_indicador_agricola as s')
            ->join('faostat_elemento as e', 'e.elemento_id', '=', 's.elemento_id')
            ->where('s.organizacion_id', self::ORG_FAOSTAT)
            ->where('s.gestion', $gestion)
            ->where('e.tipo_comercio', $tipo)
            ->whereNotNull('s.valor');

        // "valor" -> índice de valor; "peso" -> índice de volumen físico.
        if ($metrica === 'peso') {
            $q->where('e.nombre_elemento', 'ilike', '%volumen%');
        } else {
            $q->where('e.nombre_elemento', 'ilike', '%valor%')
                ->where('e.nombre_elemento', 'not ilike', '%unidad%')
                ->where('e.nombre_elemento', 'not ilike', '%volumen%');
        }

        if ($dimension === 'pais') {
            return $q->join('pais as pa', 'pa.pais_id', '=', 's.pais_id')
                ->selectRaw('pa.nombre as label')
                ->selectRaw('percentile_cont(0.5) WITHIN GROUP (ORDER BY s.valor) as valor')
                ->groupBy('pa.nombre')
                ->get()
                ->mapWithKeys(fn ($r) => [(string) $r->label => round((float) $r->valor, 1)])
                ->all();
        }

        return $q->join('producto_codigo_externo as pc', 'pc.producto_codigo_externo_id', '=', 's.producto_codigo_externo_id')
            ->selectRaw('pc.descripcion_externa as label')
            ->selectRaw('percentile_cont(0.5) WITHIN GROUP (ORDER BY s.valor) as valor')
            ->groupBy('pc.descripcion_externa')
            ->get()
            ->mapWithKeys(fn ($r) => [(string) $r->label => round((float) $r->valor, 1)])
            ->all();
    }

    /** Valor por item (label => valor) para MERCOSUR en una gestión y flujo. */
    private function valoresMercosur(int $gestion, int $flujo, string $dimension, string $metrica): array
    {
        $col = $this->colMercosur($flujo, $metrica);

        if ($dimension === 'pais') {
            return DB::table('serie_comercio_zona as s')
                ->join('pais as pa', 'pa.pais_id', '=', 's.pais_id')
                ->where('s.organizacion_id', self::ORG_MERCOSUR)
                ->where('s.gestion', $gestion)
                ->selectRaw('pa.nombre as label')
                ->selectRaw("SUM(COALESCE(s.{$col},0)) as valor")
                ->groupBy('pa.nombre')
                ->get()
                ->mapWithKeys(fn ($r) => [(string) $r->label => (float) $r->valor])
                ->all();
        }

        return DB::table('serie_comercio_producto_zona as s')
            ->where('s.organizacion_id', self::ORG_MERCOSUR)
            ->where('s.gestion', $gestion)
            ->selectRaw('COALESCE(s.ncm_descripcion, s.ncm_codigo) as label')
            ->selectRaw("SUM(COALESCE(s.{$col},0)) as valor")
            ->groupBy(DB::raw('COALESCE(s.ncm_descripcion, s.ncm_codigo)'))
            ->get()
            ->mapWithKeys(fn ($r) => [(string) $r->label => (float) $r->valor])
            ->all();
    }

    /** Valor por item (label => valor USD) para ALADI en una gestión y flujo. */
    private function valoresAladi(int $gestion, int $flujo, string $dimension): array
    {
        $codigo = (string) $flujo;

        if ($dimension === 'pais') {
            // Total derivado por país miembro: suma_top50 * 100 / pct_acumulado.
            return DB::table('ranking_comercio as rc')
                ->join('flujo_comercial as fl', 'fl.flujo_id', '=', 'rc.flujo_id')
                ->join('pais as pa', 'pa.pais_id', '=', 'rc.pais_reportante_id')
                ->where('rc.organizacion_id', self::ORG_ALADI)
                ->where('rc.gestion', $gestion)
                ->where('fl.codigo_flujo', $codigo)
                ->selectRaw('pa.nombre as label')
                ->selectRaw('SUM(COALESCE(rc.valor,0)) as suma_top')
                ->selectRaw('MAX(rc.porcentaje_acumulado) as pct')
                ->groupBy('pa.nombre')
                ->get()
                ->mapWithKeys(function ($r) {
                    $suma = (float) $r->suma_top;
                    $pct = (float) $r->pct;

                    return [$r->label => $pct > 0 ? $suma * 100 / $pct : $suma];
                })
                ->all();
        }

        // producto: suma de los rankings de todos los miembros, agrupada por
        // descripción (misma semantica que la rama del microdato, que agrupa
        // por la etiqueta de la dimension).
        return DB::table('ranking_comercio as rc')
            ->join('flujo_comercial as fl', 'fl.flujo_id', '=', 'rc.flujo_id')
            ->where('rc.organizacion_id', self::ORG_ALADI)
            ->where('rc.gestion', $gestion)
            ->where('fl.codigo_flujo', $codigo)
            ->selectRaw('COALESCE(rc.descripcion, rc.item_codigo) as label')
            ->selectRaw('SUM(COALESCE(rc.valor,0)) as valor')
            ->groupBy(DB::raw('COALESCE(rc.descripcion, rc.item_codigo)'))
            ->get()
            ->mapWithKeys(fn ($r) => [(string) $r->label => (float) $r->valor])
            ->all();
    }
}
