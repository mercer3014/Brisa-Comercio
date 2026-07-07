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
     * ALADI y MERCOSUR no usan el microdato: sus datos viven en
     * ranking_comercio y serie_comercio_producto_zona respectivamente, asi que
     * sus consultas se resuelven con ramas propias que devuelven exactamente
     * la misma forma (columnas y claves) que la del microdato.
     */
    private const ORG_ALADI    = 2;
    private const ORG_MERCOSUR = 3;

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
        if ($orgId === self::ORG_ALADI) {
            $row = $this->aplicarAladi($this->baseAladi(), $f)
                ->selectRaw('COUNT(*) as total')
                ->selectRaw('COALESCE(SUM(COALESCE(rc.valor,0)),0) as valor')
                ->first();

            return [
                'total' => (int) $row->total,
                'valor' => (float) $row->valor,
                'peso'  => 0.0, // ALADI no publica volumen fisico
            ];
        }

        if ($orgId === self::ORG_MERCOSUR) {
            $row = $this->unionMercosur($f)
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
        if ($orgId === self::ORG_ALADI) {
            // Mismas columnas/alias que la rama del microdato (la vista y la
            // exportacion no distinguen la fuente). Se envuelve en un fromSub
            // con alias "o" para que el orden por o.operacion_id siga valiendo.
            $sub = $this->aplicarAladi($this->baseAladi(), $f)
                ->leftJoin('pais as pa', 'pa.pais_id', '=', 'rc.pais_reportante_id')
                ->leftJoin('flujo_comercial as fl', 'fl.flujo_id', '=', 'rc.flujo_id')
                ->select([
                    'rc.ranking_id as operacion_id',
                    'rc.gestion',
                    DB::raw("'Anual' as mes"),
                    DB::raw('fl.descripcion as tipo_operacion'),
                    'rc.item_codigo as codigo_nandina',
                    'rc.descripcion as producto',
                    'pa.nombre as pais',
                    DB::raw('NULL as departamento'),
                    DB::raw('NULL as medio'),
                    DB::raw('NULL as via'),
                    DB::raw('NULL as peso_bruto_kg'),
                    DB::raw('NULL as peso_neto_kg'),
                    DB::raw("CASE WHEN fl.codigo_flujo = '2' THEN NULL ELSE rc.valor END as valor_fob_usd"),
                    DB::raw("CASE WHEN fl.codigo_flujo = '2' THEN rc.valor ELSE NULL END as valor_cif_frontera_usd"),
                ]);

            return DB::query()->fromSub($sub, 'o')->select('o.*');
        }

        if ($orgId === self::ORG_MERCOSUR) {
            return $this->unionMercosur($f)->select('o.*');
        }

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
        if ($orgId === self::ORG_MERCOSUR) {
            $valor = 'COALESCE(o.valor_fob_usd,0) + COALESCE(o.valor_cif_frontera_usd,0)';

            $topZonas = $this->unionMercosur($f)
                ->selectRaw('o.pais as label')
                ->selectRaw("SUM({$valor}) as valor")
                ->groupBy('o.pais')->orderByDesc('valor')->limit($n)
                ->get()->map(fn ($r) => ['label' => $r->label, 'valor' => (float) $r->valor])->all();

            $topProductos = $this->unionMercosur($f)
                ->selectRaw('o.producto as label')
                ->selectRaw("SUM({$valor}) as valor")
                ->groupBy('o.producto')->orderByDesc('valor')->limit($n)
                ->get()->map(fn ($r) => [
                    'label' => mb_strimwidth((string) ($r->label ?: '—'), 0, 40, '...'),
                    'valor' => (float) $r->valor,
                ])->all();

            return ['top_paises' => $topZonas, 'top_productos' => $topProductos];
        }

        if ($orgId === self::ORG_ALADI) {
            $topPaises = $this->aplicarAladi($this->baseAladi(), $f)
                ->join('pais as pa', 'pa.pais_id', '=', 'rc.pais_reportante_id')
                ->selectRaw('pa.nombre as label')
                ->selectRaw('SUM(COALESCE(rc.valor,0)) as valor')
                ->groupBy('pa.nombre')->orderByDesc('valor')->limit($n)
                ->get()->map(fn ($r) => ['label' => $r->label, 'valor' => (float) $r->valor])->all();

            $topProductos = $this->aplicarAladi($this->baseAladi(), $f)
                ->selectRaw('rc.item_codigo, MAX(rc.descripcion) as label')
                ->selectRaw('SUM(COALESCE(rc.valor,0)) as valor')
                ->groupBy('rc.item_codigo')->orderByDesc('valor')->limit($n)
                ->get()->map(fn ($r) => [
                    'label' => mb_strimwidth((string) ($r->label ?: $r->item_codigo), 0, 40, '...'),
                    'valor' => (float) $r->valor,
                ])->all();

            return ['top_paises' => $topPaises, 'top_productos' => $topProductos];
        }

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
        if ($orgId === self::ORG_ALADI) {
            return $this->facetasAladi($f);
        }
        if ($orgId === self::ORG_MERCOSUR) {
            return $this->facetasMercosur($f);
        }

        return $this->facetasMicrodato($orgId, $f);
    }

    /**
     * Definicion de cada faceta del microdato: columna a agrupar y joins extra
     * que necesita (ademas de tiempo, que ya esta en base()).
     */
    private function defsFacetas(): array
    {
        return [
            'tipo_operacion' => ['col' => 'o.tipo_operacion_id'],
            'flujo'          => ['col' => 'o.flujo_id'],
            'pais'           => ['col' => 'o.pais_id'],
            'departamento'   => ['col' => 'o.departamento_id'],
            'medio'          => ['col' => 'o.medio_id'],
            'via'            => ['col' => 'o.via_id'],
            'cuci'           => ['col' => 'o.cuci_id'],
            'ciiu'           => ['col' => 'o.ciiu_id'],
            'gce'            => ['col' => 'o.gce_id'],
            'tnt'            => ['col' => 'o.tnt_id'],
            'cuode'          => ['col' => 'o.cuode_id'],
            'gestion'        => ['col' => 't.gestion'],
            'mes'            => ['col' => 't.mes'],
            'zona'           => ['col' => 'paz.zona_id', 'join' => 'pais'],
            'capitulo'       => ['col' => 'pc.capitulo_id', 'join' => 'producto'],
            'seccion'        => ['col' => 'cs.seccion_id', 'join' => 'seccion'],
        ];
    }

    private function joinsFacetas(Builder $q, array $joins): Builder
    {
        if (in_array('pais', $joins, true)) {
            $q->leftJoin('pais as paz', 'paz.pais_id', '=', 'o.pais_id');
        }
        if (in_array('producto', $joins, true) || in_array('seccion', $joins, true)) {
            $q->leftJoin('producto as pc', 'pc.producto_id', '=', 'o.producto_id');
        }
        if (in_array('seccion', $joins, true)) {
            $q->leftJoin('capitulo_arancelario as cs', 'cs.capitulo_id', '=', 'pc.capitulo_id');
        }

        return $q;
    }

    /**
     * Conteos facetados del microdato.
     *
     * Las facetas SIN filtro activo comparten exactamente la misma consulta
     * base, asi que se resuelven todas en UNA sola pasada con GROUPING SETS
     * (en vez de ~16 agregaciones separadas sobre millones de filas). Solo las
     * facetas con filtro activo necesitan su propia consulta (excluyendo su
     * propio filtro).
     */
    private function facetasMicrodato(int $orgId, array $f): array
    {
        $defs = $this->defsFacetas();
        $activas = array_values(array_filter(array_keys($defs), fn ($k) => ! empty($f[$k])));
        $compartidas = array_values(array_diff(array_keys($defs), $activas));

        $facetas = [];

        if (! empty($compartidas)) {
            $cols = array_map(fn ($k) => $defs[$k]['col'], $compartidas);
            $selects = implode(', ', array_map(fn ($k) => $defs[$k]['col'].' as f_'.$k, $compartidas));
            $sets = implode(', ', array_map(fn ($c) => "({$c})", $cols));
            $grouping = implode(', ', $cols);

            $joins = array_values(array_unique(array_filter(array_map(fn ($k) => $defs[$k]['join'] ?? null, $compartidas))));

            $rows = $this->joinsFacetas($this->aplicar($this->base($orgId), $f), $joins)
                ->selectRaw("{$selects}, COUNT(*) as n, GROUPING({$grouping}) as gid")
                ->groupByRaw("GROUPING SETS ({$sets})")
                ->get();

            foreach ($compartidas as $k) {
                $facetas[$k] = [];
            }

            // GROUPING(c1..cn): el bit en 0 marca la unica columna agrupada del set.
            $total = count($compartidas);
            foreach ($rows as $row) {
                $gid = (int) $row->gid;
                for ($i = 0; $i < $total; $i++) {
                    if (($gid & (1 << ($total - 1 - $i))) === 0) {
                        $clave = $compartidas[$i];
                        $id = $row->{'f_'.$clave};
                        if ($id !== null) {
                            $facetas[$clave][$id] = (int) $row->n;
                        }
                        break;
                    }
                }
            }
        }

        // Facetas con filtro activo: consulta individual excluyendo su filtro.
        foreach ($activas as $k) {
            $def = $defs[$k];
            $q = $this->joinsFacetas($this->aplicar($this->base($orgId), $f, $k), [$def['join'] ?? null]);
            $facetas[$k] = $q
                ->select(DB::raw($def['col'].' as id'), DB::raw('COUNT(*) as n'))
                ->groupBy(DB::raw($def['col']))
                ->pluck('n', 'id')
                ->all();
        }

        return $facetas;
    }

    // =========================================================================
    //  Rama MERCOSUR (serie_comercio_producto_zona)
    // =========================================================================

    /**
     * MERCOSUR guarda una fila por (producto, zona, gestion) con exportaciones
     * e importaciones en columnas: para que el explorador muestre una fila por
     * operacion (como el microdato), cada serie se abre en una rama de
     * exportacion y otra de importacion unidas con UNION ALL. La columna
     * "pais" muestra la zona geoeconomica (la dimension geografica real de
     * las series por producto de MERCOSUR).
     */
    private function ramaMercosur(bool $exportacion, array $f, ?string $excepto = null): Builder
    {
        $col = $exportacion ? 's.exportaciones_usd' : 's.importaciones_cif_usd';
        $vol = $exportacion ? 's.volumen_export_kg' : 's.volumen_import_kg';

        $q = DB::table('serie_comercio_producto_zona as s')
            ->join('zona_geoeconomica as z', 'z.zona_id', '=', 's.zona_id')
            ->where('s.organizacion_id', self::ORG_MERCOSUR)
            ->whereRaw("{$col} IS NOT NULL")
            ->selectRaw('s.serie_prod_zona_id * 2 + '.($exportacion ? '0' : '1').' as operacion_id')
            ->selectRaw('s.gestion')
            ->selectRaw("'Anual' as mes")
            ->selectRaw("'".($exportacion ? 'Exportación' : 'Importación')."' as tipo_operacion")
            ->selectRaw(($exportacion ? '1' : '2').' as tipo_operacion_id')
            ->selectRaw('s.ncm_codigo as codigo_nandina')
            ->selectRaw('s.ncm_descripcion as producto')
            ->selectRaw('z.descripcion as pais')
            ->selectRaw('s.zona_id as zona_id')
            ->selectRaw('NULL as departamento')
            ->selectRaw('NULL as medio')
            ->selectRaw('NULL as via')
            ->selectRaw("{$vol} as peso_bruto_kg")
            ->selectRaw('NULL as peso_neto_kg')
            ->selectRaw($exportacion ? "{$col} as valor_fob_usd" : 'NULL as valor_fob_usd')
            ->selectRaw($exportacion ? 'NULL as valor_cif_frontera_usd' : "{$col} as valor_cif_frontera_usd");

        if ($excepto !== 'gestion' && ! empty($f['gestion'])) {
            $q->whereIn('s.gestion', $f['gestion']);
        }
        if ($excepto !== 'zona' && ! empty($f['zona'])) {
            $q->whereIn('s.zona_id', $f['zona']);
        }
        if (! empty($f['busqueda'])) {
            $texto = '%'.trim($f['busqueda']).'%';
            $q->where(function ($w) use ($texto) {
                $w->where('s.ncm_descripcion', 'ilike', $texto)
                    ->orWhere('s.ncm_codigo', 'ilike', $texto)
                    ->orWhere('z.descripcion', 'ilike', $texto);
            });
        }

        return $q;
    }

    /** Union exportaciones + importaciones (con el filtro de tipo de operacion aplicado). */
    private function unionMercosur(array $f, ?string $excepto = null): Builder
    {
        $tipos = $excepto === 'tipo_operacion' ? [] : ($f['tipo_operacion'] ?? []);
        // tipo_operacion del INE: 1 y 3 = exportacion, 2 = importacion
        $quiereExp = empty($tipos) || count(array_intersect([1, 3], $tipos)) > 0;
        $quiereImp = empty($tipos) || in_array(2, $tipos, true);

        $ramas = [];
        if ($quiereExp) {
            $ramas[] = $this->ramaMercosur(true, $f, $excepto);
        }
        if ($quiereImp) {
            $ramas[] = $this->ramaMercosur(false, $f, $excepto);
        }

        $union = array_shift($ramas);
        foreach ($ramas as $rama) {
            $union->unionAll($rama);
        }

        return DB::query()->fromSub($union, 'o');
    }

    private function facetasMercosur(array $f): array
    {
        return [
            'tipo_operacion' => $this->unionMercosur($f, 'tipo_operacion')
                ->select('o.tipo_operacion_id as id', DB::raw('COUNT(*) as n'))
                ->groupBy('o.tipo_operacion_id')->pluck('n', 'id')->all(),
            'gestion' => $this->unionMercosur($f, 'gestion')
                ->select('o.gestion as id', DB::raw('COUNT(*) as n'))
                ->groupBy('o.gestion')->pluck('n', 'id')->all(),
            'zona' => $this->unionMercosur($f, 'zona')
                ->select('o.zona_id as id', DB::raw('COUNT(*) as n'))
                ->groupBy('o.zona_id')->pluck('n', 'id')->all(),
        ];
    }

    // =========================================================================
    //  Rama ALADI (ranking_comercio)
    // =========================================================================

    private function baseAladi(): Builder
    {
        return DB::table('ranking_comercio as rc')->where('rc.organizacion_id', self::ORG_ALADI);
    }

    /**
     * Filtros aplicables a ALADI: gestion, pais (reportante), tipo de operacion
     * (mapeado al flujo) y busqueda libre. El resto de facetas del microdato
     * (departamento, medio, via, clasificaciones) no existe en los rankings.
     */
    private function aplicarAladi(Builder $q, array $f, ?string $excepto = null): Builder
    {
        if ($excepto !== 'gestion' && ! empty($f['gestion'])) {
            $q->whereIn('rc.gestion', $f['gestion']);
        }
        if ($excepto !== 'pais' && ! empty($f['pais'])) {
            $q->whereIn('rc.pais_reportante_id', $f['pais']);
        }

        // tipo_operacion del INE (1/3 = exportacion, 2 = importacion) -> codigo de flujo
        if ($excepto !== 'tipo_operacion' && ! empty($f['tipo_operacion'])) {
            $codigos = collect($f['tipo_operacion'])
                ->map(fn ($id) => (int) $id === 2 ? '2' : '1')
                ->unique()->values()->all();
            $q->whereIn('rc.flujo_id', function ($s) use ($codigos) {
                $s->select('flujo_id')->from('flujo_comercial')->whereIn('codigo_flujo', $codigos);
            });
        }

        if (! empty($f['busqueda'])) {
            $texto = '%'.trim($f['busqueda']).'%';
            $q->where(function ($w) use ($texto) {
                $w->where('rc.descripcion', 'ilike', $texto)
                    ->orWhere('rc.item_codigo', 'ilike', $texto)
                    ->orWhereIn('rc.pais_reportante_id', fn ($s) => $s->select('pais_id')->from('pais')->where('nombre', 'ilike', $texto));
            });
        }

        return $q;
    }

    private function facetasAladi(array $f): array
    {
        // codigo_flujo '1'/'2' coincide con tipo_operacion_id 1/2 del catalogo INE.
        $tipoOperacion = $this->aplicarAladi($this->baseAladi(), $f, 'tipo_operacion')
            ->join('flujo_comercial as fl', 'fl.flujo_id', '=', 'rc.flujo_id')
            ->select('fl.codigo_flujo as id', DB::raw('COUNT(*) as n'))
            ->groupBy('fl.codigo_flujo')
            ->pluck('n', 'id')
            ->mapWithKeys(fn ($n, $c) => [(int) $c => (int) $n])
            ->all();

        return [
            'tipo_operacion' => $tipoOperacion,
            'gestion' => $this->aplicarAladi($this->baseAladi(), $f, 'gestion')
                ->select('rc.gestion as id', DB::raw('COUNT(*) as n'))
                ->groupBy('rc.gestion')->pluck('n', 'id')->all(),
            'pais' => $this->aplicarAladi($this->baseAladi(), $f, 'pais')
                ->select('rc.pais_reportante_id as id', DB::raw('COUNT(*) as n'))
                ->groupBy('rc.pais_reportante_id')->pluck('n', 'id')->all(),
        ];
    }
}
