<?php

use App\Http\Controllers\Api\ChartDataController;
use App\Http\Controllers\Api\ComparadorController;
use App\Http\Controllers\Api\FiltroController;
use App\Http\Controllers\Api\IndicadoresController;
use App\Http\Controllers\Api\KpiController;
use App\Http\Controllers\Api\RankingController;
use App\Http\Controllers\Api\TimelineController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API pública v1 — datos JSON para ApexCharts (Fase 3)
|--------------------------------------------------------------------------
| Todas las respuestas son JSON listas para el frontend (no vistas Inertia).
| Endpoints públicos sin autenticación; los pesados usan cache + vistas
| materializadas. Prefijo efectivo: /api/v1/...
*/
Route::prefix('v1')->group(function () {

    // -- KPIs y organizaciones --------------------------------------------
    Route::get('/kpis', [KpiController::class, 'home']);
    Route::get('/kpis/{organizacion}', [KpiController::class, 'organizacion'])->whereNumber('organizacion');
    Route::get('/organizaciones', [KpiController::class, 'organizaciones']);
    Route::get('/organizaciones/{id}', [KpiController::class, 'detalle'])->whereNumber('id');

    // -- Charts INE -------------------------------------------------------
    Route::get('/charts/comercio-mensual', [ChartDataController::class, 'comercioMensual']);
    Route::get('/charts/evolucion-anual', [ChartDataController::class, 'evolucionAnual']);
    Route::get('/charts/top-productos', [ChartDataController::class, 'topProductos']);
    Route::get('/charts/top-paises', [ChartDataController::class, 'topPaises']);
    Route::get('/charts/top-departamentos', [ChartDataController::class, 'topDepartamentos']);
    Route::get('/charts/seccion-arancelaria', [ChartDataController::class, 'seccionArancelaria']);
    Route::get('/charts/transporte', [ChartDataController::class, 'transporte']);
    Route::get('/charts/tnt-evolucion', [ChartDataController::class, 'tntEvolucion']);
    Route::get('/charts/mapa-flujos', [ChartDataController::class, 'mapaFlujos']);

    // -- Charts MERCOSUR --------------------------------------------------
    Route::get('/charts/mercosur/zona', [ChartDataController::class, 'mercosurZona']);
    Route::get('/charts/mercosur/balanza', [ChartDataController::class, 'mercosurBalanza']);
    Route::get('/charts/mercosur/productos', [ChartDataController::class, 'mercosurProductos']);
    Route::get('/charts/mercosur/paises', [ChartDataController::class, 'mercosurPaises']);

    // -- ALADI ------------------------------------------------------------
    Route::get('/charts/aladi/ranking', [ChartDataController::class, 'aladiRanking']);
    Route::get('/charts/aladi/evolucion', [ChartDataController::class, 'aladiEvolucion']);
    Route::get('/charts/aladi/paises', [ChartDataController::class, 'aladiPaises']);

    // -- FAOSTAT ----------------------------------------------------------
    Route::get('/charts/faostat/evolucion', [ChartDataController::class, 'faostatEvolucion']);
    Route::get('/charts/faostat/productos', [ChartDataController::class, 'faostatProductos']);
    Route::get('/charts/faostat/filtros', [ChartDataController::class, 'faostatFiltros']);
    Route::get('/charts/faostat/{subtipo}', [ChartDataController::class, 'faostat'])
        ->whereIn('subtipo', ['poblacion', 'fertilizantes', 'subalimentacion', 'cereales']);

    // -- Rankings / indicadores / filtros ---------------------------------
    Route::get('/rankings/{tipo}', [RankingController::class, 'index']);
    Route::get('/indicadores', [IndicadoresController::class, 'index']);
    Route::get('/comparador', [ComparadorController::class, 'index']);
    Route::get('/timeline', [TimelineController::class, 'index']);
    Route::get('/filtros/paises', [FiltroController::class, 'paises']);
    Route::get('/filtros/zonas', [FiltroController::class, 'zonas']);
    Route::get('/filtros/secciones', [FiltroController::class, 'secciones']);
    Route::get('/filtros/productos', [FiltroController::class, 'productos']);
    Route::get('/filtros/gestiones', [FiltroController::class, 'gestiones']);
});
