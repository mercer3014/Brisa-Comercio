<?php

use App\Http\Controllers\Auth\AutenticacionController;
use App\Http\Controllers\BitacoraController;
use App\Http\Controllers\CalidadController;
use App\Http\Controllers\CargaController;
use App\Http\Controllers\CatalogoController;
use App\Http\Controllers\ConfiguracionController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExploradorController;
use App\Http\Controllers\ExploradorPublicoController;
use App\Http\Controllers\PortalController;
use App\Http\Controllers\RankingController;
use App\Http\Controllers\ReporteController;
use App\Http\Controllers\OrganizacionController;
use App\Http\Controllers\PerfilMapeoController;
use App\Http\Controllers\RolController;
use App\Http\Controllers\PaisDashboardController;
use App\Http\Controllers\UsuarioController;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

/*
|--------------------------------------------------------------------------
| PORTAL PUBLICO (sin autenticacion)
|--------------------------------------------------------------------------
| Es la cara abierta del sistema: lo primero que ve cualquier visitante.
| Usa el LayoutPublico. El panel de administracion vive aparte, bajo /admin.
*/
Route::get('/', [PortalController::class, 'inicio'])->name('portal.inicio');
Route::get('/portal/datos', [PortalController::class, 'datos'])->name('portal.datos');
Route::get('/explorar', [ExploradorPublicoController::class, 'index'])->name('portal.explorar');
Route::post('/explorar/consultar', [ExploradorPublicoController::class, 'consultar'])->name('portal.explorar.consultar');
Route::get('/explorar/exportar', [ExploradorPublicoController::class, 'exportar'])->name('portal.explorar.exportar');
Route::get('/rankings', [PortalController::class, 'rankings'])->name('portal.rankings');
Route::get('/rankings/datos', [RankingController::class, 'datos'])->name('portal.rankings.datos');
Route::get('/rankings/comparar', [RankingController::class, 'comparar'])->name('portal.rankings.comparar');
Route::get('/rankings/exportar', [RankingController::class, 'exportar'])->name('portal.rankings.exportar');
Route::get('/acerca', [PortalController::class, 'acerca'])->name('portal.acerca');

/*
|--------------------------------------------------------------------------
| Acceso (login de administradores/analistas)
|--------------------------------------------------------------------------
*/
Route::middleware('guest')->group(function () {
    Route::get('/acceder', [AutenticacionController::class, 'mostrarLogin'])->name('login');
    Route::post('/acceder', [AutenticacionController::class, 'login'])->name('login.intento');
});

