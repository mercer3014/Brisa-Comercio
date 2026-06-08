<?php

namespace App\Jobs;

use App\Models\CargaArchivo;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Procesa un archivo cargado y puebla operacion_comercio_exterior y sus
 * dimensiones. La logica completa del ETL se implementa en la Tarea 6.
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

        // La implementacion del ETL (lectura por lotes, mapeo, find-or-create de
        // dimensiones, insercion masiva, validaciones e idempotencia) se realiza
        // en la Tarea 6 mediante el servicio ProcesadorEtl.
        app(\App\Servicios\ProcesadorEtl::class)->procesar($carga);
    }
}
