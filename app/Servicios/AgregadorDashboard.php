<?php

namespace App\Servicios;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Calcula indicadores y series para los dashboards. Todas las agregaciones se
 * resuelven en PostgreSQL (GROUP BY/SUM), nunca en el frontend.
 *
 * Convencion: el valor de EXPORTACION vive en valor_fob_usd y el de IMPORTACION
 * en valor_cif_frontera_usd (asi los puebla el ETL). Eso permite separar flujos
 * sin depender del nombre del tipo de operacion.
 */
class AgregadorDashboard
{
    private function base(int $orgId, ?int $gestion = null): Builder
    {
        $q = DB::table('operacion_comercio_exterior as o')
            ->join('tiempo as t', 't.tiempo_id', '=', 'o.tiempo_id')
            ->where('o.organizacion_id', $orgId);

        if ($gestion) {
            $q->where('t.gestion', $gestion);
        }

        return $q;
    }

    // Expresiones reutilizables
    private string $valorExpo = 'COALESCE(o.valor_fob_usd,0)';
    private string $valorImpo = 'COALESCE(o.valor_cif_frontera_usd,0)';

    /**
     * KPIs principales. Incluye variacion interanual si se pasa gestion.
     */
    public function kpis(int $orgId, ?int $gestion = null): array
    {
        $row = $this->base($orgId, $gestion)
            ->selectRaw("SUM({$this->valorExpo}) as expo")
            ->selectRaw("SUM({$this->valorImpo}) as impo")
            ->selectRaw('SUM(COALESCE(o.peso_bruto_kg,0)) as peso_bruto')
            ->selectRaw('SUM(COALESCE(o.peso_neto_kg,0)) as peso_neto')
            ->selectRaw('COUNT(*) as registros')
            ->first();

        $expo = (float) $row->expo;
        $impo = (float) $row->impo;
        $valorTotal = $expo + $impo;
        $pesoNeto = (float) $row->peso_neto;

        // Variacion interanual (valor total) respecto al anio anterior.
        $variacion = null;
        if ($gestion) {
            $ant = $this->base($orgId, $gestion - 1)
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
            'peso_bruto'         => (float) $row->peso_bruto,
            'peso_neto'          => $pesoNeto,
            'registros'          => (int) $row->registros,
            'precio_implicito'   => $pesoNeto > 0 ? round($valorTotal / $pesoNeto, 2) : null,
            'variacion_interanual' => $variacion,
        ];
    }

    /**
     * Evolucion mensual del valor y peso (por gestion+mes).
     */
    public function evolucionMensual(int $orgId, ?int $gestion = null): array
    {
        return $this->base($orgId, $gestion)
            ->selectRaw('t.gestion, t.mes')
            ->selectRaw("SUM({$this->valorExpo} + {$this->valorImpo}) as valor")
            ->selectRaw('SUM(COALESCE(o.peso_bruto_kg,0)) as peso')
            ->groupBy('t.gestion', 't.mes')
            ->orderBy('t.gestion')->orderBy('t.mes')
            ->get()
            ->map(fn ($r) => [
                'periodo' => $r->gestion.'-'.str_pad($r->mes, 2, '0', STR_PAD_LEFT),
                'valor'   => (float) $r->valor,
                'peso'    => (float) $r->peso,
            ])->all();
    }

    /**
     * Evolucion anual (valor expo, impo y balanza por gestion).
     */
    public function evolucionAnual(int $orgId): array
    {
        return $this->base($orgId)
            ->selectRaw('t.gestion')
            ->selectRaw("SUM({$this->valorExpo}) as expo")
            ->selectRaw("SUM({$this->valorImpo}) as impo")
            ->groupBy('t.gestion')->orderBy('t.gestion')
            ->get()
            ->map(fn ($r) => [
                'gestion' => (int) $r->gestion,
                'expo'    => (float) $r->expo,
                'impo'    => (float) $r->impo,
                'balanza' => (float) $r->expo - (float) $r->impo,
            ])->all();
    }

