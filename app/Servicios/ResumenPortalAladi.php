<?php

namespace App\Servicios;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Version ALADI de ResumenPortal: misma forma de salida (mismo contrato que
 * consume Portal/Inicio.vue) pero leyendo de la tabla propia de ALADI
 * (ranking_comercio: top-50 de productos por país miembro, flujo y gestión)
 * en vez del microdato del INE o las series de MERCOSUR.
 *
 * Los totales por país se derivan del % acumulado que el top-50 representa
 * (total = suma_top50 * 100 / pct_acumulado): aritmetica del propio archivo,
 * no datos inventados. ALADI no tiene granularidad mensual ni volumen físico:
 * "evolución" (que es por mes) y el volumen se omiten.
 */
class ResumenPortalAladi
{
    private const ORG_ID = 2;

    public function gestionMasReciente(): ?int
    {
        $g = DB::table('ranking_comercio')->where('organizacion_id', self::ORG_ID)->max('gestion');

        return $g !== null ? (int) $g : null;
    }

    public function portada(?int $gestion): array
    {
        $org = DB::table('organizacion')->where('organizacion_id', self::ORG_ID)->first();
        $hayDatos = $gestion !== null && DB::table('ranking_comercio')
            ->where('organizacion_id', self::ORG_ID)->where('gestion', $gestion)->exists();

        return [
            'meta' => [
                'organizacion' => $org?->nombre,
                'sigla'        => $org?->sigla,
                'gestion'      => $gestion,
                'fuente'       => 'Fuente: ALADI - Rankings por país miembro.'.($gestion ? " Datos {$gestion}." : ''),
                'hay_datos'    => $hayDatos,
            ],
            'titulares'     => $hayDatos ? $this->titulares($gestion) : [],
            'indicadores'   => $hayDatos ? $this->indicadores($gestion) : null,
            'top_productos' => $hayDatos ? $this->topProductos($gestion, 5) : [],
            'top_destinos'  => $hayDatos ? $this->topPaises($gestion, 5) : [],
            'evolucion'     => [], // ALADI es anual: no hay desglose mensual que mostrar aquí.
        ];
    }

    // -------------------------------------------------------------------------

    /**
     * Total derivado por (país, flujo): suma del top-50 escalada por el
     * % acumulado que ese top representa del total del país.
     */
    private function totales(int $gestion): Collection
    {
        return DB::table('ranking_comercio as rc')
            ->leftJoin('flujo_comercial as fl', 'fl.flujo_id', '=', 'rc.flujo_id')
            ->leftJoin('pais as pa', 'pa.pais_id', '=', 'rc.pais_reportante_id')
            ->where('rc.organizacion_id', self::ORG_ID)
            ->where('rc.gestion', $gestion)
            ->selectRaw('rc.pais_reportante_id as pais_id, pa.nombre as pais, fl.codigo_flujo as flujo')
            ->selectRaw('SUM(rc.valor) as suma_top')
            ->selectRaw('MAX(rc.porcentaje_acumulado) as pct')
            ->groupBy('rc.pais_reportante_id', 'pa.nombre', 'fl.codigo_flujo')
            ->get()
            ->map(function ($r) {
                $suma = (float) $r->suma_top;
                $pct  = (float) $r->pct;
                $r->total = $pct > 0 ? $suma * 100 / $pct : $suma;

                return $r;
            });
    }

    private function titulares(int $gestion): array
    {
        $titulares = [];

        if ($p = $this->liderProducto($gestion, '1')) {
            $titulares[] = $this->titular('producto_exportado',
                "En {$gestion}, el producto mas exportado del bloque fue {$p->label} (USD ".$this->fmt($p->valor).').', $p);
        }
        if ($p = $this->liderProducto($gestion, '2')) {
            $titulares[] = $this->titular('producto_importado',
                "El producto mas importado fue {$p->label} (USD ".$this->fmt($p->valor).').', $p);
        }

        $totales = $this->totales($gestion);

        if ($e = $this->liderPais($totales, '1')) {
            $titulares[] = $this->titular('destino_exportacion',
                "El pais miembro con mayores exportaciones fue {$e['label']}.", (object) $e);
        }
        if ($i = $this->liderPais($totales, '2')) {
            $titulares[] = $this->titular('origen_importacion',
                "El pais miembro con mayores importaciones fue {$i['label']}.", (object) $i);
        }

        return $titulares;
    }

