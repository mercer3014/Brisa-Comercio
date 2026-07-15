<?php

namespace App\Servicios;

use Illuminate\Support\Facades\DB;

/**
 * Version FAOSTAT de ResumenPortal: misma forma de salida (mismo contrato que
 * consume Portal/Inicio.vue) pero FAOSTAT publica indices comerciales (base
 * 2014-2016 = 100), no valores en USD como INE/ALADI/MERCOSUR. Por eso
 * "indicadores" reporta el indice promedio de exportacion/importacion de
 * Bolivia en vez de expo/impo/balanza en dolares, y los "top" son productos
 * por indice (esta fuente no tiene destino bilateral por pais). FAOSTAT es
 * anual: "evolucion" (que es mensual) se omite, igual que en ALADI/MERCOSUR.
 */
class ResumenPortalFaostat
{
    private const ORG_ID = 4;

    public function gestionMasReciente(): ?int
    {
        $g = DB::table('serie_indicador_agricola')->where('organizacion_id', self::ORG_ID)->max('gestion');

        return $g !== null ? (int) $g : null;
    }

    public function portada(?int $gestion): array
    {
        $org = DB::table('organizacion')->where('organizacion_id', self::ORG_ID)->first();
        $paisId = $this->paisDefecto();
        $hayDatos = $gestion !== null && DB::table('serie_indicador_agricola')
            ->where('organizacion_id', self::ORG_ID)->where('gestion', $gestion)->exists();

        return [
            'meta' => [
                'organizacion' => $org?->nombre,
                'sigla'        => $org?->sigla,
                'gestion'      => $gestion,
                'fuente'       => 'Fuente: FAOSTAT - Bolivia.'.($gestion ? " Datos {$gestion}." : ''),
                'hay_datos'    => $hayDatos,
                'unidad'       => 'indice (2014-2016 = 100)',
            ],
            'titulares'     => $hayDatos ? $this->titulares($gestion, $paisId) : [],
            'indicadores'   => $hayDatos ? $this->indicadores($gestion, $paisId) : null,
            'top_productos' => $hayDatos ? $this->topProductos($gestion, $paisId, 'EXPORTACION', 5) : [],
            'top_destinos'  => $hayDatos ? $this->topProductos($gestion, $paisId, 'IMPORTACION', 5) : [],
            'evolucion'     => [], // FAOSTAT es anual: no hay desglose mensual que mostrar aqui.
        ];
    }

    /** Pais de referencia: Bolivia (codigo_pais = 68), igual que el panel dedicado de la organizacion. */
    private function paisDefecto(): ?int
    {
        $id = DB::table('pais as p')
            ->join('fuente_datos as f', 'f.fuente_id', '=', 'p.fuente_id')
            ->where('f.organizacion_id', self::ORG_ID)
            ->where('p.codigo_pais', 68)
            ->value('p.pais_id');

        return $id ? (int) $id : null;
    }

    private function indicadores(int $gestion, ?int $paisId): array
    {
        $expo = $this->indiceAnual($gestion, $paisId, 'EXPORTACION');
        $impo = $this->indiceAnual($gestion, $paisId, 'IMPORTACION');
        $expoAnt = $this->indiceAnual($gestion - 1, $paisId, 'EXPORTACION');
        $impoAnt = $this->indiceAnual($gestion - 1, $paisId, 'IMPORTACION');

        $base = DB::table('serie_indicador_agricola')
            ->where('organizacion_id', self::ORG_ID)->where('gestion', $gestion);

        return [
            'valor_exportado'     => $expo,
            'variacion_expo'      => $this->variacion($expo, $expoAnt),
            'valor_importado'     => $impo,
            'variacion_impo'      => $this->variacion($impo, $impoAnt),
            'balanza_comercial'   => ($expo !== null && $impo !== null) ? round($expo - $impo, 1) : null,
            'paises_destino'      => (int) (clone $base)->distinct()->count('pais_id'),
            'productos_distintos' => (int) (clone $base)->distinct()->count('producto_codigo_externo_id'),
            'gestion_anterior'    => $gestion - 1,
        ];
    }

