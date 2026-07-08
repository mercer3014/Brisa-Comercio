<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Servicios\PortalApi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Indicadores calculados del comercio exterior INE
 * (cobertura, concentración HHI, balanza, etc.).
 */
class IndicadoresController extends Controller
{
    public function __construct(private PortalApi $api)
    {
    }

    /** GET /api/v1/indicadores?org=1&gestión=2019 */
    public function index(Request $request): JsonResponse
    {
        $gestion = $request->integer('gestion') ?: null;
        $org = $request->integer('org') ?: 1;

        $datos = Cache::remember("api.indicadores.$org.$gestion", 600,
            fn () => $this->api->indicadores($gestion, $org));

        return response()->json($datos);
    }
}
