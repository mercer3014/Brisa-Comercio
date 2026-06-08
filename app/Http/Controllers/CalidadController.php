<?php

namespace App\Http\Controllers;

use App\Models\CargaArchivo;
use App\Models\IncidenciaCalidad;
use App\Servicios\Auditoria;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class CalidadController extends Controller
{
    public function index(): Response
    {
        // Cargas con su conteo de incidencias por severidad.
        $cargas = CargaArchivo::query()
            ->with('organizacion:organizacion_id,sigla')
            ->withCount([
                'incidencias',
                'incidencias as incidencias_error_count' => fn ($q) => $q->where('severidad', 'ERROR'),
                'incidencias as incidencias_pendientes_count' => fn ($q) => $q->where('estado_tratamiento', 'PENDIENTE'),
            ])
            ->having('incidencias_count', '>', 0)
            ->orderByDesc('carga_id')
            ->paginate(15);

        return Inertia::render('Admin/Calidad/Index', [
            'cargas' => $cargas,
        ]);
    }

    public function show(CargaArchivo $carga): Response
    {
        $incidencias = $carga->incidencias()
            ->with('regla:regla_id,nombre')
            ->orderBy('numero_fila')
            ->paginate(50);

        return Inertia::render('Admin/Calidad/Detalle', [
            'carga'       => $carga->load('organizacion:organizacion_id,sigla,nombre'),
            'incidencias' => $incidencias,
            'resumen'     => DB::table('incidencia_calidad')->where('carga_id', $carga->carga_id)
                ->select('severidad', DB::raw('COUNT(*) as n'))->groupBy('severidad')->pluck('n', 'severidad'),
        ]);
    }

    /**
     * Marca el tratamiento de una incidencia (corregido/aceptado/descartado).
     */
    public function tratar(Request $request, IncidenciaCalidad $incidencia): RedirectResponse
    {
        $datos = $request->validate([
            'estado_tratamiento' => ['required', Rule::in(['PENDIENTE', 'CORREGIDO', 'ACEPTADO', 'DESCARTADO'])],
        ]);

        $anterior = $incidencia->estado_tratamiento;
        $incidencia->update(['estado_tratamiento' => $datos['estado_tratamiento']]);

        Auditoria::registrar('INCIDENCIA_TRATADA', 'incidencia_calidad', (string) $incidencia->incidencia_id,
            ['estado' => $anterior], ['estado' => $datos['estado_tratamiento']]);

        return back()->with('exito', 'Incidencia actualizada.');
    }
}
