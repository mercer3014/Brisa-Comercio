<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Siembra inicial del sistema (NO toca las tablas de negocio del microdato).
     */
    public function run(): void
    {
        $this->call([
            ConfiguracionSeeder::class,
            RolPermisoSeeder::class,
            UsuarioAdminSeeder::class,
            OrganizacionIneSeeder::class,
            ReglaValidacionSeeder::class,
        ]);
    }
}
