<?php

namespace App\Servicios;

use Illuminate\Support\Facades\DB;

/**
 * Rankings y comparadores del portal publico.
 *
 * Para organizaciones tradicionales lee las vistas resumen_* basadas en microdatos.
 * Para MERCOSUR lee las tablas de series agregadas serie_comercio_* porque esos
 * archivos no cargan microdatos en operacion_comercio_exterior.
 */
class RankingPortal
{
    public const FLUJO_EXPORTACION = 1;
    public const FLUJO_IMPORTACION = 2;

    private function dim(string $dimension): array
    {
        return match ($dimension) {
            'pais' => [
                'vista' => 'resumen_anual_pais', 'tabla' => 'pais', 'fk' => 'pais_id',
                'pk' => 'pais_id', 'label' => 'nombre',
            ],
            'departamento' => [
                'vista' => 'resumen_anual_departamento', 'tabla' => 'departamento', 'fk' => 'departamento_id',
                'pk' => 'departamento_id', 'label' => 'nombre',
            ],
            default => [
                'vista' => 'resumen_anual_producto', 'tabla' => 'producto', 'fk' => 'producto_id',
                'pk' => 'producto_id', 'label' => 'descripcion',
            ],
        };
    }

    private function colMetrica(string $metrica): string
    {
        return $metrica === 'peso' ? 'peso_bruto' : 'valor';
    }

    public function ranking(int $orgId, int $gestion, int $flujo, string $dimension, string $metrica, int $limite): array
    {
        if ($this->esMercosur($orgId)) {
            return $this->rankingMercosur($orgId, $gestion, $flujo, $dimension, $metrica, $limite);
        }

        $cfg = $this->dim($dimension);
        $col = $this->colMetrica($metrica);

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

        return [
            'titulo'  => $this->titulo($dimension, $flujo, $metrica, $gestion),
            'metrica' => $metrica,
            'unidad'  => $metrica === 'peso' ? 'kg' : 'USD',
            'total'   => $total,
            'filas'   => $this->filasRanking($rows, $total),
        ];
    }

    public function compararAnios(int $orgId, string $dimension, int $flujo, int $anioA, int $anioB, int $limite): array
    {
        if ($this->esMercosur($orgId)) {
            return $this->compararAniosMercosur($orgId, $dimension, $flujo, $anioA, $anioB, $limite);
        }

        $cfg = $this->dim($dimension);
        $valoresA = $this->valoresPorItem($orgId, $anioA, $flujo, $cfg);
        $valoresB = $this->valoresPorItem($orgId, $anioB, $flujo, $cfg);

        return [
            'titulo'  => 'Comparacion ' . $anioA . ' vs ' . $anioB . ' - ' . $this->nombreDim($dimension)
                . ' (' . $this->nombreFlujo($flujo) . ')',
            'anio_a'  => $anioA,
            'anio_b'  => $anioB,
            'filas'   => $this->filasComparacionAnios($valoresA, $valoresB, $limite),
        ];
    }

    public function compararFlujos(int $orgId, string $dimension, int $gestion, int $limite): array
    {
        if ($this->esMercosur($orgId)) {
            return $this->compararFlujosMercosur($orgId, $dimension, $gestion, $limite);
        }

        $cfg = $this->dim($dimension);
        $expo = $this->valoresPorItem($orgId, $gestion, self::FLUJO_EXPORTACION, $cfg);
        $impo = $this->valoresPorItem($orgId, $gestion, self::FLUJO_IMPORTACION, $cfg);

        return [
            'titulo'  => 'Exportacion vs Importacion ' . $gestion . ' - ' . $this->nombreDim($dimension),
            'gestion' => $gestion,
            'filas'   => $this->filasComparacionFlujos($expo, $impo, $limite),
        ];
    }

    private function rankingMercosur(int $orgId, int $gestion, int $flujo, string $dimension, string $metrica, int $limite): array
    {
        if ($dimension === 'departamento') {
            return [
                'titulo' => 'MERCOSUR no clasifica por departamentos',
                'metrica' => $metrica,
                'unidad' => $metrica === 'peso' ? 'kg' : 'USD',
                'total' => 0,
                'filas' => [],
            ];
        }

        $expr = $this->exprMercosur($flujo, $metrica);
        $query = $this->baseMercosur($orgId, $gestion, $dimension);
        $label = $this->labelMercosur($dimension);

        $total = (float) (clone $query)->sum(DB::raw($expr));
        $rows = $query
            ->selectRaw("$label as label")
            ->selectRaw("SUM($expr) as valor")
            ->groupByRaw($label)
            ->orderByDesc('valor')
            ->limit($limite)
            ->get();

        return [
            'titulo' => 'Ranking MERCOSUR de ' . $this->nombreDim($dimension) . ' por ' . ($metrica === 'peso' ? 'volumen' : 'valor') . ' - ' . $this->nombreFlujo($flujo) . ' ' . $gestion,
            'metrica' => $metrica,
            'unidad' => $metrica === 'peso' ? 'kg' : 'USD',
            'total' => $total,
            'filas' => $this->filasRanking($rows, $total),
        ];
    }

    private function compararAniosMercosur(int $orgId, string $dimension, int $flujo, int $anioA, int $anioB, int $limite): array
    {
        if ($dimension === 'departamento') {
            $dimension = 'producto';
        }

        $valoresA = $this->valoresPorItemMercosur($orgId, $anioA, $flujo, $dimension);
        $valoresB = $this->valoresPorItemMercosur($orgId, $anioB, $flujo, $dimension);

        return [
            'titulo' => 'Comparacion MERCOSUR ' . $anioA . ' vs ' . $anioB . ' - ' . $this->nombreDim($dimension) . ' (' . $this->nombreFlujo($flujo) . ')',
            'anio_a' => $anioA,
            'anio_b' => $anioB,
            'filas' => $this->filasComparacionAnios($valoresA, $valoresB, $limite),
        ];
    }

