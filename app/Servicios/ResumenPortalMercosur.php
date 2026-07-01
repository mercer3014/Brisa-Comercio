<?php

namespace App\Servicios;

use Illuminate\Support\Facades\DB;

/**
 * Version MERCOSUR de ResumenPortal: misma forma de salida (mismo contrato
 * que consume Portal/Inicio.vue) pero leyendo de las tablas propias de
 * MERCOSUR (serie_comercio_zona / serie_comercio_producto_zona) en vez del
 * microdato del INE. Asi la portada publica puede mostrar cualquiera de las
 * dos organizaciones con la arquitectura que le corresponde, sin que la
 * vista necesite saber la diferencia.
 *
 * MERCOSUR no tiene granularidad mensual (series anuales) ni departamento:
 * "evolucion" (que es por mes) y el titular de departamento se omiten en vez
 * de inventar datos.
 */
class ResumenPortalMercosur
{
    private const ORG_ID = 3;

    public function gestionMasReciente(): ?int
    {
        $g = DB::table('serie_comercio_zona')->max('gestion');

        return $g !== null ? (int) $g : null;
    }

    public function portada(?int $gestion): array
    {
        $org = DB::table('organizacion')->where('organizacion_id', self::ORG_ID)->first();
        $hayDatos = $gestion !== null && DB::table('serie_comercio_zona')->where('gestion', $gestion)->exists();

        return [
            'meta' => [
                'organizacion' => $org?->nombre,
                'sigla'        => $org?->sigla,
                'gestion'      => $gestion,
                'fuente'       => 'Fuente: MERCOSUR - Bolivia.'.($gestion ? " Datos {$gestion}." : ''),
                'hay_datos'    => $hayDatos,
            ],
            'titulares'     => $hayDatos ? $this->titulares($gestion) : [],
            'indicadores'   => $hayDatos ? $this->indicadores($gestion) : null,
            'top_productos' => $hayDatos ? $this->topProductos($gestion, 5) : [],
            'top_destinos'  => $hayDatos ? $this->topDestinos($gestion, 5) : [],
            'evolucion'     => [], // MERCOSUR es anual: no hay desglose mensual que mostrar aqui.
        ];
    }

    private function titulares(int $gestion): array
    {
        $titulares = [];

        if ($p = $this->liderProducto($gestion, 'expo')) {
            $titulares[] = $this->titular('producto_exportado',
                "En {$gestion}, el producto mas exportado fue {$p->label} (USD ".$this->fmt($p->valor).').', $p);
        }
        if ($p = $this->liderProducto($gestion, 'impo')) {
            $titulares[] = $this->titular('producto_importado',
                "El producto mas importado fue {$p->label} (USD ".$this->fmt($p->valor).').', $p);
        }
        if ($d = $this->liderPais($gestion, 'expo')) {
            $titulares[] = $this->titular('destino_exportacion',
                "El principal destino de las exportaciones fue {$d->label}.", $d);
        }
        if ($o = $this->liderPais($gestion, 'impo')) {
            $titulares[] = $this->titular('origen_importacion',
                "El principal origen de las importaciones fue {$o->label}.", $o);
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

    private function liderProducto(int $gestion, string $flujo)
    {
        $col = $flujo === 'expo' ? 'exportaciones_usd' : 'importaciones_cif_usd';

        return DB::table('serie_comercio_producto_zona')
            ->where('gestion', $gestion)
            ->selectRaw('MAX(ncm_descripcion) as label')
            ->selectRaw("SUM(COALESCE({$col},0)) as valor")
            ->groupBy('ncm_codigo')
            ->orderByDesc('valor')
            ->first();
    }

    private function liderPais(int $gestion, string $flujo)
    {
        $col = $flujo === 'expo' ? 'exportaciones_usd' : 'importaciones_cif_usd';

        return DB::table('serie_comercio_zona as sz')
            ->join('pais as pa', 'pa.pais_id', '=', 'sz.pais_id')
            ->where('sz.gestion', $gestion)
            ->selectRaw('pa.nombre as label')
            ->selectRaw("SUM(COALESCE(sz.{$col},0)) as valor")
            ->groupBy('pa.nombre')
            ->orderByDesc('valor')
            ->first();
    }

    private function indicadores(int $gestion): array
    {
        $expo = $this->valorAnual($gestion, 'expo');
        $impo = $this->valorAnual($gestion, 'impo');
        $expoAnt = $this->valorAnual($gestion - 1, 'expo');
        $impoAnt = $this->valorAnual($gestion - 1, 'impo');

        $paisesDestino = (int) DB::table('serie_comercio_zona')
            ->where('gestion', $gestion)->where('exportaciones_usd', '>', 0)
            ->distinct()->count('pais_id');

        $productos = (int) DB::table('serie_comercio_producto_zona')
            ->where('gestion', $gestion)
            ->distinct()->count('ncm_codigo');

        $volumen = $this->volumenAnual($gestion);
        $volumenAnt = $this->volumenAnual($gestion - 1);

        return [
            'valor_exportado'     => $expo,
            'variacion_expo'      => $this->variacion($expo, $expoAnt),
            'valor_importado'     => $impo,
            'variacion_impo'      => $this->variacion($impo, $impoAnt),
            'balanza_comercial'   => $expo - $impo,
            'volumen_exportado'   => $volumen,
            'variacion_volumen'   => $this->variacion($volumen, $volumenAnt),
            'paises_destino'      => $paisesDestino,
            'productos_distintos' => $productos,
            'gestion_anterior'    => $gestion - 1,
        ];
    }

    private function valorAnual(int $gestion, string $flujo): float
    {
        $col = $flujo === 'expo' ? 'exportaciones_usd' : 'importaciones_cif_usd';

        return (float) DB::table('serie_comercio_zona')->where('gestion', $gestion)->sum($col);
    }

    private function volumenAnual(int $gestion): float
    {
        return (float) DB::table('serie_comercio_zona')->where('gestion', $gestion)->sum('volumen_export_kg');
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
        return DB::table('serie_comercio_producto_zona')
            ->where('gestion', $gestion)
            ->selectRaw('MAX(ncm_descripcion) as label')
            ->selectRaw('SUM(COALESCE(exportaciones_usd,0)) as valor')
            ->groupBy('ncm_codigo')
            ->orderByDesc('valor')
            ->limit($n)
            ->get()
            ->map(fn ($r) => [
                'label' => mb_strimwidth((string) $r->label, 0, 45, '...'),
                'valor' => (float) $r->valor,
            ])->all();
    }

    private function topDestinos(int $gestion, int $n): array
    {
        return DB::table('serie_comercio_zona as sz')
            ->join('pais as pa', 'pa.pais_id', '=', 'sz.pais_id')
            ->where('sz.gestion', $gestion)
            ->selectRaw('pa.nombre as label')
            ->selectRaw('SUM(COALESCE(sz.exportaciones_usd,0)) as valor')
            ->groupBy('pa.nombre')
            ->orderByDesc('valor')
            ->limit($n)
            ->get()
            ->map(fn ($r) => ['label' => $r->label, 'valor' => (float) $r->valor])->all();
    }

    private function fmt(float $v): string
    {
        return number_format($v, 0, '.', ',');
    }
}
