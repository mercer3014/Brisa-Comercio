<?php

use App\Http\Controllers\ActividadController;
use App\Http\Controllers\ActividadDocenteController;
use App\Http\Controllers\Auth\AutenticacionController;
use App\Http\Controllers\BitacoraController;
use App\Http\Controllers\CalidadController;
use App\Http\Controllers\CargaController;
use App\Http\Controllers\CatalogoController;
use App\Http\Controllers\ConfiguracionController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EducacionController;
use App\Http\Controllers\ExploradorController;
use App\Http\Controllers\ExploradorPublicoController;
use App\Http\Controllers\GestionDatosController;
use App\Http\Controllers\PortalController;
use App\Http\Controllers\PortalDatosController;
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
Route::middleware('throttle:120,1')->group(function () {
    Route::get('/', [PortalController::class, 'inicio'])->name('portal.inicio');
    Route::get('/portal/datos', [PortalController::class, 'datos'])->name('portal.datos');
    // Portal rediseñado (GEO-12): páginas y JSON con datos reales
    Route::get('/productos', [PortalController::class, 'productos'])->name('portal.productos');
    Route::get('/portal/v2/panorama', [PortalDatosController::class, 'panorama'])->name('portal.v2.panorama');
    Route::get('/portal/v2/vias', [PortalDatosController::class, 'vias'])->name('portal.v2.vias');
    Route::get('/portal/v2/productos', [PortalDatosController::class, 'productos'])->name('portal.v2.productos');
    Route::get('/portal/v2/productos/{id}', [PortalDatosController::class, 'ficha'])->whereNumber('id')->name('portal.v2.ficha');
    Route::get('/portal/v2/rankings', [PortalDatosController::class, 'rankings'])->name('portal.v2.rankings');
    Route::get('/portal/v2/indicadores', [PortalDatosController::class, 'indicadores'])->name('portal.v2.indicadores');
    Route::get('/explorar', [ExploradorPublicoController::class, 'index'])->name('portal.explorar');
    Route::post('/explorar/consultar', [ExploradorPublicoController::class, 'consultar'])->name('portal.explorar.consultar');
    Route::get('/rankings', [PortalController::class, 'rankings'])->name('portal.rankings');
    Route::get('/rankings/datos', [RankingController::class, 'datos'])->name('portal.rankings.datos');
    Route::get('/rankings/comparar', [RankingController::class, 'comparar'])->name('portal.rankings.comparar');
    Route::get('/organizaciones', [PortalController::class, 'organizaciones'])->name('portal.organizaciones');
    Route::get('/organizaciones/{id}', [PortalController::class, 'organizacionDetalle'])
        ->whereNumber('id')->name('portal.organizaciones.detalle');
    Route::get('/comparador', [PortalController::class, 'comparador'])->name('portal.comparador');
    Route::get('/comercio-por-via', [PortalController::class, 'comercioPorVia'])->name('portal.comercio-por-via');
    Route::get('/mapa-comercial', [PortalController::class, 'mapaComercial'])->name('portal.mapa-comercial');
    Route::get('/indicadores', [PortalController::class, 'indicadores'])->name('portal.indicadores');
    Route::get('/linea-de-tiempo', [PortalController::class, 'lineaDeTiempo'])->name('portal.linea-de-tiempo');
    Route::get('/acerca', [PortalController::class, 'acerca'])->name('portal.acerca');
});

/*
|--------------------------------------------------------------------------
| PANEL EDUCACIÓN (público, sin login) — track de educación
|--------------------------------------------------------------------------
| Lecciones, retos, glosario, historias y la cara del alumno del modo docente.
| Contenido estático + cifras en vivo de brisa. Sin asistente de IA.
*/
Route::middleware('throttle:120,1')->group(function () {
    Route::get('/aprende', [EducacionController::class, 'indice'])->name('edu.indice');
    Route::get('/aprende/glosario', [EducacionController::class, 'glosario'])->name('edu.glosario');
    Route::get('/aprende/retos', [EducacionController::class, 'retos'])->name('edu.retos');
    Route::get('/aprende/retos/{slug}', [EducacionController::class, 'reto'])->where('slug', '[a-z0-9\-]+')->name('edu.reto');
    Route::get('/aprende/retos/{slug}/abrir', [EducacionController::class, 'abrirReto'])->where('slug', '[a-z0-9\-]+')->name('edu.reto.abrir');
    Route::get('/aprende/{slug}', [EducacionController::class, 'leccion'])->where('slug', '[a-z0-9\-]+')->name('edu.leccion');
    Route::get('/historias', [EducacionController::class, 'historias'])->name('edu.historias');
    Route::get('/historias/{slug}', [EducacionController::class, 'historia'])->where('slug', '[a-z0-9\-]+')->name('edu.historia');
    Route::get('/actividad', [ActividadController::class, 'buscar'])->name('edu.actividad.buscar');
    Route::get('/actividad/{codigo}', [ActividadController::class, 'ver'])->where('codigo', '[A-Za-z0-9]{4,10}')->name('edu.actividad');
    Route::get('/portal/v2/aprende', [EducacionController::class, 'api'])->name('portal.v2.aprende');
    Route::get('/portal/v2/aprende/glosario', [EducacionController::class, 'glosarioJson'])->name('portal.v2.aprende.glosario');
    Route::get('/portal/v2/aprende/grafico/{id}', [EducacionController::class, 'grafico'])->where('id', '[a-z_]+')->name('portal.v2.aprende.grafico');
});
// Respuestas y progreso: límite propio (prefijo "edu-respuestas", no comparte contador con la
// navegación) para que no se puedan "fuerza-brutear" los retos. 60/min por IP: un curso entero
// suele salir a internet con la misma IP del colegio.
Route::middleware('throttle:60,1,edu-respuestas')->group(function () {
    Route::post('/aprende/participante', [EducacionController::class, 'participante'])->name('edu.participante');
    Route::post('/aprende/retos/{slug}/responder', [EducacionController::class, 'responder'])->where('slug', '[a-z0-9\-]+')->name('edu.reto.responder');
    Route::post('/aprende/{slug}/completar', [EducacionController::class, 'completarLeccion'])->where('slug', '[a-z0-9\-]+')->name('edu.leccion.completar');
    Route::post('/actividad/{codigo}/responder', [ActividadController::class, 'responder'])->where('codigo', '[A-Za-z0-9]{4,10}')->name('edu.actividad.responder');
});

