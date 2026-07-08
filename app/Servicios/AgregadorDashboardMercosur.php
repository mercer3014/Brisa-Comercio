<?php

namespace App\Servicios;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Version MERCOSUR de AgregadorDashboard: mismo contrato de salida (misma
 * forma de arreglos) que consume Dashboards/Index.vue, pero leyendo de las
 * tablas propias de MERCOSUR (serie_comercio_zona / serie_comercio_producto_zona)
 * en vez del microdato del INE. Así el dashboard admin puede mostrar cualquiera
 * de las dos organizaciones con la arquitectura que le corresponde a cada una,
 * sin que la vista sepa de donde viene el dato.
 *
 * Diferencias de forma frente al INE que no tienen equivalente en MERCOSUR
 * (sin desagregar por departamento ni por medio de transporte, sin
 * granularidad mensual real) devuelven series vacias en vez de inventar datos.
 */
class AgregadorDashboardMercosur
{
    private const ORG_ID = 3;

    private function base(?int $gestion = null): Builder
    {
        $q = DB::table('serie_comercio_zona');
        if ($gestion) {
            $q->where('gestion', $gestion);
        }

        return $q;
    }

    private string $valorExpo = 'COALESCE(exportaciones_usd,0)';
    private string $valorImpo = 'COALESCE(importaciones_cif_usd,0)';

    public function kpis(?int $gestion = null): array
    {
        $row = $this->base($gestion)
            ->selectRaw("SUM({$this->valorExpo}) as expo")
            ->selectRaw("SUM({$this->valorImpo}) as impo")
            ->selectRaw('SUM(COALESCE(volumen_export_kg,0) + COALESCE(volumen_import_kg,0)) as volumen')
            ->selectRaw('COUNT(*) as registros')
            ->first();

        $expo = (float) $row->expo;
        $impo = (float) $row->impo;
        $valorTotal = $expo + $impo;
        $volumen = (float) $row->volumen;

        $variacion = null;
        if ($gestion) {
            $ant = $this->base($gestion - 1)
                ->selectRaw("SUM({$this->valorExpo} + {$this->valorImpo}) as v")->value('v');
            $ant = (float) $ant;
            if ($ant > 0) {
                $variacion = round((($valorTotal - $ant) / $ant) * 100, 1);
            }
        }

        return [
            'valor_total'        => $valorTotal,
            'valor_exportacion'  => $expo,
            'valor_importacion'  => $impo,
            'balanza_comercial'  => $expo - $impo,
            'peso_bruto'         => $volumen,
            'peso_neto'          => $volumen,
            'registros'          => (int) $row->registros,
            'precio_implicito'   => $volumen > 0 ? round($valorTotal / $volumen, 2) : null,
            'variacion_interanual' => $variacion,
        ];
    }

    /**
     * MERCOSUR no trae desglose mensual (series anuales, mes=0). Se devuelve un
     * punto por gestión, IGNORANDO el filtro de gestión: filtrando a un solo
     * año quedaria un gráfico de una sola barra sin poder ver evolución.
     */
    public function evolucionMensual(?int $gestion = null): array
    {
        return $this->base(null)
            ->selectRaw('gestion')
            ->selectRaw("SUM({$this->valorExpo} + {$this->valorImpo}) as valor")
            ->selectRaw('SUM(COALESCE(volumen_export_kg,0) + COALESCE(volumen_import_kg,0)) as peso')
            ->groupBy('gestion')->orderBy('gestion')
            ->get()
            ->map(fn ($r) => [
                'periodo' => (string) $r->gestion,
                'valor'   => (float) $r->valor,
                'peso'    => (float) $r->peso,
            ])->all();
    }

    public function evolucionAnual(): array
    {
        return $this->base()
            ->selectRaw('gestion')
            ->selectRaw("SUM({$this->valorExpo}) as expo")
            ->selectRaw("SUM({$this->valorImpo}) as impo")
            ->groupBy('gestion')->orderBy('gestion')
            ->get()
            ->map(fn ($r) => [
                'gestion' => (int) $r->gestion,
                'expo'    => (float) $r->expo,
                'impo'    => (float) $r->impo,
                'balanza' => (float) $r->expo - (float) $r->impo,
            ])->all();
    }

    public function topPaises(?int $gestion = null, int $n = 10): array
    {
        return $this->base($gestion)
            ->join('pais as pa', 'pa.pais_id', '=', 'serie_comercio_zona.pais_id')
            ->selectRaw('pa.nombre as label')
            ->selectRaw("SUM({$this->valorExpo} + {$this->valorImpo}) as valor")
            ->groupBy('pa.nombre')->orderByDesc('valor')->limit($n)
            ->get()->map(fn ($r) => ['label' => $r->label, 'valor' => (float) $r->valor])->all();
    }

    public function topProductos(?int $gestion = null, int $n = 10): array
    {
        $q = DB::table('serie_comercio_producto_zona');
        if ($gestion) {
            $q->where('gestion', $gestion);
        }

        return $q
            ->selectRaw('ncm_codigo, MAX(ncm_descripcion) as label')
            ->selectRaw("SUM({$this->valorExpo} + {$this->valorImpo}) as valor")
            ->groupBy('ncm_codigo')->orderByDesc('valor')->limit($n)
            ->get()->map(fn ($r) => [
                'label'   => mb_strimwidth((string) ($r->label ?: $r->ncm_codigo), 0, 40, '...'),
                'nandina' => $r->ncm_codigo,
                'valor'   => (float) $r->valor,
            ])->all();
    }

    /** Zona geoeconomica: es la dimension principal de MERCOSUR, no un cruce como en INE. */
    public function distribucionZona(?int $gestion = null): array
    {
        return $this->base($gestion)
            ->join('zona_geoeconomica as z', 'z.zona_id', '=', 'serie_comercio_zona.zona_id')
            ->selectRaw('z.descripcion as label')
            ->selectRaw("SUM({$this->valorExpo} + {$this->valorImpo}) as valor")
            ->groupBy('z.descripcion')->orderByDesc('valor')
            ->get()->map(fn ($r) => ['label' => $r->label, 'valor' => (float) $r->valor])->all();
    }

    /** MERCOSUR no tiene desglose por departamento (eso es específico del microdato del INE). */
    public function distribucionDepartamento(): array
    {
        return [];
    }

    public function participacionPais(?int $gestion = null, int $n = 8): array
    {
        $top = $this->topPaises($gestion, $n);
        $total = array_sum(array_column($top, 'valor'));

        return array_map(fn ($r) => [
            'label' => $r['label'],
            'valor' => $r['valor'],
            'porcentaje' => $total > 0 ? round($r['valor'] / $total * 100, 1) : 0,
        ], $top);
    }

    /** MERCOSUR no tiene desglose por medio de transporte (eso es específico del microdato del INE). */
    public function distribucionMedio(): array
    {
        return [];
    }
}
