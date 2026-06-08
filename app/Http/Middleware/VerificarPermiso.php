<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware de autorizacion por permiso.
 * Uso en rutas: ->middleware('permiso:reporte.exportar')
 * El permiso se deriva de los roles del usuario (usuario_rol -> rol_permiso -> permiso).
 */
class VerificarPermiso
{
    public function handle(Request $request, Closure $next, string $permiso): Response
    {
        $usuario = $request->user();

        if (! $usuario) {
            return redirect()->route('login');
        }

        if (! $usuario->tienePermiso($permiso)) {
            abort(403, 'No tiene el permiso requerido: '.$permiso);
        }

        return $next($request);
    }
}
