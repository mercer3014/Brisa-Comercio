<?php

namespace App\Console\Commands;

use App\Models\CargaArchivo;
use App\Servicios\CargadorMercosurItem;
use App\Servicios\CargadorMercosurPais;
use Illuminate\Console\Command;
use App\Support\Organizaciones;
use Illuminate\Support\Facades\DB;

/**
 * Carga en lote los Excel de MERCOSUR desde una carpeta base, leyendo
 * directamente del disco (sin copiar al storage) y refrescando las vistas
 * materializadas UNA sola vez al final.
 *
 * Estructura esperada de la carpeta base:
 *   <base>/MERCOSUR <version>/Por Países/*.xlsx  -> serie_comercio_zona
 *   <base>/MERCOSUR <version>/Por Items/*.xlsx   -> serie_comercio_producto_zona
 *
 * Uso:
 *   php artisan geodata:cargar-mercosur "D:/MERCOSUR" --version=5 --fresh
 */
class CargarMercosurLote extends Command
{
    protected $signature = 'geodata:cargar-mercosur
        {base=D:/MERCOSUR : Carpeta base que contiene las carpetas "MERCOSUR N"}
        {--bloque=5 : Versión del bloque a cargar (5 = 5 miembros)}
        {--permitir-fallos : Con --fresh, confirmar igual aunque algun archivo falle}
        {--fresh : Borrar los datos previos de MERCOSUR (org 3) antes de cargar}
        {--solo= : Cargar solo "paises" o "items" (por defecto ambos)}
        {--no-refresh : No refrescar las vistas materializadas al terminar}';

    /** @var string[] archivos que fallaron en esta corrida */
    private array $fallidos = [];

    protected $description = 'Carga masiva de los Excel de MERCOSUR (por país y por item) hacia las series de comercio.';

    public function handle(CargadorMercosurPais $cargPais, CargadorMercosurItem $cargItem): int
    {
        $base    = rtrim($this->argument('base'), '/\\');
        $version = (int) $this->option('bloque');
        $dir     = "{$base}/MERCOSUR {$version}";
        $solo    = strtolower((string) $this->option('solo'));

        if (! is_dir($dir)) {
            $this->error("Carpeta no encontrada: {$dir}");
            return self::FAILURE;
        }

        if ($this->option('fresh')) {
            // Todo el lote va en una transacción: el borrado solo se confirma si la recarga sale bien.
            DB::beginTransaction();
            $org = Organizaciones::id(Organizaciones::MERCOSUR);
            $this->warn('Borrando datos previos de MERCOSUR (se confirma al final)...');
            DB::table('serie_comercio_producto_zona')->where('organizacion_id', $org)->delete();
            DB::table('serie_comercio_zona')->where('organizacion_id', $org)->delete();
        }

        $totalPais = 0;
        $totalItem = 0;
        $this->fallidos = [];

        // ---- Por Países -> serie_comercio_zona -----------------------------
        if ($solo === '' || $solo === 'paises') {
            $archivos = $this->archivosDe("{$dir}/Por Paises");
            $this->info(count($archivos) . ' archivo(s) "Por Países" en MERCOSUR ' . $version);
            foreach ($archivos as $ruta) {
                $totalPais += $this->procesar($cargPais, $ruta, 'MERCOSUR_PAIS');
            }
        }

        // ---- Por Items -> serie_comercio_producto_zona ---------------------
        if ($solo === '' || $solo === 'items') {
            $archivos = $this->archivosDe("{$dir}/Por Items");
            $this->info(count($archivos) . ' archivo(s) "Por Items" en MERCOSUR ' . $version);
            foreach ($archivos as $ruta) {
                $totalItem += $this->procesar($cargItem, $ruta, 'MERCOSUR_ITEM');
            }
        }

        if (! $this->cerrarTransaccionFresh($this->fallidos)) {
            return self::FAILURE;
        }

        // ---- Refrescar vistas materializadas una sola vez ------------------
        $this->newLine();
        if (! $this->option('no-refresh')) {
            $this->line('Refrescando vista materializada mv_resumen_mercosur_zona...');
            \Illuminate\Support\Facades\Artisan::call('geodata:refrescar-vistas', ['--solo' => 'mercosur']);
        }

        $this->line('Precalentando cache...');
        \Illuminate\Support\Facades\Artisan::call('geodata:calentar-cache');

        $this->info('Listo. Países: ' . number_format($totalPais) . ' filas · Items: ' . number_format($totalItem) . ' filas.');
        if (! empty($this->fallidos)) {
            $this->error(count($this->fallidos) . ' archivo(s) fallaron: ' . implode(', ', $this->fallidos));
            return self::FAILURE;
        }
        return self::SUCCESS;
    }

    /** @return string[] */
    private function archivosDe(string $carpeta): array
    {
        if (! is_dir($carpeta)) {
            $this->warn("  (carpeta inexistente: {$carpeta})");
            return [];
        }
        $archivos = glob($carpeta . '/*.xlsx') ?: [];
        $archivos = array_values(array_filter($archivos, fn ($f) => ! str_starts_with(basename($f), '~$')));
        sort($archivos);
        return $archivos;
    }

    /** Crea la carga, invoca el cargador con ruta directa, devuelve filas válidas. */
    private function procesar(CargadorMercosurPais|CargadorMercosurItem $cargador, string $ruta, string $flujo): int
    {
        $nombre = basename($ruta);
        $carga = CargaArchivo::create([
            'organizacion_id' => Organizaciones::id(Organizaciones::MERCOSUR),
            'usuario_id'      => 1,
            'nombre_archivo'  => $nombre,
            'tipo_flujo'      => $flujo,
            'estado'          => 'PENDIENTE',
        ]);

        $t0 = microtime(true);
        $this->output->write("  → {$nombre} ... ");
        $cargador->cargar($carga, $ruta, refrescarVistas: false);
        $carga->refresh();
        $seg = round(microtime(true) - $t0, 1);

        $ok = $carga->estado === 'COMPLETADO';
        $this->line(($ok ? '<info>OK</info>' : '<error>FALLÓ</error>') . " {$carga->total_filas_validas} filas ({$seg}s)");
        if (! $ok) {
            $this->fallidos[] = $nombre;
        }

        return (int) $carga->total_filas_validas;
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
