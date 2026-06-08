<?php

namespace App\Http\Controllers;

use App\Models\Configuracion;
use App\Servicios\AgregadorDashboard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(): Response
    {
        $orgDefecto = (int) Configuracion::obtener('organizacion_por_defecto', 1);

        return Inertia::render('Dashboards/Index', [
            'organizacionDefecto' => $orgDefecto,
            'organizaciones'      => DB::table('organizacion')->where('activo', true)->orderBy('nombre')->get(['organizacion_id', 'nombre', 'sigla']),
            'gestiones'           => DB::table('tiempo')->distinct()->orderByDesc('gestion')->pluck('gestion'),
        ]);
    }

    /**
     * Devuelve KPIs y todas las series agregadas para los dashboards.
     */
    public function datos(Request $request, AgregadorDashboard $agg): JsonResponse
    {
        $datos = $request->validate([
            'organizacion_id' => ['required', 'integer'],
            'gestion'         => ['nullable', 'integer'],
        ]);

        $org = $datos['organizacion_id'];
        $gestion = $datos['gestion'] ?? null;

        return response()->json([
            'kpis'                     => $agg->kpis($org, $gestion),
            'evolucion_mensual'        => $agg->evolucionMensual($org, $gestion),
            'evolucion_anual'          => $agg->evolucionAnual($org),
            'top_paises'               => $agg->topPaises($org, $gestion),
            'top_productos'            => $agg->topProductos($org, $gestion),
            'distribucion_zona'        => $agg->distribucionZona($org, $gestion),
            'distribucion_departamento' => $agg->distribucionDepartamento($org, $gestion),
            'participacion_pais'       => $agg->participacionPais($org, $gestion),
            'distribucion_medio'       => $agg->distribucionMedio($org, $gestion),
        ]);
    }
}