// Exportaciones (Excel): generan archivos, más costosas que una consulta normal —
// límite más estricto para que no se usen para tumbar el servidor a fuerza de descargas.
Route::middleware('throttle:10,1')->group(function () {
    Route::get('/explorar/exportar', [ExploradorPublicoController::class, 'exportar'])->name('portal.explorar.exportar');
    Route::get('/rankings/exportar', [RankingController::class, 'exportar'])->name('portal.rankings.exportar');
});

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
        // Plantillas Excel por entidad/flujo (columnas exactas de cada cargador).
        Route::get('/cargas/plantilla/{entidad}/{flujo}', [CargaController::class, 'plantilla'])
            ->middleware('permiso:carga.ver')->name('cargas.plantilla');
        Route::get('/cargas/resumen/exportar', [CargaController::class, 'exportarResumen'])
            ->middleware(['permiso:carga.ver', 'throttle:10,1'])->name('cargas.resumen.exportar');
        Route::post('/cargas/{carga}/reintentar', [CargaController::class, 'reintentar'])
            ->whereNumber('carga')->middleware('permiso:carga.procesar')->name('cargas.reintentar');
        Route::delete('/cargas/{carga}', [CargaController::class, 'destroy'])
            ->whereNumber('carga')->middleware('permiso:carga.procesar')->name('cargas.destroy');

        // Gestión de datos (diseño nuevo): explorador de gráficos y configuración.
        Route::get('/gestion/graficos', [GestionDatosController::class, 'graficos'])
            ->middleware('permiso:explorador.ver')->name('gestion.graficos');
        Route::post('/gestion/graficos/consultar', [GestionDatosController::class, 'consultarGraficos'])
            ->middleware('permiso:explorador.ver')->name('gestion.graficos.consultar');
        Route::get('/gestion/configuracion', [GestionDatosController::class, 'configuracion'])
            ->middleware('permiso:configuracion.ver')->name('gestion.configuracion');
        Route::post('/gestion/refrescar-vistas', [GestionDatosController::class, 'refrescarVistas'])
            ->middleware('permiso:configuracion.editar')->name('gestion.refrescar');

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

        /*
        |------------------------------------------------------------------
        | Educación: modo docente (actividades con código para los alumnos)
        |------------------------------------------------------------------
        */
        Route::get('/actividades', [ActividadDocenteController::class, 'index'])
            ->middleware('permiso:actividad.ver')->name('actividades.index');
        Route::get('/actividades/nueva', [ActividadDocenteController::class, 'nueva'])
            ->middleware('permiso:actividad.crear')->name('actividades.nueva');
        Route::post('/actividades', [ActividadDocenteController::class, 'guardar'])
            ->middleware('permiso:actividad.crear')->name('actividades.guardar');
        Route::get('/actividades/{id}', [ActividadDocenteController::class, 'ver'])
            ->whereNumber('id')->middleware('permiso:actividad.ver')->name('actividades.ver');
        Route::get('/actividades/{id}/respuestas', [ActividadDocenteController::class, 'respuestas'])
            ->whereNumber('id')->middleware('permiso:actividad.ver')->name('actividades.respuestas');
        Route::patch('/actividades/{id}/estado', [ActividadDocenteController::class, 'alternar'])
            ->whereNumber('id')->middleware('permiso:actividad.crear')->name('actividades.estado');

        Route::get('/paises/{pais_id}', [PaisDashboardController::class, 'show'])
            ->name('paises.dashboard');
        Route::put('/paises/{pais_id}', [PaisDashboardController::class, 'update'])
            ->middleware('permiso:pais.editar')->name('paises.update');
    });
});
