<?php

namespace App\Http\Controllers;

use App\Models\Configuracion;
use App\Servicios\ResumenPortal;
use App\Servicios\ResumenPortalAladi;
use App\Servicios\ResumenPortalFaostat;
use App\Servicios\ResumenPortalMercosur;
use App\Servicios\PortalVistas;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Portal público de Geodata: la cara abierta del sistema, sin login.
 *
 * Reune las vistas informativas (portada, explorador público, rankings y "acerca de").
 * El contenido analitico real se construye en las Tareas 12, 13 y 15; aquí se deja la
 * estructura de ruteo y las opciones base (organizaciones y gestiones) que comparten
 * todas las pantallas publicas para el selector de organización/anio.
 */
class PortalController extends Controller
{
    /**
     * Portada pública: titulares automáticos, indicadores grandes, rankings
     * destacados y evolución mensual (Tarea 12). Se renderiza con datos iniciales
     * para la organización por defecto (INE) y su gestión más reciente con datos.
     */
    public function inicio(PortalVistas $vistas): Response
    {
        // Rediseño GEO-12: portada estilo mockup con datos reales del INE.
        $gestion = $vistas->gestionDefecto();

        return Inertia::render('Portal/Inicio', array_merge($this->opcionesBase(), [
            'cobertura'      => $vistas->cobertura(),
            'gestionesPortal' => $vistas->gestiones(),
            'gestionInicial' => $gestion,
            'panorama'       => $gestion ? $vistas->panorama($gestion) : null,
        ]));
    }

    /**
     * Productos del comercio exterior (página nueva del mockup): treemap,
     * composición por sección SA, ranking, ficha y tabla por partida.
     */
    public function productos(PortalVistas $vistas): Response
    {
        $gestion = $vistas->gestionDefecto();

        return Inertia::render('Portal/Productos', [
            'cobertura'      => $vistas->cobertura(),
            'gestionesPortal' => $vistas->gestiones(),
            'gestionInicial' => $gestion,
            'datosIniciales' => $gestion ? $vistas->productos($gestion, 'exp') : null,
        ]);
    }

    /**
     * Datos de la portada en JSON, para refrescar al cambiar organización o gestión.
     * Público (sin autenticación), respetando SIEMPRE la organización seleccionada.
     */
    public function datos(Request $request, ResumenPortal $resumen, ResumenPortalMercosur $resumenMercosur, ResumenPortalAladi $resumenAladi, ResumenPortalFaostat $resumenFaostat): JsonResponse
    {
        $datos = $request->validate([
            'organizacion_id' => ['required', 'integer'],
            'gestion'         => ['nullable', 'integer'],
        ]);

        $orgId = (int) $datos['organizacion_id'];
        $gestion = $datos['gestion'] ?? $this->gestionMasReciente($orgId, $resumen, $resumenMercosur, $resumenAladi, $resumenFaostat);

        return response()->json($this->portada($orgId, $gestion, $resumen, $resumenMercosur, $resumenAladi, $resumenFaostat));
    }

    /**
     * INE, MERCOSUR, ALADI y FAOSTAT guardan sus datos con arquitecturas
     * distintas (microdato vs series por zona/producto vs rankings top-50 vs
     * indices agricolas): se elige el servicio según la organización, pero
     * todos devuelven exactamente la misma forma de respuesta.
     */
    private function gestionMasReciente(int $orgId, ResumenPortal $resumen, ResumenPortalMercosur $resumenMercosur, ResumenPortalAladi $resumenAladi, ResumenPortalFaostat $resumenFaostat): ?int
    {
        return match ($orgId) {
            4       => $resumenFaostat->gestionMasReciente(),
            3       => $resumenMercosur->gestionMasReciente(),
            2       => $resumenAladi->gestionMasReciente(),
            default => $resumen->gestionMasReciente($orgId),
        };
    }

    private function portada(int $orgId, ?int $gestion, ResumenPortal $resumen, ResumenPortalMercosur $resumenMercosur, ResumenPortalAladi $resumenAladi, ResumenPortalFaostat $resumenFaostat): array
    {
        return match ($orgId) {
            4       => $resumenFaostat->portada($gestion),
            3       => $resumenMercosur->portada($gestion),
            2       => $resumenAladi->portada($gestion),
            default => $resumen->portada($orgId, $gestion),
        };
    }

