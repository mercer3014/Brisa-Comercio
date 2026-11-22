<?php

namespace App\Http\Controllers;

use App\Servicios\ClavesCache;
use App\Servicios\ResumenGestionDatos;
use App\Support\Organizaciones;
use App\Support\ReglaValor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Pestañas "Explorador de gráficos" y "Configuración" de la Gestión de datos
 * (diseño del mockup), con datos REALES de la BD.
 */
class GestionDatosController extends Controller
{
    /**
     * Agrupaciones disponibles por fuente: clave => [etiqueta, expresión SQL de la etiqueta, joins].
     */
    private function agrupaciones(string $fuente): array
    {
        return match ($fuente) {
            'INE' => [
                'gestion'      => ['Gestión', 'o.gestion::text', ''],
                'mes'          => ['Mes', "lpad(t.mes::text, 2, '0') || ' ' || t.nombre_mes", 'JOIN tiempo t ON t.tiempo_id = o.tiempo_id'],
                'seccion'      => ['Sección SA', "sa.codigo_seccion || ' · ' || sa.descripcion", 'JOIN producto p ON p.producto_id = o.producto_id JOIN capitulo_arancelario ca ON ca.capitulo_id = p.capitulo_id JOIN seccion_arancelaria sa ON sa.seccion_id = ca.seccion_id'],
                'capitulo'     => ['Capítulo SA', "lpad(ca.codigo_capitulo::text, 2, '0') || ' · ' || ca.descripcion", 'JOIN producto p ON p.producto_id = o.producto_id JOIN capitulo_arancelario ca ON ca.capitulo_id = p.capitulo_id'],
                'partida'      => ['Partida NANDINA', "p.codigo_nandina_txt || ' · ' || p.descripcion", 'JOIN producto p ON p.producto_id = o.producto_id'],
                'pais'         => ['País', 'pa.nombre', 'JOIN pais pa ON pa.pais_id = o.pais_id'],
                'departamento' => ['Departamento', 'd.nombre', 'JOIN departamento d ON d.departamento_id = o.departamento_id'],
                'medio'        => ['Medio de transporte', 'm.descripcion', 'JOIN medio_transporte m ON m.medio_id = o.medio_id'],
                'via'          => ['Vía', 'v.descripcion', 'JOIN via_comercio v ON v.via_id = o.via_id'],
                'aduana'       => ['Aduana', "COALESCE(a.descripcion, 'Sin aduana')", 'LEFT JOIN aduana a ON a.aduana_id = o.aduana_id'],
            ],
            'MERCOSUR_PAIS' => [
                'gestion' => ['Gestión', 's.gestion::text', ''],
                'zona'    => ['Zona', 'z.descripcion', 'JOIN zona_geoeconomica z ON z.zona_id = s.zona_id'],
                'pais'    => ['País socio', "COALESCE(pa.nombre, s.pais_nombre_original, 'Sin país')", 'LEFT JOIN pais pa ON pa.pais_id = s.pais_id'],
            ],
            'MERCOSUR_ITEM' => [
                'gestion'  => ['Gestión', 's.gestion::text', ''],
                'zona'     => ['Zona', 'z.descripcion', 'JOIN zona_geoeconomica z ON z.zona_id = s.zona_id'],
                'capitulo' => ['Capítulo NCM', 'left(s.ncm_codigo, 2)', ''],
                'ncm'      => ['Ítem NCM', "s.ncm_codigo || COALESCE(' · ' || left(s.ncm_descripcion, 80), '')", ''],
            ],
            'ALADI' => [
                'gestion' => ['Gestión', 'r.gestion::text', ''],
                'item'    => ['Ítem', "r.item_codigo || COALESCE(' · ' || left(r.descripcion, 80), '')", ''],
            ],
            default => [],
        };
    }

