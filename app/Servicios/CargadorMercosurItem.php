<?php

namespace App\Servicios;

use App\Models\CargaArchivo;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Carga archivos MERCOSUR en formato "por Item/NCM" hacia
 * la tabla serie_comercio_producto_zona. Apto para archivos de 170K+ filas
 * usando inserción en lotes y bajo consumo de memoria (streaming).
 *
 * Cabecera esperada:
 *   NCM | Descripción | Año | Exportaciones | Importaciones (FOB) |
 *   Importaciones (CIF) | Volumen Exports | Volumen Imports
 */
class CargadorMercosurItem
{
    private const TAM_LOTE = 1000;
    private const ORG_ID   = 3;

    private int $fuenteId;
    private int $zonaId;
    private array $cacheTiempo = [];

    public function __construct(private LectorArchivo $lector)
    {
    }

    public function cargar(CargaArchivo $carga, ?string $rutaDirecta = null, bool $refrescarVistas = true): void
    {
        $proceso = $carga->procesos()->create([
            'estado'       => 'EN_EJECUCION',
            'fecha_inicio' => now(),
        ]);

        try {
            $ruta = $rutaDirecta ?? $this->resolverRuta($carga->carga_id);
            $ext  = pathinfo($ruta, PATHINFO_EXTENSION);
            $carga->update(['estado' => 'PROCESANDO']);

            $this->fuenteId = $this->resolverFuente();
            $this->zonaId   = $this->resolverZona($carga->nombre_archivo);

            $archivoId = $this->crearArchivoFuente($carga, $ruta);

            // Idempotencia: borrar filas previas de este archivo
            DB::table('serie_comercio_producto_zona')->where('archivo_id', $archivoId)->delete();

            $lote    = [];
            $leidas  = 0;
            $validas = 0;
            $errores = 0;

            foreach ($this->lector->iterarAsociativo($ruta, $ext) as $fila) {
                $leidas++;
                try {
                    $row = $this->mapearFila($fila, $archivoId);
                    if ($row === null) {
                        continue;
                    }
                    $lote[] = $row;
                    $validas++;

                    if (count($lote) >= self::TAM_LOTE) {
                        DB::table('serie_comercio_producto_zona')->insert($lote);
                        $lote = [];
                    }
                } catch (Throwable $e) {
                    $errores++;
                }

                if ($leidas % 10000 === 0) {
                    $proceso->update(['filas_procesadas' => $leidas]);
                    $carga->update([
                        'total_filas_leidas'  => $leidas,
                        'total_filas_validas' => $validas,
                        'total_filas_error'   => $errores,
                    ]);
                }
            }

            if (! empty($lote)) {
                DB::table('serie_comercio_producto_zona')->insert($lote);
            }

            $carga->update([
                'total_filas_leidas'  => $leidas,
                'total_filas_validas' => $validas,
                'total_filas_error'   => $errores,
                'estado'              => 'COMPLETADO',
            ]);
            DB::table('archivo_fuente')->where('archivo_id', $archivoId)
                ->update(['filas_detectadas' => $leidas, 'estado_revision' => 'OK']);

            $proceso->update([
                'estado'           => 'EXITOSO',
                'fecha_fin'        => now(),
                'filas_procesadas' => $leidas,
                'mensaje_log'      => "MERCOSUR_ITEM: {$validas}/{$leidas} filas en serie_comercio_producto_zona.",
            ]);

            if ($refrescarVistas) {
                \Illuminate\Support\Facades\Artisan::call('geodata:refrescar-vistas');
            }
        } catch (Throwable $e) {
            $carga->update(['estado' => 'FALLIDO']);
            $proceso->update([
                'estado'      => 'FALLIDO',
                'fecha_fin'   => now(),
                'mensaje_log' => 'Error: '.$e->getMessage(),
            ]);
            report($e);
        }
    }

    // -------------------------------------------------------------------------

    private function mapearFila(array $fila, int $archivoId): ?array
    {
        $ncm    = $this->buscarCol($fila, ['NCM', 'Código NCM', 'Codigo NCM', 'Item NCM', 'Item']);
        $desc   = $this->buscarCol($fila, ['Descripción', 'Descripcion', 'Description']);
        $anio   = $this->buscarCol($fila, ['Año', 'Ano', 'Year', 'AÑO']);
        $exp    = $this->buscarCol($fila, ['Exportaciones', 'Exports', 'Export']);
        $impFob = $this->buscarCol($fila, ['Importaciones (FOB)', 'Importaciones FOB', 'Imports FOB']);
        $impCif = $this->buscarCol($fila, ['Importaciones (CIF)', 'Importaciones CIF', 'Imports CIF', 'Importaciones']);
        $volExp = $this->buscarCol($fila, ['Volumen Exports', 'Vol Exports', 'Volumen Export']);
        $volImp = $this->buscarCol($fila, ['Volumen Imports', 'Vol Imports', 'Volumen Import']);

        $gestion  = (int) $this->num($anio);
        $ncmStr   = trim((string) ($ncm ?? ''));
        $descStr  = trim((string) ($desc ?? ''));

        if ($gestion < 1990 || $gestion > 2100 || $ncmStr === '') {
            return null;
        }

        return [
            'organizacion_id'       => self::ORG_ID,
            'archivo_id'            => $archivoId,
            'zona_id'               => $this->zonaId,
            'producto_codigo_externo_id' => null, // no se resuelve aún (fase ETL avanzada)
            'ncm_codigo'            => substr($ncmStr, 0, 12),
            'ncm_descripcion'       => $descStr !== '' ? $descStr : null,
            'tiempo_id'             => $this->resolverTiempo($gestion),
            'gestion'               => $gestion,
            'exportaciones_usd'     => $this->numNull($exp),
            'importaciones_fob_usd' => $this->numNull($impFob),
            'importaciones_cif_usd' => $this->numNull($impCif),
            'volumen_export_kg'     => $this->numNull($volExp),
            'volumen_import_kg'     => $this->numNull($volImp),
        ];
    }