    /**
     * Top N paises por valor.
     */
    public function topPaises(int $orgId, ?int $gestion = null, int $n = 10): array
    {
        return $this->base($orgId, $gestion)
            ->join('pais as pa', 'pa.pais_id', '=', 'o.pais_id')
            ->selectRaw('pa.nombre as label')
            ->selectRaw("SUM({$this->valorExpo} + {$this->valorImpo}) as valor")
            ->groupBy('pa.nombre')->orderByDesc('valor')->limit($n)
            ->get()->map(fn ($r) => ['label' => $r->label, 'valor' => (float) $r->valor])->all();
    }

    /**
     * Top N productos por valor.
     */
    public function topProductos(int $orgId, ?int $gestion = null, int $n = 10): array
    {
        return $this->base($orgId, $gestion)
            ->join('producto as p', 'p.producto_id', '=', 'o.producto_id')
            ->selectRaw('p.codigo_nandina, p.descripcion as label')
            ->selectRaw("SUM({$this->valorExpo} + {$this->valorImpo}) as valor")
            ->groupBy('p.codigo_nandina', 'p.descripcion')->orderByDesc('valor')->limit($n)
            ->get()->map(fn ($r) => [
                'label' => mb_strimwidth($r->label, 0, 40, '...'),
                'nandina' => $r->codigo_nandina,
                'valor' => (float) $r->valor,
            ])->all();
    }

    /**
     * Distribucion por zona geoeconomica.
     */
    public function distribucionZona(int $orgId, ?int $gestion = null): array
    {
        return $this->base($orgId, $gestion)
            ->join('pais as pa', 'pa.pais_id', '=', 'o.pais_id')
            ->join('zona_geoeconomica as z', 'z.zona_id', '=', 'pa.zona_id')
            ->selectRaw('z.descripcion as label')
            ->selectRaw("SUM({$this->valorExpo} + {$this->valorImpo}) as valor")
            ->groupBy('z.descripcion')->orderByDesc('valor')
            ->get()->map(fn ($r) => ['label' => $r->label, 'valor' => (float) $r->valor])->all();
    }

    /**
     * Distribucion por departamento.
     */
    public function distribucionDepartamento(int $orgId, ?int $gestion = null): array
    {
        return $this->base($orgId, $gestion)
            ->join('departamento as d', 'd.departamento_id', '=', 'o.departamento_id')
            ->selectRaw('d.nombre as label')
            ->selectRaw("SUM({$this->valorExpo} + {$this->valorImpo}) as valor")
            ->groupBy('d.nombre')->orderByDesc('valor')
            ->get()->map(fn ($r) => ['label' => $r->label, 'valor' => (float) $r->valor])->all();
    }

    /**
     * Participacion por pais (porcentaje del valor total).
     */
    public function participacionPais(int $orgId, ?int $gestion = null, int $n = 8): array
    {
        $top = $this->topPaises($orgId, $gestion, $n);
        $total = array_sum(array_column($top, 'valor'));

        return array_map(fn ($r) => [
            'label' => $r['label'],
            'valor' => $r['valor'],
            'porcentaje' => $total > 0 ? round($r['valor'] / $total * 100, 1) : 0,
        ], $top);
    }

    /**
     * Distribucion por medio de transporte (logistico).
     */
    public function distribucionMedio(int $orgId, ?int $gestion = null): array
    {
        return $this->base($orgId, $gestion)
            ->join('medio_transporte as m', 'm.medio_id', '=', 'o.medio_id')
            ->selectRaw('m.descripcion as label')
            ->selectRaw('SUM(COALESCE(o.peso_bruto_kg,0)) as peso')
            ->selectRaw("SUM({$this->valorExpo} + {$this->valorImpo}) as valor")
            ->groupBy('m.descripcion')->orderByDesc('valor')
            ->get()->map(fn ($r) => ['label' => $r->label, 'valor' => (float) $r->valor, 'peso' => (float) $r->peso])->all();
    }
}