    public function graficos(): Response
    {
        $fuentes = [];
        foreach (['INE' => 'INE · microdatos', 'MERCOSUR_PAIS' => 'MERCOSUR · por país', 'MERCOSUR_ITEM' => 'MERCOSUR · por ítem', 'ALADI' => 'ALADI · rankings'] as $k => $titulo) {
            $fuentes[] = [
                'clave'       => $k,
                'titulo'      => $titulo,
                'agrupar'     => collect($this->agrupaciones($k))->map(fn ($a, $c) => ['id' => $c, 'label' => $a[0]])->values(),
                'con_peso'    => $k !== 'ALADI',
            ];
        }

        return Inertia::render('Gestion/Graficos', [
            'fuentes'   => $fuentes,
            'gestiones' => DB::table('tiempo')->distinct()->orderBy('gestion')->pluck('gestion'),
            'zonas'     => DB::table('zona_geoeconomica as z')
                ->join('fuente_datos as f', 'f.fuente_id', '=', 'z.fuente_id')
                ->where('f.organizacion_id', Organizaciones::id(Organizaciones::MERCOSUR))
                ->where('z.codigo_zona', '<>', 0)
                ->orderBy('z.descripcion')->get(['z.zona_id as id', 'z.descripcion as label']),
        ]);
    }

