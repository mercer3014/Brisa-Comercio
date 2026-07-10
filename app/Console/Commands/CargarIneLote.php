<?php

namespace App\Console\Commands;

use App\Models\CargaArchivo;
use App\Servicios\CargadorIne;
use Illuminate\Console\Command;

/**
 * Carga en lote TODOS los Excel del INE de una carpeta (lee directo, sin copiar
 * al storage). Detecta la gestión por el nombre del archivo o por el contenido.
 *
 * Uso:
 *   php artisan geodata:cargar-ine "<carpeta>" --flujo=EXPORTACION
 *   php artisan geodata:cargar-ine "<carpeta>" --flujo=IMPORTACION
 */
class CargarIneLote extends Command
{
    protected $signature = 'geodata:cargar-ine
        {carpeta : Carpeta con los .xlsx del INE}
        {--flujo=EXPORTACION : EXPORTACION o IMPORTACION}
        {--desde=1990 : Año mínimo a cargar}
        {--hasta=2100 : Año máximo a cargar}
        {--mod=1 : Número de particiones (para correr en paralelo)}
        {--resto=0 : Índice de esta partición (0..mod-1)}
        {--no-refresh : No refrescar las vistas materializadas al terminar}';

    protected $description = 'Carga masiva de archivos INE (exportaciones/importaciones) desde una carpeta.';

    public function handle(CargadorIne $cargador): int
    {
        $carpeta = rtrim($this->argument('carpeta'), '/\\');
        $flujo   = strtoupper((string) $this->option('flujo'));
        $desde   = (int) $this->option('desde');
        $hasta   = (int) $this->option('hasta');

        if (! is_dir($carpeta)) {
            $this->error("Carpeta no encontrada: {$carpeta}");
            return self::FAILURE;
        }

        $archivos = glob($carpeta.'/*.xlsx') ?: [];
        // Filtrar diccionarios y temporales de Excel.
        $archivos = array_values(array_filter($archivos, function ($f) {
            $n = strtoupper(basename($f));
            return ! str_contains($n, 'DICCIONARIO') && ! str_starts_with(basename($f), '~$');
        }));
        sort($archivos);

        if (empty($archivos)) {
            $this->error('No se encontraron archivos .xlsx en la carpeta.');
            return self::FAILURE;
        }

        // Partición para ejecución en paralelo (cada worker toma 1 de cada $mod).
        $mod   = max(1, (int) $this->option('mod'));
        $resto = (int) $this->option('resto');
        if ($mod > 1) {
            $archivos = array_values(array_filter($archivos, fn ($f, $i) => $i % $mod === $resto, ARRAY_FILTER_USE_BOTH));
        }

        $this->info(count($archivos)." archivo(s) en esta partición ({$resto}/{$mod}). Flujo: {$flujo}");
        $totalFilas = 0;

        foreach ($archivos as $ruta) {
            $nombre = basename($ruta);
            // Extraer año del nombre (primer número de 4 dígitos 19xx/20xx).
            $gestion = null;
            if (preg_match('/(19|20)\d{2}/', $nombre, $m)) {
                $gestion = (int) $m[0];
            }
            if ($gestion !== null && ($gestion < $desde || $gestion > $hasta)) {
                $this->line("  — Omitido (fuera de rango): {$nombre}");
                continue;
            }

            $carga = CargaArchivo::create([
                'organizacion_id' => 1,
                'usuario_id'      => 1,
                'nombre_archivo'  => $nombre,
                'tipo_flujo'      => $flujo,
                'gestion'         => $gestion,
                'estado'          => 'PENDIENTE',
            ]);

            $t0 = microtime(true);
            $this->output->write("  → [{$gestion}] {$nombre} ... ");
            $cargador->cargar($carga, $ruta);
            $carga->refresh();
            $seg = round(microtime(true) - $t0, 1);
            $totalFilas += (int) $carga->total_filas_validas;

            $estado = $carga->estado === 'COMPLETADO' ? '<info>OK</info>' : '<error>FALLÓ</error>';
            $this->line("{$estado} {$carga->total_filas_validas} filas ({$seg}s)");
        }

        // Refrescar vistas materializadas del portal (omitible en corridas paralelas).
        $this->newLine();
        if (! $this->option('no-refresh')) {
            $this->line('Refrescando vistas materializadas...');
            foreach (['mv_resumen_anual_ine', 'mv_resumen_mensual_ine'] as $mv) {
                try {
                    \Illuminate\Support\Facades\DB::statement("REFRESH MATERIALIZED VIEW {$mv}");
                } catch (\Throwable $e) {
                    $this->warn("  No se pudo refrescar {$mv}: {$e->getMessage()}");
                }
            }
        }

        $this->info("Listo. Total filas insertadas en esta corrida: ".number_format($totalFilas));
        return self::SUCCESS;
    }
}
