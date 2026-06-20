<?php

namespace App\Servicios;

use App\Models\CargaArchivo;
use Carbon\Carbon;
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
class CargadorIne
{
    private const ORG_ID = 1;
    private const TAM_LOTE = 2000;

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

        try {
            $ruta = $rutaDirecta && file_exists($rutaDirecta) ? $rutaDirecta : $this->resolverRuta($carga->carga_id);
            $ext  = pathinfo($ruta, PATHINFO_EXTENSION);
            $carga->update(['estado' => 'PROCESANDO']);

            $this->esExport        = $carga->tipo_flujo === 'EXPORTACION';
            $this->colIndex        = [];
            $this->posCache        = [];
            $this->fuenteId        = $this->resolverFuente();
            $this->tipoOperacionId = $this->resolverTipoOperacion();
            $this->flujoId         = $this->resolverFlujo();
            $carga->update(['fuente_id' => $this->fuenteId]);

            // Idempotencia por (gestión, flujo): re-cargar un año reemplaza sus filas
            // sin duplicar, aunque provenga de otra carga. Permite reintentos seguros.
            if ($carga->gestion) {
                DB::table('operacion_comercio_exterior')
                    ->where('organizacion_id', self::ORG_ID)
                    ->where('flujo_id', $this->flujoId)
                    ->whereIn('tiempo_id', function ($q) use ($carga) {
                        $q->select('tiempo_id')->from('tiempo')->where('gestion', $carga->gestion);
                    })
                    ->delete();
            } else {
                DB::table('operacion_comercio_exterior')->where('carga_id', $carga->carga_id)->delete();
            }

            $lote = [];
            $leidas = 0; $validas = 0; $errores = 0;

            foreach ($this->iterarIne($ruta) as $fila) {
                $leidas++;
                try {
                    $row = $this->mapearFila($fila, $carga);
                    if ($row === null) { continue; }
                    $lote[] = $row;
                    $validas++;
                    if (count($lote) >= self::TAM_LOTE) {
                        DB::table('operacion_comercio_exterior')->insert($lote);
                        $lote = [];
                    }
                } catch (Throwable $e) {
                    $errores++;
                }

                if ($leidas % 20000 === 0) {
                    $proceso->update(['filas_procesadas' => $leidas]);
                    $carga->update(['total_filas_leidas' => $leidas, 'total_filas_validas' => $validas, 'total_filas_error' => $errores]);
                }
            }

            if (! empty($lote)) {
                DB::table('operacion_comercio_exterior')->insert($lote);
            }

            $carga->update([
                'total_filas_leidas'  => $leidas,
                'total_filas_validas' => $validas,
                'total_filas_error'   => $errores,
                'estado'              => 'COMPLETADO',
            ]);
            $proceso->update([
                'estado'           => 'EXITOSO',
                'fecha_fin'        => now(),
                'filas_procesadas' => $leidas,
                'mensaje_log'      => "INE {$carga->tipo_flujo}: {$validas}/{$leidas} filas insertadas.",
            ]);
        } catch (Throwable $e) {
            $carga->update(['estado' => 'FALLIDO']);
            $proceso->update(['estado' => 'FALLIDO', 'fecha_fin' => now(), 'mensaje_log' => 'Error: '.$e->getMessage()]);
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
                        $norm = array_map(fn ($v) => $this->n((string) $v), $vals);
                        if (in_array('GESTION', $norm, true) && in_array('NANDINA', $norm, true)) {
                            $cabeceras = $norm;
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

    private function mapearFila(array $f, CargaArchivo $carga): ?array
    {
        $gestion = (int) $this->num($this->col($f, ['GESTION', 'ANIO', 'ANO']) ?? $carga->gestion);
        $mes     = (int) $this->num($this->col($f, ['MES']) ?? 0);
        if ($gestion < 1980 || $gestion > 2100) {
            return null; // fila de cabecera/encabezado extra o vacía
        }

        $nandina = $this->col($f, ['NANDINA', 'PARTIDA']);
        if ($nandina === null || trim((string) $nandina) === '') {
            return null;
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

        return [
            'organizacion_id'   => self::ORG_ID,
            'carga_id'          => $carga->carga_id,
            'fuente_id'         => $this->fuenteId,
            'tiempo_id'         => $this->resolverTiempo($gestion, $mes),
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
        ];
    }

    // -------------------------------------------------------------------------
    //  Resolución de dimensiones (con nombre real, cache en memoria)
    // -------------------------------------------------------------------------

    private function resolverFuente(): int
    {
        $id = DB::table('fuente_datos')->where('organizacion_id', self::ORG_ID)
            ->where('version_nomenclatura', 'INE-base')->value('fuente_id');

        return $id ? (int) $id : (int) DB::table('fuente_datos')->insertGetId([
            'organizacion_id' => self::ORG_ID, 'version_nomenclatura' => 'INE-base',
            'fecha_descarga'  => now()->toDateString(),
        ], 'fuente_id');
    }

    private function resolverTipoOperacion(): int
    {
        $patron = $this->esExport ? 'Export%' : 'Import%';
        $id = DB::table('tipo_operacion')->where('nombre', 'ILIKE', $patron)->orderBy('tipo_operacion_id')->value('tipo_operacion_id');
        if ($id) { return (int) $id; }

        return (int) DB::table('tipo_operacion')->insertGetId([
            'nombre' => $this->esExport ? 'Exportación' : 'Importación',
            'base_valoracion' => $this->esExport ? 'FOB' : 'CIF',
        ], 'tipo_operacion_id');
    }

    private function resolverFlujo(): int
    {
        $codigo = $this->esExport ? '1' : '2';
        $id = DB::table('flujo_comercial')->where('codigo_flujo', $codigo)->value('flujo_id');
        if ($id) { return (int) $id; }

        return (int) DB::table('flujo_comercial')->insertGetId([
            'codigo_flujo' => $codigo, 'descripcion' => $this->esExport ? 'Exportación' : 'Importación',
        ], 'flujo_id');
    }

    private function resolverTiempo(int $gestion, int $mes): int
    {
        $m = max(1, min(12, $mes));
        return $this->cache["t:$gestion:$m"] ??= (function () use ($gestion, $m) {
            $id = DB::table('tiempo')->where('gestion', $gestion)->where('mes', $m)->value('tiempo_id');
            if ($id) { return (int) $id; }
            $meses = [1 => 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
            return (int) DB::table('tiempo')->insertGetId([
                'gestion' => $gestion, 'mes' => $m, 'nombre_mes' => $meses[$m],
                'trimestre' => (int) ceil($m / 3), 'semestre' => (int) ceil($m / 6),
                'fecha_inicio_mes' => Carbon::createFromDate($gestion, $m, 1)->toDateString(),
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
            $id = DB::table('producto')->where('capitulo_id', $capituloId)->where('codigo_nandina', $nan)->value('producto_id');
            return $id ? (int) $id : (int) DB::table('producto')->insertGetId([
                'capitulo_id' => $capituloId, 'codigo_nandina' => $nan,
                'descripcion' => $desc !== '' ? $desc : "NANDINA $nan", 'vigente' => true,
            ], 'producto_id');
        })();
    }

    private function resolverSeccion(int $cod, $desc): int
    {
        return $this->cache["sec:$cod"] ??= (function () use ($cod, $desc) {
            $id = DB::table('seccion_arancelaria')->where('fuente_id', $this->fuenteId)->where('codigo_seccion', $cod)->value('seccion_id');
            return $id ? (int) $id : (int) DB::table('seccion_arancelaria')->insertGetId([
                'fuente_id' => $this->fuenteId, 'codigo_seccion' => $cod,
                'descripcion' => $this->limpia($desc) ?: ($cod === 0 ? 'Sin sección' : "Sección $cod"),
            ], 'seccion_id');
        })();
    }

    private function resolverCapitulo(int $cod, $desc, int $seccionId): int
    {
        return $this->cache["cap:$seccionId:$cod"] ??= (function () use ($cod, $desc, $seccionId) {
            $id = DB::table('capitulo_arancelario')->where('seccion_id', $seccionId)->where('codigo_capitulo', $cod)->value('capitulo_id');
            return $id ? (int) $id : (int) DB::table('capitulo_arancelario')->insertGetId([
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
            $id = DB::table('pais')->where('fuente_id', $this->fuenteId)->where('codigo_pais', $cod)->value('pais_id');
            return $id ? (int) $id : (int) DB::table('pais')->insertGetId([
                'fuente_id' => $this->fuenteId, 'zona_id' => $zonaId, 'codigo_pais' => $cod,
                'nombre' => $nombre ?: "País $cod",
            ], 'pais_id');
        })();
    }

    private function resolverZona(array $f): int
    {
        $cod = $this->col($f, ['AREA']);
        $desc = $this->limpia($this->col($f, ['DESAREA', 'DESZON', 'DESZONA']));
        if ($cod !== null && $cod !== '') {
            $c = (int) $this->num($cod);
            return $this->cache["zona:c:$c"] ??= (function () use ($c, $desc) {
                $id = DB::table('zona_geoeconomica')->where('fuente_id', $this->fuenteId)->where('codigo_zona', $c)->value('zona_id');
                return $id ? (int) $id : (int) DB::table('zona_geoeconomica')->insertGetId([
                    'fuente_id' => $this->fuenteId, 'codigo_zona' => $c,
                    'descripcion' => $desc ?: ($c === 0 ? 'Sin zona' : "Zona $c"),
                ], 'zona_id');
            })();
        }
        // Importaciones: sólo descripción de zona, sin código.
        $clave = $desc !== '' ? mb_strtolower($desc) : 'sinzona';
        return $this->cache["zona:d:$clave"] ??= (function () use ($desc) {
            if ($desc !== '') {
                $id = DB::table('zona_geoeconomica')->where('fuente_id', $this->fuenteId)->whereRaw('LOWER(descripcion)=?', [mb_strtolower($desc)])->value('zona_id');
                if ($id) { return (int) $id; }
            }
            $next = (int) (DB::table('zona_geoeconomica')->where('fuente_id', $this->fuenteId)->max('codigo_zona') ?? 0) + 1;
            return (int) DB::table('zona_geoeconomica')->insertGetId([
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
            $id = DB::table('departamento')->where('fuente_id', $this->fuenteId)->where('codigo', $cod)->value('departamento_id');
            return $id ? (int) $id : (int) DB::table('departamento')->insertGetId([
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
            $id = DB::table('grupo_actividad')->where('fuente_id', $this->fuenteId)->where('codigo', $grupoCod)->value('grupo_actividad_id');
            return $id ? (int) $id : (int) DB::table('grupo_actividad')->insertGetId([
                'fuente_id' => $this->fuenteId, 'codigo' => $grupoCod, 'descripcion' => "Grupo $grupoCod",
            ], 'grupo_actividad_id');
        })();

        return $this->cache["ciiu:$grupoId:$cod"] ??= (function () use ($grupoId, $cod, $desc) {
            $id = DB::table('actividad_ciiu')->where('grupo_actividad_id', $grupoId)->where('codigo_ciiu', $cod)->where('revision', 'Rev.3')->value('ciiu_id');
            return $id ? (int) $id : (int) DB::table('actividad_ciiu')->insertGetId([
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
            $id = DB::table('clasificacion_tnt')->where('codigo_tnt', $c)->value('tnt_id');
            return $id ? (int) $id : (int) DB::table('clasificacion_tnt')->insertGetId([
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
            $id = DB::table('aduana')->where('fuente_id', $this->fuenteId)->where('codigo', $c)->value('aduana_id');
            return $id ? (int) $id : (int) DB::table('aduana')->insertGetId([
                'fuente_id' => $this->fuenteId, 'codigo' => $c, 'descripcion' => $desc ?: "Aduana $c",
            ], 'aduana_id');
        })();
    }

    /** Resolver dimensión global (no fuente-scoped) por código entero + descripción. */
    private function resolverGlobalCod(string $tabla, string $pk, $cod, $desc): int
    {
        $c = (int) $this->num($cod ?? 0);
        return $this->cache["$tabla:$c"] ??= (function () use ($tabla, $pk, $c, $desc) {
            $id = DB::table($tabla)->where('codigo', $c)->value($pk);
            return $id ? (int) $id : (int) DB::table($tabla)->insertGetId([
                'codigo' => $c, 'descripcion' => $this->limpia($desc) ?: ucfirst($tabla)." $c",
            ], $pk);
        })();
    }

    /** Resolver dimensión fuente-scoped por código texto + descripción. */
    private function resolverFuenteCod(string $tabla, string $colCod, string $pk, $cod, $desc, string $etq, array $extra = []): int
    {
        $c = $this->limpiaCod($cod) ?: '0';
        return $this->cache["$tabla:$c"] ??= (function () use ($tabla, $colCod, $pk, $c, $desc, $etq, $extra) {
            $q = DB::table($tabla)->where('fuente_id', $this->fuenteId)->where($colCod, $c);
            foreach ($extra as $k => $v) { $q->where($k, $v); }
            $id = $q->value($pk);
            if ($id) { return (int) $id; }
            return (int) DB::table($tabla)->insertGetId(array_merge([
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

    private function n(string $s): string
    {
        return strtoupper(preg_replace('/[^a-z0-9]/i', '', $s));
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
