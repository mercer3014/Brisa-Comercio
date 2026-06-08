<?php

namespace App\Http\Controllers;

use App\Models\Rol;
use App\Models\Usuario;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class UsuarioController extends Controller
{
    public function index(Request $request): Response
    {
        $busqueda = $request->string('busqueda')->toString();

        $usuarios = Usuario::query()
            ->with('roles:rol_id,nombre')
            ->when($busqueda, function ($q) use ($busqueda) {
                $q->where(function ($s) use ($busqueda) {
                    $s->where('nombre_usuario', 'ilike', "%$busqueda%")
                        ->orWhere('nombre_completo', 'ilike', "%$busqueda%")
                        ->orWhere('correo', 'ilike', "%$busqueda%");
                });
            })
            ->orderBy('nombre_completo')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Admin/Usuarios/Index', [
            'usuarios' => $usuarios,
            'roles'    => Rol::orderBy('nombre')->get(['rol_id', 'nombre']),
            'filtros'  => ['busqueda' => $busqueda],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $datos = $request->validate([
            'nombre_usuario'  => ['required', 'string', 'max:50', 'unique:usuario,nombre_usuario'],
            'correo'          => ['required', 'email', 'max:150', 'unique:usuario,correo'],
            'nombre_completo' => ['required', 'string', 'max:150'],
            'contrasena'      => ['required', 'string', 'min:8'],
            'activo'          => ['boolean'],
            'roles'           => ['array'],
            'roles.*'         => ['integer', 'exists:rol,rol_id'],
        ]);

        $usuario = Usuario::create([
            'nombre_usuario'  => $datos['nombre_usuario'],
            'correo'          => $datos['correo'],
            'nombre_completo' => $datos['nombre_completo'],
            'hash_contrasena' => Hash::make($datos['contrasena']),
            'activo'          => $datos['activo'] ?? true,
            'debe_cambiar_pwd' => true,
        ]);

        $usuario->roles()->sync($datos['roles'] ?? []);

        return redirect()->route('usuarios.index')->with('exito', 'Usuario creado correctamente.');
    }

    public function update(Request $request, Usuario $usuario): RedirectResponse
    {
        $datos = $request->validate([
            'nombre_usuario'  => ['required', 'string', 'max:50', Rule::unique('usuario', 'nombre_usuario')->ignore($usuario->usuario_id, 'usuario_id')],
            'correo'          => ['required', 'email', 'max:150', Rule::unique('usuario', 'correo')->ignore($usuario->usuario_id, 'usuario_id')],
            'nombre_completo' => ['required', 'string', 'max:150'],
            'contrasena'      => ['nullable', 'string', 'min:8'],
            'activo'          => ['boolean'],
            'roles'           => ['array'],
            'roles.*'         => ['integer', 'exists:rol,rol_id'],
        ]);

        $usuario->nombre_usuario = $datos['nombre_usuario'];
        $usuario->correo = $datos['correo'];
        $usuario->nombre_completo = $datos['nombre_completo'];
        $usuario->activo = $datos['activo'] ?? $usuario->activo;

        if (! empty($datos['contrasena'])) {
            $usuario->hash_contrasena = Hash::make($datos['contrasena']);
            $usuario->debe_cambiar_pwd = true;
        }
        $usuario->save();

        $usuario->roles()->sync($datos['roles'] ?? []);

        return redirect()->route('usuarios.index')->with('exito', 'Usuario actualizado correctamente.');
    }

    /**
     * Activa o desactiva el usuario.
     */
    public function cambiarEstado(Usuario $usuario): RedirectResponse
    {
        $usuario->activo = ! $usuario->activo;
        $usuario->save();

        $estado = $usuario->activo ? 'activado' : 'desactivado';

        return back()->with('exito', "Usuario $estado correctamente.");
    }
}
