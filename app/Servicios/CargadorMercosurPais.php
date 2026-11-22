<?php

namespace App\Servicios;

use App\Models\CargaArchivo;
use App\Servicios\Concerns\CargaTransaccional;
use App\Support\Organizaciones;
use Illuminate\Database\QueryException;
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
    use CargaTransaccional;

    private const TAM_LOTE = 500;

    private int $fuenteId;
    private int $zonaId;
    private array $cachePais  = [];
    private array $cacheTiempo = [];
    /** Gestiones de la zona ya reemplazadas en esta carga (borrado perezoso). */
    private array $gestionesBorradas = [];
    /** Claves naturales vistas en el archivo (para contar repetidas). */
    private array $clavesVistas = [];
    /** Resolver ISO 3166 (CAMBIOS_ISO.md) y carga en curso, para incidencias de países sin ISO. */
    private ?ResolverPaisIso $iso = null;
    private int $cargaIdIso = 0;
    /** Rango de la columna pais.codigo_pais (SMALLINT). */
    private const CODIGO_MIN = -32768;
    private const CODIGO_MAX = 32767;

    /** Clave natural (respaldada por un índice UNIQUE, ver migración 2026_11_22_000001). */
    private const CLAVE_NATURAL = ['organizacion_id', 'zona_id', 'pais_id', 'gestion'];
    private const COLUMNAS_VALOR = ['archivo_id', 'tiempo_id', 'exportaciones_usd', 'importaciones_fob_usd', 'importaciones_cif_usd', 'volumen_export_kg', 'volumen_import_kg'];

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
            $this->iso        = app(ResolverPaisIso::class);
            $this->cargaIdIso = (int) $carga->carga_id;
            $ext  = pathinfo($ruta, PATHINFO_EXTENSION);

            $carga->update(['estado' => 'PROCESANDO']);

            $this->fuenteId = $this->resolverFuente();
            $this->zonaId   = $this->resolverZona($carga->nombre_archivo);

            // La carga es atómica: DELETE previo + INSERTs en una sola transacción.
            $this->iniciarTransaccion();

            // Idempotencia por CLAVE NATURAL (zona, gestión): volver a subir el mismo
            // archivo/zona/año reemplaza esas filas en vez de duplicarlas, sin
            // importar desde qué carga o ruta vinieron. El borrado de cada gestión
            // se hace al encontrarla en el archivo (dentro de la transacción).
            $this->gestionesBorradas = [];
            $this->clavesVistas = [];
            $repetidas = 0;

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
                    $this->borrarGestionSiCorresponde($row['gestion']);
                    $clave = $row['gestion'].'|'.$row['pais_id'];
                    if (isset($this->clavesVistas[$clave])) {
                        $repetidas++; // misma clave dos veces en el archivo: gana la última
                    }
                    $this->clavesVistas[$clave] = true;
                    $lote[] = $row;
                    $validas++;

                    if (count($lote) >= self::TAM_LOTE) {
                        $mal = $this->upsertLote('serie_comercio_zona', $lote, self::CLAVE_NATURAL, self::COLUMNAS_VALOR);
                        $validas -= $mal; $errores += $mal;
                        $lote = [];
                    }
                } catch (Throwable $e) {
                    if ($e instanceof QueryException) {
                        throw $e; // transacción abortada: se revierte la carga completa
                    }
                    $errores++;
                }

                if ($leidas % 5000 === 0) {
                    $proceso->update(['filas_procesadas' => $leidas]);
                }
            }

            if (! empty($lote)) {
                $mal = $this->upsertLote('serie_comercio_zona', $lote, self::CLAVE_NATURAL, self::COLUMNAS_VALOR);
                $validas -= $mal; $errores += $mal;
            }

            $this->confirmarTransaccion();

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
                'mensaje_log'      => "MERCOSUR_PAIS: {$validas}/{$leidas} filas insertadas en serie_comercio_zona."
                    .($repetidas ? " {$repetidas} fila(s) repetidas en el archivo (misma clave; se conservó la última)." : '')
                    .' Gestiones reemplazadas: '.(implode(', ', array_keys($this->gestionesBorradas)) ?: 'ninguna').'.',
            ]);

            if ($refrescarVistas) {
                \Illuminate\Support\Facades\Artisan::call('geodata:refrescar-vistas');
            }
        } catch (Throwable $e) {
            $this->revertirTransaccion();
            $carga->update(['estado' => 'FALLIDO', 'total_filas_validas' => 0]);
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
        // El trigger trg_pais_iso ya vinculó el país a pais_iso; si quedó SIN_MATCH,
        // se deja una advertencia en Calidad (una sola vez por carga y país).
        $this->iso?->registrarIncidenciaSiSinIso($paisId, $this->cargaIdIso);

        return [
            'organizacion_id'       => $this->orgId(),
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
            ->where('organizacion_id', $this->orgId())
            ->where('version_nomenclatura', 'MERCOSUR-base')
            ->value('fuente_id');

        return $id ? (int) $id : (int) DB::table('fuente_datos')->insertGetId([
            'organizacion_id'      => $this->orgId(),
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

        // Código numérico ISO 3166/M49 solo si cabe en pais.codigo_pais (SMALLINT).
        $codigoNum = (preg_match('/^\d+(\.0+)?$/', $iso) && (int) $iso > 0 && (int) $iso <= self::CODIGO_MAX)
            ? (int) $iso : null;

        // 1) Buscar por ISO numérico como codigo_pais, SOLO dentro de la fuente de
        // MERCOSUR. codigo_pais solo es único por fuente (UNIQUE(fuente_id,
        // codigo_pais)): buscar "en cualquier fuente" hacia mezclar el código
        // interno del INE con el ISO 3166 de MERCOSUR cuando coinciden por
        // casualidad (p.ej. 156 es "CEILAN" para el INE pero China en ISO 3166).
        if ($codigoNum !== null) {
            $id = DB::table('pais')->where('fuente_id', $this->fuenteId)->where('codigo_pais', $codigoNum)->value('pais_id');
        }

        // 2) Buscar por iso_alpha2 o iso_alpha3 (también acotado a la fuente de MERCOSUR)
        if (! $id && $codigoNum === null && preg_match('/^[A-Za-z]{2,3}$/', $iso)) {
            $alpha = strtoupper($iso);
            $id = DB::table('pais')
                ->where('fuente_id', $this->fuenteId)
                ->where(fn ($q) => $q->where('iso_alpha2', $alpha)->orWhere('iso_alpha3', $alpha))
                ->value('pais_id');
        }

        // 3) Buscar por nombre normalizado (mayúsculas, sin tildes ni puntuación,
        // geo_normalizar_nombre de CAMBIOS_ISO.md), sólo en la fuente MERCOSUR y sólo
        // entre filas sin código ISO propio: así una zona franca sin código se
        // reutiliza en cada recarga en vez de crear otra fila.
        if (! $id && $nombre !== '' && $codigoNum === null) {
            $id = DB::table('pais')
                ->where('fuente_id', $this->fuenteId)
                ->whereRaw('geo_normalizar_nombre(nombre) = geo_normalizar_nombre(?)', [$nombre])
                ->orderByRaw('(codigo_pais < 0) DESC, pais_id')
                ->value('pais_id');
        }

        // 4) Crear país en fuente MERCOSUR
        if (! $id) {
            // Con código ISO numérico se usa ese código (el trigger lo vincula por M49).
            // Sin código (zonas francas, "Desconocido", alfa no reconocido) se usa un
            // código interno NEGATIVO correlativo: nunca choca con un M49 (1..999) y
            // siempre cabe en SMALLINT. Antes se usaba crc32(), que desbordaba la columna.
            $codPais = $codigoNum;
            if ($codPais === null || DB::table('pais')->where('fuente_id', $this->fuenteId)->where('codigo_pais', $codPais)->exists()) {
                $min = (int) (DB::table('pais')->where('fuente_id', $this->fuenteId)->where('codigo_pais', '<', 0)->min('codigo_pais') ?? 0);
                $codPais = $min - 1;
                if ($codPais < self::CODIGO_MIN) {
                    throw new \RuntimeException('Se agotaron los códigos internos de país de MERCOSUR (rango SMALLINT).');
                }
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
                // El trigger trg_pais_iso valida estos valores contra pais_iso
                // (uno inválido queda NULL / SIN_MATCH en vez de romper la FK).
                'iso_alpha2'  => preg_match('/^[A-Za-z]{2}$/', $iso) ? strtoupper($iso) : null,
                'iso_alpha3'  => preg_match('/^[A-Za-z]{3}$/', $iso) ? strtoupper($iso) : null,
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

    /**
     * Borra (una sola vez por carga) las filas existentes de esta organización,
     * zona y gestión: así una recarga reemplaza y no duplica.
     */
    private function borrarGestionSiCorresponde(int $gestion): void
    {
        if (isset($this->gestionesBorradas[$gestion])) {
            return;
        }
        DB::table('serie_comercio_zona')
            ->where('organizacion_id', $this->orgId())
            ->where('zona_id', $this->zonaId)
            ->where('gestion', $gestion)
            ->delete();
        $this->gestionesBorradas[$gestion] = true;
    }

    private function crearArchivoFuente(CargaArchivo $carga, string $ruta): int
    {
        // ruta_archivo es UNIQUE en archivo_fuente: al recargar el mismo archivo
        // (p.ej. el comando por lote con la misma ruta) se reutiliza su registro.
        $existente = DB::table('archivo_fuente')->where('ruta_archivo', $ruta)->value('archivo_id');
        if ($existente) {
            DB::table('archivo_fuente')->where('archivo_id', $existente)->update(['fecha_carga' => now()]);

            return (int) $existente;
        }

        return (int) DB::table('archivo_fuente')->insertGetId([
            'organizacion_id'  => $this->orgId(),
            'ruta_archivo'     => $ruta,
            'fecha_carga'      => now(),
        ], 'archivo_id');
    }

    private function resolverRuta(int $cargaId): string
    {
        foreach (['xlsx', 'xlsm', 'xls', 'csv'] as $ext) {
            $rel = "cargas/{$cargaId}/datos.{$ext}";
            if (Storage::disk('local')->exists($rel)) {
                return Storage::disk('local')->path($rel);
            }
        }
        throw new \RuntimeException("Archivo de datos no encontrado para carga #{$cargaId}.");
    }

    /** Público: la previsualización del panel muestra la zona que se detectará. */
    public function zonaDesdeNombre(string $nombreArchivo): string
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

    private function orgId(): int
    {
        return Organizaciones::id(Organizaciones::MERCOSUR);
    }
}
