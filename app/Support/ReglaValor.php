<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * ÚNICA regla de valor del INE (operacion_comercio_exterior), la misma que usan
 * las vistas materializadas resumen_* (migración 2026_11_21_000004):
 *
 *   exportación / reexportación (codigo_flujo 1 y 3) -> valor_fob_usd
 *   importación (codigo_flujo 2)                     -> valor_cif_frontera_usd
 *
 * Antes se sumaba valor_fob_usd de TODAS las filas como "exportación", pero el
 * INE también trae FOB en las importaciones, así que las exportaciones se
 * inflaban con el FOB importado (y "total" sumaba FOB + CIF de cada importación).
 */
final class ReglaValor
{
    private static ?array $ids = null;

    /** @return array{expo:int[], impo:int[]} flujo_id por tipo (resuelto por codigo_flujo). */
    public static function flujos(): array
    {
        if (self::$ids === null) {
            $filas = DB::table('flujo_comercial')->get(['flujo_id', 'codigo_flujo']);
            $expo = $filas->filter(fn ($f) => in_array(trim((string) $f->codigo_flujo), ['1', '3'], true))->pluck('flujo_id')->map(fn ($v) => (int) $v)->all();
            $impo = $filas->filter(fn ($f) => trim((string) $f->codigo_flujo) === '2')->pluck('flujo_id')->map(fn ($v) => (int) $v)->all();
            self::$ids = ['expo' => $expo ?: [1, 3], 'impo' => $impo ?: [2]];
        }

        return self::$ids;
    }

    /** SQL: valor exportado de la fila (0 si es importación). */
    public static function expo(string $alias = 'o'): string
    {
        $ids = implode(',', self::flujos()['expo']);

        return "(CASE WHEN {$alias}.flujo_id IN ({$ids}) THEN COALESCE({$alias}.valor_fob_usd,0) ELSE 0 END)";
    }

    /** SQL: valor importado de la fila (0 si es exportación). */
    public static function impo(string $alias = 'o'): string
    {
        $ids = implode(',', self::flujos()['impo']);

        return "(CASE WHEN {$alias}.flujo_id IN ({$ids}) THEN COALESCE({$alias}.valor_cif_frontera_usd,0) ELSE 0 END)";
    }

    /** SQL: valor de la fila según su flujo (FOB si exporta, CIF frontera si importa). */
    public static function total(string $alias = 'o'): string
    {
        return '('.self::expo($alias).' + '.self::impo($alias).')';
    }
}