    private function buscarCol(array $fila, array $claves): mixed
    {
        foreach ($claves as $clave) {
            if (array_key_exists($clave, $fila)) {
                return $fila[$clave];
            }
        }
        $claveNorm = array_map(fn ($k) => strtolower(preg_replace('/[^a-z0-9]/i', '', $k)), $claves);
        foreach ($fila as $k => $v) {
            $kn = strtolower(preg_replace('/[^a-z0-9]/i', '', (string) $k));
            if (in_array($kn, $claveNorm, true)) {
                return $v;
            }
        }
        return null;
    }

    private function resolverFuente(): int
    {
        $id = DB::table('fuente_datos')
            ->where('organizacion_id', self::ORG_ID)
            ->where('version_nomenclatura', 'MERCOSUR-base')
            ->value('fuente_id');

        return $id ? (int) $id : (int) DB::table('fuente_datos')->insertGetId([
            'organizacion_id'      => self::ORG_ID,
            'version_nomenclatura' => 'MERCOSUR-base',
            'fecha_descarga'       => now()->toDateString(),
        ], 'fuente_id');
    }

    private function resolverZona(string $nombreArchivo): int
    {
        // Reutilizar zona si ya existe para esta fuente con la misma descripción
        $desc = $this->zonaDesdeNombre($nombreArchivo);
        $norm = $this->normZona($desc);

        $zonas = DB::table('zona_geoeconomica')
            ->where('fuente_id', $this->fuenteId)
            ->get(['zona_id', 'descripcion']);

        foreach ($zonas as $z) {
            if ($this->normZona($z->descripcion) === $norm) {
                return (int) $z->zona_id;
            }
        }

        $nextCodigo = (DB::table('zona_geoeconomica')
            ->where('fuente_id', $this->fuenteId)
            ->max('codigo_zona') ?? 0) + 1;

        return (int) DB::table('zona_geoeconomica')->insertGetId([
            'fuente_id'   => $this->fuenteId,
            'codigo_zona' => $nextCodigo,
            'descripcion' => $desc,
        ], 'zona_id');
    }

    private function resolverTiempo(int $gestion): int
    {
        if (isset($this->cacheTiempo[$gestion])) {
            return $this->cacheTiempo[$gestion];
        }
        $id = DB::table('tiempo')->where('gestion', $gestion)->where('mes', 0)->value('tiempo_id');
        if (! $id) {
            $id = (int) DB::table('tiempo')->insertGetId([
                'gestion'          => $gestion,
                'mes'              => 0,
                'nombre_mes'       => 'Anual',
                'trimestre'        => 0,
                'semestre'         => 0,
                'fecha_inicio_mes' => Carbon::createFromDate($gestion, 1, 1)->toDateString(),
            ], 'tiempo_id');
        }
        $this->cacheTiempo[$gestion] = (int) $id;
        return (int) $id;
    }

    private function crearArchivoFuente(CargaArchivo $carga, string $ruta): int
    {
        return (int) DB::table('archivo_fuente')->insertGetId([
            'organizacion_id' => self::ORG_ID,
            'ruta_archivo'    => $ruta,
            
            'fecha_carga'     => now(),
        ], 'archivo_id');
    }

    private function resolverRuta(int $cargaId): string
    {
        foreach (['xlsx', 'xlsm', 'csv'] as $ext) {
            $rel = "cargas/{$cargaId}/datos.{$ext}";
            if (Storage::disk('local')->exists($rel)) {
                return Storage::disk('local')->path($rel);
            }
        }
        throw new \RuntimeException("Archivo de datos no encontrado para carga #{$cargaId}.");
    }

    private function zonaDesdeNombre(string $nombreArchivo): string
    {
        $n = strtolower(str_replace(['_', '-'], ' ', pathinfo($nombreArchivo, PATHINFO_FILENAME)));
        $n = preg_replace('/^(exp e imp ?\d{4} ?\d{4} ?|exportaciones importaciones ?\d+ ?)/i', '', $n);

        $mapa = [
            'alianza del pacifico' => 'Alianza del Pacífico',
            'alianza pacifico'     => 'Alianza del Pacífico',
            'america excepto'      => 'América (excepto MERCOSUR)',
            'africa menos'         => 'África (excl. Oriente Medio)',
            'africa'               => 'África',
            'asia'                 => 'Asia',
            'union europea'        => 'Unión Europea',
            'mercosur'             => 'MERCOSUR',
        ];

        foreach ($mapa as $clave => $valor) {
            if (str_contains($n, $clave)) {
                return $valor;
            }
        }
        return ucwords(trim($n));
    }

    private function normZona(string $s): string
    {
        return preg_replace('/[^a-z0-9]/', '', mb_strtolower(trim($s)));
    }

    private function num(mixed $v): float
    {
        if ($v === null || $v === '') {
            return 0.0;
        }
        if (is_numeric($v)) {
            return (float) $v;
        }
        $s = str_replace([' ', ','], ['', ''], (string) $v);
        return is_numeric($s) ? (float) $s : 0.0;
    }

    private function numNull(mixed $v): ?float
    {
        return ($v === null || $v === '') ? null : $this->num($v);
    }
}
