<?php

namespace App\Servicios;

use App\Models\CargaArchivo;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ProcesadorMercosur
{
    private int $tamLote = 1000;
    private array $cache = [];
    private array $lote = [];
    private string $tablaDestino = '';

    private int $leidas = 0;
    private int $validas = 0;
    private int $conError = 0;

    public function __construct(private LectorArchivo $lector)
    {
    }

    public function procesar(CargaArchivo $carga): void
    {
        $this->tamLote = (int) (\App\Models\Configuracion::obtener('lote_etl_filas', 1000) ?: 1000);
        $this->cache = [];
        $this->lote = [];
        $this->leidas = $this->validas = $this->conError = 0;

        $proceso = $carga->procesos()->create([
            'estado'       => 'EN_EJECUCION',
            'fecha_inicio' => now(),
        ]);

        try {
            $meta = $this->leerMetadata($carga);
            $extension = strtolower($meta['extension'] ?? 'xlsx');
            $rutaDatos = "cargas/{$carga->carga_id}/datos.{$extension}";

            if (! Storage::disk('local')->exists($rutaDatos)) {
                throw new \RuntimeException("No existe el archivo de datos de la carga #{$carga->carga_id}.");
            }

            $fuenteId = $this->resolverFuenteDatos($carga->organizacion_id);
            $zonaId = $this->resolverZonaDesdeMetadataONombre($carga, $meta);
            $archivoId = $this->registrarArchivoFuente($carga, $meta, $rutaDatos);

            $carga->update(['fuente_id' => $fuenteId, 'estado' => 'PROCESANDO']);

            DB::table('serie_comercio_zona')->where('archivo_id', $archivoId)->delete();
            DB::table('serie_comercio_producto_zona')->where('archivo_id', $archivoId)->delete();
            DB::table('incidencia_calidad')->where('carga_id', $carga->carga_id)->delete();

            $rutaAbs = Storage::disk('local')->path($rutaDatos);

            if ($carga->tipo_flujo === 'MERCOSUR_PAIS') {
                $this->tablaDestino = 'serie_comercio_zona';
                $this->procesarPorPaises($carga, $rutaAbs, $extension, $archivoId, $zonaId, $meta);
            } elseif ($carga->tipo_flujo === 'MERCOSUR_ITEM') {
                $this->tablaDestino = 'serie_comercio_producto_zona';
                $this->procesarItemsNcm($carga, $rutaAbs, $extension, $archivoId, $zonaId, $meta);
            } else {
                throw new \RuntimeException('Flujo MERCOSUR no soportado: '.$carga->tipo_flujo);
            }

            $this->vaciarLote();

            DB::table('archivo_fuente')->where('archivo_id', $archivoId)->update([
                'filas_detectadas' => $this->leidas,
                'estado_revision'  => $this->conError > 0 ? 'OBSERVADO' : 'OK',
                'observacion'      => $this->observacionArchivo($carga, $zonaId, $this->validas, $this->conError),
            ]);

            $carga->update([
                'total_filas_leidas'  => $this->leidas,
                'total_filas_validas' => $this->validas,
                'total_filas_error'   => $this->conError,
                'estado'              => 'COMPLETADO',
            ]);

            $proceso->update([
                'estado'           => 'EXITOSO',
                'fecha_fin'        => now(),
                'filas_procesadas' => $this->leidas,
                'mensaje_log'      => "MERCOSUR {$carga->tipo_flujo}. Leidas: {$this->leidas}, validas: {$this->validas}, con error: {$this->conError}.",
            ]);
        } catch (Throwable $e) {
            $carga->update(['estado' => 'FALLIDO']);
            $proceso->update([
                'estado'      => 'FALLIDO',
                'fecha_fin'   => now(),
                'mensaje_log' => 'Fallo MERCOSUR: '.$e->getMessage(),
            ]);
            report($e);
        }
    }

    private function procesarPorPaises(CargaArchivo $carga, string $rutaAbs, string $extension, int $archivoId, ?int $zonaId, array $meta): void
    {
        foreach ($this->lector->iterarAsociativo($rutaAbs, $extension) as $numeroFila => $filaCruda) {
            $this->leidas++;

            if ($this->filaVacia($filaCruda)) {
                continue;
            }

            try {
                $fila = $this->normalizarFila($filaCruda);
                $gestion = $this->obtenerGestion($fila);
                $iso3166 = $this->texto($this->valor($fila, ['ISO 3166', 'ISO3166', 'Codigo Pais', 'Codigo ISO']));
                $paisNombre = $this->texto($this->valor($fila, ['Pais', 'Nombre Pais']));

                if ($iso3166 === '' && $paisNombre === '') {
                    throw new \InvalidArgumentException('La fila no tiene ISO 3166 ni pais.');
                }

                $paisId = $this->resolverPaisPorIso($carga->organizacion_id, $iso3166, $paisNombre);

                $this->agregarALote([
                    'organizacion_id'       => $carga->organizacion_id,
                    'archivo_id'            => $archivoId,
                    'zona_id'               => $zonaId,
                    'pais_id'               => $paisId,
                    'pais_iso3166'          => $this->normalizarCodigoNumericoTexto($iso3166),
                    'pais_nombre_original'  => $paisNombre !== '' ? $paisNombre : null,
                    'tiempo_id'             => $this->resolverTiempoAnual($gestion),
                    'gestion'               => $gestion,
                    'exportaciones_usd'     => $this->aNumeroNull($this->valor($fila, ['Exportaciones', 'Exportaciones USD'])),
                    'importaciones_fob_usd' => $this->aNumeroNull($this->valor($fila, ['Importaciones (FOB)', 'Importaciones FOB', 'Importaciones FOB USD'])),
                    'importaciones_cif_usd' => $this->aNumeroNull($this->valor($fila, ['Importaciones (CIF)', 'Importaciones CIF', 'Importaciones CIF USD'])),
                    'volumen_export_kg'     => $this->aNumeroNull($this->valor($fila, ['Volumen Exports', 'Volumen Export', 'Volumen Exportaciones'])),
                    'volumen_import_kg'     => $this->aNumeroNull($this->valor($fila, ['Volumen Imports', 'Volumen Import', 'Volumen Importaciones'])),
                    'atributos_extra'       => $this->jsonExtra($numeroFila, $meta),
                ]);
            } catch (Throwable $e) {
                $this->conError++;
                $this->registrarIncidencia($carga->carga_id, $numeroFila + 1, $e->getMessage());
            }

            $this->actualizarAvancePeriodico($carga, $numeroFila);
        }
    }

    private function procesarItemsNcm(CargaArchivo $carga, string $rutaAbs, string $extension, int $archivoId, ?int $zonaId, array $meta): void
    {
        foreach ($this->lector->iterarAsociativo($rutaAbs, $extension) as $numeroFila => $filaCruda) {
            $this->leidas++;

            if ($this->filaVacia($filaCruda)) {
                continue;
            }

            try {
                $fila = $this->normalizarFila($filaCruda);
                $gestion = $this->obtenerGestion($fila);
                $ncmCodigo = $this->normalizarCodigoNcm($this->valor($fila, ['NCM', 'Codigo NCM']));
                $descripcion = $this->texto($this->valor($fila, ['Descripcion', 'Descripcion Producto']));

                if ($ncmCodigo === '') {
                    throw new \InvalidArgumentException('La fila no tiene codigo NCM.');
                }

                $productoExternoId = $this->resolverProductoCodigoExterno($carga->organizacion_id, $ncmCodigo, $descripcion);

                $this->agregarALote([
                    'organizacion_id'            => $carga->organizacion_id,
                    'archivo_id'                 => $archivoId,
                    'zona_id'                    => $zonaId,
                    'producto_codigo_externo_id' => $productoExternoId,
                    'ncm_codigo'                 => $ncmCodigo,
                    'ncm_descripcion'            => $descripcion !== '' ? $descripcion : null,
                    'tiempo_id'                  => $this->resolverTiempoAnual($gestion),
                    'gestion'                    => $gestion,
                    'exportaciones_usd'          => $this->aNumeroNull($this->valor($fila, ['Exportaciones', 'Exportaciones USD'])),
                    'importaciones_fob_usd'      => $this->aNumeroNull($this->valor($fila, ['Importaciones (FOB)', 'Importaciones FOB', 'Importaciones FOB USD'])),
                    'importaciones_cif_usd'      => $this->aNumeroNull($this->valor($fila, ['Importaciones (CIF)', 'Importaciones CIF', 'Importaciones CIF USD'])),
                    'volumen_export_kg'          => $this->aNumeroNull($this->valor($fila, ['Volumen Exports', 'Volumen Export', 'Volumen Exportaciones'])),
                    'volumen_import_kg'          => $this->aNumeroNull($this->valor($fila, ['Volumen Imports', 'Volumen Import', 'Volumen Importaciones'])),
                    'atributos_extra'            => $this->jsonExtra($numeroFila, $meta),
                ]);
            } catch (Throwable $e) {
                $this->conError++;
                $this->registrarIncidencia($carga->carga_id, $numeroFila + 1, $e->getMessage());
            }

            $this->actualizarAvancePeriodico($carga, $numeroFila);
        }
    }

    private function leerMetadata(CargaArchivo $carga): array
    {
        $ruta = "cargas/{$carga->carga_id}/mercosur.json";
        if (! Storage::disk('local')->exists($ruta)) {
            throw new \RuntimeException("No existe la metadata MERCOSUR de la carga #{$carga->carga_id}.");
        }

        return json_decode(Storage::disk('local')->get($ruta), true) ?: [];
    }

    private function registrarArchivoFuente(CargaArchivo $carga, array $meta, string $rutaDatos): int
    {
        $rutaFuente = $meta['ruta_origen'] ?? $rutaDatos;
        $existente = DB::table('archivo_fuente')->where('ruta_archivo', $rutaFuente)->value('archivo_id');
        $datos = [
            'organizacion_id'       => $carga->organizacion_id,
            'pais_reportante_id'    => $meta['pais_reportante_id'] ?? null,
            'flujo_id'              => null,
            'gestion'               => null,
            'filas_detectadas'      => 0,
            'estado_revision'       => 'OK',
            'observacion'           => 'Carga MERCOSUR '.$carga->tipo_flujo,
        ];

        if ($existente) {
            DB::table('archivo_fuente')->where('archivo_id', $existente)->update($datos);
            return (int) $existente;
        }

        return (int) DB::table('archivo_fuente')->insertGetId($datos + [
            'ruta_archivo' => $rutaFuente,
            'fecha_carga'  => now(),
        ], 'archivo_id');
    }

    private function resolverFuenteDatos(int $organizacionId): int
    {
        return $this->recordar("fuente-datos:$organizacionId", function () use ($organizacionId) {
            $id = DB::table('fuente_datos')
                ->where('organizacion_id', $organizacionId)
                ->where('version_nomenclatura', 'MERCOSUR_2026')
                ->value('fuente_id');

            if ($id) {
                return (int) $id;
            }

            return (int) DB::table('fuente_datos')->insertGetId([
                'organizacion_id'      => $organizacionId,
                'version_nomenclatura' => 'MERCOSUR_2026',
                'fecha_descarga'       => now()->toDateString(),
                'observaciones'        => 'Series comerciales por zona y producto NCM',
            ], 'fuente_id');
        });
    }

    private function resolverFuenteBase(int $organizacionId): int
    {
        return $this->recordar("fuente-base:$organizacionId", function () use ($organizacionId) {
            $id = DB::table('fuente_datos')
                ->where('organizacion_id', $organizacionId)
                ->where('version_nomenclatura', 'MERCOSUR-base')
                ->value('fuente_id');

            if ($id) {
                return (int) $id;
            }

            return (int) DB::table('fuente_datos')->insertGetId([
                'organizacion_id'      => $organizacionId,
                'version_nomenclatura' => 'MERCOSUR-base',
                'fecha_descarga'       => now()->toDateString(),
            ], 'fuente_id');
        });
    }

    private function resolverZonaDesdeMetadataONombre(CargaArchivo $carga, array $meta): ?int
    {
        if (! empty($meta['zona_id'])) {
            $existe = DB::table('zona_geoeconomica')->where('zona_id', $meta['zona_id'])->exists();
            if ($existe) {
                return (int) $meta['zona_id'];
            }
        }

        $codigoZona = $this->inferirCodigoZona($carga->nombre_archivo);
        if ($codigoZona === null) {
            return null;
        }

        return $this->resolverZonaPorCodigo($carga->organizacion_id, $codigoZona);
    }

    private function resolverZonaPorCodigo(int $organizacionId, int $codigoZona): int
    {
        return $this->recordar("zona:$organizacionId:$codigoZona", function () use ($organizacionId, $codigoZona) {
            $id = DB::table('zona_geoeconomica as z')
                ->join('fuente_datos as f', 'f.fuente_id', '=', 'z.fuente_id')
                ->where('f.organizacion_id', $organizacionId)
                ->where('z.codigo_zona', $codigoZona)
                ->orderByRaw("CASE WHEN f.version_nomenclatura = 'MERCOSUR_2026' THEN 0 ELSE 1 END")
                ->value('z.zona_id');

            if ($id) {
                return (int) $id;
            }

            return (int) DB::table('zona_geoeconomica')->insertGetId([
                'fuente_id'    => $this->resolverFuenteDatos($organizacionId),
                'codigo_zona'  => $codigoZona,
                'descripcion'  => "Zona MERCOSUR $codigoZona",
            ], 'zona_id');
        });
    }

    private function resolverZonaBase(int $organizacionId): int
    {
        $fuenteId = $this->resolverFuenteBase($organizacionId);

        return $this->recordar("zona-base:$organizacionId", function () use ($fuenteId) {
            $id = DB::table('zona_geoeconomica')
                ->where('fuente_id', $fuenteId)
                ->where('codigo_zona', 0)
                ->value('zona_id');

            if ($id) {
                return (int) $id;
            }

            return (int) DB::table('zona_geoeconomica')->insertGetId([
                'fuente_id'   => $fuenteId,
                'codigo_zona' => 0,
                'descripcion' => 'Sin zona',
            ], 'zona_id');
        });
    }

    private function resolverPaisPorIso(int $organizacionId, ?string $iso3166, ?string $nombre): ?int
    {
        $codigo = (int) $this->normalizarCodigoNumericoTexto($iso3166);
        if ($codigo <= 0) {
            return null;
        }

        return $this->recordar("pais:$organizacionId:$codigo", function () use ($organizacionId, $codigo, $nombre) {
            $id = DB::table('pais as p')
                ->join('fuente_datos as f', 'f.fuente_id', '=', 'p.fuente_id')
                ->where('f.organizacion_id', $organizacionId)
                ->where('p.codigo_pais', $codigo)
                ->orderByRaw("CASE WHEN f.version_nomenclatura = 'MERCOSUR-base' THEN 0 ELSE 1 END")
                ->value('p.pais_id');

            if ($id) {
                return (int) $id;
            }

            return (int) DB::table('pais')->insertGetId([
                'fuente_id'   => $this->resolverFuenteBase($organizacionId),
                'zona_id'     => $this->resolverZonaBase($organizacionId),
                'codigo_pais' => $codigo,
                'nombre'      => $nombre !== null && trim($nombre) !== '' ? trim($nombre) : "Pais $codigo",
            ], 'pais_id');
        });
    }

    private function resolverProductoCodigoExterno(int $organizacionId, string $codigo, ?string $descripcion): int
    {
        return $this->recordar("producto-ext:$organizacionId:$codigo", function () use ($organizacionId, $codigo, $descripcion) {
            $id = DB::table('producto_codigo_externo')
                ->where('organizacion_id', $organizacionId)
                ->where('nomenclatura', 'NCM')
                ->where('codigo_externo', $codigo)
                ->value('producto_codigo_externo_id');

            if ($id) {
                if ($descripcion !== null && trim($descripcion) !== '') {
                    DB::table('producto_codigo_externo')
                        ->where('producto_codigo_externo_id', $id)
                        ->whereNull('descripcion_externa')
                        ->update(['descripcion_externa' => trim($descripcion)]);
                }

                return (int) $id;
            }

            return (int) DB::table('producto_codigo_externo')->insertGetId([
                'organizacion_id'      => $organizacionId,
                'nomenclatura'         => 'NCM',
                'codigo_externo'       => $codigo,
                'descripcion_externa'  => $descripcion !== null && trim($descripcion) !== '' ? trim($descripcion) : null,
                'producto_id'          => null,
            ], 'producto_codigo_externo_id');
        });
    }

    private function resolverTiempoAnual(int $gestion): int
    {
        return $this->recordar("tiempo:$gestion:0", function () use ($gestion) {
            $id = DB::table('tiempo')->where('gestion', $gestion)->where('mes', 0)->value('tiempo_id');
            if ($id) {
                return (int) $id;
            }

            return (int) DB::table('tiempo')->insertGetId([
                'gestion'          => $gestion,
                'mes'              => 0,
                'nombre_mes'       => 'Anual',
                'trimestre'        => 0,
                'semestre'         => 0,
                'fecha_inicio_mes' => Carbon::createFromDate($gestion, 1, 1)->toDateString(),
            ], 'tiempo_id');
        });
    }

    private function agregarALote(array $fila): void
    {
        $this->lote[] = $fila;
        $this->validas++;

        if (count($this->lote) >= $this->tamLote) {
            $this->vaciarLote();
        }
    }

    private function vaciarLote(): void
    {
        if (empty($this->lote)) {
            return;
        }

        DB::table($this->tablaDestino)->insert($this->lote);
        $this->lote = [];
    }

    private function actualizarAvancePeriodico(CargaArchivo $carga, int $numeroFila): void
    {
        if ($this->leidas % 5000 !== 0) {
            return;
        }

        $carga->procesos()->latest('proceso_id')->first()?->update(['filas_procesadas' => $this->leidas]);
        $carga->update([
            'total_filas_leidas'  => $this->leidas,
            'total_filas_validas' => $this->validas,
            'total_filas_error'   => $this->conError,
        ]);
    }

    private function registrarIncidencia(int $cargaId, int $numeroFila, string $descripcion): void
    {
        DB::table('incidencia_calidad')->insert([
            'carga_id'           => $cargaId,
            'regla_id'           => null,
            'descripcion'        => mb_substr($descripcion, 0, 255),
            'severidad'          => 'ERROR',
            'numero_fila'        => $numeroFila,
            'valor_detectado'    => null,
            'estado_tratamiento' => 'PENDIENTE',
            'fecha_deteccion'    => now(),
        ]);
    }

    private function obtenerGestion(array $fila): int
    {
        $valor = $this->valor($fila, ['Anio', 'Ano', 'Gestion', 'Year']);
        $gestion = (int) $this->aNumeroNull($valor);

        if ($gestion < 1900 || $gestion > 2100) {
            throw new \InvalidArgumentException('Anio invalido o ausente.');
        }

        return $gestion;
    }

    private function normalizarFila(array $fila): array
    {
        $normalizada = [];
        foreach ($fila as $cabecera => $valor) {
            $normalizada[$this->normalizarTexto($cabecera)] = $valor;
        }

        return $normalizada;
    }

    private function valor(array $filaNormalizada, array $alias)
    {
        foreach ($alias as $nombre) {
            $clave = $this->normalizarTexto($nombre);
            if (array_key_exists($clave, $filaNormalizada)) {
                return $filaNormalizada[$clave];
            }
        }

        return null;
    }

    private function filaVacia(array $fila): bool
    {
        foreach ($fila as $valor) {
            if ($valor !== null && trim((string) $valor) !== '') {
                return false;
            }
        }

        return true;
    }

    private function texto($valor): string
    {
        return $valor === null ? '' : trim((string) $valor);
    }

    private function aNumeroNull($valor): ?float
    {
        if ($valor === null || $valor === '') {
            return null;
        }

        if (is_int($valor) || is_float($valor)) {
            $numero = (float) $valor;
        } else {
            $s = trim((string) $valor);
            if ($s === '') {
                return null;
            }

            $s = str_replace(["\xc2\xa0", ' '], '', $s);
            $posComa = strrpos($s, ',');
            $posPunto = strrpos($s, '.');

            if ($posComa !== false && $posComa > ($posPunto === false ? -1 : $posPunto)) {
                $s = str_replace('.', '', $s);
                $s = str_replace(',', '.', $s);
            } else {
                $s = str_replace(',', '', $s);
            }

            $s = preg_replace('/[^0-9.\-]/', '', $s);
            if ($s === '' || ! is_numeric($s)) {
                return null;
            }

            $numero = (float) $s;
        }

        if ($numero < 0) {
            throw new \InvalidArgumentException('Las metricas MERCOSUR no pueden ser negativas.');
        }

        return round($numero, 2);
    }

    private function normalizarCodigoNumericoTexto($valor): string
    {
        if ($valor === null || $valor === '') {
            return '';
        }

        if (is_int($valor) || is_float($valor)) {
            return (string) (int) round((float) $valor);
        }

        $texto = trim((string) $valor);
        if (preg_match('/^\d+(?:\.0+)?$/', $texto)) {
            return (string) (int) $texto;
        }

        return preg_replace('/\D+/', '', $texto) ?? '';
    }

    private function normalizarCodigoNcm($valor): string
    {
        if ($valor === null || $valor === '') {
            return '';
        }

        if (is_int($valor) || is_float($valor)) {
            $codigo = (string) (int) round((float) $valor);
        } else {
            $codigo = trim((string) $valor);
            if (preg_match('/^\d+(?:\.0+)?$/', $codigo)) {
                $codigo = (string) (int) $codigo;
            }
            $codigo = preg_replace('/[^0-9A-Za-z]/', '', $codigo) ?? '';
        }

        if ($codigo === '') {
            return '';
        }

        if (ctype_digit($codigo) && strlen($codigo) < 8) {
            $codigo = str_pad($codigo, 8, '0', STR_PAD_LEFT);
        }

        return substr($codigo, 0, 12);
    }

    private function inferirCodigoZona(string $nombreArchivo): ?int
    {
        $nombre = $this->normalizarTexto(pathinfo($nombreArchivo, PATHINFO_FILENAME));

        if (str_contains($nombre, 'MUNDO')) {
            return null;
        }

        $patrones = [
            'MERCADOCOMUNDELSUR5MERCOSUR5' => 2,
            'EXTRAZONAMERCOSUR5' => 16,
            'MERCADOCOMUNDELSURMERCOSUR' => 1,
            'SURAMERICAEXCEPTOMERCOSUR' => 6,
            'AMERICADELSUR' => 3,
            'AMERICADELNORTE' => 4,
            'ALIANZADELPACIFICO' => 7,
            'UNIONEUROPEA' => 8,
            'ASOCIACIONEUROPEADELIBRECOMERCIO' => 9,
            'EUROPAORIENTAL' => 10,
            'ASIAMENOSORIENTEMEDIO' => 11,
            'ORIENTEMEDIO' => 12,
            'OCEANIA' => 13,
            'AFRICAMENOSORIENTEMEDIO' => 14,
            'EXTRAZONA' => 15,
        ];

        foreach ($patrones as $patron => $codigo) {
            if (str_contains($nombre, $patron)) {
                return $codigo;
            }
        }

        return null;
    }

    private function normalizarTexto(string $valor): string
    {
        $texto = mb_strtoupper(trim($valor), 'UTF-8');
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $texto);
        if ($ascii !== false) {
            $texto = $ascii;
        }

        return preg_replace('/[^A-Z0-9]/', '', $texto) ?? '';
    }

    private function jsonExtra(int $numeroFila, array $meta): string
    {
        return json_encode([
            'fila_excel'          => $numeroFila + 1,
            'pais_reportante_id'  => $meta['pais_reportante_id'] ?? null,
            'tipo_archivo'        => $meta['tipo_archivo'] ?? null,
        ], JSON_UNESCAPED_UNICODE);
    }

    private function observacionArchivo(CargaArchivo $carga, ?int $zonaId, int $validas, int $errores): string
    {
        return trim("MERCOSUR {$carga->tipo_flujo}; zona_id=".($zonaId ?? 'null')."; validas=$validas; errores=$errores");
    }

    private function recordar(string $clave, callable $resolver): int
    {
        return $this->cache[$clave] ??= $resolver();
    }
}
