<?php

namespace App\Console\Commands;

use App\Servicios\ArmadorDashboard;
use App\Servicios\ClavesCache;
use App\Servicios\ConsultaExplorador;
use App\Servicios\PortalApi;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

/**
 * Precalienta el cache del explorador (sin filtros) y de los dashboards
 * (gestión más reciente + "todas") para las 4 organizaciones, de modo que
 * cambiar de organización en la interfaz responda al instante.
 *
 * Se ejecuta automáticamente al final de cada carga (comandos de lote y job
 * de ETL). Las claves incluyen la version de los datos (último carga_id), así
 * que calentar nunca pisa datos frescos con viejos.
 */
class CalentarCache extends Command
{
    protected $signature = 'ovxel:calentar-cache {--org=* : Solo estas organizaciones (por defecto las 4)}';

    protected $description = 'Precalienta el cache de explorador y dashboards para todas las organizaciones.';

    public function handle(ConsultaExplorador $ce, ArmadorDashboard $armador, PortalApi $api): int
    {
        $orgs = array_map('intval', (array) $this->option('org')) ?: [1, 2, 3, 4];
        $ver = ClavesCache::version();

        foreach ($orgs as $org) {
            $t0 = microtime(true);

            // Explorador sin filtros (la vista inicial al cambiar de organización).
            Cache::remember(ClavesCache::explAgg($ver, $org, []), ClavesCache::TTL, fn () => [
                'totales' => $ce->totales($org, []),
                'facetas' => $ce->facetas($org, []),
            ]);
            Cache::remember(ClavesCache::explGraf($ver, $org, []), ClavesCache::TTL,
                fn () => $ce->graficos($org, [], 10));
            Cache::remember(ClavesCache::explTabla($ver, $org, [], 1, 25), ClavesCache::TTL,
                fn () => $ce->tabla($org, [], 25, 1));

            // Dashboard: "todas las gestiones" y cada año con datos, para que
            // cualquier cambio de año responda del cache. Las claves ya
            // calientes se saltan al instante.
            Cache::remember(ClavesCache::dashDatos($ver, $org, null), ClavesCache::TTL,
                fn () => $armador->armar($org, null));

            foreach ($api->filtroGestiones($org) as $g) {
                Cache::remember(ClavesCache::dashDatos($ver, $org, (int) $g), ClavesCache::TTL,
                    fn () => $armador->armar($org, (int) $g));
            }

            $this->line("  org {$org}: cache caliente (".round(microtime(true) - $t0, 1).'s)');
        }

        $this->info('Cache precalentado (version '.$ver.').');

        return self::SUCCESS;
    }
}
