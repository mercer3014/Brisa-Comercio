<?php

namespace App\Http\Controllers;

use App\Models\Organizacion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class OrganizacionController extends Controller
{
    public function index(): Response
    {
        $organizaciones = Organizacion::withCount(['perfiles', 'cargas'])
            ->orderBy('organizacion_id')
            ->get();

        return Inertia::render('Admin/Organizaciones/Index', [
            'organizaciones' => $organizaciones,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $datos = $request->validate([
            'nombre'    => ['required', 'string', 'max:120', 'unique:organizacion,nombre'],
            'sigla'     => ['nullable', 'string', 'max:20'],
            'pais_iso3' => ['nullable', 'string', 'size:3'],
            'url'       => ['nullable', 'string', 'max:500'],
            'activo'    => ['boolean'],
        ]);

        Organizacion::create($datos);

        return back()->with('exito', 'Organizacion creada correctamente.');
    }

    public function update(Request $request, Organizacion $organizacion): RedirectResponse
    {
        $datos = $request->validate([
            'nombre'    => ['required', 'string', 'max:120', Rule::unique('organizacion', 'nombre')->ignore($organizacion->organizacion_id, 'organizacion_id')],
            'sigla'     => ['nullable', 'string', 'max:20'],
            'pais_iso3' => ['nullable', 'string', 'size:3'],
            'url'       => ['nullable', 'string', 'max:500'],
            'activo'    => ['boolean'],
        ]);

        $organizacion->update($datos);

        return back()->with('exito', 'Organizacion actualizada correctamente.');
    }
}
