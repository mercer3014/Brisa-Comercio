<?php

namespace App\Jobs;

use App\Models\CargaArchivo;
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

        // Ruteo por organización / tipo de flujo al cargador correspondiente.
        match (true) {
            $carga->organizacion_id === 1 => app(\App\Servicios\CargadorIne::class)->cargar($carga),
            $carga->organizacion_id === 2 => app(\App\Servicios\CargadorAladi::class)->cargar($carga),
            $carga->organizacion_id === 3 && $carga->tipo_flujo === 'MERCOSUR_ITEM' => app(\App\Servicios\CargadorMercosurItem::class)->cargar($carga),
            $carga->organizacion_id === 3 => app(\App\Servicios\CargadorMercosurPais::class)->cargar($carga),
            $carga->organizacion_id === 4 => app(\App\Servicios\CargadorFaostat::class)->cargar($carga),
            default => app(\App\Servicios\ProcesadorEtl::class)->procesar($carga),
        };

        // Tras cada carga cambia la version de los datos (carga_id): dejar el
        // cache caliente para que el explorador y los dashboards respondan al
        // instante con la nueva version.
        try {
            \Illuminate\Support\Facades\Artisan::call('ovxel:calentar-cache');
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
