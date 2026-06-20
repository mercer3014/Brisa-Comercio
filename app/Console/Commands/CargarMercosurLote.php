<?php

namespace App\Console\Commands;

use App\Models\CargaArchivo;
use App\Servicios\CargadorMercosurItem;
use App\Servicios\CargadorMercosurPais;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Carga en lote los Excel de MERCOSUR desde una carpeta base, leyendo
 * directamente del disco (sin copiar al storage) y refrescando las vistas
 * materializadas UNA sola vez al final.
 *
 * Estructura esperada de la carpeta base:
 *   <base>/MERCOSUR <version>/Por Paises/*.xlsx  -> serie_comercio_zona
 *   <base>/MERCOSUR <version>/Por Items/*.xlsx   -> serie_comercio_producto_zona
 *
 * Uso:
 *   php artisan ovxel:cargar-mercosur "D:/MERCOSUR" --version=5 --fresh
 */
class CargarMercosurLote extends Command
{
    protected $signature = 'ovxel:cargar-mercosur
        {base=D:/MERCOSUR : Carpeta base que contiene las carpetas "MERCOSUR N"}
        {--bloque=5 : Versión del bloque a cargar (5 = 5 miembros)}
        {--fresh : Borrar los datos previos de MERCOSUR (org 3) antes de cargar}
        {--solo= : Cargar solo "paises" o "items" (por defecto ambos)}
        {--no-refresh : No refrescar las vistas materializadas al terminar}';

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
            $this->warn('Borrando datos previos de MERCOSUR (org 3)...');
            DB::table('serie_comercio_producto_zona')->where('organizacion_id', 3)->delete();
            DB::table('serie_comercio_zona')->where('organizacion_id', 3)->delete();
        }

        $totalPais = 0;
        $totalItem = 0;

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

        // ---- Refrescar vistas materializadas una sola vez ------------------
        $this->newLine();
        if (! $this->option('no-refresh')) {
            $this->line('Refrescando vista materializada mv_resumen_mercosur_zona...');
            try {
                DB::statement('REFRESH MATERIALIZED VIEW mv_resumen_mercosur_zona');
            } catch (\Throwable $e) {
                $this->warn('  No se pudo refrescar la vista: ' . $e->getMessage());
            }
        }

        $this->info('Listo. Países: ' . number_format($totalPais) . ' filas · Items: ' . number_format($totalItem) . ' filas.');
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
            'organizacion_id' => 3,
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

        return (int) $carga->total_filas_validas;
    }
}
