<?php

namespace App\Http\Controllers;

use App\Models\Configuracion;
use App\Servicios\ResumenPortal;
use App\Servicios\ResumenPortalAladi;
use App\Servicios\ResumenPortalFaostat;
use App\Servicios\ResumenPortalMercosur;
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
    public function inicio(ResumenPortal $resumen, ResumenPortalMercosur $resumenMercosur, ResumenPortalAladi $resumenAladi, ResumenPortalFaostat $resumenFaostat): Response
    {
        $base = $this->opcionesBase();
        $orgId = $base['organizacionDefecto'];
        $gestion = $this->gestionMasReciente($orgId, $resumen, $resumenMercosur, $resumenAladi, $resumenFaostat);

        return Inertia::render('Portal/Inicio', array_merge($base, [
            'gestionInicial' => $gestion,
            'portada'        => $this->portada($orgId, $gestion, $resumen, $resumenMercosur, $resumenAladi, $resumenFaostat),
        ]));
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
    public function rankings(ResumenPortal $resumen): Response
    {
        $base = $this->opcionesBase();

        return Inertia::render('Portal/Rankings', array_merge($base, [
            'gestionInicial' => $resumen->gestionMasReciente($base['organizacionDefecto']),
        ]));
    }

    /**
     * Página informativa "Acerca de" el portal.
     */
    public function acerca(): Response
    {
        return Inertia::render('Portal/Acerca', $this->opcionesBase());
    }

    /**
     * Índice de organizaciones (las 4 fuentes). La página consume la API Fase 3.
     */
    public function organizaciones(): Response
    {
        return Inertia::render('Portal/Organizaciones', $this->opcionesBase());
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
    public function comercioPorVia(): Response
    {
        return Inertia::render('Portal/ComercioPorVia', $this->opcionesBase());
    }

    /**
     * Mapa comercial (choropleth mundial + departamentos).
     */
    public function mapaComercial(): Response
    {
        return Inertia::render('Portal/MapaComercial', $this->opcionesBase());
    }

    /**
     * Scorecard de indicadores transversales.
     */
    public function indicadores(): Response
    {
        return Inertia::render('Portal/Indicadores', $this->opcionesBase());
    }

    /**
     * Línea de tiempo 1992-2026 con variación anual e hitos.
     */
    public function lineaDeTiempo(): Response
    {
        return Inertia::render('Portal/LineaDeTiempo', $this->opcionesBase());
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
