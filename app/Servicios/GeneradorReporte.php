<?php

namespace App\Servicios;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Genera los reportes predefinidos como conjuntos de filas agregadas (GROUP BY)
 * sobre el microdato. Cada reporte respeta los parametros: organizacion, flujo y
 * rango de gestiones.
 */
class GeneradorReporte
{
    /**
     * Catalogo de reportes disponibles.
     */
    public static function catalogo(): array
    {
        return [
            'por_seccion'      => ['titulo' => 'Comercio por seccion arancelaria', 'columnas' => ['Codigo', 'Seccion', 'Registros', 'Valor (USD)', 'Peso bruto (kg)']],
            'por_capitulo'     => ['titulo' => 'Comercio por capitulo arancelario', 'columnas' => ['Codigo', 'Capitulo', 'Registros', 'Valor (USD)', 'Peso bruto (kg)']],
            'por_pais'         => ['titulo' => 'Comercio por pais', 'columnas' => ['Pais', 'Registros', 'Valor (USD)', 'Peso bruto (kg)']],
            'por_departamento' => ['titulo' => 'Comercio por departamento', 'columnas' => ['Departamento', 'Registros', 'Valor (USD)', 'Peso bruto (kg)']],
            'balanza_mensual'  => ['titulo' => 'Balanza comercial mensual', 'columnas' => ['Periodo', 'Exportaciones (USD)', 'Importaciones (USD)', 'Balanza (USD)']],
        ];
    }

    private string $valor = 'COALESCE(o.valor_fob_usd,0) + COALESCE(o.valor_cif_frontera_usd,0)';

    private function base(array $p): Builder
    {
        $q = DB::table('operacion_comercio_exterior as o')
            ->join('tiempo as t', 't.tiempo_id', '=', 'o.tiempo_id')
            ->where('o.organizacion_id', $p['organizacion_id']);

        if (! empty($p['gestion_desde'])) {
            $q->where('t.gestion', '>=', $p['gestion_desde']);
        }
        if (! empty($p['gestion_hasta'])) {
            $q->where('t.gestion', '<=', $p['gestion_hasta']);
        }
        // Flujo: EXPORTACION usa fob, IMPORTACION usa cif. Se filtra por el valor presente.
        if (($p['flujo'] ?? '') === 'EXPORTACION') {
            $q->whereNotNull('o.valor_fob_usd');
        } elseif (($p['flujo'] ?? '') === 'IMPORTACION') {
            $q->whereNotNull('o.valor_cif_frontera_usd');
        }

        return $q;
    }

    /**
     * Devuelve ['titulo', 'columnas', 'filas', 'resumen'] del reporte solicitado.
     */
    public function generar(string $tipo, array $p): array
    {
        $cat = self::catalogo()[$tipo] ?? null;
        if (! $cat) {
            abort(404, 'Reporte no encontrado.');
        }

        $filas = match ($tipo) {
            'por_seccion'      => $this->porSeccion($p),
            'por_capitulo'     => $this->porCapitulo($p),
            'por_pais'         => $this->porPais($p),
            'por_departamento' => $this->porDepartamento($p),
            'balanza_mensual'  => $this->balanzaMensual($p),
        };

        return [
            'tipo'     => $tipo,
            'titulo'   => $cat['titulo'],
            'columnas' => $cat['columnas'],
            'filas'    => $filas,
            'resumen'  => $this->resumen($tipo, $filas),
        ];
    }

    private function porSeccion(array $p): array
    {
        return $this->base($p)
            ->join('producto as pr', 'pr.producto_id', '=', 'o.producto_id')
            ->join('capitulo_arancelario as c', 'c.capitulo_id', '=', 'pr.capitulo_id')
            ->join('seccion_arancelaria as s', 's.seccion_id', '=', 'c.seccion_id')
            ->selectRaw('s.codigo_seccion as codigo, s.descripcion as nombre, COUNT(*) as registros')
            ->selectRaw("SUM({$this->valor}) as valor, SUM(COALESCE(o.peso_bruto_kg,0)) as peso")
            ->groupBy('s.codigo_seccion', 's.descripcion')->orderByDesc('valor')
            ->get()->map(fn ($r) => [$r->codigo, $r->nombre, (int) $r->registros, round($r->valor, 2), round($r->peso, 2)])->all();
    }

