<?php

namespace App\Servicios;

use App\Models\CargaArchivo;
use Generator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Throwable;

/**
 * Carga archivos FAOSTAT hacia serie_indicador_agricola (organizacion_id=4).
 *
 * Formato real de FAOSTAT (dominio TI "Índices comerciales", un archivo .xls
 * por país, formato largo):
 *   Código del ámbito | Ámbito | Código del área (M49) | Área |
 *   Código del elemento | Elemento | Código del producto (CPC) | Producto |
 *   Código del año | Año | Unidad | Valor | Símbolo | Descripción del Símbolo
 *
 * Los valores son INDICES (base 2014-2016 = 100), no dólares. Cada fila se
 * guarda como una serie con su país (dimension país de la fuente FAOSTAT),
 * elemento (índice de valor/volumen de exportación o importación), producto
 * CPC (producto_codigo_externo) y simbolo de calidad (E = estimado, etc.).
 *
 * Soporta .xls (vía PhpSpreadsheet) y .xlsx/.csv (vía LectorArchivo).
 */
class CargadorFaostat
{
    private const TAM_LOTE = 1000;
    private const ORG_ID   = 4;

    private int $fuenteId;
    private array $cachePais     = [];
    private array $cacheElemento = [];
    private array $cacheSimbolo  = [];
    private array $cacheProducto = [];
    private array $cacheTiempo   = [];

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
            $ext  = strtolower(pathinfo($ruta, PATHINFO_EXTENSION));
            $carga->update(['estado' => 'PROCESANDO']);

            $this->fuenteId = $this->resolverFuente();
            $archivoId = $this->crearArchivoFuente($carga, $ruta);

            // Idempotencia: recargar el mismo archivo reemplaza sus filas.
            DB::table('serie_indicador_agricola')->where('archivo_id', $archivoId)->delete();

            $lote    = [];
            $leidas  = 0;
            $validas = 0;
            $errores = 0;

            foreach ($this->iterarFilas($ruta, $ext) as $fila) {
                $leidas++;
                try {
                    $row = $this->mapearFila($fila, $archivoId);
                    if ($row === null) {
                        continue;
                    }
                    $lote[] = $row;
                    $validas++;

                    if (count($lote) >= self::TAM_LOTE) {
                        DB::table('serie_indicador_agricola')->insert($lote);
                        $lote = [];
                    }
                } catch (Throwable $e) {
                    $errores++;
                }

                if ($leidas % 10000 === 0) {
                    $proceso->update(['filas_procesadas' => $leidas]);
                }
            }

