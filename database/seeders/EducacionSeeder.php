<?php

namespace Database\Seeders;

use App\Models\Permiso;
use App\Models\Rol;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Panel Educación: siembra retos, insignias, permisos del modo docente y el rol
 * "docente". Idempotente (updateOrCreate / upsert): se puede correr varias veces.
 *
 *   php artisan db:seed --class=EducacionSeeder
 *
 * No toca datos de comercio exterior ni otros roles/permisos existentes
 * (solo AGREGA los permisos nuevos al administrador).
 */
class EducacionSeeder extends Seeder
{
    public function run(): void
    {
        // 1) Insignias
        foreach (require database_path('data/aprende/insignias.php') as $i) {
            DB::table('edu_insignia')->updateOrInsert(['codigo' => $i['codigo']], $i);
        }

        // 2) Retos (la respuesta NO se guarda: consulta_clave se evalúa en vivo)
        foreach (require database_path('data/aprende/retos.php') as $r) {
            DB::table('edu_reto')->updateOrInsert(['slug' => $r['slug']], [
                'titulo' => $r['titulo'],
                'enunciado' => $r['enunciado'],
                'pista_colegio' => $r['pista_colegio'],
                'pista_universidad' => $r['pista_universidad'],
                'pista_ruta' => $r['pista_ruta'],
                'pista_filtros' => json_encode($r['pista_filtros'] ?: new \stdClass()),
                'tipo_respuesta' => $r['tipo_respuesta'],
                'consulta_clave' => json_encode($r['consulta']),
                'puntos' => $r['puntos'],
                'nivel' => $r['nivel'],
                'insignia' => $r['insignia'],
                'orden' => $r['orden'],
                'publicado' => true,
            ]);
        }

        // 3) Permisos del modo docente
        $permisos = [];
        foreach ([
            ['actividad.ver', 'Ver actividades docentes y las respuestas de los alumnos'],
            ['actividad.crear', 'Crear, abrir y cerrar actividades docentes'],
        ] as [$codigo, $desc]) {
            $permisos[] = Permiso::updateOrCreate(['codigo' => $codigo], ['descripcion' => $desc, 'modulo' => 'educacion'])->permiso_id;
        }

        // 4) Administrador: agrega (sin quitar nada) los permisos nuevos
        if ($admin = Rol::where('nombre', 'administrador')->first()) {
            $admin->permisos()->syncWithoutDetaching($permisos);
        }

        // 5) Rol docente
        $docente = Rol::updateOrCreate(['nombre' => 'docente'], ['descripcion' => 'Docente: crea actividades con datos reales y ve las respuestas de sus alumnos.']);
        $docente->permisos()->syncWithoutDetaching($permisos);
    }
}
