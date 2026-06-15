<?php

namespace Database\Seeders;

use App\Models\Rol;
use App\Models\Usuario;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UsuarioAdminSeeder extends Seeder
{
    public function run(): void
    {
        // Credenciales del administrador inicial: configurables por variables de entorno
        // (ADMIN_USUARIO, ADMIN_CORREO, ADMIN_PASSWORD). Si no se definen, se usan los
        // valores por defecto documentados en el README. La contrasenia se guarda SOLO
        // como hash bcrypt y se obliga a cambiarla en el primer ingreso (debe_cambiar_pwd).
        $usuario = env('ADMIN_USUARIO', 'admin');
        $correo  = env('ADMIN_CORREO', 'admin@comexhub.local');
        $clave   = env('ADMIN_PASSWORD', 'Admin12345');

        $admin = Usuario::updateOrCreate(
            ['nombre_usuario' => $usuario],
            [
                'correo'          => $correo,
                'hash_contrasena' => Hash::make($clave),
                'nombre_completo' => 'Administrador del Sistema',
                'activo'          => true,
                'debe_cambiar_pwd' => true,
            ]
        );

        $rolAdmin = Rol::where('nombre', 'administrador')->first();
        if ($rolAdmin) {
            $admin->roles()->syncWithoutDetaching([$rolAdmin->rol_id]);
        }
    }
}
