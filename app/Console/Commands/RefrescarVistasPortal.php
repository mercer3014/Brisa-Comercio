<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Refresca las vistas materializadas del portal.
 *
 * Desde la migración 2026_11_21_000004 hay UNA sola familia de resúmenes del INE
 * (resumen_anual_producto/pais/departamento y resumen_mensual, con una sola regla
 * de valor). mv_resumen_anual_ine y mv_resumen_mensual_ine pasaron a ser vistas
 * normales sobre resumen_mensual, así que no se refrescan. Se suma
 * mv_resumen_mercosur_zona (MERCOSUR).
 *
 * Se ejecuta manualmente (`php artisan geodata:refrescar-vistas`) y automáticamente
 * desde el job ProcesarCargaArchivo al terminar bien una carga INE o MERCOSUR.
 *
 * Intenta REFRESH ... CONCURRENTLY (no bloquea lecturas; requiere índice único);
 * si falla (p.ej. vista aún sin poblar), cae a un REFRESH normal.
 */
class RefrescarVistasPortal extends Command
{
    protected $signature = 'geodata:refrescar-vistas
        {--solo= : Refrescar solo "ine" o "mercosur" (por defecto todas)}
        {--sin-concurrencia : Forzar REFRESH normal (con bloqueo) en vez de CONCURRENTLY}';

    protected $description = 'Refresca las vistas materializadas de resumen del portal publico.';

    /** Vistas a refrescar, por grupo y en orden. */
    private array $vistas = [
        'ine' => [
            'resumen_anual_producto',
            'resumen_anual_pais',
            'resumen_anual_departamento',
            'resumen_mensual',
        ],
        'mercosur' => [
            'mv_resumen_mercosur_zona',
        ],
    ];

    public function handle(): int
    {
        $concurrente = ! $this->option('sin-concurrencia');
        $solo = strtolower((string) $this->option('solo'));

        foreach ($this->vistas as $grupo => $vistas) {
            if ($solo !== '' && $solo !== $grupo) {
                continue;
            }
            foreach ($vistas as $vista) {
                $this->refrescar($vista, $concurrente);
            }
        }

        $this->info('Vistas materializadas del portal refrescadas.');

        return self::SUCCESS;
    }

    private function refrescar(string $vista, bool $concurrente): void
    {
        try {
            if ($concurrente) {
                DB::statement("REFRESH MATERIALIZED VIEW CONCURRENTLY {$vista}");
            } else {
                DB::statement("REFRESH MATERIALIZED VIEW {$vista}");
            }
            $this->line("  ✓ {$vista}");
        } catch (Throwable $e) {
            // CONCURRENTLY exige que la vista ya tenga datos; si no, refresco normal.
            try {
                DB::statement("REFRESH MATERIALIZED VIEW {$vista}");
                $this->line("  ✓ {$vista} (refresco normal)");
            } catch (Throwable $e2) {
                $this->error("  ✗ {$vista}: {$e2->getMessage()}");
            }
        }
    }
}
