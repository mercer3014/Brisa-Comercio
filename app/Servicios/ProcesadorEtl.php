<?php

namespace App\Servicios;

use App\Models\CargaArchivo;
use App\Models\ReglaValidacion;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Servicio ETL: lee el archivo de una carga en streaming por lotes, aplica el
 * perfil de mapeo, resuelve dimensiones con "buscar o crear" (con cache en
 * memoria), válida con regla_validacion e inserta el microdato por lotes.
 * Idempotente: reprocesar una carga no duplica registros.
 */
class ProcesadorEtl
{
    private int $tamLote = 1000;

    /** Cache de dimensiones: clave string => id. */
    private array $cache = [];

    private int $fuenteId;
    private int $organizacionId;
    private array $reglas = [];

    private array $lote = [];
    private int $leidas = 0;
    private int $validas = 0;
    private int $conError = 0;

    public function __construct(private LectorArchivo $lector)
    {
    }

    public function procesar(CargaArchivo $carga): void
    {
        $this->tamLote = (int) (\App\Models\Configuracion::obtener('lote_etl_filas', 1000) ?: 1000);
        $this->organizacionId = $carga->organizacion_id;
        $this->cache = [];
        $this->lote = [];
        $this->leidas = $this->validas = $this->conError = 0;

        // proceso_etl: marca EN_EJECUCION
        $proceso = $carga->procesos()->create([
            'estado'       => 'EN_EJECUCION',
            'fecha_inicio' => now(),
        ]);

        try {
            // 1) Cargar mapeo resuelto (Tarea 5).
            $rutaMapeo = "cargas/{$carga->carga_id}/mapeo.json";
            if (! Storage::disk('local')->exists($rutaMapeo)) {
                throw new \RuntimeException("No existe el mapeo de la carga #{$carga->carga_id}.");
            }
            $mapeo = json_decode(Storage::disk('local')->get($rutaMapeo), true);
            $extension = $mapeo['extension'] ?? 'csv';
            $columnas = $mapeo['columnas'] ?? [];

            $rutaDatos = "cargas/{$carga->carga_id}/datos.{$extension}";
            if (! Storage::disk('local')->exists($rutaDatos)) {
                throw new \RuntimeException("No existe el archivo de datos de la carga #{$carga->carga_id}.");
            }
            $rutaAbs = Storage::disk('local')->path($rutaDatos);

            // 2) Resolver/crear la fuente_datos de la organización (todas las dimensiones se asocian a ella).
            $this->fuenteId = $this->resolverFuente($carga);
            $carga->update(['fuente_id' => $this->fuenteId, 'estado' => 'PROCESANDO']);

            // 3) Idempotencia: borrar hechos e incidencias previas de esta carga.
            DB::table('operacion_comercio_exterior')->where('carga_id', $carga->carga_id)->delete();
            DB::table('incidencia_calidad')->where('carga_id', $carga->carga_id)->delete();

            // 4) Reglas activas.
            $this->reglas = ReglaValidacion::where('activa', true)->get()->all();

            // 5) Iterar el archivo por filas (streaming).
            foreach ($this->lector->iterarAsociativo($rutaAbs, $extension) as $numeroFila => $filaCruda) {
                $this->leidas++;
                [$canonico, $extra] = $this->traducirFila($filaCruda, $columnas);

                // Completar gestión/mes desde la carga si faltan en la fila.
                $canonico['gestion'] = $canonico['gestion'] ?? $carga->gestion;
                $canonico['mes'] = $canonico['mes'] ?? $carga->mes;

                // Validaciones.
                $incidencias = $this->validar($canonico, $numeroFila);
                $hayError = collect($incidencias)->contains(fn ($i) => $i['severidad'] === 'ERROR');

                if (! empty($incidencias)) {
                    foreach ($incidencias as $inc) {
                        DB::table('incidencia_calidad')->insert([
                            'carga_id'           => $carga->carga_id,
                            'regla_id'           => $inc['regla_id'],
                            'descripcion'        => $inc['descripcion'],
                            'severidad'          => $inc['severidad'],
                            'numero_fila'        => $numeroFila,
                            'valor_detectado'    => $inc['valor'],
                            'estado_tratamiento' => 'PENDIENTE',
                            'fecha_deteccion'    => now(),
                        ]);
                    }
                }

                if ($hayError) {
                    $this->conError++;
                    continue; // las filas con ERROR no se insertan
                }

                // Resolver dimensiones e insertar (acumular en lote).
                try {
                    $fila = $this->construirHecho($carga, $canonico, $extra);
                    $this->lote[] = $fila;
                    $this->validas++;

                    if (count($this->lote) >= $this->tamLote) {
                        $this->vaciarLote();
                    }
                } catch (Throwable $e) {
                    $this->conError++;
                    DB::table('incidencia_calidad')->insert([
                        'carga_id'           => $carga->carga_id,
                        'regla_id'           => null,
                        'descripcion'        => 'Error al resolver dimensiones: '.$e->getMessage(),
                        'severidad'          => 'ERROR',
                        'numero_fila'        => $numeroFila,
                        'valor_detectado'    => null,
                        'estado_tratamiento' => 'PENDIENTE',
                        'fecha_deteccion'    => now(),
                    ]);
                }

                // Avance periodico.
                if ($this->leidas % 5000 === 0) {
                    $proceso->update(['filas_procesadas' => $this->leidas]);
                    $carga->update([
                        'total_filas_leidas'  => $this->leidas,
                        'total_filas_validas' => $this->validas,
                        'total_filas_error'   => $this->conError,
                    ]);
                }
            }

            // Vaciar el último lote.
            $this->vaciarLote();

            // 6) Cierre exitoso.
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
                'mensaje_log'      => "Leidas: {$this->leidas}, validas: {$this->validas}, con error: {$this->conError}.",
            ]);

