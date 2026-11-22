<?php

namespace App\Http\Controllers;

use App\Servicios\PortalVistas;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Endpoints JSON del portal rediseñado (GEO-12). Públicos, solo lectura,
 * con cache por versión de datos dentro de PortalVistas.
 */
class PortalDatosController extends Controller
{
    public function __construct(private PortalVistas $vistas)
    {
    }

    private function gestion(Request $r): int
    {
        $g = (int) $r->query('gestion', 0);

        return $g > 1900 && $g < 2100 ? $g : (int) ($this->vistas->gestionDefecto() ?? date('Y'));
    }

    private function flujo(Request $r): string
    {
        return $r->query('flujo') === 'imp' ? 'imp' : 'exp';
    }

    public function panorama(Request $r): JsonResponse
    {
        return response()->json($this->vistas->panorama($this->gestion($r)));
    }

    public function vias(Request $r): JsonResponse
    {
        return response()->json($this->vistas->viasDetalle($this->gestion($r)));
    }

    public function productos(Request $r): JsonResponse
    {
        return response()->json($this->vistas->productos($this->gestion($r), $this->flujo($r)));
    }

    public function ficha(Request $r, int $id): JsonResponse
    {
        return response()->json($this->vistas->fichaProducto($id, $this->gestion($r), $this->flujo($r)));
    }

    public function rankings(Request $r): JsonResponse
    {
        $anios = collect((array) $r->query('anios', []))
            ->map(fn ($a) => (int) $a)->filter(fn ($a) => $a > 1900 && $a < 2100)
            ->unique()->take(6)->values()->all();
        if (! $anios) {
            $g = (int) $this->vistas->gestionDefecto();
            $anios = [$g - 1, $g];
        }
        $dim = in_array($r->query('dimension'), ['producto', 'pais', 'departamento', 'seccion'], true) ? $r->query('dimension') : 'producto';
        $top = max(5, min(25, (int) $r->query('top', 10)));

        return response()->json($this->vistas->rankings($anios, $this->flujo($r), $dim, $top));
    }

    public function indicadores(Request $r): JsonResponse
    {
        return response()->json($this->vistas->indicadores($this->gestion($r)));
    }
}
