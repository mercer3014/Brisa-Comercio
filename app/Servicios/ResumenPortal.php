<?php

namespace App\Servicios;

use Illuminate\Support\Facades\DB;

/**
 * Calcula los titulares automaticos, indicadores grandes, rankings destacados y la
 * evolucion que alimentan la portada publica.
 *
 * INE usa microdatos resumidos en vistas materializadas. MERCOSUR usa series
 * agregadas anuales, manteniendo separadas las formas de dato.
 */
class ResumenPortal
{
    private const FLUJO_EXPORTACION = 1;
    private const FLUJO_IMPORTACION = 2;

    /**
     * Gestion (anio) mas reciente con datos para una organizacion. null si no hay datos.
     */
    public function gestionMasReciente(int $orgId): ?int
    {
        if ($this->esMercosur($orgId)) {
            $g = DB::table('serie_comercio_zona')->where('organizacion_id', $orgId)->max('gestion');

            return $g !== null ? (int) $g : null;
        }

        $g = DB::table('resumen_mensual')->where('organizacion_id', $orgId)->max('gestion');

        return $g !== null ? (int) $g : null;
    }

    /**
     * Arma todo el contenido de la portada para una organizacion y gestion.
     */
    public function portada(int $orgId, ?int $gestion): array
    {
        $org = DB::table('organizacion')->where('organizacion_id', $orgId)->first();

        if ($this->esMercosur($orgId)) {
            return $this->portadaMercosur($orgId, $gestion, $org);
        }

        $hayDatos = $gestion !== null && DB::table('resumen_mensual')
            ->where('organizacion_id', $orgId)->where('gestion', $gestion)->exists();

        return [
            'meta' => [
                'modo'         => 'microdato',
                'organizacion' => $org?->nombre,
                'sigla'        => $org?->sigla,
                'gestion'      => $gestion,
                'fuente'       => $this->fuente($org?->sigla, $gestion),
                'hay_datos'    => $hayDatos,
            ],
            'titulares'     => $hayDatos ? $this->titulares($orgId, $gestion) : [],
            'indicadores'   => $hayDatos ? $this->indicadores($orgId, $gestion) : null,
            'top_productos' => $hayDatos ? $this->topProductosExportados($orgId, $gestion, 5) : [],
            'top_destinos'  => $hayDatos ? $this->topDestinosExportacion($orgId, $gestion, 5) : [],
            'evolucion'     => $hayDatos ? $this->evolucionMensual($orgId, $gestion) : [],
        ];
    }

    /**
     * Frase de fuente y periodo, p.ej. "Fuente: INE - Bolivia. Datos 2024".
     */
    private function fuente(?string $sigla, ?int $gestion): string
    {
        $sigla = $sigla ?: 'INE';
        $periodo = $gestion ? " Datos {$gestion}." : '';

        return "Fuente: {$sigla} - Bolivia.{$periodo}";
    }

    private function fuenteMercosur(?int $gestion): string
    {
        $periodo = $gestion ? " Datos {$gestion}." : '';

        return "Fuente: MERCOSUR - series agregadas.{$periodo}";
    }

    private function esMercosur(int $orgId): bool
    {
        return DB::table('organizacion')
            ->where('organizacion_id', $orgId)
            ->where('sigla', 'MERCOSUR')
            ->exists();
    }

    private function portadaMercosur(int $orgId, ?int $gestion, $org): array
    {
        $hayDatos = $gestion !== null && $this->serieZonaMundo($orgId, $gestion)->exists();

        return [
            'meta' => [
                'modo'         => 'series_mercosur',
                'organizacion' => $org?->nombre,
                'sigla'        => $org?->sigla,
                'gestion'      => $gestion,
                'fuente'       => $this->fuenteMercosur($gestion),
                'hay_datos'    => $hayDatos,
            ],
            'titulares'     => $hayDatos ? $this->titularesMercosur($orgId, $gestion) : [],
            'indicadores'   => $hayDatos ? $this->indicadoresMercosur($orgId, $gestion) : null,
            'top_productos' => $hayDatos ? $this->topProductosExportadosMercosur($orgId, $gestion, 5) : [],
            'top_destinos'  => $hayDatos ? $this->topDestinosExportacionMercosur($orgId, $gestion, 5) : [],
            'evolucion'     => $hayDatos ? $this->evolucionAnualMercosur($orgId) : [],
        ];
    }

