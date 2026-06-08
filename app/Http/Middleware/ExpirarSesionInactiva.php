<?php

namespace App\Http\Middleware;

use App\Models\Configuracion;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Expira la sesion por inactividad segun configuracion.sesion_minutos_expira.
 * Guarda en la sesion la marca de tiempo de la ultima actividad.
 */
class ExpirarSesionInactiva
{
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::check()) {
            $minutos = (int) Configuracion::obtener('sesion_minutos_expira', 30);

            if ($minutos > 0) {
                $ultima = $request->session()->get('ultima_actividad');
                $ahora = now()->timestamp;

                if ($ultima && ($ahora - $ultima) > $minutos * 60) {
                    Auth::logout();
                    $request->session()->invalidate();
                    $request->session()->regenerateToken();

                    return redirect()->route('login')
                        ->with('error', 'Su sesion expiro por inactividad. Vuelva a ingresar.');
                }

                $request->session()->put('ultima_actividad', $ahora);
            }
        }

        return $next($request);
    }
}
