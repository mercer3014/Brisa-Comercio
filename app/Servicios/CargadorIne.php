<?php

namespace App\Servicios;

use App\Models\CargaArchivo;
use App\Servicios\Concerns\CargaTransaccional;
use App\Support\Organizaciones;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Generator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;
use Throwable;

/**
 * Carga archivos del INE (microdato de comercio exterior) hacia
 * operacion_comercio_exterior, resolviendo cada dimensión con su NOMBRE REAL
 * tomado de las columnas DES* del propio archivo (DESPAIS, DESDEP, DESNAN, ...).
 *
 * Maneja las dos familias de columnas del INE:
 *   - EXPORTACIONES: ... PAIS DESPAIS ... MEDI DESMEDI VIASAL DESVIA DEPART DESDEP
 *     CUCI3 GCE3 CIIUR3 TNT DESTNT CLTNT [KILBRU] KILNET [FINO] VALOR
 *   - IMPORTACIONES: ADUANA DESADU DEPTO DESDEPTO VIA DESVIA MEDIO DESMED PAIS DESPAI
 *     DESZON NANDINA GCER3 CUODE CIIUR3 CUCIR3 KILBRU|KILOS FRO FOB ADU PAG
 *
 * Convención de valor: EXPORTACION -> valor_fob_usd (VALOR);
 * IMPORTACION -> valor_cif_frontera_usd (FRO), valor_fob_usd (FOB),
 * valor_cif_aduana_usd (ADU), gravamenes_pagados (PAG).
 *
 * Todas las dimensiones se asocian a una única fuente "INE-base" para que las
 * series sean coherentes y agregables entre gestiones (1992–2026).
 */
/** La gestión del archivo no coincide con la indicada en el formulario. */
class GestionNoCoincide extends \RuntimeException
{
}

class CargadorIne
{
    use CargaTransaccional;

    private const TAM_LOTE = 2000;
    /** Máximo de incidencias detalladas por carga (el resto se cuenta). */
    private const MAX_INCIDENCIAS = 500;

    private int $orgId;
    private array $incidencias = [];
    private array $omitidas = ['sin_gestion' => 0, 'sin_nandina' => 0];
    private bool $cabeceraHallada = false;
    /** Gestiones ya reemplazadas en esta carga. */
    private array $borradas = [];

    private int $fuenteId;
    private int $tipoOperacionId;
    private int $flujoId;
    private bool $esExport;

    /** Caches en memoria: clave => id. */
    private array $cache = [];

    /** Mapa cabecera-normalizada => posición de columna (por archivo). */
    private array $colIndex = [];
    /** Cache de posición resuelta por conjunto de sinónimos (evita regex por fila). */
    private array $posCache = [];

    public function __construct(private LectorArchivo $lector)
    {
    }

