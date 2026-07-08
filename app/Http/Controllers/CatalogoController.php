<?php

namespace App\Http\Controllers;

use App\Servicios\Auditoria;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class CatalogoController extends Controller
{
    /**
     * Catálogos editables: clave => [tabla, pk, etiqueta, campos editables, campos de búsqueda].
     */
    private function catalogos(): array
    {
        return [
            'pais'         => ['tabla' => 'pais', 'pk' => 'pais_id', 'etiqueta' => 'Paises', 'campos' => ['codigo_pais', 'nombre', 'iso_alpha2', 'iso_alpha3'], 'buscar' => ['nombre', 'iso_alpha3']],
            'producto'     => ['tabla' => 'producto', 'pk' => 'producto_id', 'etiqueta' => 'Productos (NANDINA)', 'campos' => ['codigo_nandina', 'descripcion'], 'buscar' => ['descripcion']],
            'aduana'       => ['tabla' => 'aduana', 'pk' => 'aduana_id', 'etiqueta' => 'Aduanas', 'campos' => ['codigo', 'descripcion'], 'buscar' => ['descripcion']],
            'departamento' => ['tabla' => 'departamento', 'pk' => 'departamento_id', 'etiqueta' => 'Departamentos', 'campos' => ['codigo', 'nombre'], 'buscar' => ['nombre']],
            'zona'         => ['tabla' => 'zona_geoeconomica', 'pk' => 'zona_id', 'etiqueta' => 'Zonas geoeconomicas', 'campos' => ['codigo_zona', 'descripcion'], 'buscar' => ['descripcion']],
            'medio'        => ['tabla' => 'medio_transporte', 'pk' => 'medio_id', 'etiqueta' => 'Medios de transporte', 'campos' => ['codigo', 'descripcion'], 'buscar' => ['descripcion']],
            'cuci'         => ['tabla' => 'clasificacion_cuci', 'pk' => 'cuci_id', 'etiqueta' => 'Clasificacion CUCI', 'campos' => ['codigo_cuci', 'descripcion'], 'buscar' => ['codigo_cuci', 'descripcion']],
        ];
    }

    public function index(Request $request, string $catalogo): Response
    {
        $def = $this->catalogos()[$catalogo] ?? abort(404);
        $busqueda = $request->string('busqueda')->toString();

        $registros = DB::table($def['tabla'])
            ->when($busqueda, function ($q) use ($def, $busqueda) {
                $q->where(function ($w) use ($def, $busqueda) {
                    foreach ($def['buscar'] as $campo) {
                        $w->orWhere($campo, 'ilike', "%$busqueda%");
                    }
                });
            })
            ->orderBy($def['pk'])
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Admin/Catalogos/Index', [
            'catalogoActual' => $catalogo,
            'definicion'     => ['etiqueta' => $def['etiqueta'], 'pk' => $def['pk'], 'campos' => $def['campos']],
            'catalogos'      => collect($this->catalogos())->map(fn ($c, $k) => ['clave' => $k, 'etiqueta' => $c['etiqueta']])->values(),
            'registros'      => $registros,
            'filtros'        => ['busqueda' => $busqueda],
        ]);
    }

    public function update(Request $request, string $catalogo, $id): RedirectResponse
    {
        $def = $this->catalogos()[$catalogo] ?? abort(404);

        $reglas = [];
        foreach ($def['campos'] as $campo) {
            $reglas[$campo] = ['nullable', 'string', 'max:255'];
        }
        $datos = $request->validate($reglas);

        $anterior = (array) DB::table($def['tabla'])->where($def['pk'], $id)->first();

        DB::table($def['tabla'])->where($def['pk'], $id)->update($datos);

        Auditoria::registrar('CATALOGO_EDITADO', $def['tabla'], (string) $id, $anterior, $datos);

        return back()->with('exito', 'Registro actualizado.');
    }
}
