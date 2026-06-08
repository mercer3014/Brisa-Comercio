<?php

namespace App\Servicios;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Construye consultas sobre el microdato (operacion_comercio_exterior) aplicando
 * filtros facetados de forma eficiente (agregaciones en PostgreSQL). Nunca trae
 * todas las filas: la tabla se pagina del lado servidor.
 */
class ConsultaExplorador
{
    /**
     * Filtros directos: clave de filtro => columna en la tabla de hechos.
     */
    private array $directos = [
        'tipo_operacion' => 'o.tipo_operacion_id',
        'flujo'          => 'o.flujo_id',
        'pais'           => 'o.pais_id',
        'departamento'   => 'o.departamento_id',
        'medio'          => 'o.medio_id',
        'via'            => 'o.via_id',
        'producto'       => 'o.producto_id',
        'cuci'           => 'o.cuci_id',
        'ciiu'           => 'o.ciiu_id',
        'gce'            => 'o.gce_id',
        'tnt'            => 'o.tnt_id',
        'cuode'          => 'o.cuode_id',
    ];

    /**
     * Consulta base sobre la organizacion seleccionada, con join a tiempo
     * (para gestion/mes).
     */
    private function base(int $organizacionId): Builder
    {
        return DB::table('operacion_comercio_exterior as o')
            ->join('tiempo as t', 't.tiempo_id', '=', 'o.tiempo_id')
            ->where('o.organizacion_id', $organizacionId);
    }

    /**
     * Aplica todos los filtros activos al builder, salvo el indicado en $excepto
     * (para el calculo facetado).
     */
    private function aplicar(Builder $q, array $f, ?string $excepto = null): Builder
    {
        // Filtros directos (whereIn sobre columna de hechos).
        foreach ($this->directos as $clave => $columna) {
            if ($clave === $excepto) {
                continue;
            }
            $valores = $f[$clave] ?? [];
            if (! empty($valores)) {
                $q->whereIn($columna, $valores);
            }
        }

        // Tiempo: gestion y mes.
        if ($excepto !== 'gestion' && ! empty($f['gestion'])) {
            $q->whereIn('t.gestion', $f['gestion']);
        }
        if ($excepto !== 'mes' && ! empty($f['mes'])) {
            $q->whereIn('t.mes', $f['mes']);
        }

        // Zona: a traves de pais.
        if ($excepto !== 'zona' && ! empty($f['zona'])) {
            $q->whereIn('o.pais_id', function ($sub) use ($f) {
                $sub->select('pais_id')->from('pais')->whereIn('zona_id', $f['zona']);
            });
        }

        // Capitulo: a traves de producto.
        if ($excepto !== 'capitulo' && ! empty($f['capitulo'])) {
            $q->whereIn('o.producto_id', function ($sub) use ($f) {
                $sub->select('producto_id')->from('producto')->whereIn('capitulo_id', $f['capitulo']);
            });
        }

        // Seccion: a traves de producto -> capitulo.
        if ($excepto !== 'seccion' && ! empty($f['seccion'])) {
            $q->whereIn('o.producto_id', function ($sub) use ($f) {
                $sub->select('p.producto_id')->from('producto as p')
                    ->join('capitulo_arancelario as c', 'c.capitulo_id', '=', 'p.capitulo_id')
                    ->whereIn('c.seccion_id', $f['seccion']);
            });
        }

        // Busqueda libre sobre descripciones de producto, pais y aduana.
        if (! empty($f['busqueda'])) {
            $texto = '%'.trim($f['busqueda']).'%';
            $q->where(function ($w) use ($texto) {
                $w->whereIn('o.producto_id', fn ($s) => $s->select('producto_id')->from('producto')->where('descripcion', 'ilike', $texto))
                    ->orWhereIn('o.pais_id', fn ($s) => $s->select('pais_id')->from('pais')->where('nombre', 'ilike', $texto))
                    ->orWhereIn('o.aduana_id', fn ($s) => $s->select('aduana_id')->from('aduana')->where('descripcion', 'ilike', $texto));
            });
        }

        return $q;
    }

    /**
     * Totales: cantidad de registros, suma de valor y de peso.
     * valor = FOB (exportacion) o CIF frontera (importacion); peso = peso bruto.
     */
    public function totales(int $orgId, array $f): array
    {
        $row = $this->aplicar($this->base($orgId), $f)
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('COALESCE(SUM(COALESCE(o.valor_fob_usd,0) + COALESCE(o.valor_cif_frontera_usd,0)),0) as valor')
            ->selectRaw('COALESCE(SUM(COALESCE(o.peso_bruto_kg,0)),0) as peso')
            ->first();

        return [
            'total' => (int) $row->total,
            'valor' => (float) $row->valor,
            'peso'  => (float) $row->peso,
        ];
    }

    /**
     * Consulta de detalle (microdato) con etiquetas de dimension, aplicando los filtros.
     * Reutilizada por la tabla paginada y por la exportacion.
     */
    public function detalleQuery(int $orgId, array $f): Builder
    {
        return $this->aplicar($this->base($orgId), $f)
            ->join('producto as p', 'p.producto_id', '=', 'o.producto_id')
            ->join('pais as pa', 'pa.pais_id', '=', 'o.pais_id')
            ->leftJoin('departamento as dep', 'dep.departamento_id', '=', 'o.departamento_id')
            ->leftJoin('medio_transporte as m', 'm.medio_id', '=', 'o.medio_id')
            ->leftJoin('via_comercio as vi', 'vi.via_id', '=', 'o.via_id')
            ->join('tipo_operacion as top', 'top.tipo_operacion_id', '=', 'o.tipo_operacion_id')
            ->select([
                'o.operacion_id', 't.gestion', 't.mes', 'top.nombre as tipo_operacion',
                'p.codigo_nandina', 'p.descripcion as producto', 'pa.nombre as pais',
                'dep.nombre as departamento', 'm.descripcion as medio', 'vi.descripcion as via',
                'o.peso_bruto_kg', 'o.peso_neto_kg',
                'o.valor_fob_usd', 'o.valor_cif_frontera_usd',
            ]);
    }

