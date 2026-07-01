<?php

namespace App\Console\Commands;

use App\Models\CargaArchivo;
use App\Servicios\ProcesadorMercosur;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Throwable;

class ImportarMercosurMasivo extends Command
{
    protected $signature = 'mercosur:importar-todo
        {ruta : Carpeta raiz que contiene ARGENTINA, BRASIL, URUGUAY y VENEZUELA}
        {--dry-run : Solo valida y muestra que se importaria, sin tocar la base}
        {--force : Reprocesa aunque el archivo ya exista en archivo_fuente}
        {--limit= : Limita la cantidad de archivos a procesar para pruebas}';

    protected $description = 'Importa masivamente archivos Excel MERCOSUR Por Paises e Items NCM.';

    private const PAIS_REPORTANTE = [
        'ARGENTINA' => 32,
        'BRASIL' => 76,
        'URUGUAY' => 858,
        'VENEZUELA' => 862,
    ];

    public function handle(ProcesadorMercosur $procesador): int
    {
        $raiz = realpath((string) $this->argument('ruta'));
        if ($raiz === false || ! is_dir($raiz)) {
            $this->error('La carpeta indicada no existe.');
            return self::FAILURE;
        }

        $mercosurId = (int) DB::table('organizacion')->where('sigla', 'MERCOSUR')->value('organizacion_id');
        if (! $mercosurId) {
            $this->error('No existe la organizacion MERCOSUR en la base de datos.');
            return self::FAILURE;
        }

        $usuarioId = (int) (DB::table('usuario')->orderBy('usuario_id')->value('usuario_id') ?? 0) ?: null;
        $archivos = $this->listarArchivos($raiz);
        $limit = $this->option('limit') !== null ? max(0, (int) $this->option('limit')) : null;
        if ($limit !== null && $limit > 0) {
            $archivos = array_slice($archivos, 0, $limit);
        }

        $this->info('Archivos encontrados: '.count($archivos));

        $procesados = 0;
        $omitidos = 0;
        $fallidos = 0;

        foreach ($archivos as $archivo) {
            $info = $this->clasificarArchivo($raiz, $archivo);
            if ($info === null) {
                $omitidos++;
                $this->warn('Omitido: '.$this->relativo($raiz, $archivo->getPathname()));
                continue;
            }

            $rutaOriginal = $archivo->getPathname();
            $paisReportanteId = $this->resolverPaisReportante($mercosurId, $info['codigo_pais']);
            if (! $paisReportanteId) {
                $fallidos++;
                $this->error("No se encontro pais reportante {$info['pais']} ({$info['codigo_pais']}).");
                continue;
            }

            $yaExiste = DB::table('archivo_fuente')->where('ruta_archivo', $rutaOriginal)->exists();
            if ($yaExiste && ! $this->option('force')) {
                $omitidos++;
                $this->line('Ya importado, omitido: '.$this->relativo($raiz, $rutaOriginal));
                continue;
            }

            if ($this->option('dry-run')) {
                $procesados++;
                $this->line("[dry-run] {$info['tipo_flujo']} {$info['pais']} -> ".$this->relativo($raiz, $rutaOriginal));
                continue;
            }

            try {
                $carga = $this->crearCarga($mercosurId, $usuarioId, $archivo, $info, $paisReportanteId, $rutaOriginal);
                $procesador->procesar($carga);
                $carga->refresh();

                if ($carga->estado === 'COMPLETADO') {
                    $procesados++;
                    $this->info("OK #{$carga->carga_id}: {$info['tipo_flujo']} {$info['pais']} - leidas {$carga->total_filas_leidas}, validas {$carga->total_filas_validas}, errores {$carga->total_filas_error}");
                } else {
                    $fallidos++;
                    $this->error("FALLO #{$carga->carga_id}: ".$this->relativo($raiz, $rutaOriginal));
                }
            } catch (Throwable $e) {
                $fallidos++;
                $this->error('Error '.$this->relativo($raiz, $rutaOriginal).': '.$e->getMessage());
            }
        }

        $this->newLine();
        $this->info("Resumen: procesados=$procesados, omitidos=$omitidos, fallidos=$fallidos");

        return $fallidos > 0 ? self::FAILURE : self::SUCCESS;
    }