    private function titular(string $clave, string $texto, $row): array
    {
        return [
            'clave'    => $clave,
            'texto'    => $texto,
            'etiqueta' => $row->label,
            'valor'    => (float) $row->valor,
        ];
    }

    private function liderProducto(int $gestion, string $codigoFlujo)
    {
        return DB::table('ranking_comercio as rc')
            ->join('flujo_comercial as fl', 'fl.flujo_id', '=', 'rc.flujo_id')
            ->where('rc.organizacion_id', self::ORG_ID)
            ->where('rc.gestion', $gestion)
            ->where('fl.codigo_flujo', $codigoFlujo)
            ->selectRaw('MAX(rc.descripcion) as label')
            ->selectRaw('SUM(COALESCE(rc.valor,0)) as valor')
            ->groupBy('rc.item_codigo')
            ->orderByDesc('valor')
            ->first();
    }

    private function liderPais(Collection $totales, string $codigoFlujo): ?array
    {
        $lider = $totales->where('flujo', $codigoFlujo)->sortByDesc('total')->first();

        return $lider ? ['label' => $lider->pais ?? '—', 'valor' => (float) $lider->total] : null;
    }

    private function indicadores(int $gestion): array
    {
        $act = $this->totales($gestion);
        $ant = $this->totales($gestion - 1);

        $expo = (float) $act->where('flujo', '1')->sum('total');
        $impo = (float) $act->where('flujo', '2')->sum('total');
        $expoAnt = (float) $ant->where('flujo', '1')->sum('total');
        $impoAnt = (float) $ant->where('flujo', '2')->sum('total');

        $paises = (int) DB::table('ranking_comercio')
            ->where('organizacion_id', self::ORG_ID)->where('gestion', $gestion)
            ->distinct()->count('pais_reportante_id');

        $productos = (int) DB::table('ranking_comercio')
            ->where('organizacion_id', self::ORG_ID)->where('gestion', $gestion)
            ->distinct()->count('item_codigo');

        return [
            'valor_exportado'     => $expo,
            'variacion_expo'      => $this->variacion($expo, $expoAnt),
            'valor_importado'     => $impo,
            'variacion_impo'      => $this->variacion($impo, $impoAnt),
            'balanza_comercial'   => $expo - $impo,
            'volumen_exportado'   => 0.0, // ALADI no publica volumen físico
            'variacion_volumen'   => null,
            'paises_destino'      => $paises,
            'productos_distintos' => $productos,
            'gestion_anterior'    => $gestion - 1,
        ];
    }

    private function variacion(float $actual, float $anterior): ?float
    {
        if ($anterior <= 0) {
            return null;
        }

        return round((($actual - $anterior) / $anterior) * 100, 1);
    }

    private function topProductos(int $gestion, int $n): array
    {
        return DB::table('ranking_comercio as rc')
            ->join('flujo_comercial as fl', 'fl.flujo_id', '=', 'rc.flujo_id')
            ->where('rc.organizacion_id', self::ORG_ID)
            ->where('rc.gestion', $gestion)
            ->where('fl.codigo_flujo', '1')
            ->selectRaw('MAX(rc.descripcion) as label')
            ->selectRaw('SUM(COALESCE(rc.valor,0)) as valor')
            ->groupBy('rc.item_codigo')
            ->orderByDesc('valor')
            ->limit($n)
            ->get()
            ->map(fn ($r) => [
                'label' => mb_strimwidth((string) $r->label, 0, 45, '...'),
                'valor' => (float) $r->valor,
            ])->all();
    }

    /** Top países miembros por exportaciones (total derivado del % acumulado). */
    private function topPaises(int $gestion, int $n): array
    {
        return $this->totales($gestion)
            ->where('flujo', '1')
            ->sortByDesc('total')
            ->take($n)
            ->map(fn ($r) => ['label' => $r->pais ?? '—', 'valor' => (float) $r->total])
            ->values()
            ->all();
    }

    private function fmt(float $v): string
    {
        return number_format($v, 0, '.', ',');
    }
}
