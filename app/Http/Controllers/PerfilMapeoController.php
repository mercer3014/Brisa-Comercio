<?php

namespace App\Http\Controllers;

use App\Models\MapeoColumna;
use App\Models\Organizacion;
use App\Models\PerfilMapeo;
use App\Servicios\DetectorPerfil;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class PerfilMapeoController extends Controller
{
    public function index(): Response
    {
        $perfiles = PerfilMapeo::with('organizacion:organizacion_id,nombre,sigla')
            ->withCount('columnas')
            ->orderBy('organizacion_id')
            ->orderBy('tipo_flujo')
            ->get();

        return Inertia::render('Admin/Perfiles/Index', [
            'perfiles'      => $perfiles,
            'organizaciones' => Organizacion::orderBy('nombre')->get(['organizacion_id', 'nombre', 'sigla']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $datos = $request->validate([
            'organizacion_id'  => ['required', 'integer', 'exists:organizacion,organizacion_id'],
            'tipo_flujo'       => ['required', Rule::in(['EXPORTACION', 'IMPORTACION'])],
            'etiqueta_version' => ['required', 'string', 'max:40'],
            'descripcion'      => ['nullable', 'string', 'max:255'],
        ]);

        $perfil = PerfilMapeo::create($datos);

        return redirect()->route('perfiles.edit', $perfil->perfil_id)
            ->with('exito', 'Perfil creado. Defina el mapeo de columnas.');
    }

    /**
     * Editor del perfil: tabla editable de mapeo_columna.
     */
    public function edit(PerfilMapeo $perfil): Response
    {
        $perfil->load(['organizacion:organizacion_id,nombre,sigla', 'columnas']);

        return Inertia::render('Admin/Perfiles/Edit', [
            'perfil'          => $perfil,
            'columnas'        => $perfil->columnas()->orderBy('mapeo_id')->get(),
            'camposCanonicos' => config('geodata.campos_canonicos'),
        ]);
    }

    public function update(Request $request, PerfilMapeo $perfil): RedirectResponse
    {
        $datos = $request->validate([
            'etiqueta_version' => ['required', 'string', 'max:40'],
            'descripcion'      => ['nullable', 'string', 'max:255'],
            'activo'           => ['boolean'],
        ]);

        $perfil->update($datos);

        return back()->with('exito', 'Perfil actualizado.');
    }

    /**
     * Reemplaza el mapeo completo de columnas del perfil (tabla editable).
     */
    public function guardarColumnas(Request $request, PerfilMapeo $perfil): RedirectResponse
    {
        $campos = array_keys(config('geodata.campos_canonicos'));

        $datos = $request->validate([
            'columnas'                          => ['present', 'array'],
            'columnas.*.nombre_columna_origen'  => ['required', 'string', 'max:80'],
            'columnas.*.campo_canonico'         => ['nullable', Rule::in($campos)],
            'columnas.*.guardar'                => ['boolean'],
            'columnas.*.a_extra'                => ['boolean'],
            'columnas.*.nota'                   => ['nullable', 'string', 'max:255'],
        ]);

        // Reemplazo completo (idempotente): se borra y se reinserta el set.
        $perfil->columnas()->delete();

        foreach ($datos['columnas'] as $fila) {
            MapeoColumna::create([
                'perfil_id'             => $perfil->perfil_id,
                'nombre_columna_origen' => $fila['nombre_columna_origen'],
                'campo_canonico'        => $fila['campo_canonico'] ?? null,
                'guardar'               => $fila['guardar'] ?? true,
                'a_extra'               => $fila['a_extra'] ?? false,
                'nota'                  => $fila['nota'] ?? null,
            ]);
        }

        return back()->with('exito', 'Mapeo de columnas guardado ('.count($datos['columnas']).' columnas).');
    }

    /**
     * Detector de perfil: recibe un arreglo de cabeceras y devuelve el ranking.
     */
    public function detectar(Request $request, DetectorPerfil $detector): JsonResponse
    {
        $datos = $request->validate([
            'cabeceras'       => ['required', 'array', 'min:1'],
            'cabeceras.*'     => ['string'],
            'organizacion_id' => ['nullable', 'integer'],
            'tipo_flujo'      => ['nullable', Rule::in(['EXPORTACION', 'IMPORTACION'])],
        ]);

        $resultado = $detector->detectar(
            $datos['cabeceras'],
            $datos['organizacion_id'] ?? null,
            $datos['tipo_flujo'] ?? null
        );

        return response()->json($resultado);
    }
}