    private function compararFlujosMercosur(int $orgId, string $dimension, int $gestion, int $limite): array
    {
        if ($dimension === 'departamento') {
            $dimension = 'producto';
        }

        $expo = $this->valoresPorItemMercosur($orgId, $gestion, self::FLUJO_EXPORTACION, $dimension);
        $impo = $this->valoresPorItemMercosur($orgId, $gestion, self::FLUJO_IMPORTACION, $dimension);

        return [
            'titulo' => 'Exportacion vs Importacion MERCOSUR ' . $gestion . ' - ' . $this->nombreDim($dimension),
            'gestion' => $gestion,
            'filas' => $this->filasComparacionFlujos($expo, $impo, $limite),
        ];
    }

    private function valoresPorItem(int $orgId, int $gestion, int $flujo, array $cfg): array
    {
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

    private function valoresPorItemMercosur(int $orgId, int $gestion, int $flujo, string $dimension): array
    {
        $expr = $this->exprMercosur($flujo, 'valor');
        $label = $this->labelMercosur($dimension);

        return $this->baseMercosur($orgId, $gestion, $dimension)
            ->selectRaw("$label as label")
            ->selectRaw("SUM($expr) as valor")
            ->groupByRaw($label)
            ->pluck('valor', 'label')
            ->map(fn ($v) => (float) $v)
            ->all();
    }

    private function baseMercosur(int $orgId, int $gestion, string $dimension)
    {
        if ($dimension === 'pais') {
            return DB::table('serie_comercio_zona as s')
                ->leftJoin('pais as p', 'p.pais_id', '=', 's.pais_id')
                ->where('s.organizacion_id', $orgId)
                ->where('s.gestion', $gestion)
                ->whereNull('s.zona_id');
        }

        return DB::table('serie_comercio_producto_zona as s')
            ->where('s.organizacion_id', $orgId)
            ->where('s.gestion', $gestion)
            ->whereNull('s.zona_id');
    }

    private function labelMercosur(string $dimension): string
    {
        if ($dimension === 'pais') {
            return "COALESCE(p.nombre, s.pais_nombre_original, 'Sin pais')";
        }

        return "COALESCE(NULLIF(s.ncm_descripcion, ''), s.ncm_codigo, 'Sin producto')";
    }

    private function exprMercosur(int $flujo, string $metrica): string
    {
        if ($flujo === self::FLUJO_IMPORTACION) {
            return $metrica === 'peso'
                ? 'COALESCE(s.volumen_import_kg, 0)'
                : 'COALESCE(s.importaciones_cif_usd, 0)';
        }

        return $metrica === 'peso'
            ? 'COALESCE(s.volumen_export_kg, 0)'
            : 'COALESCE(s.exportaciones_usd, 0)';
    }

    private function filasRanking($rows, float $total): array
    {
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

        return $filas;
    }

    private function filasComparacionAnios(array $valoresA, array $valoresB, int $limite): array
    {
        $labels = $valoresA + $valoresB;
        $filas = [];

        foreach (array_keys($labels) as $label) {
            $vA = $valoresA[$label] ?? 0.0;
            $vB = $valoresB[$label] ?? 0.0;
            $filas[] = [
                'label' => $label,
                'valor_a' => $vA,
                'valor_b' => $vB,
                'variacion' => $vB - $vA,
                'variacion_pct' => $vA > 0 ? round(($vB - $vA) / $vA * 100, 1) : null,
            ];
        }

        usort($filas, fn ($x, $y) => $y['valor_b'] <=> $x['valor_b']);
        return array_slice($filas, 0, $limite);
    }

    private function filasComparacionFlujos(array $expo, array $impo, int $limite): array
    {
        $labels = $expo + $impo;
        $filas = [];

        foreach (array_keys($labels) as $label) {
            $e = $expo[$label] ?? 0.0;
            $i = $impo[$label] ?? 0.0;
            $filas[] = [
                'label' => $label,
                'expo' => $e,
                'impo' => $i,
                'balance' => $e - $i,
            ];
        }

        usort($filas, fn ($x, $y) => ($y['expo'] + $y['impo']) <=> ($x['expo'] + $x['impo']));
        return array_slice($filas, 0, $limite);
    }

    private function titulo(string $dimension, int $flujo, string $metrica, int $gestion): string
    {
        $base = 'Ranking de ' . $this->nombreDim($dimension);
        $por = $metrica === 'peso' ? ' por volumen (peso)' : ' por valor';

        return $base . $por . ' - ' . $this->nombreFlujo($flujo) . ' ' . $gestion;
    }

    private function nombreDim(string $dimension): string
    {
        return match ($dimension) {
            'pais' => 'paises',
            'departamento' => 'departamentos',
            default => 'productos',
        };
    }

    private function nombreFlujo(int $flujo): string
    {
        return $flujo === self::FLUJO_EXPORTACION ? 'Exportacion' : 'Importacion';
    }

    private function esMercosur(int $orgId): bool
    {
        static $cache = [];

        if (! array_key_exists($orgId, $cache)) {
            $cache[$orgId] = DB::table('organizacion')
                ->where('organizacion_id', $orgId)
                ->where('sigla', 'MERCOSUR')
                ->exists();
        }

        return $cache[$orgId];
    }
}
