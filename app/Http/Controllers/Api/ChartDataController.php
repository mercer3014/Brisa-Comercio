<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Servicios\PortalApi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Datos para gráficos (ApexCharts) del portal público. Todos los endpoints
 * devuelven JSON con la estructura { categorías, series, meta } y usan cache
 * de 10 minutos sobre las consultas agregadas.
 */
class ChartDataController extends Controller
{
    private const TTL = 600;

    public function __construct(private PortalApi $api)
    {
    }

    private function gestion(Request $r): ?int
    {
        return $r->integer('gestion') ?: null;
    }

    private function flujo(Request $r): string
    {
        $f = strtolower((string) $r->query('flujo', 'exp'));

        return in_array($f, ['exp', 'imp', 'ambos'], true) ? $f : 'exp';
    }

    private function limit(Request $r, int $def = 10): int
    {
        return max(1, min(100, $r->integer('limit') ?: $def));
    }

    private function cache(string $key, \Closure $fn): JsonResponse
    {
        return response()->json(Cache::remember("api.chart.$key", self::TTL, $fn));
    }

    // ---- INE ----------------------------------------------------------------

    public function comercioMensual(Request $r): JsonResponse
    {
        $g = $this->gestion($r);

        return $this->cache("comercio-mensual.$g", fn () => $this->api->comercioMensual($g));
    }

    public function evolucionAnual(): JsonResponse
    {
        return $this->cache('evolucion-anual', fn () => $this->api->evolucionAnual());
    }

    public function topProductos(Request $r): JsonResponse
    {
        $g = $this->gestion($r);
        $f = $this->flujo($r);
        $l = $this->limit($r);

        return $this->cache("top-productos.$f.$g.$l", fn () => $this->api->topProductos($f, $g, $l));
    }

    public function topPaises(Request $r): JsonResponse
    {
        $g = $this->gestion($r);
        $f = $this->flujo($r);
        $l = $this->limit($r);

        return $this->cache("top-paises.$f.$g.$l", fn () => $this->api->topPaises($f, $g, $l));
    }

    public function topDepartamentos(Request $r): JsonResponse
    {
        $g = $this->gestion($r);
        $l = $this->limit($r);

        return $this->cache("top-departamentos.$g.$l", fn () => $this->api->topDepartamentos($g, $l));
    }

    public function seccionArancelaria(Request $r): JsonResponse
    {
        $g = $this->gestion($r);
        $f = $this->flujo($r);

        return $this->cache("seccion.$f.$g", fn () => $this->api->seccionArancelaria($f, $g));
    }

    public function transporte(Request $r): JsonResponse
    {
        $g = $this->gestion($r);

        return $this->cache("transporte.$g", fn () => $this->api->transporte($g));
    }

    public function comercioPorVia(Request $r): JsonResponse
    {
        $g = $this->gestion($r);
        $org = $r->integer('organizacion_id') ?: PortalApi::ORG_INE;

        return $this->cache("comercio-por-via.$g.$org", fn () => $this->api->comercioPorVia($g, $org));
    }

    public function tntEvolucion(): JsonResponse
    {
        return $this->cache('tnt-evolucion', fn () => $this->api->tntEvolucion());
    }

    public function mapaFlujos(Request $r): JsonResponse
    {
        $g = $this->gestion($r);
        $f = $this->flujo($r);
        $l = $this->limit($r, 30);

        return $this->cache("mapa-flujos.$f.$g.$l", fn () => $this->api->mapaFlujos($g, $f, $l));
    }

    // ---- MERCOSUR -----------------------------------------------------------

    public function mercosurZona(Request $r): JsonResponse
    {
        $g = $this->gestion($r);

        return $this->cache("mercosur-zona.$g", fn () => $this->api->mercosurZona($g));
    }

    public function mercosurBalanza(Request $r): JsonResponse
    {
        $g = $this->gestion($r);

        return $this->cache("mercosur-balanza.$g", fn () => $this->api->mercosurBalanza($g));
    }

    public function mercosurProductos(Request $r): JsonResponse
    {
        $g = $this->gestion($r);
        $l = $this->limit($r);

        return $this->cache("mercosur-productos.$g.$l", fn () => $this->api->mercosurProductos($g, $l));
    }

    public function mercosurPaises(Request $r): JsonResponse
    {
        $g = $this->gestion($r);
        $z = $r->integer('zona_id') ?: null;
        $l = $this->limit($r, 20);

        return $this->cache("mercosur-paises.$g.$z.$l", fn () => $this->api->mercosurPaises($g, $z, $l));
    }

    // ---- ALADI --------------------------------------------------------------

    public function aladiRanking(Request $r): JsonResponse
    {
        $g = $this->gestion($r);
        $f = strtolower((string) $r->query('flujo', '')) ?: null;
        $l = $this->limit($r, 20);
        $p = $r->integer('pais_id') ?: null;

        return $this->cache("aladi-ranking.$f.$g.$l.$p", fn () => $this->api->aladiRanking($g, $f, $l, $p));
    }

    public function aladiEvolucion(Request $r): JsonResponse
    {
        $p = $r->integer('pais_id') ?: null;

        return $this->cache("aladi-evolucion.$p", fn () => $this->api->aladiEvolucion($p));
    }

    public function aladiPaises(Request $r): JsonResponse
    {
        $g = $this->gestion($r);
        $f = strtolower((string) $r->query('flujo', '')) ?: null;

        return $this->cache("aladi-paises.$g.$f", fn () => $this->api->aladiPaises($g, $f));
    }

    // ---- FAOSTAT ------------------------------------------------------------

    public function faostatEvolucion(Request $r): JsonResponse
    {
        $p = $r->integer('pais_id') ?: null;
        $prod = $r->integer('producto_id') ?: null;

        return $this->cache("faostat-evolucion.$p.$prod", fn () => $this->api->faostatEvolucion($p, $prod));
    }

    public function faostatProductos(Request $r): JsonResponse
    {
        $p = $r->integer('pais_id') ?: null;
        $f = strtolower((string) $r->query('flujo', 'exp')) === 'imp' ? 'imp' : 'exp';
        $g = $this->gestion($r);
        $l = $this->limit($r, 10);

        return $this->cache("faostat-productos.$p.$f.$g.$l", fn () => $this->api->faostatProductos($p, $f, $g, $l));
    }

    public function faostatFiltros(Request $r): JsonResponse
    {
        $p = $r->integer('pais_id') ?: null;

        return $this->cache("faostat-filtros.$p", fn () => $this->api->faostatFiltros($p));
    }

    public function faostat(string $subtipo): JsonResponse
    {
        return $this->cache("faostat.$subtipo", fn () => $this->api->faostat($subtipo));
    }
}
