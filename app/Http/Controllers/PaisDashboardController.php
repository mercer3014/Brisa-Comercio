<?php

namespace App\Http\Controllers;

use App\Servicios\Auditoria;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class PaisDashboardController extends Controller
{
    public function show(int $pais_id): Response
    {
        $pais = DB::table('pais_panel')->where('pais_panel_id', $pais_id)->first(['pais_panel_id', 'nombre', 'iso_alpha2', 'powerbi_url']);

        abort_if(! $pais, 404);

        return Inertia::render('Paises/Dashboard', [
            'pais' => [
                'id'        => $pais->pais_panel_id,
                'nombre'    => $pais->nombre,
                'iso'       => $pais->iso_alpha2 ? mb_strtolower($pais->iso_alpha2) : null,
                'powerbiUrl' => $pais->powerbi_url,
            ],
        ]);
    }

    public function update(Request $request, int $pais_id): RedirectResponse
    {
        $pais = DB::table('pais_panel')->where('pais_panel_id', $pais_id)->first();
        abort_if(! $pais, 404);

        $datos = $request->validate([
            'powerbi_url' => ['nullable', 'url', 'max:2048'],
        ]);

        DB::table('pais_panel')->where('pais_panel_id', $pais_id)->update([
            'powerbi_url' => $datos['powerbi_url'] ?? null,
            'updated_at'  => now(),
        ]);

        Auditoria::registrar('PAIS_PANEL_URL_EDITADA', 'pais_panel', (string) $pais_id, (array) $pais, $datos);

        return back()->with('exito', $datos['powerbi_url'] ? 'URL de Power BI guardada.' : 'URL de Power BI eliminada.');
    }
}
