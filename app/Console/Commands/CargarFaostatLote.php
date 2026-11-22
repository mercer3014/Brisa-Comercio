<?php

namespace App\Console\Commands;

use App\Models\CargaArchivo;
use App\Servicios\CargadorFaostat;
use Illuminate\Console\Command;
use App\Support\Organizaciones;
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
        {--permitir-fallos : Con --fresh, confirmar igual aunque algun archivo falle}
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
            // Todo el lote va en una transacción: el borrado solo se confirma si la recarga sale bien.
            DB::beginTransaction();
            $this->warn('Borrando datos previos de FAOSTAT en serie_indicador_agricola (se confirma al final)...');
            DB::table('serie_indicador_agricola')->where('organizacion_id', Organizaciones::id(Organizaciones::FAOSTAT))->delete();
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
                'organizacion_id' => Organizaciones::id(Organizaciones::FAOSTAT),
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

        if (! $this->cerrarTransaccionFresh($fallidos)) {
            return self::FAILURE;
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

    /**
     * Con --fresh todo el lote corre en UNA transacción: si algún archivo falla
     * se revierte el borrado previo y los datos anteriores quedan intactos
     * (salvo --permitir-fallos). Devuelve false si se revirtió.
     */
    private function cerrarTransaccionFresh(array $fallidos): bool
    {
        if (! $this->option('fresh') || DB::transactionLevel() === 0) {
            return true;
        }
        if (! empty($fallidos) && ! $this->option('permitir-fallos')) {
            DB::rollBack(0);
            $this->error(count($fallidos).' archivo(s) fallaron; se revirtió todo el lote --fresh. Los datos anteriores quedan intactos:');
            foreach ($fallidos as $f) {
                $this->line("  - {$f}");
            }
            $this->line('Use --permitir-fallos para confirmar igualmente los archivos que sí cargaron.');

            return false;
        }
        DB::commit();

        return true;
    }
}
