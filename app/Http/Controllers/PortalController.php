<?php

namespace App\Http\Controllers;

use App\Models\Configuracion;
use App\Servicios\ResumenPortal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Portal publico de Geodata: la cara abierta del sistema, sin login.
 *
 * Reune las vistas informativas (portada, explorador publico, rankings y "acerca de").
 * El contenido analitico real se construye en las Tareas 12, 13 y 15; aqui se deja la
 * estructura de ruteo y las opciones base (organizaciones y gestiones) que comparten
 * todas las pantallas publicas para el selector de organizacion/anio.
 */
class PortalController extends Controller
{
    /**
     * Portada publica: titulares automaticos, indicadores grandes, rankings
     * destacados y evolucion mensual (Tarea 12). Se renderiza con datos iniciales
     * para la organizacion por defecto (INE) y su gestion mas reciente con datos.
     */
    public function inicio(ResumenPortal $resumen): Response
    {
        $base = $this->opcionesBase();
        $orgId = $base['organizacionDefecto'];
        $gestion = $resumen->gestionMasReciente($orgId);

        return Inertia::render('Portal/Inicio', array_merge($base, [
            'gestionInicial' => $gestion,
            'portada'        => $resumen->portada($orgId, $gestion),
        ]));
    }

    /**
     * Datos de la portada en JSON, para refrescar al cambiar organizacion o gestion.
     * Publico (sin autenticacion), respetando SIEMPRE la organizacion seleccionada.
     */
    public function datos(Request $request, ResumenPortal $resumen): JsonResponse
    {
        $datos = $request->validate([
            'organizacion_id' => ['required', 'integer'],
            'gestion'         => ['nullable', 'integer'],
        ]);

        $orgId = (int) $datos['organizacion_id'];
        $gestion = $datos['gestion'] ?? $resumen->gestionMasReciente($orgId);

        return response()->json($resumen->portada($orgId, $gestion));
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
     * Pagina informativa "Acerca de" el portal.
     */
    public function acerca(): Response
    {
        return Inertia::render('Portal/Acerca', $this->opcionesBase());
    }

    /**
     * Opciones compartidas por las pantallas publicas: organizaciones activas,
     * gestiones disponibles y la organizacion por defecto (INE).
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

            $gestiones = DB::query()
                ->fromSub(function ($q) {
                    $q->from('tiempo')->distinct()->select('gestion')
                        ->union(DB::table('serie_comercio_zona')->distinct()->select('gestion'));
                }, 'g')
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
