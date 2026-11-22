<?php

namespace App\Http\Controllers;

use App\Jobs\ProcesarCargaArchivo;
use App\Models\CargaArchivo;
use App\Models\Organizacion;
use App\Models\PerfilMapeo;
use App\Servicios\DetectorPerfil;
use App\Servicios\LectorArchivo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use App\Support\Organizaciones;
use App\Support\PlantillasCarga;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class CargaController extends Controller
{
    /** Valores válidos de carga_archivo.tipo_flujo (CHECK de la BD). */
    private const TIPOS_FLUJO = ['EXPORTACION', 'IMPORTACION', 'MERCOSUR_PAIS', 'MERCOSUR_ITEM', 'FAOSTAT'];

    /**
     * Pestaña "Datos cargados": historial de cargas + resumen REAL por fuente y
     * totales INE por gestión (exportación, importación, saldo).
     */
    public function index(): Response
    {
        $cargas = CargaArchivo::with([
            'organizacion:organizacion_id,nombre,sigla',
            'perfil:perfil_id,etiqueta_version',
            'usuario:usuario_id,nombre_completo',
        ])
            // Mensaje del último proceso ETL (motivo del FALLIDO, filas omitidas...).
            ->addSelect(['ultimo_mensaje' => \App\Models\ProcesoEtl::select('mensaje_log')
                ->whereColumn('proceso_etl.carga_id', 'carga_archivo.carga_id')
                ->orderByDesc('proceso_id')->limit(1)])
            ->withCount('incidencias')
            ->orderByDesc('carga_id')
            ->paginate(15);

        // Marca qué cargas tienen su archivo guardado (se pueden reintentar).
        $cargas->getCollection()->transform(function ($c) {
            $c->setAttribute('archivo_disponible', $this->rutaArchivoCarga($c->carga_id) !== null);

            return $c;
        });

        return Inertia::render('Gestion/Datos', [
            'cargas'        => $cargas,
            'resumenFuentes' => app(\App\Servicios\ResumenGestionDatos::class)->porFuente(),
            'totalesIne'    => app(\App\Servicios\ResumenGestionDatos::class)->totalesIne(),
            'estados'       => CargaArchivo::selectRaw('estado, count(*) AS n')->groupBy('estado')->pluck('n', 'estado'),
        ]);
    }

    /** Excel con el resumen por fuente y los totales INE por gestión. */
    public function exportarResumen(): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $servicio = app(\App\Servicios\ResumenGestionDatos::class);
        $fuentes = $servicio->porFuente();
        $ine = $servicio->totalesIne();
        $archivo = 'geodata_datos_cargados_'.now()->format('Ymd_His').'.xlsx';

        return response()->streamDownload(function () use ($fuentes, $ine) {
            $w = new \OpenSpout\Writer\XLSX\Writer();
            $w->openToFile('php://output');
            $w->getCurrentSheet()->setName('Por fuente');
            $w->addRow(\OpenSpout\Common\Entity\Row::fromValues(['Fuente', 'Tabla', 'Filas', 'Gestión desde', 'Gestión hasta', 'Cargas completadas', 'Última carga']));
            foreach ($fuentes as $f) {
                $w->addRow(\OpenSpout\Common\Entity\Row::fromValues([$f['sigla'], $f['tabla'], (int) $f['filas'], $f['desde'], $f['hasta'], (int) $f['cargas'], $f['ultima_carga']]));
            }
            $w->addNewSheetAndMakeItCurrent()->setName('INE por gestión');
            $w->addRow(\OpenSpout\Common\Entity\Row::fromValues(['Gestión', 'Exportaciones FOB (USD)', 'Importaciones CIF (USD)', 'Saldo (USD)', 'Operaciones']));
            foreach ($ine as $r) {
                $w->addRow(\OpenSpout\Common\Entity\Row::fromValues([(int) $r['gestion'], (float) $r['exportaciones'], (float) $r['importaciones'], (float) $r['saldo'], (int) $r['operaciones']]));
            }
            $w->close();
        }, $archivo, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }

    /**
     * Pestaña "Cargar datos": fuente (las 4 organizaciones con cargador), flujo,
     * plantillas por entidad/flujo y formatos que acepta cada lector.
     */
    public function create(): Response
    {
        $orgs = Organizacion::where('activo', true)->orderBy('organizacion_id')->get(['organizacion_id', 'nombre', 'sigla']);

        return Inertia::render('Gestion/Cargar', [
            'organizaciones' => $orgs->map(fn ($o) => [
                'organizacion_id' => $o->organizacion_id,
                'nombre'          => $o->nombre,
                'sigla'           => $o->sigla,
                'flujos'          => PlantillasCarga::FLUJOS_POR_SIGLA[$o->sigla] ?? ['EXPORTACION', 'IMPORTACION'],
                'extensiones'     => PlantillasCarga::EXTENSIONES_POR_SIGLA[$o->sigla] ?? ['xlsx', 'xlsm', 'csv'],
                'con_cargador'    => isset(PlantillasCarga::FLUJOS_POR_SIGLA[$o->sigla]),
            ])->values(),
            'plantillas'     => PlantillasCarga::catalogo(),
            'perfiles'       => PerfilMapeo::where('activo', true)->get(['perfil_id', 'organizacion_id', 'tipo_flujo', 'etiqueta_version']),
        ]);
    }

    /** GET /admin/cargas/plantilla/{entidad}/{flujo}: plantilla .xlsx generada al vuelo. */
    public function plantilla(string $entidad, string $flujo, \App\Servicios\GeneradorPlantilla $generador): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        $clave = strtolower($entidad).'/'.strtolower($flujo);
        abort_unless(isset(PlantillasCarga::RUTAS[$clave]), 404, 'Plantilla no encontrada.');
        [$sigla, $tipoFlujo] = PlantillasCarga::RUTAS[$clave];

        $dir = storage_path('app/plantillas_tmp');
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $tmp = $dir.DIRECTORY_SEPARATOR.Str::uuid()->toString().'.xlsx';
        $def = $generador->escribir($sigla, $tipoFlujo, $tmp);

        return response()->download($tmp, $def['archivo'], [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    /**
     * Sube el archivo a una ubicacion temporal, lee cabeceras + muestra,
     * detecta el perfil y devuelve la propuesta de mapeo por columna.
     */
    public function previsualizar(Request $request, LectorArchivo $lector, DetectorPerfil $detector): JsonResponse
    {
        $datos = $request->validate([
            'organizacion_id' => ['required', 'integer', 'exists:organizacion,organizacion_id'],
            'tipo_flujo'      => ['required', Rule::in(self::TIPOS_FLUJO)],
            'archivo'         => ['required', 'file', 'max:512000'], // 500 MB
        ]);

        $archivo = $request->file('archivo');
        $ext = strtolower($archivo->getClientOriginalExtension());
        $sigla = Organizaciones::sigla((int) $datos['organizacion_id']);
        $this->validarCombinacion($sigla, $datos['tipo_flujo'], $ext);

        // Guarda temporalmente con un nombre seguro (uuid).
        $token = Str::uuid()->toString().'.'.$ext;
        $rutaTmp = $archivo->storeAs('cargas_tmp', $token, 'local');
        $rutaAbs = Storage::disk('local')->path($rutaTmp);

        // Lee cabeceras + 20 filas de muestra (streaming).
        $lectura = $lector->leerCabecerasYMuestra($rutaAbs, $ext, 20);
        $cabeceras = $lectura['cabeceras'];

        // Detecta el perfil por las cabeceras.
        $deteccion = $detector->detectar($cabeceras, $datos['organizacion_id'], $datos['tipo_flujo']);

        // Mapa de columnas del mejor perfil (normalizado => fila de mapeo).
        $mapaPerfil = [];
        $perfilId = $deteccion['mejor']['perfil_id'] ?? null;
        if ($perfilId) {
            $perfil = PerfilMapeo::with('columnas')->find($perfilId);
            foreach ($perfil->columnas as $col) {
                $mapaPerfil[DetectorPerfil::normalizar($col->nombre_columna_origen)] = $col;
            }
        }

        // Propuesta por columna del archivo.
        $propuesta = [];
        foreach ($cabeceras as $origen) {
            $norm = DetectorPerfil::normalizar($origen);
            if (isset($mapaPerfil[$norm])) {
                $col = $mapaPerfil[$norm];
                $propuesta[] = [
                    'origen'         => $origen,
                    'campo_canonico' => $col->campo_canonico,
                    'guardar'        => (bool) $col->guardar,
                    'a_extra'        => (bool) $col->a_extra,
                    'desconocida'    => false,
                ];
            } else {
                // No esta en el perfil: intenta sugerir por alias.
                $sugerido = DetectorPerfil::sugerirCampo($origen);
                $propuesta[] = [
                    'origen'         => $origen,
                    'campo_canonico' => $sugerido,
                    'guardar'        => $sugerido !== null,
                    'a_extra'        => false,
                    'desconocida'    => true, // requiere decision del usuario
                ];
            }
        }

        // Columnas que reconoce el cargador específico de la organización/flujo
        // (los cargadores INE/ALADI/MERCOSUR/FAOSTAT leen por nombre de cabecera;
        // el mapeo editable solo lo usa el ETL genérico).
        $reconocimiento = PlantillasCarga::reconocer((string) $sigla, $datos['tipo_flujo'], $cabeceras);
        $zona = null;
        if ($sigla === Organizaciones::MERCOSUR) {
            $cargador = $datos['tipo_flujo'] === 'MERCOSUR_ITEM' ? \App\Servicios\CargadorMercosurItem::class : \App\Servicios\CargadorMercosurPais::class;
            $zona = app($cargador)->zonaDesdeNombre($archivo->getClientOriginalName());
        }

        return response()->json([
            'reconocimiento' => $reconocimiento,
            'zona_detectada' => $zona,
            'con_cargador'   => isset(PlantillasCarga::FLUJOS_POR_SIGLA[$sigla]),
            'token'          => $token,
            'extension'      => $ext,
            'cabeceras'      => $cabeceras,
            'muestra'        => $lectura['muestra'],
            'deteccion'      => $deteccion,
            'perfil_id'      => $perfilId,
            'propuesta'      => $propuesta,
        ]);
    }

    /**
     * Confirma la carga: registra carga_archivo (PENDIENTE), mueve el archivo a
     * storage definitivo, guarda el mapeo resuelto y despacha el Job de ETL.
     */
    public function store(Request $request): RedirectResponse
    {
        $campos = array_keys(config('geodata.campos_canonicos'));

        $datos = $request->validate([
            'token'           => ['required', 'string', 'regex:/^[a-f0-9\-]+\.(xlsx|xlsm|xls|csv|txt)$/i'],
            'organizacion_id' => ['required', 'integer', 'exists:organizacion,organizacion_id'],
            'perfil_id'       => ['nullable', 'integer', 'exists:perfil_mapeo,perfil_id'],
            'tipo_flujo'      => ['required', Rule::in(self::TIPOS_FLUJO)],
            'nombre_archivo'  => ['required', 'string', 'max:255'],
            'gestion'         => ['nullable', 'integer', 'min:1900', 'max:2100'],
            'mes'             => ['nullable', 'integer', 'min:0', 'max:12'], // 0 = anual
            'columnas'                          => ['required', 'array', 'min:1'],
            'columnas.*.origen'                 => ['required', 'string'],
            'columnas.*.campo_canonico'         => ['nullable', Rule::in($campos)],
            'columnas.*.guardar'                => ['boolean'],
            'columnas.*.a_extra'                => ['boolean'],
        ]);

        $rutaTmp = 'cargas_tmp/'.$datos['token'];
        if (! Storage::disk('local')->exists($rutaTmp)) {
            return back()->with('error', 'El archivo temporal expiro. Vuelva a subirlo.');
        }

        $ext = strtolower(pathinfo($datos['token'], PATHINFO_EXTENSION));
        $sigla = Organizaciones::sigla((int) $datos['organizacion_id']);
        $this->validarCombinacion($sigla, $datos['tipo_flujo'], $ext);
        if ($sigla === Organizaciones::ALADI && empty($datos['gestion'])) {
            throw ValidationException::withMessages(['gestion' => 'ALADI: indique la gestión (el archivo de ranking no la trae).']);
        }

        // 1) Registrar la carga (PENDIENTE)
        $carga = CargaArchivo::create([
            'organizacion_id' => $datos['organizacion_id'],
            'perfil_id'       => $datos['perfil_id'] ?? null,
            'usuario_id'      => $request->user()->usuario_id,
            'nombre_archivo'  => $datos['nombre_archivo'],
            'tipo_flujo'      => $datos['tipo_flujo'],
            'gestion'         => $datos['gestion'] ?? null,
            'mes'             => $datos['mes'] ?? null,
            'estado'          => 'PENDIENTE',
        ]);

        // 2) Mover el archivo a su ubicacion definitiva: cargas/{id}/datos.{ext}
        $rutaDestino = "cargas/{$carga->carga_id}/datos.{$ext}";
        Storage::disk('local')->makeDirectory("cargas/{$carga->carga_id}");
        $movido = Storage::disk('local')->move($rutaTmp, $rutaDestino);

        if (! $movido) {
            $carga->update(['estado' => 'FALLIDO']);
            Storage::disk('local')->deleteDirectory("cargas/{$carga->carga_id}");

            \App\Servicios\Auditoria::registrar('CARGA_FALLIDA', 'carga_archivo', (string) $carga->carga_id, null, [
                'nombre_archivo' => $carga->nombre_archivo,
                'tipo_flujo'     => $carga->tipo_flujo,
                'motivo'         => 'No se pudo mover el archivo a su ubicacion definitiva.',
            ]);

            return back()->with('error', "No se pudo guardar el archivo de la carga #{$carga->carga_id}. Vuelva a intentar la subida.");
        }

        // 3) Guardar el mapeo resuelto (solo columnas marcadas) para el ETL.
        $mapeoResuelto = collect($datos['columnas'])
            ->filter(fn ($c) => ($c['guardar'] ?? false) || ($c['a_extra'] ?? false))
            ->map(fn ($c) => [
                'origen'         => $c['origen'],
                'campo_canonico' => $c['campo_canonico'] ?? null,
                'guardar'        => (bool) ($c['guardar'] ?? false),
                'a_extra'        => (bool) ($c['a_extra'] ?? false),
            ])
            ->values()
            ->all();

        Storage::disk('local')->put(
            "cargas/{$carga->carga_id}/mapeo.json",
            json_encode([
                'extension' => $ext,
                'columnas'  => $mapeoResuelto,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        );

        // 4) Despachar el Job de ETL (Tarea 6).
        ProcesarCargaArchivo::dispatch($carga->carga_id);

        \App\Servicios\Auditoria::registrar('CARGA_REGISTRADA', 'carga_archivo', (string) $carga->carga_id, null, [
            'nombre_archivo' => $carga->nombre_archivo,
            'tipo_flujo'     => $carga->tipo_flujo,
        ]);

        return redirect()->route('cargas.index')
            ->with('exito', "Carga #{$carga->carga_id} registrada y encolada para procesamiento.");
    }

    /**
     * Valida que el flujo corresponda a la organización y que el lector de esa
     * organización sepa leer la extensión (mensaje claro si no).
     */
    private function validarCombinacion(?string $sigla, string $tipoFlujo, string $ext): void
    {
        $flujos = PlantillasCarga::FLUJOS_POR_SIGLA[$sigla] ?? ['EXPORTACION', 'IMPORTACION'];
        if (! in_array($tipoFlujo, $flujos, true)) {
            throw ValidationException::withMessages([
                'tipo_flujo' => "El flujo {$tipoFlujo} no corresponde a {$sigla}. Opciones: ".implode(', ', $flujos).'.',
            ]);
        }

        $exts = PlantillasCarga::EXTENSIONES_POR_SIGLA[$sigla] ?? ['xlsx', 'xlsm', 'csv'];
        if (! in_array($ext, $exts, true)) {
            $consejo = $ext === 'xls'
                ? ' Abra el archivo en Excel y use "Guardar como" > Libro de Excel (.xlsx).'
                : '';
            throw ValidationException::withMessages([
                'archivo' => "{$sigla} no admite archivos .{$ext}. Formatos aceptados: .".implode(', .', $exts).'.'.$consejo,
            ]);
        }
    }

    /** Ruta absoluta del archivo guardado de una carga (o null si no existe). */
    private function rutaArchivoCarga(int $cargaId): ?string
    {
        foreach (['xlsx', 'xlsm', 'xls', 'csv', 'txt'] as $ext) {
            $rel = "cargas/{$cargaId}/datos.{$ext}";
            if (Storage::disk('local')->exists($rel)) {
                return Storage::disk('local')->path($rel);
            }
        }

        return null;
    }

    /**
     * Reintenta una carga FALLIDA con el mismo archivo y mapeo: vuelve a
     * PENDIENTE y despacha el job. Los cargadores son idempotentes.
     */
    public function reintentar(CargaArchivo $carga): RedirectResponse
    {
        if ($carga->estado !== 'FALLIDO') {
            return back()->with('error', "Solo se pueden reintentar cargas FALLIDAS (la #{$carga->carga_id} está {$carga->estado}).");
        }
        if ($this->rutaArchivoCarga($carga->carga_id) === null) {
            return back()->with('error', "La carga #{$carga->carga_id} no tiene su archivo guardado (p.ej. vino de un comando por lote). Súbalo de nuevo.");
        }

        $carga->update(['estado' => 'PENDIENTE', 'total_filas_leidas' => 0, 'total_filas_validas' => 0, 'total_filas_error' => 0, 'total_filas_omitidas' => 0]);
        $carga->incidencias()->delete();
        ProcesarCargaArchivo::dispatch($carga->carga_id);

        \App\Servicios\Auditoria::registrar('CARGA_REINTENTADA', 'carga_archivo', (string) $carga->carga_id, null, [
            'nombre_archivo' => $carga->nombre_archivo,
        ]);

        return back()->with('exito', "Carga #{$carga->carga_id} reencolada.");
    }

    /**
     * Elimina una carga y TODAS las filas que insertó, en una sola transacción.
     * Solo FALLIDO o COMPLETADO (no mientras se procesa).
     */
    public function destroy(CargaArchivo $carga): RedirectResponse
    {
        if (! in_array($carga->estado, ['FALLIDO', 'COMPLETADO'], true)) {
            return back()->with('error', "La carga #{$carga->carga_id} está {$carga->estado}; espere a que termine para eliminarla.");
        }

        $sigla = Organizaciones::sigla((int) $carga->organizacion_id);
        $tablasPorSigla = [
            Organizaciones::MERCOSUR => ['serie_comercio_zona', 'serie_comercio_producto_zona'],
            Organizaciones::ALADI    => ['ranking_comercio'],
            Organizaciones::FAOSTAT  => ['serie_indicador_agricola'],
        ];
        $ruta = $this->rutaArchivoCarga($carga->carga_id);
        $id = $carga->carga_id;
        $filas = 0;

        try {
            DB::transaction(function () use ($carga, $sigla, $tablasPorSigla, $ruta, &$filas) {
                // Hechos INE (y ETL genérico): enlazados por carga_id.
                $filas += DB::table('operacion_comercio_exterior')->where('carga_id', $carga->carga_id)->delete();

                // ALADI / MERCOSUR / FAOSTAT: enlazados por archivo_fuente (ruta del archivo guardado).
                if (isset($tablasPorSigla[$sigla])) {
                    $archivoIds = $ruta
                        ? DB::table('archivo_fuente')->where('ruta_archivo', $ruta)->pluck('archivo_id')->all()
                        : [];
                    if (empty($archivoIds) && $carga->estado === 'COMPLETADO' && (int) $carga->total_filas_validas > 0) {
                        throw new \RuntimeException('no se pueden identificar sus filas (carga hecha por comando de lote, sin archivo guardado). Use el comando con --fresh para reemplazar esos datos.');
                    }
                    foreach ($tablasPorSigla[$sigla] as $tabla) {
                        $filas += DB::table($tabla)->whereIn('archivo_id', $archivoIds)->delete();
                    }
                    DB::table('archivo_fuente')->whereIn('archivo_id', $archivoIds)->delete();
                }

                // incidencia_calidad y proceso_etl caen por ON DELETE CASCADE.
                $carga->delete();
            });
        } catch (\RuntimeException $e) {
            return back()->with('error', "No se eliminó la carga #{$id}: ".$e->getMessage());
        }

        Storage::disk('local')->deleteDirectory("cargas/{$id}");

        if ($filas > 0) {
            $vistas = match ($sigla) {
                Organizaciones::INE      => 'ine',
                Organizaciones::MERCOSUR => 'mercosur',
                default                  => null,
            };
            try {
                if ($vistas) {
                    \Illuminate\Support\Facades\Artisan::call('geodata:refrescar-vistas', ['--solo' => $vistas]);
                }
                // La versión del cache es el último carga_id: al borrar una carga
                // podría "volver" a una versión vieja, así que se limpia el cache.
                \Illuminate\Support\Facades\Cache::flush();
                \Illuminate\Support\Facades\Artisan::call('geodata:calentar-cache');
            } catch (\Throwable $e) {
                report($e);
            }
        }

        \App\Servicios\Auditoria::registrar('CARGA_ELIMINADA', 'carga_archivo', (string) $id, [
            'nombre_archivo' => $carga->nombre_archivo,
            'estado'         => $carga->estado,
        ], ['filas_eliminadas' => $filas]);

        return redirect()->route('cargas.index')->with('exito', "Carga #{$id} eliminada ({$filas} fila(s) de datos borradas).");
    }
}