/*
|--------------------------------------------------------------------------
| PANEL DE ADMINISTRACION (requiere autenticacion) — prefijo /admin
|--------------------------------------------------------------------------
*/
Route::middleware('auth')->group(function () {

    Route::post('/logout', [AutenticacionController::class, 'logout'])->name('logout');

    Route::prefix('admin')->group(function () {

        // Inicio del panel: prueba de conexion + bienvenida.
        Route::get('/', function () {
            $organizaciones = 0;
            $estadoBd = 'error';
            try {
                $organizaciones = DB::table('organizacion')->count();
                $estadoBd = 'conectada';
            } catch (\Throwable $e) {
                $estadoBd = 'error';
            }

            return Inertia::render('Inicio', [
                'organizaciones' => $organizaciones,
                'estadoBd'       => $estadoBd,
            ]);
        })->name('admin.inicio');

        /*
        |------------------------------------------------------------------
        | Usuarios, roles y permisos (solo con permiso)
        |------------------------------------------------------------------
        */
        Route::get('/usuarios', [UsuarioController::class, 'index'])
            ->middleware('permiso:usuario.ver')->name('usuarios.index');
        Route::post('/usuarios', [UsuarioController::class, 'store'])
            ->middleware('permiso:usuario.crear')->name('usuarios.store');
        Route::put('/usuarios/{usuario}', [UsuarioController::class, 'update'])
            ->middleware('permiso:usuario.editar')->name('usuarios.update');
        Route::patch('/usuarios/{usuario}/estado', [UsuarioController::class, 'cambiarEstado'])
            ->middleware('permiso:usuario.estado')->name('usuarios.estado');

        Route::get('/roles', [RolController::class, 'index'])
            ->middleware('permiso:rol.ver')->name('roles.index');
        Route::post('/roles', [RolController::class, 'store'])
            ->middleware('permiso:rol.crear')->name('roles.store');
        Route::put('/roles/{rol}', [RolController::class, 'update'])
            ->middleware('permiso:rol.editar')->name('roles.update');

        /*
        |------------------------------------------------------------------
        | Organizaciones
        |------------------------------------------------------------------
        */
        Route::get('/organizaciones', [OrganizacionController::class, 'index'])
            ->middleware('permiso:organizacion.ver')->name('organizaciones.index');
        Route::post('/organizaciones', [OrganizacionController::class, 'store'])
            ->middleware('permiso:organizacion.crear')->name('organizaciones.store');
        Route::put('/organizaciones/{organizacion}', [OrganizacionController::class, 'update'])
            ->middleware('permiso:organizacion.editar')->name('organizaciones.update');

        /*
        |------------------------------------------------------------------
        | Perfiles de mapeo de columnas
        |------------------------------------------------------------------
        */
        Route::get('/perfiles', [PerfilMapeoController::class, 'index'])
            ->middleware('permiso:perfil.ver')->name('perfiles.index');
        Route::get('/perfiles/{perfil}/editar', [PerfilMapeoController::class, 'edit'])
            ->middleware('permiso:perfil.ver')->name('perfiles.edit');
        Route::post('/perfiles', [PerfilMapeoController::class, 'store'])
            ->middleware('permiso:perfil.crear')->name('perfiles.store');
        Route::put('/perfiles/{perfil}', [PerfilMapeoController::class, 'update'])
            ->middleware('permiso:perfil.editar')->name('perfiles.update');
        Route::put('/perfiles/{perfil}/columnas', [PerfilMapeoController::class, 'guardarColumnas'])
            ->middleware('permiso:perfil.editar')->name('perfiles.columnas');
        Route::post('/perfiles/detectar', [PerfilMapeoController::class, 'detectar'])
            ->middleware('permiso:perfil.ver')->name('perfiles.detectar');

        /*
        |------------------------------------------------------------------
        | Cargas de archivos
        |------------------------------------------------------------------
        */
        Route::get('/cargas', [CargaController::class, 'index'])
            ->middleware('permiso:carga.ver')->name('cargas.index');
        Route::get('/cargas/nueva', [CargaController::class, 'create'])
            ->middleware('permiso:carga.crear')->name('cargas.create');
        Route::post('/cargas/previsualizar', [CargaController::class, 'previsualizar'])
            ->middleware('permiso:carga.crear')->name('cargas.previsualizar');
        Route::post('/cargas', [CargaController::class, 'store'])
            ->middleware('permiso:carga.crear')->name('cargas.store');

        /*
        |------------------------------------------------------------------
        | Explorador de microdatos (privado)
        |------------------------------------------------------------------
        */
        Route::get('/explorador', [ExploradorController::class, 'index'])
            ->middleware('permiso:explorador.ver')->name('explorador.index');
        Route::post('/explorador/consultar', [ExploradorController::class, 'consultar'])
            ->middleware('permiso:explorador.ver')->name('explorador.consultar');

        /*
        |------------------------------------------------------------------
        | Dashboards e indicadores
        |------------------------------------------------------------------
        */
        Route::get('/dashboards', [DashboardController::class, 'index'])
            ->middleware('permiso:dashboard.ver')->name('dashboards.index');
        Route::post('/dashboards/datos', [DashboardController::class, 'datos'])
            ->middleware('permiso:dashboard.ver')->name('dashboards.datos');

        /*
        |------------------------------------------------------------------
        | Reportes y exportacion
        |------------------------------------------------------------------
        */
        Route::get('/reportes', [ReporteController::class, 'index'])
            ->middleware('permiso:reporte.ver')->name('reportes.index');
        Route::post('/reportes/generar', [ReporteController::class, 'generar'])
            ->middleware('permiso:reporte.ver')->name('reportes.generar');
        Route::get('/reportes/exportar', [ReporteController::class, 'exportar'])
            ->middleware('permiso:reporte.exportar')->name('reportes.exportar');

        /*
        |------------------------------------------------------------------
        | Calidad de datos
        |------------------------------------------------------------------
        */
        Route::get('/calidad', [CalidadController::class, 'index'])
            ->middleware('permiso:calidad.ver')->name('calidad.index');
        Route::get('/calidad/{carga}', [CalidadController::class, 'show'])
            ->middleware('permiso:calidad.ver')->name('calidad.show');
        Route::patch('/calidad/incidencia/{incidencia}', [CalidadController::class, 'tratar'])
            ->middleware('permiso:calidad.tratar')->name('calidad.tratar');

        /*
        |------------------------------------------------------------------
        | Bitacora, configuracion, catalogos
        |------------------------------------------------------------------
        */
        Route::get('/bitacora', [BitacoraController::class, 'index'])
            ->middleware('permiso:bitacora.ver')->name('bitacora.index');

        Route::get('/configuracion', [ConfiguracionController::class, 'index'])
            ->middleware('permiso:configuracion.ver')->name('configuracion.index');
        Route::put('/configuracion', [ConfiguracionController::class, 'update'])
            ->middleware('permiso:configuracion.editar')->name('configuracion.update');

        Route::get('/catalogos/{catalogo}', [CatalogoController::class, 'index'])
            ->middleware('permiso:catalogo.ver')->name('catalogos.index');
        Route::put('/catalogos/{catalogo}/{id}', [CatalogoController::class, 'update'])
            ->middleware('permiso:catalogo.editar')->name('catalogos.update');

        Route::get('/paises/{pais_id}', [PaisDashboardController::class, 'show'])
            ->name('paises.dashboard');
    });
});
