<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Servicios\PortalApi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class ComparadorController extends Controller
{
    private const TTL = 600;

    public function __construct(private PortalApi $api)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $payload = [
            'modo' => strtolower((string) $request->query('modo', 'anio')),
            'dimension' => strtolower((string) $request->query('dimension', 'top-productos')),
            'flujo' => strtolower((string) $request->query('flujo', 'exp')),
            'anio_a' => $request->integer('anio_a') ?: null,
            'anio_b' => $request->integer('anio_b') ?: null,
            'pais_a' => $request->integer('pais_a') ?: null,
            'pais_b' => $request->integer('pais_b') ?: null,
            'producto_a' => $request->integer('producto_a') ?: null,
            'producto_b' => $request->integer('producto_b') ?: null,
            'limit' => max(1, min(50, $request->integer('limit') ?: 12)),
        ];

        $key = 'api.comparador.' . md5(json_encode($payload));

        return response()->json(Cache::remember($key, self::TTL, fn () => $this->api->comparador($payload)));
    }
}
