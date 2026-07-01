<?php

namespace App\Http\Controllers;

use App\Models\Configuracion;
use App\Servicios\AgregadorDashboard;
use App\Servicios\AgregadorDashboardMercosur;
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
     *
     * Cada organizacion guarda sus datos con una arquitectura distinta (el INE
     * en el microdato operacion_comercio_exterior; MERCOSUR en sus series
     * propias serie_comercio_zona / serie_comercio_producto_zona). Se elige el
     * agregador segun la organizacion, pero ambos devuelven exactamente la
     * misma forma de respuesta para que la vista no necesite saber la diferencia.
     */
    public function datos(Request $request, AgregadorDashboard $agg, AgregadorDashboardMercosur $aggMercosur): JsonResponse
    {
        $datos = $request->validate([
            'organizacion_id' => ['required', 'integer'],
            'gestion'         => ['nullable', 'integer'],
        ]);

        $org = $datos['organizacion_id'];
        $gestion = $datos['gestion'] ?? null;

        if ($org === 3) {
            return response()->json([
                'kpis'                      => $aggMercosur->kpis($gestion),
                'evolucion_mensual'         => $aggMercosur->evolucionMensual($gestion),
                'evolucion_anual'           => $aggMercosur->evolucionAnual(),
                'top_paises'                => $aggMercosur->topPaises($gestion),
                'top_productos'             => $aggMercosur->topProductos($gestion),
                'distribucion_zona'         => $aggMercosur->distribucionZona($gestion),
                'distribucion_departamento' => $aggMercosur->distribucionDepartamento(),
                'participacion_pais'        => $aggMercosur->participacionPais($gestion),
                'distribucion_medio'        => $aggMercosur->distribucionMedio(),
            ]);
        }

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
