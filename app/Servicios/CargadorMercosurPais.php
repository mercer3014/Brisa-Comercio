<?php

namespace App\Servicios;

use App\Models\CargaArchivo;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Carga archivos MERCOSUR en formato "por País" (ISO 3166) hacia
 * la tabla serie_comercio_zona. Funciona con archivos de 100K+ filas
 * usando inserción en lotes.
 *
 * Cabecera esperada (normalizada):
 *   ISO 3166 | País | Año | Exportaciones | Importaciones (FOB) |
 *   Importaciones (CIF) | Volumen Exports | Volumen Imports
 */
class CargadorMercosurPais
{
    private const TAM_LOTE = 500;
    private const ORG_ID   = 3;

    private int $fuenteId;
    private int $zonaId;
    private array $cachePais  = [];
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

            // Idempotencia: borrar filas previas de esta misma carga.
            DB::table('serie_comercio_zona')->where('archivo_id', function ($q) use ($carga, $ext) {
                $q->select('archivo_id')
                  ->from('archivo_fuente')
                  ->where('ruta_archivo', "cargas/{$carga->carga_id}/datos.{$ext}");
            })->delete();

            $archivoId = $this->crearArchivoFuente($carga, $ruta);

            $lote     = [];
            $leidas   = 0;
            $validas  = 0;
            $errores  = 0;

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
                        DB::table('serie_comercio_zona')->insert($lote);
                        $lote = [];
                    }
                } catch (Throwable $e) {
                    $errores++;
                }

                if ($leidas % 5000 === 0) {
                    $proceso->update(['filas_procesadas' => $leidas]);
                }
            }

            if (! empty($lote)) {
                DB::table('serie_comercio_zona')->insert($lote);
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
                'mensaje_log'      => "MERCOSUR_PAIS: {$validas}/{$leidas} filas insertadas en serie_comercio_zona.",
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
        // Buscar las cabeceras normalizadas
        $iso    = $this->buscarCol($fila, ['ISO 3166', 'ISO3166', 'ISO']);
        $nombre = $this->buscarCol($fila, ['País', 'Pais', 'Country', 'Socio']);
        $anio   = $this->buscarCol($fila, ['Año', 'Ano', 'Year', 'AÑO']);
        $exp    = $this->buscarCol($fila, ['Exportaciones', 'Exports', 'Export']);
        $impFob = $this->buscarCol($fila, ['Importaciones (FOB)', 'Importaciones FOB', 'Imports FOB']);
        $impCif = $this->buscarCol($fila, ['Importaciones (CIF)', 'Importaciones CIF', 'Imports CIF', 'Importaciones']);
        $volExp = $this->buscarCol($fila, ['Volumen Exports', 'Vol Exports', 'Volumen Export', 'VolumeExports']);
        $volImp = $this->buscarCol($fila, ['Volumen Imports', 'Vol Imports', 'Volumen Import', 'VolumeImports']);

        $gestion = (int) $this->num($anio);
        if ($gestion < 1990 || $gestion > 2100) {
            return null; // fila de cabecera extra o vacía
        }

        $isoStr  = trim((string) ($iso ?? ''));
        $nomStr  = trim((string) ($nombre ?? ''));
        if ($isoStr === '' && $nomStr === '') {
            // Ni ISO 3166 ni País: no es realmente una fila "por país" (p.ej. un
            // archivo con formato de item/NCM mal ubicado en la carpeta "Por Países").
            // Sin esto, todas esas filas caerian en un único país generado por hash
            // y contaminarian el total de la zona.
            return null;
        }
        $paisId  = $this->resolverPais($isoStr, $nomStr);

        return [
            'organizacion_id'       => self::ORG_ID,
            'archivo_id'            => $archivoId,
            'zona_id'               => $this->zonaId,
            'pais_id'               => $paisId,
            'pais_iso3166'          => substr($isoStr, 0, 10) ?: null,
            'pais_nombre_original'  => substr($nomStr, 0, 150) ?: null,
            'tiempo_id'             => $this->resolverTiempo($gestion),
            'gestion'               => $gestion,
            'exportaciones_usd'     => $this->numNull($exp),
            'importaciones_fob_usd' => $this->numNull($impFob),
            'importaciones_cif_usd' => $this->numNull($impCif),
            'volumen_export_kg'     => $this->numNull($volExp),
            'volumen_import_kg'     => $this->numNull($volImp),
            // balanza_comercial_usd es GENERATED ALWAYS AS en PG — no insertar
        ];
    }

    private function buscarCol(array $fila, array $claves): mixed
    {
        foreach ($claves as $clave) {
            if (array_key_exists($clave, $fila)) {
                return $fila[$clave];
            }
        }
        // búsqueda insensible a mayúsculas
        $claveNorm = array_map(fn ($k) => strtolower(preg_replace('/[^a-z0-9]/i', '', $k)), $claves);
        foreach ($fila as $k => $v) {
            $kn = strtolower(preg_replace('/[^a-z0-9]/i', '', (string) $k));
            if (in_array($kn, $claveNorm, true)) {
                return $v;
            }
        }
        return null;
    }

    // -------------------------------------------------------------------------
    //  Resolución de dimensiones
    // -------------------------------------------------------------------------

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
        $desc = $this->zonaDesdeNombre($nombreArchivo);
        $norm = $this->normZona($desc);

        // Buscar zona existente de MERCOSUR por descripción normalizada
        $zonas = DB::table('zona_geoeconomica')
            ->where('fuente_id', $this->fuenteId)
            ->get(['zona_id', 'descripcion']);

        foreach ($zonas as $z) {
            if ($this->normZona($z->descripcion) === $norm) {
                return (int) $z->zona_id;
            }
        }

        // Crear nueva zona con código incremental
        $nextCodigo = (DB::table('zona_geoeconomica')
            ->where('fuente_id', $this->fuenteId)
            ->max('codigo_zona') ?? 0) + 1;

        return (int) DB::table('zona_geoeconomica')->insertGetId([
            'fuente_id'    => $this->fuenteId,
            'codigo_zona'  => $nextCodigo,
            'descripcion'  => $desc,
        ], 'zona_id');
    }

    private function resolverPais(string $iso, string $nombre): int
    {
        $cacheKey = $iso ?: $nombre;
        if (isset($this->cachePais[$cacheKey])) {
            return $this->cachePais[$cacheKey];
        }

        $id = null;

        // 1) Buscar por ISO numérico como codigo_pais, SOLO dentro de la fuente de
        // MERCOSUR. codigo_pais solo es único por fuente (UNIQUE(fuente_id,
        // codigo_pais)): buscar "en cualquier fuente" hacia mezclar el código
        // interno del INE con el ISO 3166 de MERCOSUR cuando coinciden por
        // casualidad (p.ej. 156 es "CEILAN" para el INE pero China en ISO 3166).
        if (is_numeric($iso) && (int) $iso > 0) {
            $id = DB::table('pais')->where('fuente_id', $this->fuenteId)->where('codigo_pais', (int) $iso)->value('pais_id');
        }

        // 2) Buscar por iso_alpha2 o iso_alpha3 (también acotado a la fuente de MERCOSUR)
        if (! $id && ! is_numeric($iso) && strlen($iso) <= 3 && $iso !== '') {
            $id = DB::table('pais')
                ->where('fuente_id', $this->fuenteId)
                ->where(fn ($q) => $q->where('iso_alpha2', $iso)->orWhere('iso_alpha3', $iso))
                ->value('pais_id');
        }

        // 3) Buscar por nombre parcial (sólo en fuente MERCOSUR)
        if (! $id && $nombre !== '') {
            $id = DB::table('pais')
                ->where('fuente_id', $this->fuenteId)
                ->whereRaw('LOWER(nombre) = ?', [mb_strtolower($nombre)])
                ->value('pais_id');
        }

        // 4) Crear país en fuente MERCOSUR
        if (! $id) {
            $codPais = is_numeric($iso) ? (int) $iso : (crc32($nombre ?: $iso) & 0x7FFFFFFF);
            // Asegurar unicidad en la fuente
            $existe = DB::table('pais')
                ->where('fuente_id', $this->fuenteId)
                ->where('codigo_pais', $codPais)
                ->exists();
            if ($existe) {
                $codPais = (DB::table('pais')->where('fuente_id', $this->fuenteId)->max('codigo_pais') ?? 0) + 1;
            }

            // MERCOSUR usa zona=0 para países no catalogados
            $zonaId0 = DB::table('zona_geoeconomica')
                ->where('fuente_id', $this->fuenteId)
                ->where('codigo_zona', 0)
                ->value('zona_id');
            if (! $zonaId0) {
                $zonaId0 = (int) DB::table('zona_geoeconomica')->insertGetId([
                    'fuente_id'   => $this->fuenteId,
                    'codigo_zona' => 0,
                    'descripcion' => 'Sin zona',
                ], 'zona_id');
            }

            $id = (int) DB::table('pais')->insertGetId([
                'fuente_id'   => $this->fuenteId,
                'zona_id'     => $zonaId0,
                'codigo_pais' => $codPais,
                'nombre'      => $nombre ?: "País $iso",
                'iso_alpha2'  => (! is_numeric($iso) && strlen($iso) === 2) ? $iso : null,
                'iso_alpha3'  => (! is_numeric($iso) && strlen($iso) === 3) ? $iso : null,
            ], 'pais_id');
        }

        $this->cachePais[$cacheKey] = (int) $id;
        return (int) $id;
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
            'organizacion_id'  => self::ORG_ID,
            'ruta_archivo'     => $ruta,
            'fecha_carga'      => now(),
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
        $n = trim(preg_replace('/\s+/', ' ', $n));
        // Quitar prefijos comunes de los archivos MERCOSUR (acepta cualquier cantidad
        // de espacios entre los años, ej. "2000   2026" tras normalizar el guion).
        $n = preg_replace('/^(exp e imp ?\d{4} *\d{4} ?|exportaciones importaciones ?\d+ ?)/i', '', $n);

        $mapa = [
            'asociacion europea'     => 'AELC (Asociación Europea de Libre Comercio)',
            'union europea'          => 'Unión Europea',
            'europa oriental'        => 'Europa Oriental',
            'oriente medio'          => 'Oriente Medio',
            'america del norte'      => 'América del Norte',
            'norte america'          => 'América del Norte',
            'america del sur'        => 'América del Sur',
            'sur america excepto'    => 'Sudamérica (excepto MERCOSUR)',
            'extrazona mercosur 5'   => 'Extrazona MERCOSUR 5',
            'extrazona mercosur'     => 'Extrazona MERCOSUR',
            'extrazona'              => 'Extrazona',
            'oceania'                => 'Oceanía',
            'africa menos'           => 'África (excl. Oriente Medio)',
            'africa'                 => 'África',
            'aelc'                   => 'AELC',
            'alianza del pacifico'   => 'Alianza del Pacífico',
            'alianza pacifico'       => 'Alianza del Pacífico',
            'asia menos oriente'     => 'Asia (excl. Oriente Medio)',
            'asia'                   => 'Asia',
            'mercosur 5'             => 'MERCOSUR 5',
            'mercado comun'          => 'MERCOSUR',
            'mercosur'               => 'MERCOSUR',
        ];

        foreach ($mapa as $clave => $valor) {
            if (str_contains($n, $clave)) {
                return $valor;
            }
        }

        // fallback: usar el nombre del archivo limpio en title case
        return ucwords(trim($n));
    }

    private function normZona(string $s): string
    {
        $s = mb_strtolower(trim($s));
        return preg_replace('/[^a-z0-9]/', '', $s);
    }

    // -------------------------------------------------------------------------
    //  Utilidades numéricas
    // -------------------------------------------------------------------------

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