    /**
     * Tabla paginada del lado servidor con etiquetas de dimension para mostrar.
     */
    public function tabla(int $orgId, array $f, int $porPagina = 25, int $pagina = 1): array
    {
        $q = $this->detalleQuery($orgId, $f)->orderByDesc('o.operacion_id');

        $total = (clone $q)->count();
        $rows = $q->forPage($pagina, $porPagina)->get();

        return [
            'data'         => $rows,
            'total'        => $total,
            'por_pagina'   => $porPagina,
            'pagina'       => $pagina,
            'ultima_pagina' => (int) ceil($total / $porPagina),
        ];
    }

    /**
     * Graficos del subconjunto filtrado: top N paises y top N productos por valor.
     */
    public function graficos(int $orgId, array $f, int $n = 10): array
    {
        $valor = 'COALESCE(o.valor_fob_usd,0) + COALESCE(o.valor_cif_frontera_usd,0)';

        $topPaises = $this->aplicar($this->base($orgId), $f)
            ->join('pais as pa', 'pa.pais_id', '=', 'o.pais_id')
            ->selectRaw('pa.nombre as label')
            ->selectRaw("SUM({$valor}) as valor")
            ->groupBy('pa.nombre')->orderByDesc('valor')->limit($n)
            ->get()->map(fn ($r) => ['label' => $r->label, 'valor' => (float) $r->valor])->all();

        $topProductos = $this->aplicar($this->base($orgId), $f)
            ->join('producto as p', 'p.producto_id', '=', 'o.producto_id')
            ->selectRaw('p.descripcion as label')
            ->selectRaw("SUM({$valor}) as valor")
            ->groupBy('p.descripcion')->orderByDesc('valor')->limit($n)
            ->get()->map(fn ($r) => [
                'label' => mb_strimwidth((string) $r->label, 0, 40, '...'),
                'valor' => (float) $r->valor,
            ])->all();

        return ['top_paises' => $topPaises, 'top_productos' => $topProductos];
    }

    /**
     * Conteos facetados: para cada faceta, recalcula cuantos registros quedan por
     * opcion aplicando todos los filtros EXCEPTO el de esa faceta.
     *
     * @return array<string, array<int|string, int>>  facetKey => [id => count]
     */
    public function facetas(int $orgId, array $f): array
    {
        $facetas = [];

        // Facetas por columna directa de hechos.
        $directas = [
            'tipo_operacion' => 'o.tipo_operacion_id',
            'flujo'          => 'o.flujo_id',
            'pais'           => 'o.pais_id',
            'departamento'   => 'o.departamento_id',
            'medio'          => 'o.medio_id',
            'via'            => 'o.via_id',
            'cuci'           => 'o.cuci_id',
            'ciiu'           => 'o.ciiu_id',
            'gce'            => 'o.gce_id',
            'tnt'            => 'o.tnt_id',
            'cuode'          => 'o.cuode_id',
        ];
        foreach ($directas as $clave => $columna) {
            $facetas[$clave] = $this->aplicar($this->base($orgId), $f, $clave)
                ->select($columna.' as id', DB::raw('COUNT(*) as n'))
                ->groupBy($columna)
                ->pluck('n', 'id')
                ->all();
        }

        // Gestion y mes (desde tiempo).
        $facetas['gestion'] = $this->aplicar($this->base($orgId), $f, 'gestion')
            ->select('t.gestion as id', DB::raw('COUNT(*) as n'))->groupBy('t.gestion')->pluck('n', 'id')->all();
        $facetas['mes'] = $this->aplicar($this->base($orgId), $f, 'mes')
            ->select('t.mes as id', DB::raw('COUNT(*) as n'))->groupBy('t.mes')->pluck('n', 'id')->all();

        // Zona (via pais).
        $facetas['zona'] = $this->aplicar($this->base($orgId), $f, 'zona')
            ->join('pais as paz', 'paz.pais_id', '=', 'o.pais_id')
            ->select('paz.zona_id as id', DB::raw('COUNT(*) as n'))->groupBy('paz.zona_id')->pluck('n', 'id')->all();

        // Seccion y capitulo (via producto).
        $facetas['capitulo'] = $this->aplicar($this->base($orgId), $f, 'capitulo')
            ->join('producto as pc', 'pc.producto_id', '=', 'o.producto_id')
            ->select('pc.capitulo_id as id', DB::raw('COUNT(*) as n'))->groupBy('pc.capitulo_id')->pluck('n', 'id')->all();
        $facetas['seccion'] = $this->aplicar($this->base($orgId), $f, 'seccion')
            ->join('producto as ps', 'ps.producto_id', '=', 'o.producto_id')
            ->join('capitulo_arancelario as cs', 'cs.capitulo_id', '=', 'ps.capitulo_id')
            ->select('cs.seccion_id as id', DB::raw('COUNT(*) as n'))->groupBy('cs.seccion_id')->pluck('n', 'id')->all();

        return $facetas;
    }
}
