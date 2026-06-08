<?php

namespace App\Http\Controllers;

use App\Servicios\ExportadorReporte;
use App\Servicios\GeneradorReporte;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ReporteController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Reportes/Index', [
            'catalogo'       => collect(GeneradorReporte::catalogo())->map(fn ($c, $k) => [
                'tipo' => $k, 'titulo' => $c['titulo'], 'columnas' => $c['columnas'],
            ])->values(),
            'organizaciones' => DB::table('organizacion')->where('activo', true)->orderBy('nombre')->get(['organizacion_id', 'nombre']),
            'gestiones'      => DB::table('tiempo')->distinct()->orderByDesc('gestion')->pluck('gestion'),
        ]);
    }

    /**
     * Genera el reporte y devuelve la tabla + resumen (vista previa).
     */
    public function generar(Request $request, GeneradorReporte $gen): JsonResponse
    {
        $p = $this->validar($request);

        return response()->json($gen->generar($p['tipo'], $p));
    }

    /**
     * Exporta el reporte a xlsx, csv o pdf.
     */
    public function exportar(Request $request, GeneradorReporte $gen, ExportadorReporte $exp)
    {
        $p = $this->validar($request);
        $formato = $request->validate(['formato' => ['required', Rule::in(['xlsx', 'csv', 'pdf'])]])['formato'];

        $reporte = $gen->generar($p['tipo'], $p);

        \App\Servicios\Auditoria::registrar('REPORTE_EXPORTADO', 'reporte', $p['tipo'], null, [
            'formato' => $formato, 'parametros' => $p,
        ]);

        return $exp->descargar($reporte, $formato);
    }

    private function validar(Request $request): array
    {
        return $request->validate([
            'tipo'            => ['required', Rule::in(array_keys(GeneradorReporte::catalogo()))],
            'organizacion_id' => ['required', 'integer'],
            'flujo'           => ['nullable', Rule::in(['EXPORTACION', 'IMPORTACION'])],
            'gestion_desde'   => ['nullable', 'integer'],
            'gestion_hasta'   => ['nullable', 'integer'],
        ]);
    }
}
