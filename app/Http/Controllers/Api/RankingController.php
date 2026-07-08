<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Servicios\PortalApi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Rankings dinámicos del portal (API). Cubre dimensiones INE
 * (productos|países|departamentos|aduanas) y el ranking ALADI.
 */
class RankingController extends Controller
{
    public function __construct(private PortalApi $api)
    {
    }

    /** GET /api/v1/rankings/{tipo} — tipo: productos|países|departamentos|aduanas|aladi. */
    public function index(Request $request, string $tipo): JsonResponse
    {
        $gestion = $request->integer('gestion') ?: null;
        $limit   = max(1, min(100, $request->integer('limit') ?: 10));

        // Flujo explícito del query (sin forzar default); null = sin filtro de flujo.
        $flujoRaw = strtolower((string) $request->query('flujo', ''));
        $flujoOpt = in_array($flujoRaw, ['exp', 'imp'], true) ? $flujoRaw : null;

        if ($tipo === 'aladi') {
            // ALADI_RANKING no distingue flujo salvo que se pida explícitamente.
            $datos = Cache::remember("api.ranking.aladi.{$flujoOpt}.$gestion.$limit", 600,
                fn () => $this->api->aladiRanking($gestion, $flujoOpt, $limit));

            return response()->json($datos);
        }

        // Para dimensiones INE el flujo por defecto es exportación.
        $flujo = $flujoOpt ?? 'exp';

        if (! in_array($tipo, ['productos', 'paises', 'departamentos', 'aduanas'], true)) {
            return response()->json(['message' => "Tipo de ranking no soportado: {$tipo}"], 422);
        }

        $datos = Cache::remember("api.ranking.$tipo.$flujo.$gestion.$limit", 600,
            fn () => $this->api->rankingDinamico($tipo, $flujo, $gestion, $limit));

        return response()->json($datos);
    }
}