    public function cargar(CargaArchivo $carga, ?string $rutaDirecta = null): void
    {
        @set_time_limit(0);
        @ini_set('memory_limit', '2048M');

        $proceso = $carga->procesos()->create([
            'estado'       => 'EN_EJECUCION',
            'fecha_inicio' => now(),
        ]);

        // Estado por carga (la instancia puede reutilizarse en los comandos por lote).
        $this->cache        = [];
        $this->colIndex     = [];
        $this->posCache     = [];
        $this->incidencias  = [];
        $this->omitidas     = ['sin_gestion' => 0, 'sin_nandina' => 0];
        $this->cabeceraHallada = false;
        $this->borradas     = [];
        $this->negativos    = 0;
        $this->orgId        = Organizaciones::id(Organizaciones::INE);

        $leidas = 0; $validas = 0; $errores = 0;

        try {
            $ruta = $rutaDirecta && file_exists($rutaDirecta) ? $rutaDirecta : $this->resolverRuta($carga->carga_id);
            $carga->update(['estado' => 'PROCESANDO']);

            $this->esExport = $carga->tipo_flujo === 'EXPORTACION';

            // TODO el reemplazo (DELETE + INSERT) va en UNA transacción: si el archivo
            // falla a mitad de camino, se revierte y los datos previos quedan intactos.
            $this->iniciarTransaccion();

            $this->fuenteId        = $this->resolverFuente();
            $this->tipoOperacionId = $this->resolverTipoOperacion();
            $this->flujoId         = $this->resolverFlujo();

            // Sin gestión en el formulario: idempotencia por carga_id (comportamiento previo).
            if (! $carga->gestion) {
                DB::table('operacion_comercio_exterior')->where('carga_id', $carga->carga_id)->delete();
            }

            $lote = [];

            foreach ($this->iterarIne($ruta) as $fila) {
                $leidas++;
                try {
                    $row = $this->mapearFila($fila, $carga, $leidas);
                    if ($row === null) { continue; }
                    // Idempotencia por (gestión, flujo): la gestión se borra recién cuando
                    // aparece en el archivo y ya fue validada contra el formulario.
                    $this->borrarGestionSiCorresponde($row['gestion']);
                    $lote[] = $row;
                    $validas++;
                    if (count($lote) >= self::TAM_LOTE) {
                        $mal = $this->insertarLote('operacion_comercio_exterior', $lote);
                        $validas -= $mal; $errores += $mal;
                        $lote = [];
                    }
                } catch (GestionNoCoincide $e) {
                    throw $e;
                } catch (QueryException $e) {
                    throw $e; // la transacción quedó abortada: se revierte la carga completa
                } catch (Throwable $e) {
                    $errores++;
                    $this->registrarIncidencia('ERROR', 'Fila con error de conversión: '.mb_substr($e->getMessage(), 0, 200), $leidas + 1);
                }

                if ($leidas % 20000 === 0) {
                    $proceso->update(['filas_procesadas' => $leidas]);
                }
            }

            if (! empty($lote)) {
                $mal = $this->insertarLote('operacion_comercio_exterior', $lote);
                $validas -= $mal; $errores += $mal;
            }

            if (! $this->cabeceraHallada) {
                throw new \RuntimeException('No se encontró la fila de cabecera: ninguna hoja tiene columnas de año (GESTION/ANIO/ANO) y de producto (NANDINA/PARTIDA). No se modificó ningún dato.');
            }
            if ($validas === 0) {
                throw new \RuntimeException("El archivo no produjo filas válidas ({$leidas} leídas). No se modificó ningún dato.");
            }

            $carga->update(['fuente_id' => $this->fuenteId]);
            $this->confirmarTransaccion();

            $omitidas = array_sum($this->omitidas);
            if ($this->negativos > 0) {
                $this->registrarIncidencia('ADVERTENCIA', "Resumen: {$this->negativos} fila(s) con valores negativos (devoluciones/ajustes del INE, sobre todo PAG). Se cargaron con esa columna en NULL y el valor original en atributos_extra.negativos.", null);
            }
            if ($errores > 0 && $this->ultimoErrorFila) {
                $this->registrarIncidencia('ERROR', "{$errores} fila(s) rechazadas por la BD. Último error: {$this->ultimoErrorFila}", null);
            }
            $this->guardarIncidencias($carga, $omitidas);

            $carga->update([
                'total_filas_leidas'   => $leidas,
                'total_filas_validas'  => $validas,
                'total_filas_error'    => $errores,
                'total_filas_omitidas' => $omitidas,
                'estado'               => 'COMPLETADO',
            ]);
            $proceso->update([
                'estado'           => 'EXITOSO',
                'fecha_fin'        => now(),
                'filas_procesadas' => $leidas,
                'mensaje_log'      => "INE {$carga->tipo_flujo}: {$validas}/{$leidas} filas insertadas"
                    .($omitidas ? ", {$omitidas} omitidas (sin año: {$this->omitidas['sin_gestion']}, sin NANDINA: {$this->omitidas['sin_nandina']})" : '')
                    .($errores ? ", {$errores} con error" : '')
                    .($this->negativos ? ", {$this->negativos} con valores negativos apartados" : '').'.',
            ]);
        } catch (Throwable $e) {
            $this->revertirTransaccion();
            $this->cache = []; // los IDs de dimensiones creadas en la transacción ya no existen
            $this->registrarIncidencia('ERROR', 'Carga revertida: '.mb_substr($e->getMessage(), 0, 220), null);
            $this->guardarIncidencias($carga, array_sum($this->omitidas));
            $carga->update([
                'estado'               => 'FALLIDO',
                'total_filas_leidas'   => $leidas,
                'total_filas_validas'  => 0,
                'total_filas_error'    => $errores,
                'total_filas_omitidas' => array_sum($this->omitidas),
            ]);
            $proceso->update(['estado' => 'FALLIDO', 'fecha_fin' => now(), 'mensaje_log' => 'Error (sin cambios en los datos): '.$e->getMessage()]);
            if (! $e instanceof GestionNoCoincide) {
                report($e);
            }
        }
    }

    /** Borra (una sola vez por carga) las filas previas de esa gestión y flujo. */
    private function borrarGestionSiCorresponde(int $gestion): void
    {
        if (isset($this->borradas[$gestion])) {
            return;
        }
        $this->borradas[$gestion] = true;
        DB::table('operacion_comercio_exterior')
            ->where('organizacion_id', $this->orgId)
            ->where('flujo_id', $this->flujoId)
            ->where('gestion', $gestion)
            ->delete();
    }

    /** Acumula una incidencia en memoria (se escriben fuera de la transacción). */
    private function registrarIncidencia(string $severidad, string $descripcion, ?int $fila, ?string $valor = null): void
    {
        if (count($this->incidencias) >= self::MAX_INCIDENCIAS) {
            return;
        }
        $this->incidencias[] = [
            'severidad'       => $severidad,
            'descripcion'     => mb_substr($descripcion, 0, 255),
            'numero_fila'     => $fila,
            'valor_detectado' => $valor !== null ? mb_substr($valor, 0, 255) : null,
        ];
    }