    /** @return array<int, SplFileInfo> */
    private function listarArchivos(string $raiz): array
    {
        $iterador = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($raiz));
        $archivos = [];

        foreach ($iterador as $archivo) {
            if (! $archivo instanceof SplFileInfo || ! $archivo->isFile()) {
                continue;
            }
            if (strtolower($archivo->getExtension()) !== 'xlsx') {
                continue;
            }
            if (str_starts_with($archivo->getFilename(), '~$')) {
                continue;
            }
            $archivos[] = $archivo;
        }

        usort($archivos, fn (SplFileInfo $a, SplFileInfo $b) => strcmp($a->getPathname(), $b->getPathname()));

        return $archivos;
    }

    private function clasificarArchivo(string $raiz, SplFileInfo $archivo): ?array
    {
        $relativo = $this->relativo($raiz, $archivo->getPathname());
        $partes = preg_split('/[\\\\\/]+/', $relativo) ?: [];
        if (count($partes) < 3) {
            return null;
        }

        $pais = strtoupper($partes[0]);
        $carpetaTipo = strtoupper($partes[1]);
        $codigoPais = self::PAIS_REPORTANTE[$pais] ?? null;
        if ($codigoPais === null) {
            return null;
        }

        $tipoFlujo = match ($carpetaTipo) {
            'POR PAISES' => 'MERCOSUR_PAIS',
            'ITEMS NCM' => 'MERCOSUR_ITEM',
            default => null,
        };

        if ($tipoFlujo === null) {
            return null;
        }

        return [
            'pais' => $pais,
            'codigo_pais' => $codigoPais,
            'tipo_flujo' => $tipoFlujo,
        ];
    }

    private function resolverPaisReportante(int $mercosurId, int $codigoPais): ?int
    {
        $id = DB::table('pais as p')
            ->join('fuente_datos as f', 'f.fuente_id', '=', 'p.fuente_id')
            ->where('f.organizacion_id', $mercosurId)
            ->where('p.codigo_pais', $codigoPais)
            ->orderByRaw("CASE WHEN f.version_nomenclatura = 'MERCOSUR-base' THEN 0 ELSE 1 END")
            ->value('p.pais_id');

        return $id ? (int) $id : null;
    }

    private function crearCarga(int $mercosurId, ?int $usuarioId, SplFileInfo $archivo, array $info, int $paisReportanteId, string $rutaOriginal): CargaArchivo
    {
        $carga = CargaArchivo::create([
            'organizacion_id' => $mercosurId,
            'usuario_id'      => $usuarioId,
            'nombre_archivo'  => $archivo->getFilename(),
            'tipo_flujo'      => $info['tipo_flujo'],
            'estado'          => 'PENDIENTE',
        ]);

        $extension = strtolower($archivo->getExtension());
        $dir = "cargas/{$carga->carga_id}";
        $destino = "$dir/datos.$extension";
        Storage::disk('local')->makeDirectory($dir);
        $destinoAbsoluto = Storage::disk('local')->path($destino);
        File::ensureDirectoryExists(dirname($destinoAbsoluto));

        if (! copy($rutaOriginal, $destinoAbsoluto)) {
            $carga->delete();
            throw new \RuntimeException('No se pudo copiar el archivo al almacenamiento local.');
        }

        Storage::disk('local')->put("$dir/mercosur.json", json_encode([
            'extension'          => $extension,
            'tipo_archivo'       => $info['tipo_flujo'] === 'MERCOSUR_PAIS' ? 'POR_PAISES' : 'ITEMS_NCM',
            'pais_reportante_id' => $paisReportanteId,
            'zona_id'            => null,
            'ruta_origen'        => $rutaOriginal,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        return $carga;
    }

    private function relativo(string $raiz, string $ruta): string
    {
        return Str::after($ruta, rtrim($raiz, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR);
    }
}