            if (! empty($lote)) {
                DB::table('serie_indicador_agricola')->insert($lote);
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
                'mensaje_log'      => "FAOSTAT: {$validas}/{$leidas} series en serie_indicador_agricola.",
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
    //  Lectura
    // -------------------------------------------------------------------------

    /**
     * Itera filas asociativas cabecera => valor. Los .xls (binarios) se leen
     * con PhpSpreadsheet; xlsx/csv con el lector streaming de siempre.
     *
     * @return Generator<int, array<string, mixed>>
     */
    private function iterarFilas(string $ruta, string $ext): Generator
    {
        if ($ext !== 'xls') {
            yield from $this->lector->iterarAsociativo($ruta, $ext);

            return;
        }

        $reader = IOFactory::createReaderForFile($ruta);
        $reader->setReadDataOnly(true);
        $libro = $reader->load($ruta);
        $hoja  = $libro->getSheet(0);

        $maxFila = $hoja->getHighestRow();
        $maxCol  = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($hoja->getHighestColumn());

        $cabeceras = [];
        for ($c = 1; $c <= $maxCol; $c++) {
            $cabeceras[] = trim((string) $hoja->getCell([$c, 1])->getValue());
        }

        // Por bloques para no armar un arreglo gigante de una vez.
        for ($desde = 2; $desde <= $maxFila; $desde += 5000) {
            $hasta = min($desde + 4999, $maxFila);
            $bloque = $hoja->rangeToArray("A{$desde}:".$hoja->getHighestColumn().$hasta, null, false, false, false);
            foreach ($bloque as $valores) {
                $asoc = [];
                foreach ($cabeceras as $i => $nombre) {
                    $asoc[$nombre] = $valores[$i] ?? null;
                }
                yield $asoc;
            }
        }

        $libro->disconnectWorksheets();
        unset($libro);
    }

    // -------------------------------------------------------------------------
    //  Mapeo
    // -------------------------------------------------------------------------

    private function mapearFila(array $fila, int $archivoId): ?array
    {
        $m49      = trim((string) ($this->buscarCol($fila, ['Código del área (M49)', 'Codigo del area (M49)', 'Área Código', 'Area Code']) ?? ''));
        $area     = trim((string) ($this->buscarCol($fila, ['Área', 'Area']) ?? ''));
        $codElem  = trim((string) ($this->buscarCol($fila, ['Código del elemento', 'Codigo del elemento', 'Element Code']) ?? ''));
        $elemento = trim((string) ($this->buscarCol($fila, ['Elemento', 'Element']) ?? ''));
        $codProd  = trim((string) ($this->buscarCol($fila, ['Código del producto (CPC)', 'Codigo del producto (CPC)', 'Item Code (CPC)']) ?? ''));
        $producto = trim((string) ($this->buscarCol($fila, ['Producto', 'Item']) ?? ''));
        $anio     = $this->buscarCol($fila, ['Año', 'Ano', 'Year']);
        $unidad   = trim((string) ($this->buscarCol($fila, ['Unidad', 'Unit']) ?? ''));
        $valor    = $this->buscarCol($fila, ['Valor', 'Value']);
        $simbolo  = trim((string) ($this->buscarCol($fila, ['Símbolo', 'Simbolo', 'Flag']) ?? ''));
        $simDesc  = trim((string) ($this->buscarCol($fila, ['Descripción del Símbolo', 'Descripcion del Simbolo', 'Flag Description']) ?? ''));

        $gestion = (int) $this->num($anio);
        if ($gestion < 1950 || $gestion > 2100 || $elemento === '' || $area === '') {
            return null;
        }

        return [
            'organizacion_id'            => self::ORG_ID,
            'archivo_id'                 => $archivoId,
            'pais_id'                    => $this->resolverPais($m49, $area),
            'producto_codigo_externo_id' => $this->resolverProducto($codProd, $producto),
            'tiempo_id'                  => $this->resolverTiempo($gestion),
            'gestion'                    => $gestion,
            'elemento_id'                => $this->resolverElemento($codElem, $elemento),
            'simbolo_id'                 => $this->resolverSimbolo($simbolo, $simDesc),
            'valor'                      => $this->numNull($valor),
            'unidad'                     => $unidad !== '' ? substr($unidad, 0, 50) : 'índice (2014-2016 = 100)',
            'atributos_extra'            => null,
        ];
    }

    // -------------------------------------------------------------------------
    //  Resolución de dimensiones (con cache en memoria para la carga en lote)
    // -------------------------------------------------------------------------

    private function resolverFuente(): int
    {
        $id = DB::table('fuente_datos')
            ->where('organizacion_id', self::ORG_ID)
            ->where('version_nomenclatura', 'FAOSTAT-base')
            ->value('fuente_id');

        return $id ? (int) $id : (int) DB::table('fuente_datos')->insertGetId([
            'organizacion_id'      => self::ORG_ID,
            'version_nomenclatura' => 'FAOSTAT-base',
            'fecha_descarga'       => now()->toDateString(),
        ], 'fuente_id');
    }

    /** País por código M49 dentro de la fuente FAOSTAT (se crea si no existe). */
    private function resolverPais(string $m49, string $nombre): int
    {
        $codigo = (int) ltrim($m49, '0');
        $clave = $codigo.'|'.$nombre;
        if (isset($this->cachePais[$clave])) {
            return $this->cachePais[$clave];
        }

        $id = DB::table('pais')
            ->where('fuente_id', $this->fuenteId)
            ->where('codigo_pais', $codigo)
            ->value('pais_id');

        if (! $id) {
            $id = DB::table('pais')->insertGetId([
                'fuente_id'   => $this->fuenteId,
                'zona_id'     => $this->zonaSinZona(),
                'codigo_pais' => $codigo,
                'nombre'      => substr($nombre, 0, 150) ?: "País {$m49}",
            ], 'pais_id');
        }

        return $this->cachePais[$clave] = (int) $id;
    }

    private function zonaSinZona(): int
    {
        $id = DB::table('zona_geoeconomica')
            ->where('fuente_id', $this->fuenteId)
            ->where('codigo_zona', 0)
            ->value('zona_id');

        return $id ? (int) $id : (int) DB::table('zona_geoeconomica')->insertGetId([
            'fuente_id'   => $this->fuenteId,
            'codigo_zona' => 0,
            'descripcion' => 'Sin zona',
        ], 'zona_id');
    }

    /** Elemento FAOSTAT (índice); tipo_comercio según sea de exportación o importación. */
    private function resolverElemento(string $codigo, string $nombre): int
    {
        $clave = $codigo.'|'.$nombre;
        if (isset($this->cacheElemento[$clave])) {
            return $this->cacheElemento[$clave];
        }

        $id = DB::table('faostat_elemento')
            ->where('nombre_elemento', $nombre)
            ->value('elemento_id');

        if (! $id) {
            $n = mb_strtolower($nombre);
            $tipo = str_contains($n, 'import') ? 'IMPORTACION'
                : (str_contains($n, 'export') ? 'EXPORTACION' : 'OTRO');

            $id = DB::table('faostat_elemento')->insertGetId([
                'codigo_elemento' => substr($codigo ?: md5($nombre), 0, 10),
                'nombre_elemento' => substr($nombre, 0, 200),
                'tipo_comercio'   => $tipo,
            ], 'elemento_id');
        }

        return $this->cacheElemento[$clave] = (int) $id;
    }

    private function resolverSimbolo(string $codigo, string $descripcion): ?int
    {
        if ($codigo === '') {
            return null;
        }
        if (isset($this->cacheSimbolo[$codigo])) {
            return $this->cacheSimbolo[$codigo];
        }

        $id = DB::table('faostat_simbolo')->where('codigo', $codigo)->value('simbolo_id');
        if (! $id) {
            $id = DB::table('faostat_simbolo')->insertGetId([
                'codigo'      => substr($codigo, 0, 10),
                'descripcion' => $descripcion !== '' ? substr($descripcion, 0, 200) : null,
            ], 'simbolo_id');
        }

        return $this->cacheSimbolo[$codigo] = (int) $id;
    }

    /** Producto CPC como código externo de la organización FAOSTAT. */
    private function resolverProducto(string $cpc, string $nombre): ?int
    {
        if ($cpc === '' && $nombre === '') {
            return null;
        }
        $codigo = $cpc !== '' ? $cpc : mb_substr($nombre, 0, 30);
        if (isset($this->cacheProducto[$codigo])) {
            return $this->cacheProducto[$codigo];
        }

        $id = DB::table('producto_codigo_externo')
            ->where('organizacion_id', self::ORG_ID)
            ->where('nomenclatura', 'CPC')
            ->where('codigo_externo', $codigo)
            ->value('producto_codigo_externo_id');

        if (! $id) {
            $id = DB::table('producto_codigo_externo')->insertGetId([
                'producto_id'         => null,
                'organizacion_id'     => self::ORG_ID,
                'nomenclatura'        => 'CPC',
                'codigo_externo'      => substr($codigo, 0, 30),
                'descripcion_externa' => $nombre !== '' ? substr($nombre, 0, 300) : null,
            ], 'producto_codigo_externo_id');
        }

        return $this->cacheProducto[$codigo] = (int) $id;
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
                'fecha_inicio_mes' => \Carbon\Carbon::createFromDate($gestion, 1, 1)->toDateString(),
            ], 'tiempo_id');
        }

        return $this->cacheTiempo[$gestion] = (int) $id;
    }

    private function crearArchivoFuente(CargaArchivo $carga, string $ruta): int
    {
        // ruta_archivo es UNIQUE: al recargar el mismo archivo se reutiliza su
        // registro (y la idempotencia por archivo_id reemplaza sus filas).
        $existente = DB::table('archivo_fuente')->where('ruta_archivo', $ruta)->value('archivo_id');
        if ($existente) {
            DB::table('archivo_fuente')->where('archivo_id', $existente)->update(['fecha_carga' => now()]);

            return (int) $existente;
        }

        return (int) DB::table('archivo_fuente')->insertGetId([
            'organizacion_id' => self::ORG_ID,
            'ruta_archivo'    => $ruta,
            'fecha_carga'     => now(),
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
        throw new \RuntimeException("Archivo FAOSTAT no encontrado para carga #{$cargaId}.");
    }

    private function buscarCol(array $fila, array $claves): mixed
    {
        foreach ($claves as $clave) {
            if (array_key_exists($clave, $fila)) {
                return $fila[$clave];
            }
        }
        $norm = array_map(fn ($k) => strtolower(preg_replace('/[^a-z0-9]/i', '', $k)), $claves);
        foreach ($fila as $k => $v) {
            if (in_array(strtolower(preg_replace('/[^a-z0-9]/i', '', (string) $k)), $norm, true)) {
                return $v;
            }
        }

        return null;
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