    /** Escribe las incidencias de la carga (reemplaza las de un intento anterior). */
    private function guardarIncidencias(CargaArchivo $carga, int $omitidas): void
    {
        try {
            DB::table('incidencia_calidad')->where('carga_id', $carga->carga_id)->delete();
            if ($omitidas > 0) {
                array_unshift($this->incidencias, [
                    'severidad'       => 'ADVERTENCIA',
                    'descripcion'     => "Resumen: {$omitidas} fila(s) omitidas (sin año válido: {$this->omitidas['sin_gestion']}, sin NANDINA: {$this->omitidas['sin_nandina']}). Se detallan las primeras ".self::MAX_INCIDENCIAS.'.',
                    'numero_fila'     => null,
                    'valor_detectado' => null,
                ]);
            }
            $filas = array_map(fn ($i) => $i + ['carga_id' => $carga->carga_id, 'estado_tratamiento' => 'PENDIENTE'], $this->incidencias);
            foreach (array_chunk($filas, 500) as $chunk) {
                DB::table('incidencia_calidad')->insert($chunk);
            }
        } catch (Throwable $e) {
            report($e);
        }
    }

    /**
     * Itera las filas del archivo INE como arreglos asociativos cabecera=>valor.
     * Busca en cada hoja la fila de cabecera real (la que contiene GESTION y NANDINA),
     * de modo que ignora hojas de resumen/dinámicas y filas-título iniciales.
     */
    private function iterarIne(string $ruta): Generator
    {
        $reader = new XlsxReader();
        $reader->open($ruta);
        try {
            foreach ($reader->getSheetIterator() as $hoja) {
                $cabeceras = null;
                $intentos = 0;
                foreach ($hoja->getRowIterator() as $fila) {
                    $vals = array_map(function ($c) {
                        $v = $c->getValue();
                        return $v instanceof \DateTimeInterface ? $v->format('Y-m-d') : $v;
                    }, $fila->getCells());

                    if ($cabeceras === null) {
                        $norm = array_map(fn ($v) => $this->canonCabecera($this->n((string) $v)), $vals);
                        $tieneAnio = (bool) array_intersect(['GESTION', 'ANIO', 'ANO'], $norm);
                        $tieneProd = (bool) array_intersect(['NANDINA', 'PARTIDA'], $norm);
                        if ($tieneAnio && $tieneProd) {
                            $cabeceras = $norm;
                            $this->cabeceraHallada = true;
                            // Índice normalizado => posición (se calcula una sola vez por archivo).
                            $this->colIndex = [];
                            foreach ($norm as $i => $nn) {
                                if ($nn !== '' && ! isset($this->colIndex[$nn])) {
                                    $this->colIndex[$nn] = $i;
                                }
                            }
                            $this->posCache = [];
                        } elseif (++$intentos > 10) {
                            break; // esta hoja no tiene cabecera de datos; probar la siguiente
                        }
                        continue;
                    }

                    // Se entrega la fila indexada cruda; el acceso por nombre se hace
                    // con posiciones precomputadas (sin regex por fila).
                    yield $vals;
                }
                if ($cabeceras !== null) {
                    break; // ya procesamos la hoja de datos
                }
            }
        } finally {
            $reader->close();
        }
    }

    // -------------------------------------------------------------------------

