<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Refresca las vistas materializadas del portal (Tarea 14).
 *
 * Se ejecuta manualmente (`php artisan geodata:refrescar-vistas`) y automáticamente
 * al final de cada ETL exitoso, para que los resumenes queden al día tras cada carga.
 *
 * Intenta REFRESH ... CONCURRENTLY (no bloquea lecturas; requiere índice único, que
 * la migracion crea); si falla (p.ej. vista aún sin poblar), cae a un REFRESH normal.
 */
class RefrescarVistasPortal extends Command
{
    protected $signature = 'geodata:refrescar-vistas {--sin-concurrencia : Forzar REFRESH normal (con bloqueo) en vez de CONCURRENTLY}';

    protected $description = 'Refresca las vistas materializadas de resumen del portal publico.';

    /** Vistas a refrescar, en orden. */
    private array $vistas = [
        'resumen_anual_producto',
        'resumen_anual_pais',
        'resumen_anual_departamento',
        'resumen_mensual',
    ];

    public function handle(): int
    {
        $concurrente = ! $this->option('sin-concurrencia');

        foreach ($this->vistas as $vista) {
            $this->refrescar($vista, $concurrente);
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
