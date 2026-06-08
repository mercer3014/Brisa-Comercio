<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Configuracion;
use App\Models\IntentoAcceso;
use App\Models\Usuario;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AutenticacionController extends Controller
{
    /**
     * Muestra el formulario de login (sin layout de la app).
     */
    public function mostrarLogin(): Response
    {
        return Inertia::render('Auth/Login');
    }

    /**
     * Procesa el login: valida credenciales, registra el intento en intento_acceso
     * y bloquea tras N intentos fallidos (configuracion.max_intentos_login).
     */
    public function login(Request $request): RedirectResponse
    {
        $datos = $request->validate([
            'nombre_usuario' => ['required', 'string'],
            'contrasena'     => ['required', 'string'],
        ]);

        $nombre = $datos['nombre_usuario'];
        $ip = $request->ip();

        // 1) Bloqueo por intentos fallidos recientes
        $maxIntentos = (int) Configuracion::obtener('max_intentos_login', 5);
        $ventana = (int) Configuracion::obtener('ventana_bloqueo_minutos', 15);

        $fallidosRecientes = IntentoAcceso::where('nombre_usuario_intento', $nombre)
            ->where('exito', false)
            ->where('fecha_hora', '>=', now()->subMinutes($ventana))
            ->count();

        if ($maxIntentos > 0 && $fallidosRecientes >= $maxIntentos) {
            $this->registrar($nombre, false, $ip, 'Cuenta bloqueada por intentos fallidos');
            throw ValidationException::withMessages([
                'nombre_usuario' => "Cuenta bloqueada por superar $maxIntentos intentos fallidos. Intente en $ventana minutos.",
            ]);
        }

        // 2) Buscar usuario
        $usuario = Usuario::where('nombre_usuario', $nombre)->first();

        if (! $usuario || ! Hash::check($datos['contrasena'], $usuario->hash_contrasena)) {
            $this->registrar($nombre, false, $ip, 'Credenciales incorrectas');
            throw ValidationException::withMessages([
                'nombre_usuario' => 'Usuario o contrasenia incorrectos.',
            ]);
        }

        // 3) Cuenta inactiva
        if (! $usuario->activo) {
            $this->registrar($nombre, false, $ip, 'Cuenta inactiva');
            throw ValidationException::withMessages([
                'nombre_usuario' => 'La cuenta esta inactiva. Contacte al administrador.',
            ]);
        }

        // 4) Login exitoso
        Auth::login($usuario, remember: false);
        $request->session()->regenerate();
        $request->session()->put('ultima_actividad', now()->timestamp);

        $usuario->forceFill(['ultimo_acceso' => now()])->save();

        $this->registrar($nombre, true, $ip, null);
        \App\Servicios\Auditoria::registrar('LOGIN', 'usuario', (string) $usuario->usuario_id);

        // Tras iniciar sesion, ir al panel de administracion (/admin).
        return redirect()->intended(route('admin.inicio'));
    }

    public function logout(Request $request): RedirectResponse
    {
        \App\Servicios\Auditoria::registrar('LOGOUT', 'usuario', (string) Auth::id());
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        // Tras cerrar sesion, volver al portal publico.
        return redirect()->route('portal.inicio')->with('exito', 'Sesion cerrada correctamente.');
    }

    /**
     * Registra un intento de acceso en intento_acceso.
     */
    private function registrar(string $nombre, bool $exito, ?string $ip, ?string $motivo): void
    {
        IntentoAcceso::create([
            'nombre_usuario_intento' => $nombre,
            'exito'                  => $exito,
            'ip_origen'              => $ip,
            'motivo_fallo'           => $motivo,
        ]);
    }
}