    private function serieZonaMundo(int $orgId, int $gestion)
    {
        return DB::table('serie_comercio_zona')
            ->where('serie_comercio_zona.organizacion_id', $orgId)
            ->where('serie_comercio_zona.gestion', $gestion)
            ->whereNull('serie_comercio_zona.zona_id');
    }

    private function serieProductoMundo(int $orgId, int $gestion)
    {
        return DB::table('serie_comercio_producto_zona')
            ->where('serie_comercio_producto_zona.organizacion_id', $orgId)
            ->where('serie_comercio_producto_zona.gestion', $gestion)
            ->whereNull('serie_comercio_producto_zona.zona_id');
    }

    /**
     * Titulares automaticos: producto/pais/departamento lider de cada flujo.
     */
    private function titulares(int $orgId, int $gestion): array
    {
        $titulares = [];

        if ($p = $this->liderProducto($orgId, $gestion, self::FLUJO_EXPORTACION)) {
            $titulares[] = $this->titular('producto_exportado',
                "En {$gestion}, el producto mas exportado fue {$p->label} (USD " . $this->fmt($p->valor) . ').', $p);
        }
        if ($p = $this->liderProducto($orgId, $gestion, self::FLUJO_IMPORTACION)) {
            $titulares[] = $this->titular('producto_importado',
                "El producto mas importado fue {$p->label} (USD " . $this->fmt($p->valor) . ').', $p);
        }
        if ($d = $this->liderPais($orgId, $gestion, self::FLUJO_EXPORTACION)) {
            $titulares[] = $this->titular('destino_exportacion',
                "El principal destino de las exportaciones fue {$d->label}.", $d);
        }
        if ($o = $this->liderPais($orgId, $gestion, self::FLUJO_IMPORTACION)) {
            $titulares[] = $this->titular('origen_importacion',
                "El principal origen de las importaciones fue {$o->label}.", $o);
        }
        if ($dep = $this->liderDepartamento($orgId, $gestion)) {
            $titulares[] = $this->titular('departamento_exportador',
                "El departamento que mas exporto fue {$dep->label}.", $dep);
        }

        return $titulares;
    }

