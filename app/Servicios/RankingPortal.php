<?php

namespace App\Servicios;

use Illuminate\Support\Facades\DB;

/**
 * Rankings y comparadores del portal publico (Tarea 13).
 *
 * Lee de las vistas materializadas de la Tarea 14 (resumen_anual_producto/pais/departamento),
 * SIEMPRE filtradas por organizacion. Cada ranking devuelve posicion, nombre, valor, % del total
 * y % acumulado; los comparadores cruzan dos anios o los dos flujos por dimension.
 */
class RankingPortal
{
    public const FLUJO_EXPORTACION = 1;
    public const FLUJO_IMPORTACION = 2;

    /**
     * Configuracion por dimension: vista, tabla de nombre y columnas de join/etiqueta.
     */
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
     * @return array{titulo:string, metrica:string, total:float, unidad:string, filas:array}
     */
    public function ranking(int $orgId, int $gestion, int $flujo, string $dimension, string $metrica, int $limite): array
    {
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
     * Comparador de dos anios para una dimension y flujo: variacion por item.
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

        // Ordenar por el valor del anio mas reciente (B) descendente.
        usort($filas, fn ($x, $y) => $y['valor_b'] <=> $x['valor_b']);
        $filas = array_slice($filas, 0, $limite);

        return [
            'titulo'  => 'Comparacion ' . $anioA . ' vs ' . $anioB . ' — ' . $this->nombreDim($dimension)
                . ' (' . $this->nombreFlujo($flujo) . ')',
            'anio_a'  => $anioA,
            'anio_b'  => $anioB,
            'filas'   => $filas,
        ];
    }

    /**
     * Comparador exportacion vs importacion de una dimension en una gestion.
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
            'titulo'  => 'Exportacion vs Importacion ' . $gestion . ' — ' . $this->nombreDim($dimension),
            'gestion' => $gestion,
            'filas'   => $filas,
        ];
    }

    /**
     * Valor por item (label => valor) para una gestion y flujo.
     */
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

    private function titulo(string $dimension, int $flujo, string $metrica, int $gestion): string
    {
        $base = 'Ranking de ' . $this->nombreDim($dimension);
        $por = $metrica === 'peso' ? ' por volumen (peso)' : ' por valor';

        return $base . $por . ' — ' . $this->nombreFlujo($flujo) . ' ' . $gestion;
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
}
