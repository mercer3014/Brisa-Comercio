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
        // Usuario administrador inicial. La contrasenia se guarda SOLO como hash bcrypt.
        $admin = Usuario::updateOrCreate(
            ['nombre_usuario' => 'admin'],
            [
                'correo'          => 'admin@comexhub.local',
                'hash_contrasena' => Hash::make('Admin12345'),
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