    /**
     * Rankings y comparadores (Tarea 13).
     */
    public function rankings(PortalVistas $vistas): Response
    {
        $g = $vistas->gestionDefecto();

        return Inertia::render('Portal/Rankings', [
            'cobertura'      => $vistas->cobertura(),
            'gestionesPortal' => $vistas->gestiones(),
            'gestionInicial' => $g,
        ]);
    }

    /**
     * Página informativa "Acerca de" el portal.
     */
    public function acerca(PortalVistas $vistas): Response
    {
        $fuentes = $vistas->estadoFuentes();

        return Inertia::render('Portal/Acerca', array_merge($this->opcionesBase(), [
            'cobertura' => $vistas->cobertura(),
            'registros' => array_sum(array_column($fuentes, 'filas')),
            'fuentesConDatos' => count(array_filter($fuentes, fn ($f) => $f['filas'] > 0)),
        ]));
    }

    /**
     * Índice de organizaciones (las 4 fuentes). La página consume la API Fase 3.
     */
    public function organizaciones(PortalVistas $vistas): Response
    {
        return Inertia::render('Portal/Organizaciones', array_merge($this->opcionesBase(), [
            'fuentes'   => $vistas->estadoFuentes(),
            'cobertura' => $vistas->cobertura(),
        ]));
    }

    /**
     * Detalle de una organización: todos sus indicadores. Recibe el id por ruta.
     */
    public function organizacionDetalle(int $id): Response
    {
        return Inertia::render('Portal/OrganizacionDetalle', array_merge($this->opcionesBase(), [
            'organizacionId' => $id,
        ]));
    }

    /**
     * Comparador de países / productos / años.
     */
    public function comparador(): Response
    {
        return Inertia::render('Portal/Comparador', $this->opcionesBase());
    }

    /**
     * Exportaciones e importaciones por vía de transporte (Marítimo /
     * Terrestre / Aéreo / Otros): a donde llevan los paneles de la portada.
     */
    public function comercioPorVia(PortalVistas $vistas): Response
    {
        // GEO-12: vista estilo mockup (tarjetas con foto por vía + desglose por medio).
        $g = $vistas->gestionDefecto();

        return Inertia::render('Portal/ComercioPorVia', [
            'cobertura'      => $vistas->cobertura(),
            'gestionesPortal' => $vistas->gestiones(),
            'gestionInicial' => $g,
            'detalle'        => $g ? $vistas->viasDetalle($g) : null,
        ]);
    }

    /**
     * Mapa comercial (choropleth mundial + departamentos).
     */
    public function mapaComercial(PortalVistas $vistas): Response
    {
        return Inertia::render('Portal/MapaComercial', array_merge($this->opcionesBase(), [
            'gestiones'      => $vistas->gestiones() ?: $this->opcionesBase()['gestiones'],
            'gestionInicial' => $vistas->gestionDefecto(),
            'cobertura'      => $vistas->cobertura(),
        ]));
    }

    /**
     * Scorecard de indicadores transversales.
     */
    public function indicadores(PortalVistas $vistas): Response
    {
        $g = $vistas->gestionDefecto();

        return Inertia::render('Portal/Indicadores', [
            'cobertura'      => $vistas->cobertura(),
            'gestionesPortal' => $vistas->gestiones(),
            'gestionInicial' => $g,
            'datosIniciales' => $g ? $vistas->indicadores($g) : null,
        ]);
    }

    /**
     * Línea de tiempo 1992-2026 con variación anual e hitos.
     */
    public function lineaDeTiempo(PortalVistas $vistas): Response
    {
        return Inertia::render('Portal/LineaDeTiempo', $vistas->lineaDeTiempo());
    }

    /**
     * Opciones compartidas por las pantallas publicas: organizaciones activas,
     * gestiones disponibles y la organización por defecto (INE).
     */
    private function opcionesBase(): array
    {
        $organizaciones = [];
        $gestiones = [];
        $orgDefecto = (int) Configuracion::obtener('organizacion_por_defecto', 1);

        try {
            $organizaciones = DB::table('organizacion')
                ->where('activo', true)
                ->orderBy('nombre')
                ->get(['organizacion_id', 'nombre', 'sigla']);

            $gestiones = DB::table('tiempo')
                ->distinct()
                ->orderByDesc('gestion')
                ->pluck('gestion');
        } catch (\Throwable $e) {
            // Si la base no responde, el portal igual carga con un mensaje amable.
        }

        return [
            'organizaciones'      => $organizaciones,
            'gestiones'           => $gestiones,
            'organizacionDefecto' => $orgDefecto,
        ];
    }
}
