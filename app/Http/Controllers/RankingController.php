<?php

namespace App\Http\Controllers;

use App\Servicios\ExportadorReporte;
use App\Servicios\RankingPortal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Endpoints publicos de rankings y comparadores (Tarea 13). Sin autenticación;
 * respetan SIEMPRE la organización seleccionada.
 */
class RankingController extends Controller
{
    /**
     * Reglas de validación comunes a un ranking.
     */
    private function reglasRanking(): array
    {
        return [
            'organizacion_id' => ['required', 'integer'],
            'gestion'         => ['required', 'integer'],
            'flujo'           => ['required', 'integer', 'in:1,2'],
            'dimension'       => ['required', 'string', 'in:producto,pais,departamento'],
            'metrica'         => ['required', 'string', 'in:valor,peso'],
            'limite'          => ['required', 'integer', 'in:10,20,50'],
        ];
    }

    public function datos(Request $request, RankingPortal $r): JsonResponse
    {
        $d = $request->validate($this->reglasRanking());

        return response()->json($r->ranking(
            (int) $d['organizacion_id'], (int) $d['gestion'], (int) $d['flujo'],
            $d['dimension'], $d['metrica'], (int) $d['limite']
        ));
    }

    public function comparar(Request $request, RankingPortal $r): JsonResponse
    {
        $d = $request->validate([
            'modo'            => ['required', 'string', 'in:anios,flujos'],
            'organizacion_id' => ['required', 'integer'],
            'dimension'       => ['required', 'string', 'in:producto,pais'],
            'flujo'           => ['nullable', 'integer', 'in:1,2'],
            'anio_a'          => ['nullable', 'integer'],
            'anio_b'          => ['nullable', 'integer'],
            'gestion'         => ['nullable', 'integer'],
            'limite'          => ['required', 'integer', 'in:10,20,50'],
        ]);

        $org = (int) $d['organizacion_id'];
        $limite = (int) $d['limite'];

        if ($d['modo'] === 'anios') {
            $request->validate([
                'anio_a' => ['required', 'integer'],
                'anio_b' => ['required', 'integer'],
                'flujo'  => ['required', 'integer', 'in:1,2'],
            ]);

            return response()->json($r->compararAnios(
                $org, $d['dimension'], (int) $d['flujo'], (int) $d['anio_a'], (int) $d['anio_b'], $limite
            ));
        }

        $request->validate(['gestion' => ['required', 'integer']]);

        return response()->json($r->compararFlujos($org, $d['dimension'], (int) $d['gestion'], $limite));
    }

    /**
     * Descarga un ranking en Excel/CSV.
     */
    public function exportar(Request $request, RankingPortal $r, ExportadorReporte $exp): mixed
    {
        $d = $request->validate($this->reglasRanking() + [
            'formato' => ['required', 'string', 'in:xlsx,csv'],
        ]);

        $ranking = $r->ranking(
            (int) $d['organizacion_id'], (int) $d['gestion'], (int) $d['flujo'],
            $d['dimension'], $d['metrica'], (int) $d['limite']
        );

        $unidad = $ranking['unidad'];
        $reporte = [
            'titulo'   => $ranking['titulo'],
            'columnas' => ['Posición', 'Nombre', "Valor ({$unidad})", '% del total', '% acumulado'],
            'filas'    => array_map(fn ($f) => [
                $f['posicion'], $f['label'], $f['valor'], $f['porcentaje'], $f['acumulado'],
            ], $ranking['filas']),
            'resumen'  => ['Total (' . $unidad . ')' => $ranking['total']],
        ];

        return $exp->descargar($reporte, $d['formato']);
    }
}