    private function mapearFila(array $f, CargaArchivo $carga, int $numFila = 0): ?array
    {
        $rawGestion = $this->col($f, ['GESTION', 'ANIO', 'ANO']);
        // Igual que antes: solo si la celda no existe se usa la gestión del formulario.
        $gestion = (int) $this->num($rawGestion ?? $carga->gestion);
        $mes     = (int) $this->num($this->col($f, ['MES']) ?? 0);
        $nandina = $this->col($f, ['NANDINA', 'PARTIDA']);
        $sinNandina = $nandina === null || trim((string) $nandina) === '';

        if ($gestion < 1980 || $gestion > 2100) {
            // Filas totalmente vacías (fin de hoja) no cuentan como omitidas.
            if (! $sinNandina || ($rawGestion !== null && trim((string) $rawGestion) !== '')) {
                $this->omitidas['sin_gestion']++;
                $this->registrarIncidencia('ADVERTENCIA', 'Fila omitida: año (GESTION) vacío o fuera de rango 1980-2100.', $numFila + 1, (string) $rawGestion);
            }
            return null;
        }

        if ($sinNandina) {
            $this->omitidas['sin_nandina']++;
            $this->registrarIncidencia('ADVERTENCIA', 'Fila omitida: código NANDINA vacío.', $numFila + 1, "GESTION={$gestion}");
            return null;
        }

        // La gestión del formulario debe coincidir con la del archivo: evita que un
        // error de tipeo borre otro año completo.
        if ($carga->gestion && $gestion !== (int) $carga->gestion) {
            throw new GestionNoCoincide("El archivo trae filas de la gestión {$gestion} (fila ".($numFila + 1).") pero el formulario indica {$carga->gestion}. No se modificó ningún dato; corrija la gestión o deje el campo vacío.");
        }

        // Mes fuera de 1..12 (o ausente) = registro ANUAL (mes 0), igual que ALADI/MERCOSUR/FAOSTAT.
        if ($mes < 1 || $mes > 12) {
            $mes = 0;
        }

        if ($this->esExport) {
            $pesoBruto = $this->col($f, ['KILBRU']);
            $pesoNeto  = $this->col($f, ['KILNET']);
            $pesoFino  = $this->col($f, ['FINO', 'PFINO']);
            $fob       = $this->col($f, ['VALOR', 'FOB']);
            $cifFro = $cifAdu = $grav = null;
        } else {
            $pesoBruto = $this->col($f, ['KILBRU', 'KILOS']);
            $pesoNeto  = null;
            $pesoFino  = null;
            $fob       = $this->col($f, ['FOB']);
            $cifFro    = $this->col($f, ['FRO']);
            $cifAdu    = $this->col($f, ['ADU']);
            $grav      = $this->col($f, ['PAG']);
        }

        return $this->apartarNegativos([
            'organizacion_id'   => $this->orgId,
            'carga_id'          => $carga->carga_id,
            'fuente_id'         => $this->fuenteId,
            'tiempo_id'         => $this->resolverTiempo($gestion, $mes),
            'gestion'           => $gestion,
            'tipo_operacion_id' => $this->tipoOperacionId,
            'flujo_id'          => $this->flujoId,
            'producto_id'       => $this->resolverProducto($f, $nandina),
            'cuci_id'           => $this->resolverFuenteCod('clasificacion_cuci', 'codigo_cuci', 'cuci_id',
                                        $this->col($f, ['CUCI3', 'CUCIR3', 'CUCI']), $this->col($f, ['DESCUCI3', 'DESCUCI']), 'CUCI', ['revision' => 'Rev.3']),
            'gce_id'            => $this->resolverFuenteCod('categoria_economica_gce', 'codigo_gce', 'gce_id',
                                        $this->col($f, ['GCE3', 'GCER3', 'GCE']), $this->col($f, ['DESGCE3', 'DESGCE']), 'GCE', ['revision' => 'Rev.3']),
            'ciiu_id'           => $this->resolverCiiu($f),
            'pais_id'           => $this->resolverPais($f),
            'departamento_id'   => $this->resolverDepartamento($f),
            'medio_id'          => $this->resolverGlobalCod('medio_transporte', 'medio_id',
                                        $this->col($f, ['MEDI', 'MEDIO']), $this->col($f, ['DESMEDI', 'DESMED'])),
            'via_id'            => $this->resolverGlobalCod('via_comercio', 'via_id',
                                        $this->col($f, ['VIASAL', 'VIA']), $this->col($f, ['DESVIA'])),
            'tnt_id'            => $this->resolverTnt($f),
            'cuode_id'          => $this->resolverFuenteCod('clasificacion_cuode', 'codigo_cuode', 'cuode_id',
                                        $this->col($f, ['CUODE']), $this->col($f, ['DESCUO', 'DESCUODE']), 'CUODE'),
            'aduana_id'         => $this->resolverAduana($f),
            'despachante_id'    => null,
            'peso_bruto_kg'          => $this->numNull($pesoBruto),
            'peso_neto_kg'           => $this->numNull($pesoNeto),
            'peso_fino_kg'           => $this->numNull($pesoFino),
            'valor_fob_usd'          => $this->numNull($fob),
            'valor_cif_frontera_usd' => $this->numNull($cifFro),
            'valor_cif_aduana_usd'   => $this->numNull($cifAdu),
            'gravamenes_pagados'     => $this->numNull($grav),
            'atributos_extra'        => null,
        ], $numFila);
    }

    /**
     * Las bases de importación del INE traen algunos valores NEGATIVOS (sobre todo PAG,
     * gravámenes: devoluciones/ajustes). La tabla exige >= 0 (CHECK): antes la fila
     * hacía fallar su lote, se reintentaba fila por fila (un SAVEPOINT por fila) y con
     * miles de filas se agotaba la tabla de bloqueos de PostgreSQL
     * ("memoria compartida agotada", max_locks_per_transaction) y la carga se revertía.
     * Ahora el valor original se guarda en atributos_extra.negativos, la columna queda
     * NULL y se informa en Calidad (resumen por carga + primeras filas).
     */
    private function apartarNegativos(array $row, int $numFila): array
    {
        $extra = [];
        foreach (self::COLUMNAS_NO_NEGATIVAS as $col) {
            if ($row[$col] !== null && $row[$col] < 0) {
                $extra[$col] = $row[$col];
                $row[$col] = null;
            }
        }
        if ($extra) {
            $this->negativos++;
            $row['atributos_extra'] = json_encode(['negativos' => $extra]);
            if ($this->negativos <= 20) {
                $this->registrarIncidencia('ADVERTENCIA', 'Valor negativo apartado en atributos_extra (la columna exige >= 0).', $numFila + 1,
                    json_encode($extra));
            }
        }

        return $row;
    }

    // -------------------------------------------------------------------------
    //  Resolución de dimensiones (con nombre real, cache en memoria)
    // -------------------------------------------------------------------------

    private function resolverFuente(): int
    {
        $id = $this->dim()->table('fuente_datos')->where('organizacion_id', $this->orgId)
            ->where('version_nomenclatura', 'INE-base')->value('fuente_id');

        return $id ? (int) $id : (int) $this->dim()->table('fuente_datos')->insertGetId([
            'organizacion_id' => $this->orgId, 'version_nomenclatura' => 'INE-base',
            'fecha_descarga'  => now()->toDateString(),
        ], 'fuente_id');
    }

    private function resolverTipoOperacion(): int
    {
        $patron = $this->esExport ? 'Export%' : 'Import%';
        $id = $this->dim()->table('tipo_operacion')->where('nombre', 'ILIKE', $patron)->orderBy('tipo_operacion_id')->value('tipo_operacion_id');
        if ($id) { return (int) $id; }

        return (int) $this->dim()->table('tipo_operacion')->insertGetId([
            'nombre' => $this->esExport ? 'Exportación' : 'Importación',
            'base_valoracion' => $this->esExport ? 'FOB' : 'CIF',
        ], 'tipo_operacion_id');
    }

