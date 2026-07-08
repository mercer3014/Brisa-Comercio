<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * Plantilla raiz que carga la app de Inertia en la primera visita.
     */
    protected $rootView = 'app';

    /**
     * Determina la version actual de los assets.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Datos compartidos con todas las páginas de Inertia.
     * Aquí se expone el usuario autenticado y sus permisos para el frontend.
     */
    public function share(Request $request): array
    {
        $usuario = $request->user();

        return array_merge(parent::share($request), [
            'auth' => [
                'usuario' => $usuario ? [
                    'usuario_id'      => $usuario->usuario_id,
                    'nombre_usuario'  => $usuario->nombre_usuario,
                    'nombre_completo' => $usuario->nombre_completo,
                    'correo'          => $usuario->correo,
                    'roles'           => method_exists($usuario, 'roles')
                        ? $usuario->roles->pluck('nombre')
                        : [],
                    'permisos'        => method_exists($usuario, 'codigosPermisos')
                        ? $usuario->codigosPermisos()
                        : [],
                ] : null,
            ],
            'flash' => [
                'exito' => fn () => $request->session()->get('exito'),
                'error' => fn () => $request->session()->get('error'),
            ],
            'app' => [
                'nombre' => config('app.name'),
            ],
            'paises' => function () {
                try {
                    return DB::table('pais_panel')
                        ->where('activo', true)
                        ->orderBy('nombre')
                        ->get(['pais_panel_id as id', 'nombre']);
                } catch (\Throwable) {
                    return [];
                }
            },
        ]);
    }
}
