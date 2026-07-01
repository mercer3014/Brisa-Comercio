<?php

namespace App\Jobs;

use App\Models\CargaArchivo;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Procesa un archivo cargado y delega al ETL correspondiente segun el flujo.
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

        if (in_array($carga->tipo_flujo, ['MERCOSUR_PAIS', 'MERCOSUR_ITEM'], true)) {
            app(\App\Servicios\ProcesadorMercosur::class)->procesar($carga);
            return;
        }

        app(\App\Servicios\ProcesadorEtl::class)->procesar($carga);
    }
}
