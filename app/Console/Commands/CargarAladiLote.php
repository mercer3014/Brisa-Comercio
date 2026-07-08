<?php

namespace App\Console\Commands;

use App\Models\CargaArchivo;
use App\Servicios\CargadorAladi;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Carga en lote los Excel de ALADI desde una carpeta base, leyendo
 * directamente del disco (sin copiar al storage) y refrescando las vistas
 * materializadas UNA sola vez al final.
 *
 * Estructura esperada de la carpeta base (rankings top-50 por país miembro):
 *   <base>/<PAIS>/EXPORTACIONES/<gestión>.xlsx
 *   <base>/<PAIS>/IMPORTACIONES/<gestión>.xlsx
 *
 * Uso:
 *   php artisan ovxel:cargar-aladi "C:/Users/.../Desktop/ALADI" --fresh
 */
class CargarAladiLote extends Command
{
    protected $signature = 'ovxel:cargar-aladi
        {base : Carpeta base con una subcarpeta por pais miembro}
        {--fresh : Borrar los datos previos de ALADI (org 2) antes de cargar}
        {--pais= : Cargar solo un pais (nombre de la carpeta, ej. ARGENTINA)}
        {--no-refresh : No refrescar las vistas materializadas al terminar}';

    protected $description = 'Carga masiva de los rankings Excel de ALADI (pais x flujo x gestion) hacia ranking_comercio.';

    /**
     * Las carpetas de flujo no vienen con nombre uniforme en el dataset
     * (ej. Paraguay usa "EXPORTACION" en singular): se clasifican por prefijo.
     */
    private function flujoDeCarpeta(string $nombre): ?string
    {
        $n = strtoupper($nombre);

        return match (true) {
            str_starts_with($n, 'EXPORT') => 'EXPORTACION',
            str_starts_with($n, 'IMPORT') => 'IMPORTACION',
            default                       => null,
        };
    }

    public function handle(CargadorAladi $cargador): int
    {
        $base = rtrim($this->argument('base'), '/\\');

        if (! is_dir($base)) {
            $this->error("Carpeta no encontrada: {$base}");
            return self::FAILURE;
        }

        if ($this->option('fresh')) {
            $this->warn('Borrando datos previos de ALADI (org 2) en ranking_comercio...');
            DB::table('ranking_comercio')->where('organizacion_id', 2)->delete();
        }

        $soloPais = strtoupper((string) $this->option('pais'));
        $paises = array_values(array_filter(glob("{$base}/*", GLOB_ONLYDIR) ?: [], function ($dir) use ($soloPais) {
            return $soloPais === '' || strtoupper(basename($dir)) === $soloPais;
        }));
        sort($paises);

        if (empty($paises)) {
            $this->error('No se encontraron carpetas de paises en la carpeta base.');
            return self::FAILURE;
        }

        $totalFilas    = 0;
        $totalArchivos = 0;
        $fallidos      = [];

        foreach ($paises as $dirPais) {
            $pais = basename($dirPais);
            $this->info($pais);

            foreach (glob("{$dirPais}/*", GLOB_ONLYDIR) ?: [] as $dirFlujo) {
                $carpeta = basename($dirFlujo);
                $tipoFlujo = $this->flujoDeCarpeta($carpeta);
                if ($tipoFlujo === null) {
                    $this->warn("  (carpeta de flujo no reconocida: {$carpeta})");
                    continue;
                }

                $archivos = glob("{$dirFlujo}/*.xlsx") ?: [];
                $archivos = array_values(array_filter($archivos, fn ($f) => ! str_starts_with(basename($f), '~$')));
                sort($archivos);

                foreach ($archivos as $ruta) {
                    $gestion = (int) pathinfo($ruta, PATHINFO_FILENAME);
                    if ($gestion < 1990 || $gestion > 2100) {
                        $this->warn("  (omitido, nombre sin gestion valida: ".basename($ruta).')');
                        continue;
                    }

                    $nombre = "{$pais}/{$carpeta}/".basename($ruta);
                    $carga = CargaArchivo::create([
                        'organizacion_id' => 2,
                        'usuario_id'      => 1,
                        'nombre_archivo'  => $nombre,
                        'tipo_flujo'      => $tipoFlujo,
                        'gestion'         => $gestion,
                        'estado'          => 'PENDIENTE',
                    ]);

                    $this->output->write("  → {$nombre} ... ");
                    $cargador->cargar($carga, $ruta, refrescarVistas: false, paisReportante: $pais);
                    $carga->refresh();

                    $ok = $carga->estado === 'COMPLETADO';
                    $this->line(($ok ? '<info>OK</info>' : '<error>FALLÓ</error>')." {$carga->total_filas_validas} filas");

                    if (! $ok) {
                        $fallidos[] = $nombre;
                    }
                    $totalFilas += (int) $carga->total_filas_validas;
                    $totalArchivos++;
                }
            }
        }

        $this->newLine();
        if (! $this->option('no-refresh')) {
            $this->line('Refrescando vistas materializadas...');
            try {
                \Illuminate\Support\Facades\Artisan::call('comexhub:refrescar-vistas');
            } catch (\Throwable $e) {
                $this->warn('  No se pudieron refrescar las vistas: '.$e->getMessage());
            }
        }

        $this->line('Precalentando cache...');
        \Illuminate\Support\Facades\Artisan::call('ovxel:calentar-cache');

        $this->info("Listo. {$totalArchivos} archivo(s), ".number_format($totalFilas).' filas insertadas.');
        if (! empty($fallidos)) {
            $this->error(count($fallidos).' archivo(s) fallaron:');
            foreach ($fallidos as $f) {
                $this->line("  - {$f}");
            }
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
