<?php

namespace App\Servicios;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Calcula indicadores y series para dashboards.
 *
 * Las organizaciones de microdatos usan operacion_comercio_exterior.
 * MERCOSUR usa serie_comercio_zona y serie_comercio_producto_zona.
 */
class AgregadorDashboard
{
    private string $valorExpo = 'COALESCE(o.valor_fob_usd,0)';
    private string $valorImpo = 'COALESCE(o.valor_cif_frontera_usd,0)';

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

    public function kpis(int $orgId, ?int $gestion = null): array
    {
        if ($this->esMercosur($orgId)) {
            return $this->kpisMercosur($orgId, $gestion);
        }

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
            'valor_total' => $valorTotal,
            'valor_exportacion' => $expo,
            'valor_importacion' => $impo,
            'balanza_comercial' => $expo - $impo,
            'peso_bruto' => (float) $row->peso_bruto,
            'peso_neto' => $pesoNeto,
            'registros' => (int) $row->registros,
            'precio_implicito' => $pesoNeto > 0 ? round($valorTotal / $pesoNeto, 2) : null,
            'variacion_interanual' => $variacion,
        ];
    }

    public function evolucionMensual(int $orgId, ?int $gestion = null): array
    {
        if ($this->esMercosur($orgId)) {
            return $this->evolucionAnualMercosur($orgId)
                ->filter(fn ($r) => ! $gestion || (int) $r['gestion'] === (int) $gestion)
                ->map(fn ($r) => [
                    'periodo' => (string) $r['gestion'],
                    'valor' => $r['expo'] + $r['impo'],
                    'peso' => $r['peso'],
                ])->values()->all();
        }

        return $this->base($orgId, $gestion)
            ->selectRaw('t.gestion, t.mes')
            ->selectRaw("SUM({$this->valorExpo} + {$this->valorImpo}) as valor")
            ->selectRaw('SUM(COALESCE(o.peso_bruto_kg,0)) as peso')
            ->groupBy('t.gestion', 't.mes')
            ->orderBy('t.gestion')->orderBy('t.mes')
            ->get()
            ->map(fn ($r) => [
                'periodo' => $r->gestion.'-'.str_pad($r->mes, 2, '0', STR_PAD_LEFT),
                'valor' => (float) $r->valor,
                'peso' => (float) $r->peso,
            ])->all();
    }

    public function evolucionAnual(int $orgId): array
    {
        if ($this->esMercosur($orgId)) {
            return $this->evolucionAnualMercosur($orgId)
                ->map(fn ($r) => [
                    'gestion' => $r['gestion'],
                    'expo' => $r['expo'],
                    'impo' => $r['impo'],
                    'balanza' => $r['balanza'],
                ])->all();
        }

        return $this->base($orgId)
            ->selectRaw('t.gestion')
            ->selectRaw("SUM({$this->valorExpo}) as expo")
            ->selectRaw("SUM({$this->valorImpo}) as impo")
            ->groupBy('t.gestion')->orderBy('t.gestion')
            ->get()
            ->map(fn ($r) => [
                'gestion' => (int) $r->gestion,
                'expo' => (float) $r->expo,
                'impo' => (float) $r->impo,
                'balanza' => (float) $r->expo - (float) $r->impo,
            ])->all();
    }

    public function topPaises(int $orgId, ?int $gestion = null, int $n = 10): array
    {
        if ($this->esMercosur($orgId)) {
            return $this->baseMercosurZona($orgId, $gestion)
                ->leftJoin('pais as pa', 'pa.pais_id', '=', 's.pais_id')
                ->selectRaw("COALESCE(pa.nombre, s.pais_nombre_original, 'Sin pais') as label")
                ->selectRaw('SUM(COALESCE(s.exportaciones_usd,0) + COALESCE(s.importaciones_cif_usd,0)) as valor')
                ->groupByRaw("COALESCE(pa.nombre, s.pais_nombre_original, 'Sin pais')")
                ->orderByDesc('valor')->limit($n)
                ->get()->map(fn ($r) => ['label' => $r->label, 'valor' => (float) $r->valor])->all();
        }

        return $this->base($orgId, $gestion)
            ->join('pais as pa', 'pa.pais_id', '=', 'o.pais_id')
            ->selectRaw('pa.nombre as label')
            ->selectRaw("SUM({$this->valorExpo} + {$this->valorImpo}) as valor")
            ->groupBy('pa.nombre')->orderByDesc('valor')->limit($n)
            ->get()->map(fn ($r) => ['label' => $r->label, 'valor' => (float) $r->valor])->all();
    }

    public function topProductos(int $orgId, ?int $gestion = null, int $n = 10): array
    {
        if ($this->esMercosur($orgId)) {
            return $this->baseMercosurProducto($orgId, $gestion)
                ->selectRaw("s.ncm_codigo as nandina, COALESCE(NULLIF(s.ncm_descripcion, ''), s.ncm_codigo, 'Sin producto') as label")
                ->selectRaw('SUM(COALESCE(s.exportaciones_usd,0) + COALESCE(s.importaciones_cif_usd,0)) as valor')
                ->groupBy('s.ncm_codigo', 's.ncm_descripcion')
                ->orderByDesc('valor')->limit($n)
                ->get()->map(fn ($r) => [
                    'label' => mb_strimwidth((string) $r->label, 0, 40, '...'),
                    'nandina' => $r->nandina,
                    'valor' => (float) $r->valor,
                ])->all();
        }

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

    public function distribucionZona(int $orgId, ?int $gestion = null): array
    {
        if ($this->esMercosur($orgId)) {
            return DB::table('serie_comercio_zona as s')
                ->leftJoin('zona_geoeconomica as z', 'z.zona_id', '=', 's.zona_id')
                ->where('s.organizacion_id', $orgId)
                ->when($gestion, fn ($q) => $q->where('s.gestion', $gestion))
                ->whereNotNull('s.zona_id')
                ->selectRaw("COALESCE(z.descripcion, 'Mundo') as label")
                ->selectRaw('SUM(COALESCE(s.exportaciones_usd,0) + COALESCE(s.importaciones_cif_usd,0)) as valor')
                ->groupByRaw("COALESCE(z.descripcion, 'Mundo')")
                ->orderByDesc('valor')
                ->limit(12)
                ->get()->map(fn ($r) => ['label' => $r->label, 'valor' => (float) $r->valor])->all();
        }

        return $this->base($orgId, $gestion)
            ->join('pais as pa', 'pa.pais_id', '=', 'o.pais_id')
            ->join('zona_geoeconomica as z', 'z.zona_id', '=', 'pa.zona_id')
            ->selectRaw('z.descripcion as label')
            ->selectRaw("SUM({$this->valorExpo} + {$this->valorImpo}) as valor")
            ->groupBy('z.descripcion')->orderByDesc('valor')
            ->get()->map(fn ($r) => ['label' => $r->label, 'valor' => (float) $r->valor])->all();
    }

    public function distribucionDepartamento(int $orgId, ?int $gestion = null): array
    {
        if ($this->esMercosur($orgId)) {
            return [];
        }

        return $this->base($orgId, $gestion)
            ->join('departamento as d', 'd.departamento_id', '=', 'o.departamento_id')
            ->selectRaw('d.nombre as label')
            ->selectRaw("SUM({$this->valorExpo} + {$this->valorImpo}) as valor")
            ->groupBy('d.nombre')->orderByDesc('valor')
            ->get()->map(fn ($r) => ['label' => $r->label, 'valor' => (float) $r->valor])->all();
    }

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

    public function distribucionMedio(int $orgId, ?int $gestion = null): array
    {
        if ($this->esMercosur($orgId)) {
            return [];
        }

        return $this->base($orgId, $gestion)
            ->join('medio_transporte as m', 'm.medio_id', '=', 'o.medio_id')
            ->selectRaw('m.descripcion as label')
            ->selectRaw('SUM(COALESCE(o.peso_bruto_kg,0)) as peso')
            ->selectRaw("SUM({$this->valorExpo} + {$this->valorImpo}) as valor")
            ->groupBy('m.descripcion')->orderByDesc('valor')
            ->get()->map(fn ($r) => ['label' => $r->label, 'valor' => (float) $r->valor, 'peso' => (float) $r->peso])->all();
    }

    private function kpisMercosur(int $orgId, ?int $gestion): array
    {
        $row = $this->baseMercosurZona($orgId, $gestion)
            ->selectRaw('SUM(COALESCE(s.exportaciones_usd,0)) as expo')
            ->selectRaw('SUM(COALESCE(s.importaciones_cif_usd,0)) as impo')
            ->selectRaw('SUM(COALESCE(s.volumen_export_kg,0) + COALESCE(s.volumen_import_kg,0)) as peso')
            ->selectRaw('COUNT(*) as registros')
            ->first();

        $expo = (float) ($row->expo ?? 0);
        $impo = (float) ($row->impo ?? 0);
        $peso = (float) ($row->peso ?? 0);
        $valorTotal = $expo + $impo;
        $variacion = null;

        if ($gestion) {
            $ant = $this->baseMercosurZona($orgId, $gestion - 1)
                ->selectRaw('SUM(COALESCE(s.exportaciones_usd,0) + COALESCE(s.importaciones_cif_usd,0)) as valor')
                ->value('valor');
            $ant = (float) $ant;
            if ($ant > 0) {
                $variacion = round((($valorTotal - $ant) / $ant) * 100, 1);
            }
        }

        return [
            'valor_total' => $valorTotal,
            'valor_exportacion' => $expo,
            'valor_importacion' => $impo,
            'balanza_comercial' => $expo - $impo,
            'peso_bruto' => $peso,
            'peso_neto' => $peso,
            'registros' => (int) ($row->registros ?? 0),
            'precio_implicito' => $peso > 0 ? round($valorTotal / $peso, 2) : null,
            'variacion_interanual' => $variacion,
        ];
    }

    private function evolucionAnualMercosur(int $orgId)
    {
        return $this->baseMercosurZona($orgId, null)
            ->selectRaw('s.gestion')
            ->selectRaw('SUM(COALESCE(s.exportaciones_usd,0)) as expo')
            ->selectRaw('SUM(COALESCE(s.importaciones_cif_usd,0)) as impo')
            ->selectRaw('SUM(COALESCE(s.volumen_export_kg,0) + COALESCE(s.volumen_import_kg,0)) as peso')
            ->groupBy('s.gestion')->orderBy('s.gestion')
            ->get()
            ->map(fn ($r) => [
                'gestion' => (int) $r->gestion,
                'expo' => (float) $r->expo,
                'impo' => (float) $r->impo,
                'balanza' => (float) $r->expo - (float) $r->impo,
                'peso' => (float) $r->peso,
            ]);
    }

    private function baseMercosurZona(int $orgId, ?int $gestion)
    {
        return DB::table('serie_comercio_zona as s')
            ->where('s.organizacion_id', $orgId)
            ->whereNull('s.zona_id')
            ->when($gestion, fn ($q) => $q->where('s.gestion', $gestion));
    }

    private function baseMercosurProducto(int $orgId, ?int $gestion)
    {
        return DB::table('serie_comercio_producto_zona as s')
            ->where('s.organizacion_id', $orgId)
            ->whereNull('s.zona_id')
            ->when($gestion, fn ($q) => $q->where('s.gestion', $gestion));
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
