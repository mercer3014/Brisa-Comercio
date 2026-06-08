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
use Inertia\Inertia;
use Inertia\Response;

class CargaController extends Controller
{
    public function index(): Response
    {
        $cargas = CargaArchivo::with([
            'organizacion:organizacion_id,nombre,sigla',
            'perfil:perfil_id,etiqueta_version',
            'usuario:usuario_id,nombre_completo',
        ])
            ->orderByDesc('carga_id')
            ->paginate(15);

        return Inertia::render('Cargas/Index', [
            'cargas' => $cargas,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Cargas/Create', [
            'organizaciones' => Organizacion::where('activo', true)->orderBy('nombre')->get(['organizacion_id', 'nombre', 'sigla']),
            'perfiles'       => PerfilMapeo::where('activo', true)->get(['perfil_id', 'organizacion_id', 'tipo_flujo', 'etiqueta_version']),
        ]);
    }

    /**
     * Sube el archivo a una ubicacion temporal, lee cabeceras + muestra,
     * detecta el perfil y devuelve la propuesta de mapeo por columna.
     */
    public function previsualizar(Request $request, LectorArchivo $lector, DetectorPerfil $detector): JsonResponse
    {
        $datos = $request->validate([
            'organizacion_id' => ['required', 'integer', 'exists:organizacion,organizacion_id'],
            'tipo_flujo'      => ['required', Rule::in(['EXPORTACION', 'IMPORTACION'])],
            'archivo'         => ['required', 'file', 'mimes:xlsx,xlsm,csv,txt', 'max:512000'], // 500 MB
        ]);

        $archivo = $request->file('archivo');
        $ext = strtolower($archivo->getClientOriginalExtension());
        $ext = in_array($ext, ['xlsx', 'xlsm', 'csv', 'txt']) ? $ext : 'csv';

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

        return response()->json([
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
        $campos = array_keys(config('comexhub.campos_canonicos'));

        $datos = $request->validate([
            'token'           => ['required', 'string', 'regex:/^[a-f0-9\-]+\.(xlsx|xlsm|csv|txt)$/i'],
            'organizacion_id' => ['required', 'integer', 'exists:organizacion,organizacion_id'],
            'perfil_id'       => ['nullable', 'integer', 'exists:perfil_mapeo,perfil_id'],
            'tipo_flujo'      => ['required', Rule::in(['EXPORTACION', 'IMPORTACION'])],
            'nombre_archivo'  => ['required', 'string', 'max:255'],
            'gestion'         => ['nullable', 'integer', 'min:1900', 'max:2100'],
            'mes'             => ['nullable', 'integer', 'min:1', 'max:12'],
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
        Storage::disk('local')->move($rutaTmp, $rutaDestino);

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
}
