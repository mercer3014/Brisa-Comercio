<?php

namespace App\Http\Controllers;

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
}