    private function titularesMercosur(int $orgId, int $gestion): array
    {
        $titulares = [];

        if ($p = $this->liderProductoMercosur($orgId, $gestion, 'exportaciones_usd')) {
            $titulares[] = $this->titular('producto_exportado',
                "En {$gestion}, el producto mas exportado en MERCOSUR fue {$p->label} (USD " . $this->fmt($p->valor) . ').', $p);
        }
        if ($p = $this->liderProductoMercosur($orgId, $gestion, 'importaciones_cif_usd')) {
            $titulares[] = $this->titular('producto_importado',
                "El producto mas importado en MERCOSUR fue {$p->label} (USD " . $this->fmt($p->valor) . ').', $p);
        }
        if ($d = $this->liderPaisMercosur($orgId, $gestion, 'exportaciones_usd')) {
            $titulares[] = $this->titular('destino_exportacion',
                "El principal pais por exportaciones fue {$d->label}.", $d);
        }
        if ($o = $this->liderPaisMercosur($orgId, $gestion, 'importaciones_cif_usd')) {
            $titulares[] = $this->titular('origen_importacion',
                "El principal pais por importaciones fue {$o->label}.", $o);
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

    private function liderProducto(int $orgId, int $gestion, int $flujo)
    {
        return DB::table('resumen_anual_producto as r')
            ->join('producto as p', 'p.producto_id', '=', 'r.producto_id')
            ->where('r.organizacion_id', $orgId)->where('r.gestion', $gestion)->where('r.flujo_id', $flujo)
            ->selectRaw('p.descripcion as label')
            ->selectRaw('SUM(r.valor) as valor')
            ->groupBy('p.descripcion')
            ->orderByDesc('valor')
            ->first();
    }

    private function liderPais(int $orgId, int $gestion, int $flujo)
    {
        return DB::table('resumen_anual_pais as r')
            ->join('pais as pa', 'pa.pais_id', '=', 'r.pais_id')
            ->where('r.organizacion_id', $orgId)->where('r.gestion', $gestion)->where('r.flujo_id', $flujo)
            ->selectRaw('pa.nombre as label')
            ->selectRaw('SUM(r.valor) as valor')
            ->groupBy('pa.nombre')
            ->orderByDesc('valor')
            ->first();
    }

    private function liderDepartamento(int $orgId, int $gestion)
    {
        return DB::table('resumen_anual_departamento as r')
            ->join('departamento as d', 'd.departamento_id', '=', 'r.departamento_id')
            ->where('r.organizacion_id', $orgId)->where('r.gestion', $gestion)->where('r.flujo_id', self::FLUJO_EXPORTACION)
            ->selectRaw('d.nombre as label')
            ->selectRaw('SUM(r.valor) as valor')
            ->groupBy('d.nombre')
            ->orderByDesc('valor')
            ->first();
    }

    private function liderProductoMercosur(int $orgId, int $gestion, string $campo)
    {
        return $this->serieProductoMundo($orgId, $gestion)
            ->selectRaw("COALESCE(NULLIF(ncm_descripcion, ''), ncm_codigo, 'Sin producto') as label")
            ->selectRaw("SUM(COALESCE({$campo},0)) as valor")
            ->groupByRaw("COALESCE(NULLIF(ncm_descripcion, ''), ncm_codigo, 'Sin producto')")
            ->orderByDesc('valor')
            ->first();
    }

    private function liderPaisMercosur(int $orgId, int $gestion, string $campo)
    {
        return $this->serieZonaMundo($orgId, $gestion)
            ->leftJoin('pais as p', 'p.pais_id', '=', 'serie_comercio_zona.pais_id')
            ->selectRaw("COALESCE(p.nombre, serie_comercio_zona.pais_nombre_original, 'Sin pais') as label")
            ->selectRaw("SUM(COALESCE(serie_comercio_zona.{$campo},0)) as valor")
            ->groupByRaw("COALESCE(p.nombre, serie_comercio_zona.pais_nombre_original, 'Sin pais')")
            ->orderByDesc('valor')
            ->first();
    }

    /**
     * Indicadores grandes (KPIs) con variacion interanual.
     */
    private function indicadores(int $orgId, int $gestion): array
    {
        $expo = $this->valorAnual($orgId, $gestion, self::FLUJO_EXPORTACION);
        $impo = $this->valorAnual($orgId, $gestion, self::FLUJO_IMPORTACION);
        $expoAnt = $this->valorAnual($orgId, $gestion - 1, self::FLUJO_EXPORTACION);
        $impoAnt = $this->valorAnual($orgId, $gestion - 1, self::FLUJO_IMPORTACION);

        $paisesDestino = (int) DB::table('resumen_anual_pais')
            ->where('organizacion_id', $orgId)->where('gestion', $gestion)->where('flujo_id', self::FLUJO_EXPORTACION)
            ->count();

        $productos = (int) DB::table('resumen_anual_producto')
            ->where('organizacion_id', $orgId)->where('gestion', $gestion)
            ->distinct()->count('producto_id');

        $volumen = $this->volumenAnual($orgId, $gestion, self::FLUJO_EXPORTACION);
        $volumenAnt = $this->volumenAnual($orgId, $gestion - 1, self::FLUJO_EXPORTACION);

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

    private function indicadoresMercosur(int $orgId, int $gestion): array
    {
        $actual = $this->totalesMercosurGestion($orgId, $gestion);
        $anterior = $this->totalesMercosurGestion($orgId, $gestion - 1);

        $paisesDestino = (int) $this->serieZonaMundo($orgId, $gestion)
            ->whereNotNull('pais_id')
            ->distinct()
            ->count('pais_id');

        $productos = (int) $this->serieProductoMundo($orgId, $gestion)
            ->whereNotNull('ncm_codigo')
            ->distinct()
            ->count('ncm_codigo');

        return [
            'valor_exportado'     => $actual['expo'],
            'variacion_expo'      => $this->variacion($actual['expo'], $anterior['expo']),
            'valor_importado'     => $actual['impo'],
            'variacion_impo'      => $this->variacion($actual['impo'], $anterior['impo']),
            'balanza_comercial'   => $actual['expo'] - $actual['impo'],
            'volumen_exportado'   => $actual['volumen_expo'],
            'variacion_volumen'   => $this->variacion($actual['volumen_expo'], $anterior['volumen_expo']),
            'paises_destino'      => $paisesDestino,
            'productos_distintos' => $productos,
            'gestion_anterior'    => $gestion - 1,
        ];
    }

    private function totalesMercosurGestion(int $orgId, int $gestion): array
    {
        $row = $this->serieZonaMundo($orgId, $gestion)
            ->selectRaw('COALESCE(SUM(exportaciones_usd),0) as expo')
            ->selectRaw('COALESCE(SUM(importaciones_cif_usd),0) as impo')
            ->selectRaw('COALESCE(SUM(volumen_export_kg),0) as volumen_expo')
            ->first();

        return [
            'expo' => (float) ($row->expo ?? 0),
            'impo' => (float) ($row->impo ?? 0),
            'volumen_expo' => (float) ($row->volumen_expo ?? 0),
        ];
    }

    /**
     * Valor total de un flujo en una gestion, desde resumen_mensual.
     */
    private function valorAnual(int $orgId, int $gestion, int $flujo): float
    {
        return (float) DB::table('resumen_mensual')
            ->where('organizacion_id', $orgId)->where('gestion', $gestion)->where('flujo_id', $flujo)
            ->sum('valor');
    }

    /**
     * Volumen (peso bruto en kg) de un flujo en una gestion, desde resumen_mensual.
     */
    private function volumenAnual(int $orgId, int $gestion, int $flujo): float
    {
        return (float) DB::table('resumen_mensual')
            ->where('organizacion_id', $orgId)->where('gestion', $gestion)->where('flujo_id', $flujo)
            ->sum('peso_bruto');
    }

    private function variacion(float $actual, float $anterior): ?float
    {
        if ($anterior <= 0) {
            return null;
        }

        return round((($actual - $anterior) / $anterior) * 100, 1);
    }

    /**
     * Top N productos exportados (ranking destacado).
     */
    private function topProductosExportados(int $orgId, int $gestion, int $n): array
    {
        return DB::table('resumen_anual_producto as r')
            ->join('producto as p', 'p.producto_id', '=', 'r.producto_id')
            ->where('r.organizacion_id', $orgId)->where('r.gestion', $gestion)->where('r.flujo_id', self::FLUJO_EXPORTACION)
            ->selectRaw('p.descripcion as label')
            ->selectRaw('SUM(r.valor) as valor')
            ->groupBy('p.descripcion')
            ->orderByDesc('valor')
            ->limit($n)
            ->get()
            ->map(fn ($r) => [
                'label' => mb_strimwidth((string) $r->label, 0, 45, '...'),
                'valor' => (float) $r->valor,
            ])->all();
    }

    private function topProductosExportadosMercosur(int $orgId, int $gestion, int $n): array
    {
        return $this->serieProductoMundo($orgId, $gestion)
            ->selectRaw("COALESCE(NULLIF(ncm_descripcion, ''), ncm_codigo, 'Sin producto') as label")
            ->selectRaw('SUM(COALESCE(exportaciones_usd,0)) as valor')
            ->groupByRaw("COALESCE(NULLIF(ncm_descripcion, ''), ncm_codigo, 'Sin producto')")
            ->orderByDesc('valor')
            ->limit($n)
            ->get()
            ->map(fn ($r) => [
                'label' => mb_strimwidth((string) $r->label, 0, 45, '...'),
                'valor' => (float) $r->valor,
            ])->all();
    }

    /**
     * Top N paises destino de exportacion (ranking destacado).
     */
    private function topDestinosExportacion(int $orgId, int $gestion, int $n): array
    {
        return DB::table('resumen_anual_pais as r')
            ->join('pais as pa', 'pa.pais_id', '=', 'r.pais_id')
            ->where('r.organizacion_id', $orgId)->where('r.gestion', $gestion)->where('r.flujo_id', self::FLUJO_EXPORTACION)
            ->selectRaw('pa.nombre as label')
            ->selectRaw('SUM(r.valor) as valor')
            ->groupBy('pa.nombre')
            ->orderByDesc('valor')
            ->limit($n)
            ->get()
            ->map(fn ($r) => ['label' => $r->label, 'valor' => (float) $r->valor])->all();
    }

    private function topDestinosExportacionMercosur(int $orgId, int $gestion, int $n): array
    {
        return $this->serieZonaMundo($orgId, $gestion)
            ->leftJoin('pais as p', 'p.pais_id', '=', 'serie_comercio_zona.pais_id')
            ->selectRaw("COALESCE(p.nombre, serie_comercio_zona.pais_nombre_original, 'Sin pais') as label")
            ->selectRaw('SUM(COALESCE(serie_comercio_zona.exportaciones_usd,0)) as valor')
            ->groupByRaw("COALESCE(p.nombre, serie_comercio_zona.pais_nombre_original, 'Sin pais')")
            ->orderByDesc('valor')
            ->limit($n)
            ->get()
            ->map(fn ($r) => ['label' => $r->label, 'valor' => (float) $r->valor])->all();
    }

    /**
     * Evolucion mensual del valor exportado e importado del anio (desde resumen_mensual).
     */
    private function evolucionMensual(int $orgId, int $gestion): array
    {
        return DB::table('resumen_mensual')
            ->where('organizacion_id', $orgId)->where('gestion', $gestion)
            ->selectRaw('mes')
            ->selectRaw('SUM(CASE WHEN flujo_id = ' . self::FLUJO_EXPORTACION . ' THEN valor ELSE 0 END) as expo')
            ->selectRaw('SUM(CASE WHEN flujo_id = ' . self::FLUJO_IMPORTACION . ' THEN valor ELSE 0 END) as impo')
            ->groupBy('mes')
            ->orderBy('mes')
            ->get()
            ->map(fn ($r) => [
                'mes'  => (int) $r->mes,
                'expo' => (float) $r->expo,
                'impo' => (float) $r->impo,
            ])->all();
    }

    private function evolucionAnualMercosur(int $orgId): array
    {
        return DB::table('serie_comercio_zona')
            ->where('organizacion_id', $orgId)
            ->whereNull('zona_id')
            ->selectRaw('gestion')
            ->selectRaw('SUM(COALESCE(exportaciones_usd,0)) as expo')
            ->selectRaw('SUM(COALESCE(importaciones_cif_usd,0)) as impo')
            ->groupBy('gestion')
            ->orderBy('gestion')
            ->get()
            ->map(fn ($r) => [
                'periodo' => (string) $r->gestion,
                'expo'    => (float) $r->expo,
                'impo'    => (float) $r->impo,
            ])->all();
    }

    /**
     * Formatea un numero grande con separadores de miles.
     */
    private function fmt(float $v): string
    {
        return number_format($v, 0, '.', ',');
    }
}
