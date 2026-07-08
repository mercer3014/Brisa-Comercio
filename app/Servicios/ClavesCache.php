<?php

namespace App\Servicios;

use Illuminate\Support\Facades\DB;

/**
 * Claves de cache compartidas entre los controladores (explorador admin,
 * explorador público, dashboards) y el comando que las precalienta
 * (ovxel:calentar-cache). Definirlas en un solo lugar evita que el calentador
 * y los controladores se desincronicen.
 *
 * Todas incluyen la "version" de los datos (el último carga_id): cargar un
 * archivo nuevo cambia la clave y el cache viejo muere solo.
 */
class ClavesCache
{
    public const TTL = 86400;

    public static function version(): int
    {
        return (int) DB::table('carga_archivo')->max('carga_id');
    }

    /** Totales + facetas del explorador (compartida entre admin y público). */
    public static function explAgg(int $ver, int $org, array $filtros): string
    {
        return "expl.agg.{$ver}.".md5(json_encode([$org, $filtros]));
    }

    /** Gráficos del explorador público. */
    public static function explGraf(int $ver, int $org, array $filtros): string
    {
        return "expl.graf.{$ver}.".md5(json_encode([$org, $filtros]));
    }

    /** Tabla paginada del explorador. */
    public static function explTabla(int $ver, int $org, array $filtros, int $pagina, int $porPagina): string
    {
        return "expl.tabla.{$ver}.".md5(json_encode([$org, $filtros])).".{$pagina}.{$porPagina}";
    }

    /** Respuesta completa del dashboard admin. */
    public static function dashDatos(int $ver, int $org, ?int $gestion): string
    {
        return "dash.datos.{$ver}.{$org}.".($gestion ?? 'todas');
    }
}
