<?php

namespace App\Http\Controllers;

use App\Models\BitacoraAuditoria;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BitacoraController extends Controller
{
    public function index(Request $request): Response
    {
        $f = $request->only(['accion', 'entidad', 'desde', 'hasta']);

        $registros = BitacoraAuditoria::query()
            ->with('usuario:usuario_id,nombre_completo,nombre_usuario')
            ->when($f['accion'] ?? null, fn ($q, $v) => $q->where('accion', $v))
            ->when($f['entidad'] ?? null, fn ($q, $v) => $q->where('entidad_afectada', 'ilike', "%$v%"))
            ->when($f['desde'] ?? null, fn ($q, $v) => $q->where('fecha_hora', '>=', $v))
            ->when($f['hasta'] ?? null, fn ($q, $v) => $q->where('fecha_hora', '<=', $v.' 23:59:59'))
            ->orderByDesc('bitacora_id')
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('Admin/Bitacora/Index', [
            'registros' => $registros,
            'acciones'  => BitacoraAuditoria::distinct()->orderBy('accion')->pluck('accion'),
            'filtros'   => $f,
        ]);
    }
}
