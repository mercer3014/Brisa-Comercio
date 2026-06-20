<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Servicios\PortalApi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Catálogos para poblar selects del frontend (filtros).
 * Cache larga (30 min) porque cambian poco.
 */
class FiltroController extends Controller
{
    private const TTL = 1800;

    public function __construct(private PortalApi $api)
    {
    }

    /** GET /api/v1/filtros/paises */
    public function paises(): JsonResponse
    {
        return response()->json(['data' => Cache::remember('api.filtros.paises', self::TTL,
            fn () => $this->api->filtroPaises())]);
    }

    /** GET /api/v1/filtros/zonas */
    public function zonas(): JsonResponse
    {
        return response()->json(['data' => Cache::remember('api.filtros.zonas', self::TTL,
            fn () => $this->api->filtroZonas())]);
    }

    /** GET /api/v1/filtros/secciones */
    public function secciones(): JsonResponse
    {
        return response()->json(['data' => Cache::remember('api.filtros.secciones', self::TTL,
            fn () => $this->api->filtroSecciones())]);
    }

    /** GET /api/v1/filtros/productos?buscar=texto */
    public function productos(Request $request): JsonResponse
    {
        $buscar = trim((string) $request->query('buscar', '')) ?: null;
        $limit = max(1, min(100, $request->integer('limit') ?: 50));
        $key = 'api.filtros.productos.' . md5(($buscar ?? '') . '|' . $limit);

        return response()->json(['data' => Cache::remember($key, self::TTL,
            fn () => $this->api->filtroProductos($buscar, $limit))]);
    }

    /** GET /api/v1/filtros/gestiones — años disponibles (opcional ?org=). */
    public function gestiones(Request $request): JsonResponse
    {
        $org = $request->integer('org') ?: null;

        return response()->json(['data' => Cache::remember("api.filtros.gestiones.$org", self::TTL,
            fn () => $this->api->filtroGestiones($org))]);
    }
}
