<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Servicios\PortalApi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * KPIs generales y listado de organizaciones para el home del portal.
 * Endpoints públicos (JSON), bajo /api/v1.
 */
class KpiController extends Controller
{
    public function __construct(private PortalApi $api)
    {
    }

    /** GET /api/v1/kpis — KPIs del home (INE por defecto). */
    public function home(Request $request): JsonResponse
    {
        $gestion = $request->integer('gestion') ?: null;

        $datos = Cache::remember("api.kpis.ine.{$gestion}", 600,
            fn () => $this->api->kpis(PortalApi::ORG_INE, $gestion));

        return response()->json($datos);
    }

    /** GET /api/v1/kpis/{organización} — KPIs de una organización. */
    public function organizacion(Request $request, int $organizacion): JsonResponse
    {
        $gestion = $request->integer('gestion') ?: null;

        $datos = Cache::remember("api.kpis.{$organizacion}.{$gestion}", 600,
            fn () => $this->api->kpis($organizacion, $gestion));

        return response()->json($datos);
    }

    /** GET /api/v1/organizaciones — lista con detalle y conteo de registros. */
    public function organizaciones(): JsonResponse
    {
        $datos = Cache::remember('api.organizaciones', 600,
            fn () => $this->api->organizaciones());

        return response()->json(['data' => $datos]);
    }

    /** GET /api/v1/organizaciones/{id} — detalle de una organización. */
    public function detalle(int $id): JsonResponse
    {
        $org = $this->api->organizacion($id);

        if (! $org) {
            return response()->json(['message' => 'Organización no encontrada'], 404);
        }

        return response()->json($org);
    }
}
