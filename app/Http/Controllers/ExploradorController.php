<?php

namespace App\Http\Controllers;

use App\Models\Configuracion;
use App\Servicios\ConsultaExplorador;
use App\Servicios\ConsultaExploradorMercosur;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ExploradorController extends Controller
{
    private array $clavesFiltro = [
        'tipo_operacion', 'gestion', 'mes', 'pais', 'zona', 'departamento',
        'medio', 'via', 'seccion', 'capitulo', 'producto', 'cuci', 'ciiu', 'gce', 'tnt', 'cuode',
    ];

    public function index(): Response
    {
        $orgDefecto = (int) Configuracion::obtener('organizacion_por_defecto', 1);

        return Inertia::render('Explorador/Index', [
            'organizacionDefecto' => $orgDefecto,
            'opciones'            => $this->opciones(),
        ]);
    }

    /**
     * Devuelve totales, tabla paginada y conteos facetados para los filtros dados.
     */
    public function consultar(Request $request, ConsultaExplorador $consulta, ConsultaExploradorMercosur $mercosur): JsonResponse
    {
        $datos = $request->validate([
            'organizacion_id' => ['required', 'integer'],
            'pagina'          => ['nullable', 'integer', 'min:1'],
            'por_pagina'      => ['nullable', 'integer', 'min:10', 'max:100'],
            'filtros'         => ['nullable', 'array'],
        ]);

        $org = (int) $datos['organizacion_id'];
        $filtros = $this->normalizarFiltros($datos['filtros'] ?? []);
        $esMercosur = $this->esMercosur($org);
        $servicio = $esMercosur ? $mercosur : $consulta;

        return response()->json([
            'modo'    => $esMercosur ? 'series_mercosur' : 'microdato',
            'totales' => $servicio->totales($org, $filtros),
            'tabla'   => $servicio->tabla($org, $filtros, $datos['por_pagina'] ?? 25, $datos['pagina'] ?? 1),
            'facetas' => $servicio->facetas($org, $filtros),
        ]);
    }

    /**
     * Limpia los filtros recibidos (arreglos de ids enteros + busqueda).
     */
    private function normalizarFiltros(array $filtros): array
    {
        $limpio = [];
        foreach ($this->clavesFiltro as $k) {
            if (! empty($filtros[$k]) && is_array($filtros[$k])) {
                $limpio[$k] = array_values(array_filter(array_map('intval', $filtros[$k]), fn ($v) => $v !== null));
            }
        }
        if (! empty($filtros['busqueda'])) {
            $limpio['busqueda'] = (string) $filtros['busqueda'];
        }

        return $limpio;
    }

    private function esMercosur(int $orgId): bool
    {
        return DB::table('organizacion')
            ->where('organizacion_id', $orgId)
            ->where('sigla', 'MERCOSUR')
            ->exists();
    }

    /**
     * Opciones de cada faceta (id => etiqueta). Solo dimensiones presentes en hechos.
     */
    private function opciones(): array
    {
        $meses = [1 => 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];

        $gestiones = DB::query()
            ->fromSub(function ($q) {
                $q->from('tiempo')->distinct()->select('gestion')
                    ->union(DB::table('serie_comercio_zona')->distinct()->select('gestion'));
            }, 'g')
            ->orderByDesc('gestion')
            ->pluck('gestion')
            ->map(fn ($g) => ['id' => $g, 'label' => (string) $g])
            ->all();

        return [
            'organizaciones' => DB::table('organizacion')->where('activo', true)->orderBy('nombre')->get(['organizacion_id', 'nombre', 'sigla']),
            'tipo_operacion' => DB::table('tipo_operacion')->orderBy('nombre')->get(['tipo_operacion_id as id', 'nombre as label']),
            'gestion'        => $gestiones,
            'mes'            => collect($meses)->map(fn ($n, $i) => ['id' => $i, 'label' => $n])->values()->all(),
            'pais'           => DB::table('pais')->orderBy('nombre')->get(['pais_id as id', 'nombre as label']),
            'zona'           => DB::table('zona_geoeconomica')->orderBy('descripcion')->get(['zona_id as id', 'descripcion as label']),
            'departamento'   => DB::table('departamento')->orderBy('nombre')->get(['departamento_id as id', 'nombre as label']),
            'medio'          => DB::table('medio_transporte')->orderBy('descripcion')->get(['medio_id as id', 'descripcion as label']),
            'via'            => DB::table('via_comercio')->orderBy('descripcion')->get(['via_id as id', 'descripcion as label']),
            'seccion'        => DB::table('seccion_arancelaria')->orderBy('codigo_seccion')->get(['seccion_id as id', 'descripcion as label', 'codigo_seccion']),
            'capitulo'       => DB::table('capitulo_arancelario')->orderBy('codigo_capitulo')->get(['capitulo_id as id', 'descripcion as label', 'codigo_capitulo', 'seccion_id']),
            'cuci'           => DB::table('clasificacion_cuci')->orderBy('codigo_cuci')->get(['cuci_id as id', 'codigo_cuci as label']),
            'ciiu'           => DB::table('actividad_ciiu')->orderBy('codigo_ciiu')->get(['ciiu_id as id', 'codigo_ciiu as label']),
            'gce'            => DB::table('categoria_economica_gce')->orderBy('codigo_gce')->get(['gce_id as id', 'codigo_gce as label']),
            'tnt'            => DB::table('clasificacion_tnt')->orderBy('codigo_tnt')->get(['tnt_id as id', 'descripcion as label']),
            'cuode'          => DB::table('clasificacion_cuode')->orderBy('codigo_cuode')->get(['cuode_id as id', 'codigo_cuode as label']),
        ];
    }
}
