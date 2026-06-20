<?php

namespace App\Console\Commands;

use App\Jobs\ProcesarCargaArchivo;
use App\Models\CargaArchivo;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Comando de prueba del ETL: carga un archivo Excel directamente
 * desde el sistema de archivos sin pasar por el flujo web.
 *
 * Uso:
 *   php artisan ovxel:probar-etl <ruta_archivo> --org=1 --flujo=EXPORTACION [--gestion=2023] [--sync]
 *
 * Opciones:
 *   --org    organizacion_id (1=INE, 2=ALADI, 3=MERCOSUR, 4=FAOSTAT)
 *   --flujo  tipo_flujo: EXPORTACION, IMPORTACION, MERCOSUR_PAIS, MERCOSUR_ITEM, ALADI_RANKING, FAOSTAT
 *   --gestion año de la gestión (opcional, para INE/ALADI)
 *   --sync   ejecutar de forma síncrona (sin cola); por defecto sin cola para facilitar pruebas
 */
class ProbarEtl extends Command
{
    protected $signature = 'ovxel:probar-etl
        {archivo : Ruta absoluta o relativa (desde storage/app) del archivo Excel}
        {--org=1 : organizacion_id (1=INE, 2=ALADI, 3=MERCOSUR, 4=FAOSTAT)}
        {--flujo=EXPORTACION : tipo_flujo}
        {--gestion= : Gestión (año) del archivo}
        {--sync : Ejecutar sincrónicamente (sin despachar a la cola)}';

    protected $description = 'Carga un archivo Excel directamente al ETL para pruebas (sin interfaz web).';

    public function handle(): int
    {
        $rutaInput   = $this->argument('archivo');
        $orgId       = (int) $this->option('org');
        $tipoFlujo   = strtoupper((string) $this->option('flujo'));
        $gestion     = $this->option('gestion') ? (int) $this->option('gestion') : null;
        $sincrono    = (bool) $this->option('sync');

        // Resolver ruta absoluta
        $rutaAbs = file_exists($rutaInput)
            ? $rutaInput
            : Storage::disk('local')->path($rutaInput);

        if (! file_exists($rutaAbs)) {
            $this->error("Archivo no encontrado: {$rutaAbs}");
            return self::FAILURE;
        }

        $ext         = strtolower(pathinfo($rutaAbs, PATHINFO_EXTENSION));
        $nombreArch  = basename($rutaAbs);

        // 1) Crear registro de carga
        $carga = CargaArchivo::create([
            'organizacion_id' => $orgId,
            'usuario_id'      => 1, // admin
            'nombre_archivo'  => $nombreArch,
            'tipo_flujo'      => $tipoFlujo,
            'gestion'         => $gestion,
            'estado'          => 'PENDIENTE',
        ]);

        $this->info("Carga #{$carga->carga_id} creada para: {$nombreArch}");
        $this->line("  Organización: {$orgId} | Flujo: {$tipoFlujo}" . ($gestion ? " | Gestión: {$gestion}" : ''));

        // 2) Copiar el archivo al storage definitivo
        $dirDestino  = "cargas/{$carga->carga_id}";
        $rutaDest    = "{$dirDestino}/datos.{$ext}";
        Storage::disk('local')->makeDirectory($dirDestino);
        Storage::disk('local')->put($rutaDest, file_get_contents($rutaAbs));

        $this->line("  Archivo copiado a: storage/app/{$rutaDest}");

        // 3) Para INE, crear un mapeo.json mínimo (ProcesadorEtl lo necesita)
        if (in_array($tipoFlujo, ['EXPORTACION', 'IMPORTACION'])) {
            Storage::disk('local')->put(
                "{$dirDestino}/mapeo.json",
                json_encode([
                    'extension' => $ext,
                    'columnas'  => [], // vacío: ProcesadorEtl usará alias_columnas como fallback
                ], JSON_PRETTY_PRINT)
            );
            $this->line('  mapeo.json creado (vacío — se usarán alias_columnas del config).');
        }

        // 4) Ejecutar el ETL
        if ($sincrono) {
            $this->line('Ejecutando ETL de forma síncrona...');
            (new ProcesarCargaArchivo($carga->carga_id))->handle();
        } else {
            ProcesarCargaArchivo::dispatchSync($carga->carga_id);
        }

        // 5) Reporte de resultado
        $carga->refresh();
        $this->newLine();
        $estado = $carga->estado;
        $color  = $estado === 'COMPLETADO' ? 'info' : 'error';

        $this->{$color}("Estado final: {$estado}");
        $this->table(
            ['Leídas', 'Válidas', 'Con error'],
            [[
                $carga->total_filas_leidas  ?? 0,
                $carga->total_filas_validas ?? 0,
                $carga->total_filas_error   ?? 0,
            ]]
        );

        $proceso = $carga->procesos()->latest('proceso_id')->first();
        if ($proceso?->mensaje_log) {
            $this->line('Log: '.$proceso->mensaje_log);
        }

        return $estado === 'COMPLETADO' ? self::SUCCESS : self::FAILURE;
    }
}