            // Bitácora del ETL (sin usuario autenticado en cola: se usa el de la carga).
            \App\Models\BitacoraAuditoria::create([
                'usuario_id'        => $carga->usuario_id,
                'accion'            => 'ETL_COMPLETADO',
                'entidad_afectada'  => 'carga_archivo',
                'registro_afectado' => (string) $carga->carga_id,
                'valores_nuevos'    => [
                    'leidas' => $this->leidas, 'validas' => $this->validas, 'error' => $this->conError,
                ],
                'fecha_hora'        => now(),
            ]);

            // Tarea 14: refrescar las vistas materializadas del portal para que los
            // resumenes precalculados queden al día tras esta carga.
            $this->refrescarVistasPortal();
        } catch (Throwable $e) {
            $carga->update(['estado' => 'FALLIDO']);
            $proceso->update([
                'estado'      => 'FALLIDO',
                'fecha_fin'   => now(),
                'mensaje_log' => 'Fallo: '.$e->getMessage(),
            ]);
            report($e);
        }
    }

    /**
     * Refresca las vistas materializadas del portal tras un ETL exitoso.
     * Tolerante a fallos: si las vistas aún no existen, no interrumpe la carga.
     */
    private function refrescarVistasPortal(): void
    {
        try {
            \Illuminate\Support\Facades\Artisan::call('geodata:refrescar-vistas');
        } catch (Throwable $e) {
            report($e);
        }
    }

    /**
     * Inserta el lote acumulado en una sola consulta.
     */
    private function vaciarLote(): void
    {
        if (empty($this->lote)) {
            return;
        }
        DB::table('operacion_comercio_exterior')->insert($this->lote);
        $this->lote = [];
    }

    /**
     * Traduce una fila cruda (cabecera=>valor) a [canonico, extra] según el mapeo.
     */
    private function traducirFila(array $filaCruda, array $columnas): array
    {
        $canonico = [];
        $extra = [];

        // Indexar mapeo por nombre de columna origen (normalizado).
        foreach ($columnas as $col) {
            $origen = $col['origen'];
            $valor = $filaCruda[$origen] ?? null;

            if (($col['a_extra'] ?? false)) {
                $extra[$origen] = $valor;
            } elseif (($col['guardar'] ?? false) && ! empty($col['campo_canonico'])) {
                $canonico[$col['campo_canonico']] = $valor;
            }
        }

        return [$canonico, $extra];
    }

    /**
     * Aplica las reglas de validación a la fila canonica.
     *
     * @return array<int, array{regla_id:int, descripción:string, severidad:string, valor:?string}>
     */
    private function validar(array $canonico, int $numeroFila): array
    {
        $incidencias = [];

        foreach ($this->reglas as $regla) {
            $campo = $regla->campo_objetivo;
            $valor = $campo ? ($canonico[$campo] ?? null) : null;
            $violada = false;

            switch ($regla->expresion) {
                case 'no_nulo':
                    $violada = ($valor === null || $valor === '');
                    break;
                case 'no_negativo':
                    $violada = ($valor !== null && $valor !== '' && $this->aNumero($valor) < 0);
                    break;
                case 'rango_mes':
                    if ($valor !== null && $valor !== '') {
                        $m = (int) $this->aNumero($valor);
                        $violada = ($m < 1 || $m > 12);
                    }
                    break;
            }

            if ($violada) {
                $incidencias[] = [
                    'regla_id'    => $regla->regla_id,
                    'descripcion' => $regla->descripcion,
                    'severidad'   => $regla->severidad,
                    'valor'       => $valor !== null ? (string) $valor : null,
                ];
            }
        }

        return $incidencias;
    }

    /**
     * Construye el arreglo de un hecho resolviendo todas sus dimensiones.
     */
    private function construirHecho(CargaArchivo $carga, array $c, array $extra): array
    {
        $gestion = (int) $this->aNumero($c['gestion'] ?? 0);
        $mes = (int) $this->aNumero($c['mes'] ?? 0);

        return [
            'organizacion_id'   => $this->organizacionId,
            'carga_id'          => $carga->carga_id,
            'fuente_id'         => $this->fuenteId,
            'tiempo_id'         => $this->resolverTiempo($gestion, $mes),
            'tipo_operacion_id' => $this->resolverTipoOperacion($carga->tipo_flujo),
            'flujo_id'          => $this->resolverFlujo($c['flujo'] ?? null, $carga->tipo_flujo),
            'producto_id'       => $this->resolverProducto($c),
            'cuci_id'           => $this->resolverCuci($c['codigo_cuci'] ?? null),
            'gce_id'            => $this->resolverGce($c['codigo_gce'] ?? null),
            'ciiu_id'           => $this->resolverCiiu($c['codigo_ciiu'] ?? null, $c['codigo_grupo_actividad'] ?? null),
            'pais_id'           => $this->resolverPais($c['codigo_pais'] ?? null, $c['codigo_zona'] ?? null),
            'departamento_id'   => $this->resolverDepartamento($c['codigo_departamento'] ?? null),
            'medio_id'          => $this->resolverMedio($c['codigo_medio'] ?? null),
            'via_id'            => $this->resolverVia($c['codigo_via'] ?? null),
            'tnt_id'            => isset($c['codigo_tnt']) ? $this->resolverTnt($c['codigo_tnt']) : null,
            'cuode_id'          => isset($c['codigo_cuode']) ? $this->resolverCuode($c['codigo_cuode']) : null,
            'aduana_id'         => isset($c['codigo_aduana']) ? $this->resolverAduana($c['codigo_aduana']) : null,
            'despachante_id'    => null,
            'peso_bruto_kg'           => $this->aNumeroNull($c['peso_bruto_kg'] ?? null),
            'peso_neto_kg'            => $this->aNumeroNull($c['peso_neto_kg'] ?? null),
            'peso_fino_kg'            => $this->aNumeroNull($c['peso_fino_kg'] ?? null),
            'valor_fob_usd'           => $this->aNumeroNull($c['valor_fob_usd'] ?? null),
            'valor_cif_frontera_usd'  => $this->aNumeroNull($c['valor_cif_frontera_usd'] ?? null),
            'valor_cif_aduana_usd'    => $this->aNumeroNull($c['valor_cif_aduana_usd'] ?? null),
            'gravamenes_pagados'      => $this->aNumeroNull($c['gravamenes_pagados'] ?? null),
            'atributos_extra'         => ! empty($extra) ? json_encode($extra, JSON_UNESCAPED_UNICODE) : null,
        ];
    }

    // =====================================================================
    //  Resolucion de dimensiones (buscar o crear, con cache en memoria)
    // =====================================================================

    private function recordar(string $clave, callable $crear): int
    {
        return $this->cache[$clave] ??= $crear();
    }

    private function resolverFuente(CargaArchivo $carga): int
    {
        $version = 'INE-'.($carga->gestion ?: 'base');

        $id = DB::table('fuente_datos')
            ->where('organizacion_id', $carga->organizacion_id)
            ->where('version_nomenclatura', $version)
            ->value('fuente_id');

        if ($id) {
            return (int) $id;
        }

        return (int) DB::table('fuente_datos')->insertGetId([
            'organizacion_id'      => $carga->organizacion_id,
            'version_nomenclatura' => $version,
            'fecha_descarga'       => now()->toDateString(),
        ], 'fuente_id');
    }

    private function resolverTiempo(int $gestion, int $mes): int
    {
        return $this->recordar("tiempo:$gestion:$mes", function () use ($gestion, $mes) {
            $existe = DB::table('tiempo')->where('gestion', $gestion)->where('mes', $mes)->value('tiempo_id');
            if ($existe) {
                return (int) $existe;
            }
            $meses = [1 => 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
            $m = max(1, min(12, $mes));

            return (int) DB::table('tiempo')->insertGetId([
                'gestion'          => $gestion,
                'mes'              => $m,
                'nombre_mes'       => $meses[$m],
                'trimestre'        => (int) ceil($m / 3),
                'semestre'         => (int) ceil($m / 6),
                'fecha_inicio_mes' => Carbon::createFromDate($gestion, $m, 1)->toDateString(),
            ], 'tiempo_id');
        });
    }

    private function resolverTipoOperacion(string $tipoFlujo): int
    {
        return $this->recordar("tipoop:$tipoFlujo", function () use ($tipoFlujo) {
            $nombre = $tipoFlujo === 'EXPORTACION' ? 'Exportacion' : 'Importacion';
            $base = $tipoFlujo === 'EXPORTACION' ? 'FOB' : 'CIF';
            $id = DB::table('tipo_operacion')->where('nombre', $nombre)->value('tipo_operacion_id');

            return $id ? (int) $id : (int) DB::table('tipo_operacion')->insertGetId(
                ['nombre' => $nombre, 'base_valoracion' => $base], 'tipo_operacion_id'
            );
        });
    }

    private function resolverFlujo(?string $flujo, string $tipoFlujo): int
    {
        $codigo = $flujo !== null && $flujo !== '' ? substr((string) $flujo, 0, 2) : ($tipoFlujo === 'EXPORTACION' ? 'E' : 'I');

        return $this->recordar("flujo:$codigo", function () use ($codigo, $tipoFlujo) {
            $id = DB::table('flujo_comercial')->where('codigo_flujo', $codigo)->value('flujo_id');

            return $id ? (int) $id : (int) DB::table('flujo_comercial')->insertGetId(
                ['codigo_flujo' => $codigo, 'descripcion' => ucfirst(strtolower($tipoFlujo))], 'flujo_id'
            );
        });
    }

    private function resolverSeccion(int $codigoSeccion): int
    {
        return $this->recordar("seccion:{$this->fuenteId}:$codigoSeccion", function () use ($codigoSeccion) {
            $id = DB::table('seccion_arancelaria')->where('fuente_id', $this->fuenteId)->where('codigo_seccion', $codigoSeccion)->value('seccion_id');

            return $id ? (int) $id : (int) DB::table('seccion_arancelaria')->insertGetId([
                'fuente_id' => $this->fuenteId, 'codigo_seccion' => $codigoSeccion,
                'descripcion' => $codigoSeccion === 0 ? 'Sin seccion' : "Seccion $codigoSeccion",
            ], 'seccion_id');
        });
    }

    private function resolverCapitulo(int $codigoCapitulo, int $seccionId): int
    {
        return $this->recordar("capitulo:$seccionId:$codigoCapitulo", function () use ($codigoCapitulo, $seccionId) {
            $id = DB::table('capitulo_arancelario')->where('seccion_id', $seccionId)->where('codigo_capitulo', $codigoCapitulo)->value('capitulo_id');

            return $id ? (int) $id : (int) DB::table('capitulo_arancelario')->insertGetId([
                'seccion_id' => $seccionId, 'codigo_capitulo' => $codigoCapitulo,
                'descripcion' => "Capitulo $codigoCapitulo",
            ], 'capitulo_id');
        });
    }

    private function resolverProducto(array $c): int
    {
        $nandina = (int) $this->aNumero($c['codigo_nandina'] ?? 0);
        // Capítulo: explicito o derivado de los 2 primeros digitos de la NANDINA (10 digitos).
        $codCap = isset($c['codigo_capitulo']) && $c['codigo_capitulo'] !== ''
            ? (int) $this->aNumero($c['codigo_capitulo'])
            : (int) substr(str_pad((string) $nandina, 10, '0', STR_PAD_LEFT), 0, 2);
        $codSec = isset($c['codigo_seccion']) && $c['codigo_seccion'] !== ''
            ? (int) $this->aNumero($c['codigo_seccion']) : 0;

        $seccionId = $this->resolverSeccion($codSec);
        $capituloId = $this->resolverCapitulo($codCap, $seccionId);

        return $this->recordar("producto:$capituloId:$nandina", function () use ($capituloId, $nandina, $c) {
            $id = DB::table('producto')->where('capitulo_id', $capituloId)->where('codigo_nandina', $nandina)->value('producto_id');
            if ($id) {
                return (int) $id;
            }
            $desc = $c['descripcion_producto'] ?? "Producto $nandina";

            return (int) DB::table('producto')->insertGetId([
                'capitulo_id' => $capituloId, 'codigo_nandina' => $nandina,
                'descripcion' => $desc !== '' ? $desc : "Producto $nandina", 'vigente' => true,
            ], 'producto_id');
        });
    }

    private function resolverCuci(?string $codigo): int
    {
        $cod = $codigo !== null && $codigo !== '' ? substr((string) $codigo, 0, 5) : '0';

        return $this->recordar("cuci:{$this->fuenteId}:$cod", function () use ($cod) {
            $id = DB::table('clasificacion_cuci')->where('fuente_id', $this->fuenteId)->where('codigo_cuci', $cod)->where('revision', 'Rev.3')->value('cuci_id');

            return $id ? (int) $id : (int) DB::table('clasificacion_cuci')->insertGetId([
                'fuente_id' => $this->fuenteId, 'codigo_cuci' => $cod, 'descripcion' => "CUCI $cod", 'revision' => 'Rev.3',
            ], 'cuci_id');
        });
    }

    private function resolverGce(?string $codigo): int
    {
        $cod = $codigo !== null && $codigo !== '' ? substr((string) $codigo, 0, 5) : '0';

        return $this->recordar("gce:{$this->fuenteId}:$cod", function () use ($cod) {
            $id = DB::table('categoria_economica_gce')->where('fuente_id', $this->fuenteId)->where('codigo_gce', $cod)->where('revision', 'Rev.3')->value('gce_id');

            return $id ? (int) $id : (int) DB::table('categoria_economica_gce')->insertGetId([
                'fuente_id' => $this->fuenteId, 'codigo_gce' => $cod, 'descripcion' => "GCE $cod", 'revision' => 'Rev.3',
            ], 'gce_id');
        });
    }

    private function resolverGrupoActividad(string $codigo): int
    {
        $cod = substr($codigo, 0, 5);

        return $this->recordar("grupo:{$this->fuenteId}:$cod", function () use ($cod) {
            $id = DB::table('grupo_actividad')->where('fuente_id', $this->fuenteId)->where('codigo', $cod)->value('grupo_actividad_id');

            return $id ? (int) $id : (int) DB::table('grupo_actividad')->insertGetId([
                'fuente_id' => $this->fuenteId, 'codigo' => $cod, 'descripcion' => "Grupo $cod",
            ], 'grupo_actividad_id');
        });
    }

    private function resolverCiiu(?string $codigo, ?string $codigoGrupo): int
    {
        $cod = $codigo !== null && $codigo !== '' ? substr((string) $codigo, 0, 5) : '0';
        // Grupo: explicito o derivado (primeros 3 digitos del CIIU).
        $grupo = $codigoGrupo !== null && $codigoGrupo !== '' ? (string) $codigoGrupo : substr($cod, 0, 3);
        $grupoId = $this->resolverGrupoActividad($grupo !== '' ? $grupo : '0');

        return $this->recordar("ciiu:$grupoId:$cod", function () use ($grupoId, $cod) {
            $id = DB::table('actividad_ciiu')->where('grupo_actividad_id', $grupoId)->where('codigo_ciiu', $cod)->where('revision', 'Rev.3')->value('ciiu_id');

            return $id ? (int) $id : (int) DB::table('actividad_ciiu')->insertGetId([
                'grupo_actividad_id' => $grupoId, 'codigo_ciiu' => $cod, 'descripcion' => "CIIU $cod", 'revision' => 'Rev.3',
            ], 'ciiu_id');
        });
    }

    private function resolverZona(int $codigoZona): int
    {
        return $this->recordar("zona:{$this->fuenteId}:$codigoZona", function () use ($codigoZona) {
            $id = DB::table('zona_geoeconomica')->where('fuente_id', $this->fuenteId)->where('codigo_zona', $codigoZona)->value('zona_id');

            return $id ? (int) $id : (int) DB::table('zona_geoeconomica')->insertGetId([
                'fuente_id' => $this->fuenteId, 'codigo_zona' => $codigoZona,
                'descripcion' => $codigoZona === 0 ? 'Sin zona' : "Zona $codigoZona",
            ], 'zona_id');
        });
    }

    private function resolverPais(?string $codigo, ?string $codigoZona): int
    {
        $cod = (int) $this->aNumero($codigo ?? 0);
        $codZona = $codigoZona !== null && $codigoZona !== '' ? (int) $this->aNumero($codigoZona) : 0;
        $zonaId = $this->resolverZona($codZona);

        return $this->recordar("pais:{$this->fuenteId}:$cod", function () use ($cod, $zonaId) {
            $id = DB::table('pais')->where('fuente_id', $this->fuenteId)->where('codigo_pais', $cod)->value('pais_id');

            return $id ? (int) $id : (int) DB::table('pais')->insertGetId([
                'fuente_id' => $this->fuenteId, 'zona_id' => $zonaId, 'codigo_pais' => $cod, 'nombre' => "Pais $cod",
            ], 'pais_id');
        });
    }

    private function resolverDepartamento(?string $codigo): int
    {
        $cod = (int) $this->aNumero($codigo ?? 0);

        return $this->recordar("depto:{$this->fuenteId}:$cod", function () use ($cod) {
            $id = DB::table('departamento')->where('fuente_id', $this->fuenteId)->where('codigo', $cod)->value('departamento_id');

            return $id ? (int) $id : (int) DB::table('departamento')->insertGetId([
                'fuente_id' => $this->fuenteId, 'codigo' => $cod, 'nombre' => "Departamento $cod",
            ], 'departamento_id');
        });
    }

    private function resolverMedio(?string $codigo): int
    {
        $cod = (int) $this->aNumero($codigo ?? 0);

        return $this->recordar("medio:$cod", function () use ($cod) {
            $id = DB::table('medio_transporte')->where('codigo', $cod)->value('medio_id');

            return $id ? (int) $id : (int) DB::table('medio_transporte')->insertGetId([
                'codigo' => $cod, 'descripcion' => "Medio $cod",
            ], 'medio_id');
        });
    }

    private function resolverVia(?string $codigo): int
    {
        $cod = (int) $this->aNumero($codigo ?? 0);

        return $this->recordar("via:$cod", function () use ($cod) {
            $id = DB::table('via_comercio')->where('codigo', $cod)->value('via_id');

            return $id ? (int) $id : (int) DB::table('via_comercio')->insertGetId([
                'codigo' => $cod, 'descripcion' => "Via $cod",
            ], 'via_id');
        });
    }

    private function resolverTnt(string $codigo): int
    {
        $cod = (int) $this->aNumero($codigo);

        return $this->recordar("tnt:$cod", function () use ($cod) {
            $id = DB::table('clasificacion_tnt')->where('codigo_tnt', $cod)->value('tnt_id');

            return $id ? (int) $id : (int) DB::table('clasificacion_tnt')->insertGetId([
                'codigo_tnt' => $cod, 'descripcion' => "TNT $cod", 'clase' => 'Sin clasificar',
            ], 'tnt_id');
        });
    }

    private function resolverCuode(string $codigo): int
    {
        $cod = substr($codigo, 0, 5);

        return $this->recordar("cuode:{$this->fuenteId}:$cod", function () use ($cod) {
            $id = DB::table('clasificacion_cuode')->where('fuente_id', $this->fuenteId)->where('codigo_cuode', $cod)->value('cuode_id');

            return $id ? (int) $id : (int) DB::table('clasificacion_cuode')->insertGetId([
                'fuente_id' => $this->fuenteId, 'codigo_cuode' => $cod, 'descripcion' => "CUODE $cod",
            ], 'cuode_id');
        });
    }

    private function resolverAduana(string $codigo): int
    {
        $cod = (int) $this->aNumero($codigo);

        return $this->recordar("aduana:{$this->fuenteId}:$cod", function () use ($cod) {
            $id = DB::table('aduana')->where('fuente_id', $this->fuenteId)->where('codigo', $cod)->value('aduana_id');

            return $id ? (int) $id : (int) DB::table('aduana')->insertGetId([
                'fuente_id' => $this->fuenteId, 'codigo' => $cod, 'descripcion' => "Aduana $cod",
            ], 'aduana_id');
        });
    }

    // =====================================================================
    //  Utilidades numericas
    // =====================================================================

    /**
     * Convierte un valor de celda a número, tolerando separadores de miles y
     * decimales. Si tiene coma como último separador, se interpreta como decimal.
     */
    private function aNumero($valor): float
    {
        if ($valor === null || $valor === '') {
            return 0.0;
        }
        if (is_numeric($valor)) {
            return (float) $valor;
        }
        $s = trim((string) $valor);
        $posComa = strrpos($s, ',');
        $posPunto = strrpos($s, '.');
        if ($posComa !== false && $posComa > ($posPunto === false ? -1 : $posPunto)) {
            // coma decimal: quitar puntos (miles) y cambiar coma por punto
            $s = str_replace('.', '', $s);
            $s = str_replace(',', '.', $s);
        } else {
            // punto decimal: quitar comas (miles)
            $s = str_replace(',', '', $s);
        }
        $s = preg_replace('/[^0-9.\-]/', '', $s);

        return is_numeric($s) ? (float) $s : 0.0;
    }

    private function aNumeroNull($valor): ?float
    {
        if ($valor === null || $valor === '') {
            return null;
        }

        return $this->aNumero($valor);
    }
}
