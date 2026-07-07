<?php

namespace App\Http\Controllers;

use App\Models\Configuracion;
use App\Servicios\ConsultaExplorador;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\CSV\Writer as CsvWriter;
use OpenSpout\Writer\XLSX\Writer as XlsxWriter;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Explorador PUBLICO con detalle (Tarea 15): version abierta y visual del explorador
 * facetado privado. Reutiliza ConsultaExplorador. Sin autenticacion; respeta SIEMPRE
 * la organizacion seleccionada.
 */
class ExploradorPublicoController extends Controller
{
    /** Claves de filtro aceptadas (arreglos de ids). */
    private array $clavesFiltro = [
        'tipo_operacion', 'flujo', 'gestion', 'mes', 'pais', 'zona', 'departamento',
        'medio', 'via', 'seccion', 'capitulo', 'producto', 'cuci', 'ciiu', 'gce', 'tnt', 'cuode',
    ];

    public function index(): Response
    {
        $orgDefecto = (int) Configuracion::obtener('organizacion_por_defecto', 1);

        return Inertia::render('Portal/Explorar', [
            'organizacionDefecto' => $orgDefecto,
            'opciones'            => $this->opciones(),
        ]);
    }

    /**
     * Totales + tabla paginada + facetas + graficos del subconjunto filtrado.
     */
    public function consultar(Request $request, ConsultaExplorador $consulta): JsonResponse
    {
        $datos = $request->validate([
            'organizacion_id' => ['required', 'integer'],
            'pagina'          => ['nullable', 'integer', 'min:1'],
            'por_pagina'      => ['nullable', 'integer', 'min:10', 'max:100'],
            'filtros'         => ['nullable', 'array'],
        ]);

        $org = (int) $datos['organizacion_id'];
        $filtros = $this->normalizarFiltros($datos['filtros'] ?? []);
        $porPagina = $datos['por_pagina'] ?? 25;
        $pagina = $datos['pagina'] ?? 1;

        // Cache: los agregados sobre millones de filas son costosos y solo
        // cambian cuando se carga un archivo nuevo (la version del cache es el
        // ultimo carga_id). Totales+facetas+graficos se cachean sin la pagina.
        $ver = (int) DB::table('carga_archivo')->max('carga_id');
        $hash = md5(json_encode([$org, $filtros]));

        // Mismas claves que el explorador admin (consultan lo mismo): calentar
        // una calienta la otra. Los graficos son solo del publico.
        $agregados = Cache::remember("expl.agg.{$ver}.{$hash}", 86400, fn () => [
            'totales' => $consulta->totales($org, $filtros),
            'facetas' => $consulta->facetas($org, $filtros),
        ]);
        $graficos = Cache::remember("expl.graf.{$ver}.{$hash}", 86400,
            fn () => $consulta->graficos($org, $filtros, 10));
        $tabla = Cache::remember("expl.tabla.{$ver}.{$hash}.{$pagina}.{$porPagina}", 86400,
            fn () => $consulta->tabla($org, $filtros, $porPagina, $pagina));

        return response()->json([
            'totales'  => $agregados['totales'],
            'tabla'    => $tabla,
            'facetas'  => $agregados['facetas'],
            'graficos' => $graficos,
        ]);
    }

    /**
     * Descarga el resultado filtrado (detalle) en XLSX o CSV, en streaming.
     */
    public function exportar(Request $request, ConsultaExplorador $consulta): StreamedResponse
    {
        $datos = $request->validate([
            'organizacion_id' => ['required', 'integer'],
            'formato'         => ['required', 'string', 'in:xlsx,csv'],
            'filtros'         => ['nullable', 'array'],
        ]);

        $org = (int) $datos['organizacion_id'];
        $filtros = $this->normalizarFiltros($datos['filtros'] ?? []);
        $formato = $datos['formato'];

        $query = $consulta->detalleQuery($org, $filtros)->orderByDesc('o.operacion_id');

        $columnas = ['Gestion', 'Mes', 'Tipo', 'NANDINA', 'Producto', 'Pais', 'Departamento',
            'Medio', 'Via', 'Peso bruto (kg)', 'Peso neto (kg)', 'FOB (USD)', 'CIF frontera (USD)'];

        $archivo = 'explorador_'.now()->format('Ymd_His').'.'.$formato;
        $writer = $formato === 'xlsx' ? new XlsxWriter() : new CsvWriter();

        return new StreamedResponse(function () use ($query, $writer, $columnas) {
            $writer->openToFile('php://output');
            $writer->addRow(Row::fromValues($columnas));

            // Cursor: itera sin cargar todo en memoria (apto para grandes volumenes).
            foreach ($query->cursor() as $r) {
                $writer->addRow(Row::fromValues([
                    $r->gestion, $r->mes, $r->tipo_operacion, $r->codigo_nandina, $r->producto,
                    $r->pais, $r->departamento, $r->medio, $r->via,
                    $r->peso_bruto_kg, $r->peso_neto_kg, $r->valor_fob_usd, $r->valor_cif_frontera_usd,
                ]));
            }
            $writer->close();
        }, 200, [
            'Content-Type'        => $formato === 'xlsx'
                ? 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
                : 'text/csv',
            'Content-Disposition' => 'attachment; filename="'.$archivo.'"',
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

    /**
     * Opciones de cada faceta (id => etiqueta).
     */
    private function opciones(): array
    {
        $meses = [1 => 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];

        return [
            'organizaciones' => DB::table('organizacion')->where('activo', true)->orderBy('nombre')->get(['organizacion_id', 'nombre', 'sigla']),
            'flujo'          => DB::table('flujo_comercial')->orderBy('flujo_id')->get(['flujo_id as id', 'descripcion as label']),
            'tipo_operacion' => DB::table('tipo_operacion')->orderBy('nombre')->get(['tipo_operacion_id as id', 'nombre as label']),
            'gestion'        => DB::table('tiempo')->distinct()->orderByDesc('gestion')->pluck('gestion')->map(fn ($g) => ['id' => $g, 'label' => (string) $g])->all(),
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
