<?php

namespace App\Servicios;

use App\Support\Organizaciones;
use Illuminate\Support\Facades\DB;

/**
 * Cifras REALES para las pestañas "Datos cargados" y "Configuración" de la
 * gestión de datos: qué hay cargado por fuente y totales INE por gestión.
 * Usa las vistas de resumen para no recorrer millones de filas.
 */
class ResumenGestionDatos
{
    /**
     * @return array<int, array{sigla:string, nombre:string, tabla:string, filas:int, desde:?int, hasta:?int, cargas:int, ultima_carga:?string}>
     */
    public function porFuente(): array
    {
        $orgs = DB::table('organizacion')->pluck('nombre', 'sigla');
        $cargas = DB::table('carga_archivo')
            ->join('organizacion', 'organizacion.organizacion_id', '=', 'carga_archivo.organizacion_id')
            ->where('estado', 'COMPLETADO')
            ->groupBy('organizacion.sigla')
            ->selectRaw('organizacion.sigla, count(*) AS n, max(fecha_carga) AS ultima')
            ->get()->keyBy('sigla');

        $fuentes = [
            // INE: la vista anual suma n_operaciones (evita COUNT(*) sobre el hecho).
            [Organizaciones::INE, 'operacion_comercio_exterior',
                "SELECT COALESCE(sum(total_operaciones),0) AS filas, min(gestion) AS desde, max(gestion) AS hasta FROM mv_resumen_anual_ine"],
            [Organizaciones::MERCOSUR, 'serie_comercio_zona (por país)',
                "SELECT count(*) AS filas, min(gestion) AS desde, max(gestion) AS hasta FROM serie_comercio_zona"],
            [Organizaciones::MERCOSUR, 'serie_comercio_producto_zona (por ítem)',
                "SELECT count(*) AS filas, min(gestion) AS desde, max(gestion) AS hasta FROM serie_comercio_producto_zona"],
            [Organizaciones::ALADI, 'ranking_comercio',
                "SELECT count(*) AS filas, min(gestion) AS desde, max(gestion) AS hasta FROM ranking_comercio WHERE organizacion_id = ?", true],
            [Organizaciones::FAOSTAT, 'serie_indicador_agricola',
                "SELECT count(*) AS filas, min(gestion) AS desde, max(gestion) AS hasta FROM serie_indicador_agricola WHERE organizacion_id = ?", true],
        ];

        $res = [];
        foreach ($fuentes as $f) {
            [$sigla, $tabla, $sql] = $f;
            try {
                $r = DB::selectOne($sql, ! empty($f[3]) ? [Organizaciones::id($sigla)] : []);
            } catch (\Throwable $e) {
                report($e);
                $r = (object) ['filas' => 0, 'desde' => null, 'hasta' => null];
            }
            $c = $cargas[$sigla] ?? null;
            $res[] = [
                'sigla'        => $sigla,
                'nombre'       => $orgs[$sigla] ?? $sigla,
                'tabla'        => $tabla,
                'filas'        => (int) $r->filas,
                'desde'        => $r->desde !== null ? (int) $r->desde : null,
                'hasta'        => $r->hasta !== null ? (int) $r->hasta : null,
                'cargas'       => (int) ($c->n ?? 0),
                'ultima_carga' => $c->ultima ?? null,
            ];
        }

        return $res;
    }

    /**
     * Exportaciones FOB, importaciones CIF frontera (FOB si no hay CIF) y saldo
     * del INE por gestión.
     */
    public function totalesIne(): array
    {
        try {
            $filas = DB::select(<<<'SQL'
                SELECT gestion,
                       COALESCE(sum(total_fob_usd) FILTER (WHERE tipo_operacion = 'Exportación'), 0) AS exportaciones,
                       COALESCE(sum(COALESCE(total_cif_usd, total_fob_usd)) FILTER (WHERE tipo_operacion = 'Importación'), 0) AS importaciones,
                       COALESCE(sum(total_operaciones), 0) AS operaciones
                FROM mv_resumen_anual_ine
                GROUP BY gestion
                ORDER BY gestion DESC
            SQL);
        } catch (\Throwable $e) {
            report($e);

            return [];
        }

        return array_map(fn ($r) => [
            'gestion'       => (int) $r->gestion,
            'exportaciones' => (float) $r->exportaciones,
            'importaciones' => (float) $r->importaciones,
            'saldo'         => (float) $r->exportaciones - (float) $r->importaciones,
            'operaciones'   => (int) $r->operaciones,
        ], $filas);
    }
}
