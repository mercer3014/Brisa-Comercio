<?php

namespace App\Http\Controllers;

use App\Models\Configuracion;
use App\Servicios\AgregadorDashboard;
use App\Servicios\AgregadorDashboardAladi;
use App\Servicios\AgregadorDashboardFaostat;
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
     * Cada organización guarda sus datos con una arquitectura distinta (el INE
     * en el microdato operacion_comercio_exterior; MERCOSUR en sus series
     * propias serie_comercio_zona / serie_comercio_producto_zona; ALADI en los
     * rankings top-50 de ranking_comercio). Se elige el agregador según la
     * organización, pero todos devuelven exactamente la misma forma de
     * respuesta para que la vista no necesite saber la diferencia.
     */
    public function datos(Request $request, \App\Servicios\ArmadorDashboard $armador): JsonResponse
    {
        $datos = $request->validate([
            'organizacion_id' => ['required', 'integer'],
            'gestion'         => ['nullable', 'integer'],
        ]);

        $org = $datos['organizacion_id'];
        $gestion = $datos['gestion'] ?? null;

        // Cache: las agregaciones (sobre todo las del microdato del INE, con
        // millones de filas) son costosas y solo cambian con una carga nueva
        // (la version del cache es el último carga_id). Las claves viven en
        // ClavesCache porque ovxel:calentar-cache las precalienta.
        $ver = \App\Servicios\ClavesCache::version();

        $respuesta = Cache::remember(
            \App\Servicios\ClavesCache::dashDatos($ver, $org, $gestion),
            \App\Servicios\ClavesCache::TTL,
            fn () => $armador->armar($org, $gestion)
        );

        return response()->json($respuesta);
    }
}
