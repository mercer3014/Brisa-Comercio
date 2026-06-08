<?php

namespace App\Servicios;

use Illuminate\Support\Facades\DB;

/**
 * Calcula los titulares automaticos, indicadores grandes, rankings destacados y la
 * evolucion mensual que alimentan la PORTADA PUBLICA (Tarea 12).
 *
 * Desde la Tarea 14 lee de las VISTAS MATERIALIZADAS de resumen (resumen_anual_producto,
 * resumen_anual_pais, resumen_anual_departamento, resumen_mensual), que precalculan las
 * agregaciones por organizacion + gestion + flujo. Esas vistas guardan ya un campo `valor`
 * (FOB para exportacion, CIF frontera para importacion) coherente con AgregadorDashboard.
 *
 * Todo se filtra SIEMPRE por organizacion y, normalmente, por gestion.
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
        $g = DB::table('resumen_mensual')->where('organizacion_id', $orgId)->max('gestion');

        return $g !== null ? (int) $g : null;
    }

    /**
     * Arma todo el contenido de la portada para una organizacion y gestion.
     */
    public function portada(int $orgId, ?int $gestion): array
    {
        $org = DB::table('organizacion')->where('organizacion_id', $orgId)->first();
        $hayDatos = $gestion !== null && DB::table('resumen_mensual')
            ->where('organizacion_id', $orgId)->where('gestion', $gestion)->exists();

        return [
            'meta' => [
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

        return [
            'valor_exportado'     => $expo,
            'variacion_expo'      => $this->variacion($expo, $expoAnt),
            'valor_importado'     => $impo,
            'variacion_impo'      => $this->variacion($impo, $impoAnt),
            'balanza_comercial'   => $expo - $impo,
            'paises_destino'      => $paisesDestino,
            'productos_distintos' => $productos,
            'gestion_anterior'    => $gestion - 1,
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

    /**
     * Formatea un numero grande con separadores de miles.
     */
    private function fmt(float $v): string
    {
        return number_format($v, 0, '.', ',');
    }
}
