<?php

namespace App\Servicios;

use Illuminate\Support\Facades\DB;

class ConsultaExploradorMercosur
{
    public function totales(int $orgId, array $f): array
    {
        $row = $this->baseZona($orgId, $f)
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('COALESCE(SUM(COALESCE(s.exportaciones_usd,0) + COALESCE(s.importaciones_cif_usd,0)),0) as valor')
            ->selectRaw('COALESCE(SUM(COALESCE(s.volumen_export_kg,0) + COALESCE(s.volumen_import_kg,0)),0) as peso')
            ->first();

        return [
            'total' => (int) ($row->total ?? 0),
            'valor' => (float) ($row->valor ?? 0),
            'peso' => (float) ($row->peso ?? 0),
        ];
    }

    public function tabla(int $orgId, array $f, int $porPagina = 25, int $pagina = 1): array
    {
        $q = $this->baseZona($orgId, $f)
            ->leftJoin('pais as p', 'p.pais_id', '=', 's.pais_id')
            ->leftJoin('zona_geoeconomica as z', 'z.zona_id', '=', 's.zona_id')
            ->selectRaw('s.serie_zona_id')
            ->selectRaw('s.gestion')
            ->selectRaw("COALESCE(z.descripcion, 'Mundo') as zona")
            ->selectRaw("COALESCE(p.nombre, s.pais_nombre_original, 'Sin pais') as pais")
            ->selectRaw('s.pais_iso3166')
            ->selectRaw('COALESCE(s.exportaciones_usd,0) as exportaciones_usd')
            ->selectRaw('COALESCE(s.importaciones_cif_usd,0) as importaciones_cif_usd')
            ->selectRaw('COALESCE(s.balanza_comercial_usd, COALESCE(s.exportaciones_usd,0) - COALESCE(s.importaciones_cif_usd,0)) as balanza_comercial_usd')
            ->selectRaw('COALESCE(s.volumen_export_kg,0) as volumen_export_kg')
            ->selectRaw('COALESCE(s.volumen_import_kg,0) as volumen_import_kg')
            ->orderByDesc('s.gestion')
            ->orderByDesc('exportaciones_usd');

        $total = (clone $q)->count();
        $rows = $q->forPage($pagina, $porPagina)->get();

        return [
            'data' => $rows,
            'total' => $total,
            'por_pagina' => $porPagina,
            'pagina' => $pagina,
            'ultima_pagina' => (int) max(1, ceil($total / $porPagina)),
        ];
    }

    public function graficos(int $orgId, array $f, int $n = 10): array
    {
        $topPaises = $this->baseZona($orgId, $f)
            ->leftJoin('pais as p', 'p.pais_id', '=', 's.pais_id')
            ->selectRaw("COALESCE(p.nombre, s.pais_nombre_original, 'Sin pais') as label")
            ->selectRaw('SUM(COALESCE(s.exportaciones_usd,0) + COALESCE(s.importaciones_cif_usd,0)) as valor')
            ->groupByRaw("COALESCE(p.nombre, s.pais_nombre_original, 'Sin pais')")
            ->orderByDesc('valor')
            ->limit($n)
            ->get()
            ->map(fn ($r) => ['label' => $r->label, 'valor' => (float) $r->valor])
            ->all();

        $topProductos = $this->baseProducto($orgId, $f)
            ->selectRaw("COALESCE(NULLIF(s.ncm_descripcion, ''), s.ncm_codigo, 'Sin producto') as label")
            ->selectRaw('SUM(COALESCE(s.exportaciones_usd,0) + COALESCE(s.importaciones_cif_usd,0)) as valor')
            ->groupByRaw("COALESCE(NULLIF(s.ncm_descripcion, ''), s.ncm_codigo, 'Sin producto')")
            ->orderByDesc('valor')
            ->limit($n)
            ->get()
            ->map(fn ($r) => ['label' => mb_strimwidth((string) $r->label, 0, 40, '...'), 'valor' => (float) $r->valor])
            ->all();

        return ['top_paises' => $topPaises, 'top_productos' => $topProductos];
    }

    public function facetas(int $orgId, array $f): array
    {
        $vacias = array_fill_keys([
            'tipo_operacion', 'flujo', 'mes', 'departamento', 'medio', 'via', 'seccion',
            'capitulo', 'producto', 'cuci', 'ciiu', 'gce', 'tnt', 'cuode',
        ], []);

        return $vacias + [
            'gestion' => DB::table('serie_comercio_zona as s')
                ->where('s.organizacion_id', $orgId)
                ->select('s.gestion as id', DB::raw('COUNT(*) as n'))
                ->groupBy('s.gestion')
                ->pluck('n', 'id')
                ->all(),
            'zona' => DB::table('serie_comercio_zona as s')
                ->where('s.organizacion_id', $orgId)
                ->whereNotNull('s.zona_id')
                ->select('s.zona_id as id', DB::raw('COUNT(*) as n'))
                ->groupBy('s.zona_id')
                ->pluck('n', 'id')
                ->all(),
            'pais' => $this->baseZona($orgId, $f, false)
                ->whereNotNull('s.pais_id')
                ->select('s.pais_id as id', DB::raw('COUNT(*) as n'))
                ->groupBy('s.pais_id')
                ->pluck('n', 'id')
                ->all(),
        ];
    }

    private function baseZona(int $orgId, array $f, bool $aplicarPais = true)
    {
        $q = DB::table('serie_comercio_zona as s')
            ->where('s.organizacion_id', $orgId);

        $this->aplicarTiempoYZona($q, $f);

        if ($aplicarPais && ! empty($f['pais'])) {
            $q->whereIn('s.pais_id', $f['pais']);
        }

        if (! empty($f['busqueda'])) {
            $texto = '%'.trim($f['busqueda']).'%';
            $q->where(function ($w) use ($texto) {
                $w->where('s.pais_nombre_original', 'ilike', $texto)
                    ->orWhere('s.pais_iso3166', 'ilike', $texto)
                    ->orWhereIn('s.pais_id', fn ($sub) => $sub->select('pais_id')->from('pais')->where('nombre', 'ilike', $texto));
            });
        }

        return $q;
    }

    private function baseProducto(int $orgId, array $f)
    {
        $q = DB::table('serie_comercio_producto_zona as s')
            ->where('s.organizacion_id', $orgId);

        $this->aplicarTiempoYZona($q, $f);

        if (! empty($f['busqueda'])) {
            $texto = '%'.trim($f['busqueda']).'%';
            $q->where(function ($w) use ($texto) {
                $w->where('s.ncm_descripcion', 'ilike', $texto)
                    ->orWhere('s.ncm_codigo', 'ilike', $texto);
            });
        }

        return $q;
    }

    private function aplicarTiempoYZona($q, array $f): void
    {
        if (! empty($f['gestion'])) {
            $q->whereIn('s.gestion', $f['gestion']);
        }

        if (! empty($f['zona'])) {
            $q->whereIn('s.zona_id', $f['zona']);
        } else {
            $q->whereNull('s.zona_id');
        }
    }
}
