<?php

namespace Database\Seeders;

use App\Models\Permiso;
use App\Models\Rol;
use Illuminate\Database\Seeder;

class RolPermisoSeeder extends Seeder
{
    /**
     * Catalogo de permisos por modulo. Cada entrada: [codigo, descripcion].
     */
    private function catalogoPermisos(): array
    {
        return [
            'usuarios' => [
                ['usuario.ver', 'Ver usuarios'],
                ['usuario.crear', 'Crear usuarios'],
                ['usuario.editar', 'Editar usuarios'],
                ['usuario.estado', 'Activar/desactivar usuarios'],
            ],
            'roles' => [
                ['rol.ver', 'Ver roles y permisos'],
                ['rol.crear', 'Crear roles'],
                ['rol.editar', 'Editar roles y matriz de permisos'],
            ],
            'organizaciones' => [
                ['organizacion.ver', 'Ver organizaciones'],
                ['organizacion.crear', 'Crear organizaciones'],
                ['organizacion.editar', 'Editar organizaciones'],
            ],
            'perfiles' => [
                ['perfil.ver', 'Ver perfiles de mapeo'],
                ['perfil.crear', 'Crear perfiles de mapeo'],
                ['perfil.editar', 'Editar perfiles y mapeo de columnas'],
            ],
            'cargas' => [
                ['carga.ver', 'Ver cargas de archivos'],
                ['carga.crear', 'Subir y registrar cargas'],
                ['carga.procesar', 'Procesar/reprocesar cargas (ETL)'],
            ],
            'explorador' => [
                ['explorador.ver', 'Acceder al explorador de microdatos'],
            ],
            'dashboards' => [
                ['dashboard.ver', 'Ver dashboards e indicadores'],
            ],
            'reportes' => [
                ['reporte.ver', 'Ver reportes'],
                ['reporte.exportar', 'Exportar reportes (Excel/CSV/PDF)'],
            ],
            'catalogos' => [
                ['catalogo.ver', 'Ver catalogos/dimensiones'],
                ['catalogo.editar', 'Editar catalogos/dimensiones'],
            ],
            'calidad' => [
                ['calidad.ver', 'Ver tablero de calidad de datos'],
                ['calidad.tratar', 'Tratar incidencias de calidad'],
            ],
            'configuracion' => [
                ['configuracion.ver', 'Ver configuracion del sistema'],
                ['configuracion.editar', 'Editar configuracion del sistema'],
            ],
            'bitacora' => [
                ['bitacora.ver', 'Consultar la bitacora de auditoria'],
            ],
            'paises' => [
                ['pais.editar', 'Configurar URL de Power BI por pais'],
            ],
        ];
    }

    /**
     * Permisos asignados a cada rol. 'administrador' recibe TODOS.
     */
    private function permisosPorRol(): array
    {
        return [
            'analista' => [
                'explorador.ver', 'dashboard.ver',
                'carga.ver', 'carga.crear', 'carga.procesar',
                'reporte.ver', 'reporte.exportar',
                'catalogo.ver', 'perfil.ver',
                'calidad.ver', 'calidad.tratar',
            ],
            'consultor' => [
                'explorador.ver', 'dashboard.ver', 'reporte.ver',
            ],
            'invitado' => [
                'explorador.ver', 'dashboard.ver',
            ],
        ];
    }

    public function run(): void
    {
        // 1) Permisos
        $permisos = [];
        foreach ($this->catalogoPermisos() as $modulo => $lista) {
            foreach ($lista as [$codigo, $descripcion]) {
                $permisos[$codigo] = Permiso::updateOrCreate(
                    ['codigo' => $codigo],
                    ['descripcion' => $descripcion, 'modulo' => $modulo]
                );
            }
        }

        // 2) Roles base
        $roles = [
            'administrador' => 'Acceso total al sistema.',
            'analista'      => 'Carga, procesa y analiza datos; gestiona calidad.',
            'consultor'     => 'Consulta el explorador, dashboards y reportes.',
            'invitado'      => 'Acceso de solo lectura limitado.',
        ];
        $rolesModelo = [];
        foreach ($roles as $nombre => $descripcion) {
            $rolesModelo[$nombre] = Rol::updateOrCreate(
                ['nombre' => $nombre],
                ['descripcion' => $descripcion]
            );
        }

        // 3) Matriz rol x permiso
        // administrador -> todos
        $rolesModelo['administrador']->permisos()->sync(
            collect($permisos)->pluck('permiso_id')->all()
        );

        foreach ($this->permisosPorRol() as $rol => $codigos) {
            $ids = collect($codigos)
                ->map(fn ($c) => $permisos[$c]->permiso_id ?? null)
                ->filter()
                ->values()
                ->all();
            $rolesModelo[$rol]->permisos()->sync($ids);
        }
    }
}
