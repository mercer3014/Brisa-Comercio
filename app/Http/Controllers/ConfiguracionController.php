<?php

namespace App\Http\Controllers;

use App\Models\Configuracion;
use App\Servicios\Auditoria;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ConfiguracionController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Configuracion/Index', [
            'parametros' => Configuracion::orderBy('clave')->get(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $datos = $request->validate([
            'parametros'           => ['required', 'array'],
            'parametros.*.clave'   => ['required', 'string', 'exists:configuracion,clave'],
            'parametros.*.valor'   => ['nullable', 'string', 'max:255'],
        ]);

        foreach ($datos['parametros'] as $p) {
            $config = Configuracion::find($p['clave']);
            if ($config && $config->valor !== ($p['valor'] ?? '')) {
                $anterior = $config->valor;
                $config->update([
                    'valor'              => $p['valor'] ?? '',
                    'fecha_modificacion' => now(),
                    'usuario_modifico'   => $request->user()->usuario_id,
                ]);
                Auditoria::registrar('CONFIG_ACTUALIZADA', 'configuracion', $p['clave'],
                    ['valor' => $anterior], ['valor' => $p['valor'] ?? '']);
            }
        }

        return back()->with('exito', 'Configuracion actualizada.');
    }
}
