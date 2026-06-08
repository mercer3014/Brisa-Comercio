<?php

namespace App\Http\Controllers;

use App\Models\Permiso;
use App\Models\Rol;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class RolController extends Controller
{
    public function index(): Response
    {
        $roles = Rol::with('permisos:permiso_id,codigo')
            ->orderBy('nombre')
            ->get()
            ->map(fn ($r) => [
                'rol_id'      => $r->rol_id,
                'nombre'      => $r->nombre,
                'descripcion' => $r->descripcion,
                'permisos'    => $r->permisos->pluck('permiso_id'),
                'total'       => $r->permisos->count(),
            ]);

        // Permisos agrupados por modulo para la matriz.
        $permisos = Permiso::orderBy('modulo')->orderBy('codigo')
            ->get(['permiso_id', 'codigo', 'descripcion', 'modulo'])
            ->groupBy('modulo');

        return Inertia::render('Admin/Roles/Index', [
            'roles'    => $roles,
            'permisos' => $permisos,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $datos = $request->validate([
            'nombre'      => ['required', 'string', 'max:40', 'unique:rol,nombre'],
            'descripcion' => ['nullable', 'string', 'max:255'],
        ]);

        Rol::create($datos);

        return back()->with('exito', 'Rol creado correctamente.');
    }

    public function update(Request $request, Rol $rol): RedirectResponse
    {
        $datos = $request->validate([
            'nombre'      => ['required', 'string', 'max:40', Rule::unique('rol', 'nombre')->ignore($rol->rol_id, 'rol_id')],
            'descripcion' => ['nullable', 'string', 'max:255'],
            'permisos'    => ['array'],
            'permisos.*'  => ['integer', 'exists:permiso,permiso_id'],
        ]);

        $rol->update([
            'nombre'      => $datos['nombre'],
            'descripcion' => $datos['descripcion'] ?? null,
        ]);

        // Actualiza la matriz rol x permiso.
        $rol->permisos()->sync($datos['permisos'] ?? []);

        return back()->with('exito', 'Rol y permisos actualizados.');
    }
}