    public function consultarGraficos(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'fuente'   => ['required', Rule::in(['INE', 'MERCOSUR_PAIS', 'MERCOSUR_ITEM', 'ALADI'])],
            'agrupar'  => ['required', 'string'],
            'variable' => ['required', Rule::in(['usd', 'kg'])],
            'desde'    => ['nullable', 'integer', 'min:1900', 'max:2100'],
            'hasta'    => ['nullable', 'integer', 'min:1900', 'max:2100'],
            'top'      => ['nullable', 'integer', 'min:1', 'max:100'],
            'zona_id'  => ['nullable', 'integer'],
            'filtro'   => ['nullable', 'string', 'max:100'],
        ]);

        $agr = $this->agrupaciones($datos['fuente']);
        abort_unless(isset($agr[$datos['agrupar']]), 422, 'Agrupación no válida para la fuente.');
        if ($datos['fuente'] === 'ALADI') {
            $datos['variable'] = 'usd';
        }

        $clave = 'gestion.graf.'.ClavesCache::version().'.'.md5(json_encode($datos));
        $res = Cache::remember($clave, 3600, fn () => $this->ejecutarConsulta($datos, $agr[$datos['agrupar']]));

        return response()->json($res);
    }

    private function ejecutarConsulta(array $d, array $agr): array
    {
        [, $etiqueta, $joins] = $agr;
        $where = [];
        $bind = [];
        $kg = $d['variable'] === 'kg';

        switch ($d['fuente']) {
            case 'INE':
                $from = 'operacion_comercio_exterior o';
                $where[] = 'o.organizacion_id = ?';
                $bind[] = Organizaciones::id(Organizaciones::INE);
                $f = ReglaValor::flujos();
                $exp = $kg
                    ? 'COALESCE(sum(o.peso_bruto_kg) FILTER (WHERE o.flujo_id IN ('.implode(',', $f['expo']).')), 0)'
                    : 'COALESCE(sum('.ReglaValor::expo('o').'), 0)';
                $imp = $kg
                    ? 'COALESCE(sum(o.peso_bruto_kg) FILTER (WHERE o.flujo_id IN ('.implode(',', $f['impo']).')), 0)'
                    : 'COALESCE(sum('.ReglaValor::impo('o').'), 0)';
                $colGestion = 'o.gestion';
                break;
            case 'MERCOSUR_PAIS':
            case 'MERCOSUR_ITEM':
                $from = ($d['fuente'] === 'MERCOSUR_PAIS' ? 'serie_comercio_zona' : 'serie_comercio_producto_zona').' s';
                $exp = $kg ? 'COALESCE(sum(s.volumen_export_kg), 0)' : 'COALESCE(sum(s.exportaciones_usd), 0)';
                $imp = $kg ? 'COALESCE(sum(s.volumen_import_kg), 0)' : 'COALESCE(sum(COALESCE(s.importaciones_cif_usd, s.importaciones_fob_usd)), 0)';
                $colGestion = 's.gestion';
                if (! empty($d['zona_id'])) {
                    $where[] = 's.zona_id = ?';
                    $bind[] = (int) $d['zona_id'];
                }
                break;
            case 'ALADI':
                $from = 'ranking_comercio r JOIN flujo_comercial fc ON fc.flujo_id = r.flujo_id';
                $where[] = 'r.organizacion_id = ?';
                $bind[] = Organizaciones::id(Organizaciones::ALADI);
                $where[] = 'NOT r.es_confidencial';
                $exp = "COALESCE(sum(r.valor) FILTER (WHERE fc.codigo_flujo IN ('1','3')), 0)";
                $imp = "COALESCE(sum(r.valor) FILTER (WHERE fc.codigo_flujo = '2'), 0)";
                $colGestion = 'r.gestion';
                break;
        }

        if (! empty($d['desde'])) {
            $where[] = "{$colGestion} >= ?";
            $bind[] = (int) $d['desde'];
        }
        if (! empty($d['hasta'])) {
            $where[] = "{$colGestion} <= ?";
            $bind[] = (int) $d['hasta'];
        }
        if (! empty($d['filtro'])) {
            $where[] = "({$etiqueta}) ILIKE ?";
            $bind[] = '%'.str_replace(['%', '_'], ['\%', '\_'], $d['filtro']).'%';
        }

        $whereSql = $where ? 'WHERE '.implode(' AND ', $where) : '';
        $orden = in_array($d['agrupar'], ['gestion', 'mes'], true)
            ? 'etiqueta'
            : 'GREATEST(exportaciones, importaciones) DESC';
        $top = (int) ($d['top'] ?? 15);
        $limite = in_array($d['agrupar'], ['gestion', 'mes'], true) ? '' : "LIMIT {$top}";

        $sql = "SELECT * FROM (
                    SELECT {$etiqueta} AS etiqueta, {$exp} AS exportaciones, {$imp} AS importaciones
                    FROM {$from} {$joins} {$whereSql}
                    GROUP BY 1
                ) g ORDER BY {$orden} {$limite}";

        $filas = array_map(fn ($r) => [
            'etiqueta'      => (string) ($r->etiqueta ?? 'Sin dato'),
            'exportaciones' => (float) $r->exportaciones,
            'importaciones' => (float) $r->importaciones,
            'saldo'         => (float) $r->exportaciones - (float) $r->importaciones,
        ], DB::select($sql, $bind));

        return ['filas' => $filas, 'unidad' => $kg ? 'kg' : 'USD'];
    }

    /** Pestaña Configuración: estado REAL de los datos y accesos a parámetros. */
    public function configuracion(ResumenGestionDatos $resumen): Response
    {
        $fuentes = $resumen->porFuente();
        $gestiones = array_filter(array_merge(array_column($fuentes, 'desde'), array_column($fuentes, 'hasta')));
        $ultima = DB::table('carga_archivo')->where('estado', 'COMPLETADO')->max('fecha_carga');
        $filas = array_sum(array_column($fuentes, 'filas'));

        return Inertia::render('Gestion/Configuracion', [
            'estado' => [
                'migracion'      => DB::table('migrations')->max('migration'),
                'corte'          => $ultima,
                'datos'          => $filas > 0 ? 'Datos reales cargados' : 'Sin datos cargados',
                'filas'          => $filas,
                'gestiones'      => $gestiones ? min($gestiones).'–'.max($gestiones) : null,
                'pendientes'     => DB::table('carga_archivo')->whereIn('estado', ['PENDIENTE', 'PROCESANDO'])->count(),
                'fallidas'       => DB::table('carga_archivo')->where('estado', 'FALLIDO')->count(),
                'trabajos_cola'  => $this->contarCola(),
            ],
            'fuentes'    => $fuentes,
            'parametros' => DB::table('configuracion')->orderBy('clave')->get(['clave', 'valor', 'descripcion']),
        ]);
    }

    private function contarCola(): ?int
    {
        if (config('queue.default') !== 'database') {
            return null;
        }
        try {
            return DB::table(config('queue.connections.database.table', 'jobs'))->count();
        } catch (\Throwable) {
            return null;
        }
    }

    /** Refresca las vistas de resumen y precalienta el cache. */
    public function refrescarVistas(): RedirectResponse
    {
        try {
            Artisan::call('geodata:refrescar-vistas');
            Artisan::call('geodata:calentar-cache');
        } catch (\Throwable $e) {
            report($e);

            return back()->with('error', 'No se pudieron refrescar las vistas: '.$e->getMessage());
        }

        \App\Servicios\Auditoria::registrar('VISTAS_REFRESCADAS', 'configuracion', 'vistas', null, null);

        return back()->with('exito', 'Vistas de resumen refrescadas.');
    }
}
