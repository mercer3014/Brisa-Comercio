<?php

namespace App\Http\Controllers;

use App\Models\Configuracion;
use App\Servicios\AgregadorDashboard;
use App\Servicios\AgregadorDashboardAladi;
use App\Servicios\AgregadorDashboardMercosur;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
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
     * propias serie_comercio_zona / serie_comercio_producto_zona; ALADI en los
     * rankings top-50 de ranking_comercio). Se elige el agregador segun la
     * organizacion, pero todos devuelven exactamente la misma forma de
     * respuesta para que la vista no necesite saber la diferencia.
     */
    public function datos(
        Request $request,
        AgregadorDashboard $agg,
        AgregadorDashboardMercosur $aggMercosur,
        AgregadorDashboardAladi $aggAladi
    ): JsonResponse {
        $datos = $request->validate([
            'organizacion_id' => ['required', 'integer'],
            'gestion'         => ['nullable', 'integer'],
        ]);

        $org = $datos['organizacion_id'];
        $gestion = $datos['gestion'] ?? null;

        // Cache: las agregaciones (sobre todo las del microdato del INE, con
        // millones de filas) son costosas y solo cambian con una carga nueva
        // (la version del cache es el ultimo carga_id).
        $ver = (int) DB::table('carga_archivo')->max('carga_id');

        $respuesta = Cache::remember("dash.datos.{$ver}.{$org}.".($gestion ?? 'todas'), 86400, function () use ($org, $gestion, $agg, $aggMercosur, $aggAladi) {
            if ($org === 3 || $org === 2) {
                $servicio = $org === 3 ? $aggMercosur : $aggAladi;

                return [
                    'kpis'                      => $servicio->kpis($gestion),
                    'evolucion_mensual'         => $servicio->evolucionMensual($gestion),
                    'evolucion_anual'           => $servicio->evolucionAnual(),
                    'top_paises'                => $servicio->topPaises($gestion),
                    'top_productos'             => $servicio->topProductos($gestion),
                    'distribucion_zona'         => $servicio->distribucionZona($gestion),
                    'distribucion_departamento' => $servicio->distribucionDepartamento(),
                    'participacion_pais'        => $servicio->participacionPais($gestion),
                    'distribucion_medio'        => $servicio->distribucionMedio(),
                ];
            }

            return [
                'kpis'                     => $agg->kpis($org, $gestion),
                'evolucion_mensual'        => $agg->evolucionMensual($org, $gestion),
                'evolucion_anual'          => $agg->evolucionAnual($org),
                'top_paises'               => $agg->topPaises($org, $gestion),
                'top_productos'            => $agg->topProductos($org, $gestion),
                'distribucion_zona'        => $agg->distribucionZona($org, $gestion),
                'distribucion_departamento' => $agg->distribucionDepartamento($org, $gestion),
                'participacion_pais'       => $agg->participacionPais($org, $gestion),
                'distribucion_medio'       => $agg->distribucionMedio($org, $gestion),
            ];
        });

        return response()->json($respuesta);
    }
}
