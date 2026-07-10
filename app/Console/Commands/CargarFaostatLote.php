<?php

namespace App\Console\Commands;

use App\Models\CargaArchivo;
use App\Servicios\CargadorFaostat;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Carga en lote los .xls de FAOSTAT (uno por país, dominio "Índices
 * comerciales") desde una carpeta base, leyendo directamente del disco.
 *
 * Uso:
 *   php artisan geodata:cargar-faostat "C:/Users/.../Desktop/FAOSTAT" --fresh
 */
class CargarFaostatLote extends Command
{
    protected $signature = 'geodata:cargar-faostat
        {base : Carpeta con los .xls de FAOSTAT (uno por pais)}
        {--fresh : Borrar los datos previos de FAOSTAT (org 4) antes de cargar}
        {--pais= : Cargar solo el archivo cuyo nombre empiece asi (ej. Bolivia)}
        {--desde= : Reanudar desde el archivo cuyo nombre empiece asi (orden alfabetico)}';

    protected $description = 'Carga masiva de los .xls de FAOSTAT (indices comerciales por pais) hacia serie_indicador_agricola.';

    public function handle(CargadorFaostat $cargador): int
    {
        $base = rtrim($this->argument('base'), '/\\');

        if (! is_dir($base)) {
            $this->error("Carpeta no encontrada: {$base}");
            return self::FAILURE;
        }

        if ($this->option('fresh')) {
            $this->warn('Borrando datos previos de FAOSTAT (org 4) en serie_indicador_agricola...');
            DB::table('serie_indicador_agricola')->where('organizacion_id', 4)->delete();
        }

        $soloPais = mb_strtolower((string) $this->option('pais'));
        $archivos = array_merge(glob("{$base}/*.xls") ?: [], glob("{$base}/*.xlsx") ?: []);
        $archivos = array_values(array_filter($archivos, function ($f) use ($soloPais) {
            $nombre = basename($f);
            if (str_starts_with($nombre, '~$') || stripos($nombre, 'Modelo Madre') !== false) {
                return false; // plantilla, no datos
            }

            return $soloPais === '' || str_starts_with(mb_strtolower($nombre), $soloPais);
        }));
        sort($archivos);

        $desde = mb_strtolower((string) $this->option('desde'));
        if ($desde !== '') {
            $archivos = array_values(array_filter($archivos,
                fn ($f) => strcmp(mb_strtolower(basename($f)), $desde) >= 0));
        }

        if (empty($archivos)) {
            $this->error('No se encontraron archivos .xls en la carpeta base.');
            return self::FAILURE;
        }

        $this->info(count($archivos).' archivo(s) FAOSTAT por cargar.');

        $totalFilas = 0;
        $fallidos = [];
        $n = 0;

        foreach ($archivos as $ruta) {
            $nombre = basename($ruta);
            $n++;

            $carga = CargaArchivo::create([
                'organizacion_id' => 4,
                'usuario_id'      => 1,
                'nombre_archivo'  => $nombre,
                'tipo_flujo'      => 'FAOSTAT',
                'estado'          => 'PENDIENTE',
            ]);

            $t0 = microtime(true);
            $this->output->write("  [{$n}/".count($archivos)."] {$nombre} ... ");
            $cargador->cargar($carga, $ruta, refrescarVistas: false);
            $carga->refresh();
            $seg = round(microtime(true) - $t0, 1);

            $ok = $carga->estado === 'COMPLETADO';
            $this->line(($ok ? '<info>OK</info>' : '<error>FALLÓ</error>')." {$carga->total_filas_validas} filas ({$seg}s)");

            if (! $ok) {
                $fallidos[] = $nombre;
            }
            $totalFilas += (int) $carga->total_filas_validas;
        }

        $this->newLine();
        $this->line('Precalentando cache...');
        \Illuminate\Support\Facades\Artisan::call('geodata:calentar-cache');

        $this->info('Listo. '.count($archivos).' archivo(s), '.number_format($totalFilas).' series insertadas.');
        if (! empty($fallidos)) {
            $this->error(count($fallidos).' archivo(s) fallaron: '.implode(', ', $fallidos));
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
