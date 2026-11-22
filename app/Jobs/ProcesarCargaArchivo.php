<?php

namespace App\Jobs;

use App\Models\CargaArchivo;
use App\Support\Organizaciones;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Procesa un archivo cargado y puebla operacion_comercio_exterior y sus
 * dimensiones. La lógica completa del ETL se implementa en la Tarea 6.
 */
class ProcesarCargaArchivo implements ShouldQueue
{
    use Queueable;

    public int $timeout = 3600;      // archivos grandes
    public int $tries = 1;           // idempotente; reintento manual

    public function __construct(public int $cargaId)
    {
    }

    public function handle(): void
    {
        $carga = CargaArchivo::find($this->cargaId);
        if (! $carga) {
            return;
        }

        // Ruteo por SIGLA de la organización (no por IDs fijos 1/2/3/4).
        $sigla = Organizaciones::sigla((int) $carga->organizacion_id);
        match (true) {
            $sigla === Organizaciones::INE      => app(\App\Servicios\CargadorIne::class)->cargar($carga),
            $sigla === Organizaciones::ALADI    => app(\App\Servicios\CargadorAladi::class)->cargar($carga, refrescarVistas: false),
            $sigla === Organizaciones::MERCOSUR && $carga->tipo_flujo === 'MERCOSUR_ITEM'
                                                => app(\App\Servicios\CargadorMercosurItem::class)->cargar($carga, refrescarVistas: false),
            $sigla === Organizaciones::MERCOSUR => app(\App\Servicios\CargadorMercosurPais::class)->cargar($carga, refrescarVistas: false),
            $sigla === Organizaciones::FAOSTAT  => app(\App\Servicios\CargadorFaostat::class)->cargar($carga, refrescarVistas: false),
            default => app(\App\Servicios\ProcesadorEtl::class)->procesar($carga),
        };

        // Si la carga terminó bien, refrescar UNA vez las vistas materializadas
        // (resumen_* del INE y mv_resumen_mercosur_zona) para que portal y
        // dashboards muestren los datos nuevos, y luego calentar el cache.
        // (ALADI y FAOSTAT no alimentan vistas materializadas; el ProcesadorEtl
        // genérico ya refresca por su cuenta.)
        $carga->refresh();
        $vistas = match ($sigla) {
            Organizaciones::INE      => 'ine',
            Organizaciones::MERCOSUR => 'mercosur',
            default                  => null,
        };
        if ($carga->estado === 'COMPLETADO' && $vistas !== null) {
            try {
                \Illuminate\Support\Facades\Artisan::call('geodata:refrescar-vistas', ['--solo' => $vistas]);
            } catch (\Throwable $e) {
                report($e);
            }
        }

        try {
            \Illuminate\Support\Facades\Artisan::call('geodata:calentar-cache');
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