    private function resolverFlujo(): int
    {
        $codigo = $this->esExport ? '1' : '2';
        $id = $this->dim()->table('flujo_comercial')->where('codigo_flujo', $codigo)->value('flujo_id');
        if ($id) { return (int) $id; }

        return (int) $this->dim()->table('flujo_comercial')->insertGetId([
            'codigo_flujo' => $codigo, 'descripcion' => $this->esExport ? 'Exportación' : 'Importación',
        ], 'flujo_id');
    }

    private function resolverTiempo(int $gestion, int $mes): int
    {
        $m = ($mes >= 1 && $mes <= 12) ? $mes : 0; // 0 = anual (sin mes)
        return $this->cache["t:$gestion:$m"] ??= (function () use ($gestion, $m) {
            $id = $this->dim()->table('tiempo')->where('gestion', $gestion)->where('mes', $m)->value('tiempo_id');
            if ($id) { return (int) $id; }
            $meses = [0 => 'Anual', 1 => 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
            return (int) $this->dim()->table('tiempo')->insertGetId([
                'gestion' => $gestion, 'mes' => $m, 'nombre_mes' => $meses[$m],
                'trimestre' => (int) ceil($m / 3), 'semestre' => (int) ceil($m / 6),
                'fecha_inicio_mes' => Carbon::createFromDate($gestion, max(1, $m), 1)->toDateString(),
            ], 'tiempo_id');
        })();
    }

    private function resolverProducto(array $f, $nandina): int
    {
        $nan = (int) $this->num($nandina);
        $capCod = (int) $this->num($this->col($f, ['CAP']) ?? substr(str_pad((string) $nan, 10, '0', STR_PAD_LEFT), 0, 2));
        $secCod = (int) $this->num($this->col($f, ['SECC']) ?? 0);
        $seccionId = $this->resolverSeccion($secCod, $this->col($f, ['DESSEC']));
        $capituloId = $this->resolverCapitulo($capCod, $this->col($f, ['DESCAP']), $seccionId);
        $desc = trim((string) ($this->col($f, ['DESNAN', 'DESCRIPCION', 'GLOSA']) ?? ''));

        return $this->cache["prod:$capituloId:$nan"] ??= (function () use ($capituloId, $nan, $desc) {
            $id = $this->dim()->table('producto')->where('capitulo_id', $capituloId)->where('codigo_nandina', $nan)->value('producto_id');
            return $id ? (int) $id : (int) $this->dim()->table('producto')->insertGetId([
                'capitulo_id' => $capituloId, 'codigo_nandina' => $nan,
                'descripcion' => $desc !== '' ? $desc : "NANDINA $nan", 'vigente' => true,
            ], 'producto_id');
        })();
    }

    private function resolverSeccion(int $cod, $desc): int
    {
        return $this->cache["sec:$cod"] ??= (function () use ($cod, $desc) {
            $id = $this->dim()->table('seccion_arancelaria')->where('fuente_id', $this->fuenteId)->where('codigo_seccion', $cod)->value('seccion_id');
            return $id ? (int) $id : (int) $this->dim()->table('seccion_arancelaria')->insertGetId([
                'fuente_id' => $this->fuenteId, 'codigo_seccion' => $cod,
                'descripcion' => $this->limpia($desc) ?: ($cod === 0 ? 'Sin sección' : "Sección $cod"),
            ], 'seccion_id');
        })();
    }

    private function resolverCapitulo(int $cod, $desc, int $seccionId): int
    {
        return $this->cache["cap:$seccionId:$cod"] ??= (function () use ($cod, $desc, $seccionId) {
            $id = $this->dim()->table('capitulo_arancelario')->where('seccion_id', $seccionId)->where('codigo_capitulo', $cod)->value('capitulo_id');
            return $id ? (int) $id : (int) $this->dim()->table('capitulo_arancelario')->insertGetId([
                'seccion_id' => $seccionId, 'codigo_capitulo' => $cod,
                'descripcion' => $this->limpia($desc) ?: "Capítulo $cod",
            ], 'capitulo_id');
        })();
    }

    private function resolverPais(array $f): int
    {
        $cod = (int) $this->num($this->col($f, ['PAIS', 'CODPAIS']) ?? 0);
        $nombre = $this->limpia($this->col($f, ['DESPAIS', 'DESPAI']));
        $zonaId = $this->resolverZona($f);

        return $this->cache["pais:$cod"] ??= (function () use ($cod, $nombre, $zonaId) {
            $id = $this->dim()->table('pais')->where('fuente_id', $this->fuenteId)->where('codigo_pais', $cod)->value('pais_id');
            $id = $id ? (int) $id : (int) $this->dim()->table('pais')->insertGetId([
                'fuente_id' => $this->fuenteId, 'zona_id' => $zonaId, 'codigo_pais' => $cod,
                'nombre' => $nombre ?: "País $cod",
            ], 'pais_id');
            // ISO 3166: el trigger trg_pais_iso ya vinculó la fila; si no se reconoció, avisar en Calidad.
            $iso = $this->dim()->table('pais')->where('pais_id', $id)->value('iso_metodo');
            if ($iso === 'SIN_MATCH') {
                $this->registrarIncidencia('ADVERTENCIA', app(ResolverPaisIso::class)->textoIncidencia($cod, $nombre), null, "$cod | $nombre");
            }

            return $id;
        })();
    }

    private function resolverZona(array $f): int
    {
        $cod = $this->col($f, ['AREA']);
        $desc = $this->limpia($this->col($f, ['DESAREA', 'DESZON', 'DESZONA']));
        if ($cod !== null && $cod !== '') {
            $c = (int) $this->num($cod);
            return $this->cache["zona:c:$c"] ??= (function () use ($c, $desc) {
                $id = $this->dim()->table('zona_geoeconomica')->where('fuente_id', $this->fuenteId)->where('codigo_zona', $c)->value('zona_id');
                return $id ? (int) $id : (int) $this->dim()->table('zona_geoeconomica')->insertGetId([
                    'fuente_id' => $this->fuenteId, 'codigo_zona' => $c,
                    'descripcion' => $desc ?: ($c === 0 ? 'Sin zona' : "Zona $c"),
                ], 'zona_id');
            })();
        }
        // Importaciones: sólo descripción de zona, sin código.
        $clave = $desc !== '' ? mb_strtolower($desc) : 'sinzona';
        return $this->cache["zona:d:$clave"] ??= (function () use ($desc) {
            if ($desc !== '') {
                $id = $this->dim()->table('zona_geoeconomica')->where('fuente_id', $this->fuenteId)->whereRaw('LOWER(descripcion)=?', [mb_strtolower($desc)])->value('zona_id');
            } else {
                // Sin zona: reutilizar la zona 0 si ya existe (antes se intentaba crearla
                // de nuevo en cada carga y chocaba con uq_zona).
                $id = $this->dim()->table('zona_geoeconomica')->where('fuente_id', $this->fuenteId)->where('codigo_zona', 0)->value('zona_id');
            }
            if ($id) { return (int) $id; }
            $next = (int) ($this->dim()->table('zona_geoeconomica')->where('fuente_id', $this->fuenteId)->max('codigo_zona') ?? 0) + 1;
            return (int) $this->dim()->table('zona_geoeconomica')->insertGetId([
                'fuente_id' => $this->fuenteId, 'codigo_zona' => $desc !== '' ? $next : 0,
                'descripcion' => $desc ?: 'Sin zona',
            ], 'zona_id');
        })();
    }

    private function resolverDepartamento(array $f): int
    {
        $cod = (int) $this->num($this->col($f, ['DEPART', 'DEPTO', 'DEPARTAMENTO']) ?? 0);
        $nombre = $this->limpia($this->col($f, ['DESDEP', 'DESDEPTO']));

        return $this->cache["dep:$cod"] ??= (function () use ($cod, $nombre) {
            $id = $this->dim()->table('departamento')->where('fuente_id', $this->fuenteId)->where('codigo', $cod)->value('departamento_id');
            return $id ? (int) $id : (int) $this->dim()->table('departamento')->insertGetId([
                'fuente_id' => $this->fuenteId, 'codigo' => $cod, 'nombre' => $nombre ?: "Departamento $cod",
            ], 'departamento_id');
        })();
    }

    private function resolverCiiu(array $f): int
    {
        $cod = $this->limpiaCod($this->col($f, ['CIIUR3', 'CIIU'])) ?: '0';
        $desc = $this->limpia($this->col($f, ['DESCIIU3', 'DESCIIU']));
        $grupoCod = substr($cod, 0, 3) ?: '0';
        $grupoId = $this->cache["grp:$grupoCod"] ??= (function () use ($grupoCod) {
            $id = $this->dim()->table('grupo_actividad')->where('fuente_id', $this->fuenteId)->where('codigo', $grupoCod)->value('grupo_actividad_id');
            return $id ? (int) $id : (int) $this->dim()->table('grupo_actividad')->insertGetId([
                'fuente_id' => $this->fuenteId, 'codigo' => $grupoCod, 'descripcion' => "Grupo $grupoCod",
            ], 'grupo_actividad_id');
        })();

        return $this->cache["ciiu:$grupoId:$cod"] ??= (function () use ($grupoId, $cod, $desc) {
            $id = $this->dim()->table('actividad_ciiu')->where('grupo_actividad_id', $grupoId)->where('codigo_ciiu', $cod)->where('revision', 'Rev.3')->value('ciiu_id');
            return $id ? (int) $id : (int) $this->dim()->table('actividad_ciiu')->insertGetId([
                'grupo_actividad_id' => $grupoId, 'codigo_ciiu' => $cod,
                'descripcion' => $desc ?: "CIIU $cod", 'revision' => 'Rev.3',
            ], 'ciiu_id');
        })();
    }

    private function resolverTnt(array $f): ?int
    {
        $cod = $this->col($f, ['TNT']);
        if ($cod === null || $cod === '') { return null; }
        $c = (int) $this->num($cod);
        $desc = $this->limpia($this->col($f, ['DESTNT']));
        $clase = $this->limpia($this->col($f, ['CLTNT'])) ?: 'Sin clasificar';

        return $this->cache["tnt:$c"] ??= (function () use ($c, $desc, $clase) {
            $id = $this->dim()->table('clasificacion_tnt')->where('codigo_tnt', $c)->value('tnt_id');
            return $id ? (int) $id : (int) $this->dim()->table('clasificacion_tnt')->insertGetId([
                'codigo_tnt' => $c, 'descripcion' => $desc ?: "TNT $c", 'clase' => $clase,
            ], 'tnt_id');
        })();
    }

    private function resolverAduana(array $f): ?int
    {
        $cod = $this->col($f, ['ADUANA', 'ADUDES']);
        if ($cod === null || $cod === '') { return null; }
        $c = (int) $this->num($cod);
        $desc = $this->limpia($this->col($f, ['DESADU']));

        return $this->cache["adu:$c"] ??= (function () use ($c, $desc) {
            $id = $this->dim()->table('aduana')->where('fuente_id', $this->fuenteId)->where('codigo', $c)->value('aduana_id');
            return $id ? (int) $id : (int) $this->dim()->table('aduana')->insertGetId([
                'fuente_id' => $this->fuenteId, 'codigo' => $c, 'descripcion' => $desc ?: "Aduana $c",
            ], 'aduana_id');
        })();
    }

    /** Resolver dimensión global (no fuente-scoped) por código entero + descripción. */
    private function resolverGlobalCod(string $tabla, string $pk, $cod, $desc): int
    {
        $c = (int) $this->num($cod ?? 0);
        return $this->cache["$tabla:$c"] ??= (function () use ($tabla, $pk, $c, $desc) {
            $id = $this->dim()->table($tabla)->where('codigo', $c)->value($pk);
            return $id ? (int) $id : (int) $this->dim()->table($tabla)->insertGetId([
                'codigo' => $c, 'descripcion' => $this->limpia($desc) ?: ucfirst($tabla)." $c",
            ], $pk);
        })();
    }

    /** Resolver dimensión fuente-scoped por código texto + descripción. */
    private function resolverFuenteCod(string $tabla, string $colCod, string $pk, $cod, $desc, string $etq, array $extra = []): int
    {
        $c = $this->limpiaCod($cod) ?: '0';
        return $this->cache["$tabla:$c"] ??= (function () use ($tabla, $colCod, $pk, $c, $desc, $etq, $extra) {
            $q = $this->dim()->table($tabla)->where('fuente_id', $this->fuenteId)->where($colCod, $c);
            foreach ($extra as $k => $v) { $q->where($k, $v); }
            $id = $q->value($pk);
            if ($id) { return (int) $id; }
            return (int) $this->dim()->table($tabla)->insertGetId(array_merge([
                'fuente_id' => $this->fuenteId, $colCod => $c, 'descripcion' => $this->limpia($desc) ?: "$etq $c",
            ], $extra), $pk);
        })();
    }

    // -------------------------------------------------------------------------
    //  Utilidades
    // -------------------------------------------------------------------------

    /**
     * Devuelve el valor de la primera columna (sinónimo) presente, usando posiciones
     * precomputadas. La resolución de posición se memoiza por conjunto de sinónimos,
     * así que el regex de normalización corre una sola vez por archivo, no por fila.
     */
    private function col(array $vals, array $claves)
    {
        $k = $claves[0].(count($claves) > 1 ? '|'.implode('|', $claves) : '');
        if (! array_key_exists($k, $this->posCache)) {
            $pos = null;
            foreach ($claves as $clave) {
                $nn = $this->n($clave);
                if (isset($this->colIndex[$nn])) { $pos = $this->colIndex[$nn]; break; }
            }
            $this->posCache[$k] = $pos;
        }
        $p = $this->posCache[$k];
        return $p === null ? null : ($vals[$p] ?? null);
    }

    /**
     * Sinónimos de cabecera: varios Excel del INE publicados en el Drive (exportaciones
     * 1992-2021, entre otros) traen cabeceras DESCRIPTIVAS largas ("GESTIÓN DE REFERENCIA
     * EN LA QUE SE REALIZA...", "CODIGO ARANCELARIO NANDINA (...)", "VALOR FOB (dólares)")
     * en lugar de los nombres cortos del diccionario (GESTION, NANDINA, VALOR). Se traducen
     * al nombre corto ANTES de buscar la fila de cabecera; las cabeceras cortas no cambian.
     * Patrones sobre la cabecera ya normalizada por n() (sin tildes, espacios ni símbolos).
     */
    /** Columnas con CHECK (>= 0) en operacion_comercio_exterior. */
    private const COLUMNAS_NO_NEGATIVAS = ['peso_bruto_kg', 'peso_neto_kg', 'peso_fino_kg', 'valor_fob_usd',
        'valor_cif_frontera_usd', 'valor_cif_aduana_usd', 'gravamenes_pagados'];

    /** Valores negativos apartados en esta carga (ver apartarNegativos). */
    private int $negativos = 0;

    private const CABECERAS_LARGAS = [
        '/^GESTION(DE)?REFERENCIA/'                      => 'GESTION',
        '/^MES(DE)?REFERENCIA/'                          => 'MES',
        '/^FLUJOQUEDESCRIBE/'                            => 'FLUJO',
        '/^CODIGOARANCELARIONANDINA/'                    => 'NANDINA',
        '/^DESCRIPCIONDENANDINA/'                        => 'DESNAN',
        '/^CAPITULODENANDINA/'                           => 'CAP',
        '/^DESCRI(P)?CIONDELCAPITULO/'                   => 'DESCAP',
        '/^SECCIONDENANDINA/'                            => 'SECC',
        '/^DESCRIPCIONSECCION/'                          => 'DESSEC',
        '/^CODIGOPAIS/'                                  => 'PAIS',
        '/^DESCRIPCIONCODIGOPAIS/'                       => 'DESPAIS',
        '/^CODIGODEZONA/'                                => 'AREA',
        '/^DESCRIPCIONDEZONA/'                           => 'DESAREA',
        '/^OTRASZONAS/'                                  => 'OTROS',
        '/^TRATADODECOMERCIO/'                           => 'TCP',
        '/^CODIGOMEDIO/'                                 => 'MEDI',
        '/^DESCRIPCIONCODI[DG]OMEDIO/'                   => 'DESMEDI',
        '/^CODIGOVIA/'                                   => 'VIA',
        '/^DESCRIPCIONCODIGOVIA/'                        => 'DESVIA',
        '/^CODIGODEP(AR)?TAMENTO/'                       => 'DEPART',
        '/^DESCRIPCIONCODIGODEP/'                        => 'DESDEP',
        '/^CODIGOSUBGRUPOCUCI/'                          => 'CUCI3',
        '/^DESCRIPCIONDECUCI/'                           => 'DESCUCI3',
        '/^CODIGOGRANDESCATEGORIAS/'                     => 'GCE3',
        '/^DESCRIPCIONDEGCE/'                            => 'DESGCE3',
        '/^CODIGOCIIU/'                                  => 'CIIUR3',
        '/^DESCRIPCIONDECIIU/'                           => 'DESCIIU3',
        '/^CLASIFICACIONPORACTIVIDAD/'                   => 'CLACT',
        '/^CODIGODELPRODUCTODELAACTIVIDAD/'              => 'CODACT',
        '/^DESCRIPCIONCODIGODELPRODUCTODELAACTIVIDAD/'   => 'DESACT',
        '/^CODIGODELPRODUCTODEL+A?CLASIFICACIONTRADICIONAL/' => 'TNT',
        '/^DESCRIPCIONCODIGODELPRODUCTOTNT/'             => 'DESTNT',
        '/^CLASIFICACIONDEPRODUCTOSTNT/'                 => 'CLTNT',
        '/^PESOBRUTO/'                                   => 'KILBRU',
        '/^PESONETO/'                                    => 'KILNET',
        '/^PESOFINO/'                                    => 'FINO',
        '/^VALORFOB/'                                    => 'FOB',
        '/^ADUANADE(DESTINO|SALIDA|INGRESO)/'            => 'ADUANA',
        '/^DESPACHANTE/'                                 => 'DESPACHANTE',
    ];

    private function canonCabecera(string $norm): string
    {
        // Los patrones son frases largas: ningún nombre corto del diccionario coincide.
        foreach (self::CABECERAS_LARGAS as $patron => $corto) {
            if (preg_match($patron, $norm)) {
                return $corto;
            }
        }

        return $norm;
    }

    /** Normaliza una cabecera: sin tildes/ñ, sin espacios ni símbolos, en mayúsculas (GESTIÓN -> GESTION, AÑO -> ANO). */
    private function n(string $s): string
    {
        return strtoupper(preg_replace('/[^a-z0-9]/i', '', \Illuminate\Support\Str::ascii($s)));
    }

    /**
     * Conexión aparte (autocommit) para crear filas de dimensiones (país, producto,
     * tiempo...). Así la transacción de la carga solo contiene los hechos y dos
     * cargas en paralelo no se bloquean ni chocan al crear el mismo país.
     */
    private function dim(): \Illuminate\Database\ConnectionInterface
    {
        $nombre = 'geodata_dimensiones';
        if (! config("database.connections.{$nombre}")) {
            config(["database.connections.{$nombre}" => config('database.connections.'.config('database.default'))]);
        }

        return DB::connection($nombre);
    }

    private function limpia($v): string
    {
        $s = trim((string) ($v ?? ''));
        return mb_substr($s, 0, 250);
    }

    /** Código textual: recorta espacios, limita longitud (para CUCI/GCE/CIIU). */
    private function limpiaCod($v): string
    {
        return substr(trim((string) ($v ?? '')), 0, 5);
    }

    private function num($v): float
    {
        if ($v === null || $v === '') { return 0.0; }
        if (is_numeric($v)) { return (float) $v; }
        $s = trim((string) $v);
        $pc = strrpos($s, ','); $pp = strrpos($s, '.');
        if ($pc !== false && $pc > ($pp === false ? -1 : $pp)) {
            $s = str_replace('.', '', $s); $s = str_replace(',', '.', $s);
        } else {
            $s = str_replace(',', '', $s);
        }
        $s = preg_replace('/[^0-9.\-]/', '', $s);
        return is_numeric($s) ? (float) $s : 0.0;
    }

    private function numNull($v): ?float
    {
        return ($v === null || $v === '') ? null : $this->num($v);
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
}