    /** Indice promedio de "valor" (exportacion o importacion) de Bolivia en una gestion. */
    private function indiceAnual(?int $gestion, ?int $paisId, string $tipo): ?float
    {
        if (! $gestion || ! $paisId) {
            return null;
        }

        $v = DB::table('serie_indicador_agricola as s')
            ->join('faostat_elemento as e', 'e.elemento_id', '=', 's.elemento_id')
            ->where('s.organizacion_id', self::ORG_ID)
            ->where('s.pais_id', $paisId)
            ->where('s.gestion', $gestion)
            ->where('e.tipo_comercio', $tipo)
            ->where('e.nombre_elemento', 'ilike', '%valor%')
            ->where('e.nombre_elemento', 'not ilike', '%unidad%')
            ->where('e.nombre_elemento', 'not ilike', '%volumen%')
            ->whereNotNull('s.valor')
            ->avg('s.valor');

        return $v !== null ? round((float) $v, 1) : null;
    }

    private function variacion(?float $actual, ?float $anterior): ?float
    {
        if ($actual === null || $anterior === null || $anterior <= 0) {
            return null;
        }

        return round((($actual - $anterior) / $anterior) * 100, 1);
    }

    /**
     * Titulares: producto con mayor indice de exportacion/importacion de
     * Bolivia en la gestion (indice de valor, base 2014-2016 = 100).
     */
    private function titulares(int $gestion, ?int $paisId): array
    {
        if (! $paisId) {
            return [];
        }

        $titulares = [];

        if ($p = $this->liderProducto($gestion, $paisId, 'EXPORTACION')) {
            $titulares[] = $this->titular('producto_exportado',
                "En {$gestion}, el producto con mayor indice de exportacion fue {$p->label} (indice ".$this->fmt($p->valor).').', $p);
        }
        if ($p = $this->liderProducto($gestion, $paisId, 'IMPORTACION')) {
            $titulares[] = $this->titular('producto_importado',
                "El producto con mayor indice de importacion fue {$p->label} (indice ".$this->fmt($p->valor).').', $p);
        }

        return $titulares;
    }

    private function titular(string $clave, string $texto, $row): array
    {
        return [
            'clave'    => $clave,
            'texto'    => $texto,
            'etiqueta' => $row->label,
            'valor'    => (float) $row->valor,
        ];
    }

    private function liderProducto(int $gestion, int $paisId, string $tipo)
    {
        return $this->baseProductos($gestion, $paisId, $tipo)
            ->selectRaw('pc.descripcion_externa as label')
            ->selectRaw('MAX(s.valor) as valor')
            ->groupBy('pc.descripcion_externa')
            ->orderByDesc('valor')
            ->first();
    }

    /** Top N productos de Bolivia por indice de valor (exportacion o importacion). */
    private function topProductos(int $gestion, ?int $paisId, string $tipo, int $n): array
    {
        if (! $paisId) {
            return [];
        }

        return $this->baseProductos($gestion, $paisId, $tipo)
            ->selectRaw('pc.descripcion_externa as label')
            ->selectRaw('MAX(s.valor) as valor')
            ->groupBy('pc.descripcion_externa')
            ->orderByDesc('valor')
            ->limit($n)
            ->get()
            ->map(fn ($r) => [
                'label' => mb_strimwidth((string) $r->label, 0, 45, '...'),
                'valor' => (float) $r->valor,
            ])->all();
    }

    private function baseProductos(int $gestion, int $paisId, string $tipo)
    {
        return DB::table('serie_indicador_agricola as s')
            ->join('faostat_elemento as e', 'e.elemento_id', '=', 's.elemento_id')
            ->join('producto_codigo_externo as pc', 'pc.producto_codigo_externo_id', '=', 's.producto_codigo_externo_id')
            ->where('s.organizacion_id', self::ORG_ID)
            ->where('s.pais_id', $paisId)
            ->where('s.gestion', $gestion)
            ->where('e.tipo_comercio', $tipo)
            ->where('e.nombre_elemento', 'ilike', '%valor%')
            ->where('e.nombre_elemento', 'not ilike', '%unidad%')
            ->where('e.nombre_elemento', 'not ilike', '%volumen%')
            ->whereNotNull('s.valor');
    }

    private function fmt(float $v): string
    {
        return number_format($v, 1, '.', ',');
    }
}