    private function porCapitulo(array $p): array
    {
        return $this->base($p)
            ->join('producto as pr', 'pr.producto_id', '=', 'o.producto_id')
            ->join('capitulo_arancelario as c', 'c.capitulo_id', '=', 'pr.capitulo_id')
            ->selectRaw('c.codigo_capitulo as codigo, c.descripcion as nombre, COUNT(*) as registros')
            ->selectRaw("SUM({$this->valor}) as valor, SUM(COALESCE(o.peso_bruto_kg,0)) as peso")
            ->groupBy('c.codigo_capitulo', 'c.descripcion')->orderByDesc('valor')
            ->get()->map(fn ($r) => [$r->codigo, $r->nombre, (int) $r->registros, round($r->valor, 2), round($r->peso, 2)])->all();
    }

    private function porPais(array $p): array
    {
        return $this->base($p)
            ->join('pais as pa', 'pa.pais_id', '=', 'o.pais_id')
            ->selectRaw('pa.nombre as nombre, COUNT(*) as registros')
            ->selectRaw("SUM({$this->valor}) as valor, SUM(COALESCE(o.peso_bruto_kg,0)) as peso")
            ->groupBy('pa.nombre')->orderByDesc('valor')
            ->get()->map(fn ($r) => [$r->nombre, (int) $r->registros, round($r->valor, 2), round($r->peso, 2)])->all();
    }

    private function porDepartamento(array $p): array
    {
        return $this->base($p)
            ->join('departamento as d', 'd.departamento_id', '=', 'o.departamento_id')
            ->selectRaw('d.nombre as nombre, COUNT(*) as registros')
            ->selectRaw("SUM({$this->valor}) as valor, SUM(COALESCE(o.peso_bruto_kg,0)) as peso")
            ->groupBy('d.nombre')->orderByDesc('valor')
            ->get()->map(fn ($r) => [$r->nombre, (int) $r->registros, round($r->valor, 2), round($r->peso, 2)])->all();
    }

    private function balanzaMensual(array $p): array
    {
        return $this->base($p)
            ->selectRaw("t.gestion, t.mes")
            ->selectRaw('SUM(COALESCE(o.valor_fob_usd,0)) as expo, SUM(COALESCE(o.valor_cif_frontera_usd,0)) as impo')
            ->groupBy('t.gestion', 't.mes')->orderBy('t.gestion')->orderBy('t.mes')
            ->get()->map(fn ($r) => [
                $r->gestion.'-'.str_pad($r->mes, 2, '0', STR_PAD_LEFT),
                round($r->expo, 2), round($r->impo, 2), round($r->expo - $r->impo, 2),
            ])->all();
    }

    /**
     * Resumen (totales) segun el tipo de reporte.
     */
    private function resumen(string $tipo, array $filas): array
    {
        if ($tipo === 'balanza_mensual') {
            return [
                'Exportaciones' => array_sum(array_column($filas, 1)),
                'Importaciones' => array_sum(array_column($filas, 2)),
                'Balanza'       => array_sum(array_column($filas, 3)),
            ];
        }

        // Para los demas, valor es la penultima columna y peso la ultima.
        $idxValor = count(($filas[0] ?? [])) - 2;
        $idxPeso = count(($filas[0] ?? [])) - 1;

        return [
            'Filas'       => count($filas),
            'Valor total' => $idxValor >= 0 ? array_sum(array_column($filas, $idxValor)) : 0,
            'Peso total'  => $idxPeso >= 0 ? array_sum(array_column($filas, $idxPeso)) : 0,
        ];
    }
}
