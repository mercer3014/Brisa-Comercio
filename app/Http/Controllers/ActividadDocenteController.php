<?php

namespace App\Http\Controllers;

use App\Servicios\Auditoria;
use App\Servicios\Educacion\DatosEducativos;
use App\Servicios\Educacion\EvaluadorReto;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Modo docente, cara del PROFESOR (mejora 4), dentro del panel /admin.
 * Permisos: actividad.ver (listar, ver respuestas) y actividad.crear.
 * Un docente ve solo sus actividades; el administrador ve todas.
 */
class ActividadDocenteController extends Controller
{
    private const PAGINAS = [
        '/rankings' => 'Rankings y comparador',
        '/indicadores' => 'Indicadores',
        '/linea-de-tiempo' => 'Línea de tiempo',
        '/comercio-por-via' => 'Comercio por vía',
        '/productos' => 'Productos',
        '/mapa-comercial' => 'Mapa comercial',
        '/explorar' => 'Explorador de datos',
        '/aprende' => 'Aprende (lecciones)',
        '/historias' => 'Historias con datos',
    ];

    public function __construct(private EvaluadorReto $evaluador, private DatosEducativos $datos)
    {
    }

    private function esAdmin(Request $r): bool
    {
        return (bool) $r->user()?->tieneRol('administrador');
    }

    private function cargar(Request $r, int $id): object
    {
        $a = DB::table('edu_actividad')->where('actividad_id', $id)->first();
        abort_unless($a, 404);
        abort_unless($this->esAdmin($r) || (int) $a->docente_usuario_id === (int) $r->user()->usuario_id, 403, 'Esta actividad es de otro docente.');

        return $a;
    }

    public function index(Request $r): Response
    {
        $q = DB::table('edu_actividad as a')->leftJoin('usuario as u', 'u.usuario_id', '=', 'a.docente_usuario_id')
            ->selectRaw('a.actividad_id, a.codigo_share, a.titulo, a.nivel, a.activa, a.creada_en, a.cierra_en, u.nombre_completo AS docente,
                (SELECT COUNT(*) FROM edu_actividad_pregunta p WHERE p.actividad_id = a.actividad_id) AS preguntas,
                (SELECT COUNT(*) FROM edu_actividad_participante x WHERE x.actividad_id = a.actividad_id) AS participantes')
            ->orderByDesc('a.creada_en');
        if (! $this->esAdmin($r)) {
            $q->where('a.docente_usuario_id', $r->user()->usuario_id);
        }

        return Inertia::render('Educacion/Actividades/Index', [
            'actividades' => $q->get(),
            'puedeCrear' => $r->user()->tienePermiso('actividad.crear'),
        ]);
    }

    public function nueva(): Response
    {
        return Inertia::render('Educacion/Actividades/Nueva', [
            'tipos' => EvaluadorReto::TIPOS,
            'paginas' => self::PAGINAS,
            'gestiones' => $this->datos->gestiones(),
            'cobertura' => $this->datos->cobertura(),
            'retos' => DB::table('edu_reto')->where('publicado', true)->orderBy('orden')->get(['slug', 'titulo', 'enunciado', 'consulta_clave'])
                ->map(fn ($x) => ['slug' => $x->slug, 'titulo' => $x->titulo, 'enunciado' => $x->enunciado, 'consulta' => json_decode($x->consulta_clave, true)]),
        ]);
    }

    private function codigoUnico(): string
    {
        $alfabeto = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; // sin 0/O ni 1/I
        do {
            $c = '';
            for ($i = 0; $i < 6; $i++) {
                $c .= $alfabeto[random_int(0, strlen($alfabeto) - 1)];
            }
        } while (DB::table('edu_actividad')->where('codigo_share', $c)->exists());

        return $c;
    }

    public function guardar(Request $r): RedirectResponse
    {
        $d = $r->validate([
            'titulo' => ['required', 'string', 'max:160'],
            'consigna' => ['required', 'string', 'max:4000'],
            'nivel' => ['required', 'in:colegio,universidad'],
            'cierra_en' => ['nullable', 'date'],
            'enlace.pagina' => ['nullable', 'in:'.implode(',', array_keys(self::PAGINAS))],
            'enlace.gestion' => ['nullable', 'integer', 'between:1990,2100'],
            'enlace.anios' => ['nullable', 'string', 'max:60'],
            'enlace.flujo' => ['nullable', 'in:exp,imp'],
            'enlace.dimension' => ['nullable', 'in:producto,pais,departamento,seccion'],
            'preguntas' => ['required', 'array', 'min:1', 'max:20'],
            'preguntas.*.enunciado' => ['required', 'string', 'max:1000'],
            'preguntas.*.tipo' => ['required', 'in:dato,opcion,abierta'],
            'preguntas.*.consulta' => ['nullable', 'array'],
            'preguntas.*.consulta.tipo' => ['nullable', 'in:'.implode(',', array_keys(EvaluadorReto::TIPOS))],
            'preguntas.*.consulta.gestion' => ['nullable', 'integer', 'between:1990,2100'],
            'preguntas.*.consulta.desde' => ['nullable', 'integer', 'between:1990,2100'],
            'preguntas.*.consulta.hasta' => ['nullable', 'integer', 'between:1990,2100'],
            'preguntas.*.consulta.flujo' => ['nullable', 'in:exp,imp'],
            'preguntas.*.consulta.tolerancia' => ['nullable', 'numeric', 'between:0,50'],
            'preguntas.*.opciones' => ['nullable', 'array', 'max:8'],
            'preguntas.*.opciones.*' => ['nullable', 'string', 'max:200'],
            'preguntas.*.correcta' => ['nullable', 'integer', 'min:0'],
        ]);

        // Enlace a los filtros prearmados del portal
        $enlace = null;
        if ($pag = data_get($d, 'enlace.pagina')) {
            $qs = array_filter([
                'gestion' => data_get($d, 'enlace.gestion'),
                'anios' => data_get($d, 'enlace.anios') ? preg_replace('/[^0-9,]/', '', data_get($d, 'enlace.anios')) : null,
                'flujo' => data_get($d, 'enlace.flujo'),
                'dimension' => data_get($d, 'enlace.dimension'),
            ]);
            $enlace = $pag.($qs ? '?'.http_build_query($qs) : '');
        }

        $id = DB::transaction(function () use ($d, $r, $enlace) {
            $id = DB::table('edu_actividad')->insertGetId([
                'docente_usuario_id' => $r->user()->usuario_id,
                'codigo_share' => $this->codigoUnico(),
                'titulo' => $d['titulo'],
                'consigna' => $d['consigna'],
                'nivel' => $d['nivel'],
                'configuracion' => json_encode(['enlace' => $enlace] + (array) ($d['enlace'] ?? [])),
                'cierra_en' => $d['cierra_en'] ?? null,
            ], 'actividad_id');
            foreach (array_values($d['preguntas']) as $i => $p) {
                $esp = match ($p['tipo']) {
                    'dato' => ['consulta' => array_filter($p['consulta'] ?? [], fn ($v) => $v !== null && $v !== '')],
                    'opcion' => ['opciones' => array_values(array_filter($p['opciones'] ?? [], fn ($o) => trim((string) $o) !== '')), 'correcta' => (int) ($p['correcta'] ?? 0)],
                    default => new \stdClass(),
                };
                DB::table('edu_actividad_pregunta')->insert([
                    'actividad_id' => $id, 'enunciado' => $p['enunciado'], 'tipo' => $p['tipo'],
                    'respuesta_esperada' => json_encode($esp), 'orden' => $i + 1,
                ]);
            }

            return $id;
        });
        Auditoria::registrar('ACTIVIDAD_CREADA', 'edu_actividad', (string) $id, null, ['titulo' => $d['titulo']]);

        return redirect('/admin/actividades/'.$id)->with('exito', 'Actividad creada. Comparte el código con tus alumnos.');
    }

    private function preguntasConEstadistica(object $a): array
    {
        return DB::table('edu_actividad_pregunta as p')->where('p.actividad_id', $a->actividad_id)->orderBy('p.orden')
            ->selectRaw('p.*, (SELECT COUNT(*) FROM edu_actividad_respuesta r WHERE r.pregunta_id = p.pregunta_id AND COALESCE(r.valor, \'\') <> \'\') AS respondidas,
                (SELECT COUNT(*) FROM edu_actividad_respuesta r WHERE r.pregunta_id = p.pregunta_id AND r.correcta) AS correctas')
            ->get()->map(function ($p) {
                $esp = json_decode($p->respuesta_esperada ?? '{}', true) ?: [];
                $esperada = null;
                if ($p->tipo === 'dato') {
                    $e = $this->evaluador->evaluar($esp['consulta'] ?? []);
                    $esperada = $e['disponible'] ? $e['etiqueta'].' (calculado en vivo)' : 'No disponible: '.$e['motivo'];
                } elseif ($p->tipo === 'opcion') {
                    $esperada = $esp['opciones'][$esp['correcta'] ?? 0] ?? null;
                }

                return [
                    'pregunta_id' => $p->pregunta_id, 'orden' => $p->orden, 'enunciado' => $p->enunciado, 'tipo' => $p->tipo,
                    'esperada' => $esperada, 'respondidas' => (int) $p->respondidas, 'correctas' => (int) $p->correctas,
                    'pct' => $p->tipo !== 'abierta' && $p->respondidas ? round($p->correctas / $p->respondidas * 100) : null,
                    'abiertas' => $p->tipo === 'abierta' ? DB::table('edu_actividad_respuesta as r')->join('edu_actividad_participante as x', 'x.participante_id', '=', 'r.participante_id')
                        ->where('r.pregunta_id', $p->pregunta_id)->whereRaw("COALESCE(r.valor,'') <> ''")->orderByDesc('r.enviado_en')->limit(50)->get(['x.alias', 'r.valor', 'r.enviado_en']) : [],
                ];
            })->all();
    }

    public function ver(Request $r, int $id): Response
    {
        $a = $this->cargar($r, $id);

        return Inertia::render('Educacion/Actividades/Detalle', [
            'actividad' => array_merge((array) $a, ['configuracion' => json_decode($a->configuracion ?? '{}', true)]),
            'url' => url('/actividad/'.$a->codigo_share),
            'participantes' => DB::table('edu_actividad_participante')->where('actividad_id', $a->actividad_id)->count(),
            'preguntas' => $this->preguntasConEstadistica($a),
            'puedeCrear' => $r->user()->tienePermiso('actividad.crear'),
            'pestana' => 'resumen',
        ]);
    }

    private function matriz(object $a): array
    {
        $preg = DB::table('edu_actividad_pregunta')->where('actividad_id', $a->actividad_id)->orderBy('orden')->get(['pregunta_id', 'orden', 'enunciado', 'tipo']);
        $parts = DB::table('edu_actividad_participante')->where('actividad_id', $a->actividad_id)->orderBy('creado_en')->get();
        $resp = DB::table('edu_actividad_respuesta as r')->join('edu_actividad_participante as x', 'x.participante_id', '=', 'r.participante_id')
            ->where('x.actividad_id', $a->actividad_id)->get(['r.participante_id', 'r.pregunta_id', 'r.valor', 'r.correcta'])
            ->groupBy('participante_id');
        $filas = $parts->map(fn ($x) => [
            'alias' => $x->alias, 'enviado' => $x->creado_en,
            'respuestas' => $preg->map(function ($q) use ($resp, $x) {
                $v = collect($resp[$x->participante_id] ?? [])->firstWhere('pregunta_id', $q->pregunta_id);

                return ['valor' => $v->valor ?? null, 'correcta' => $v->correcta ?? null];
            })->all(),
            'aciertos' => collect($resp[$x->participante_id] ?? [])->where('correcta', true)->count(),
        ])->all();

        return [$preg, $filas];
    }

    public function respuestas(Request $r, int $id): Response|StreamedResponse
    {
        $a = $this->cargar($r, $id);
        [$preg, $filas] = $this->matriz($a);

        if ($r->query('formato') === 'csv') {
            return response()->streamDownload(function () use ($preg, $filas) {
                $out = fopen('php://output', 'w');
                fwrite($out, "\xEF\xBB\xBF");
                fputcsv($out, array_merge(['Alias', 'Enviado', 'Aciertos'], $preg->map(fn ($q) => 'P'.$q->orden.' '.mb_strimwidth($q->enunciado, 0, 60, '…'))->all()), ';');
                foreach ($filas as $f) {
                    fputcsv($out, array_merge([$f['alias'], $f['enviado'], $f['aciertos']], array_map(fn ($x) => ($x['valor'] ?? '').($x['correcta'] === null ? '' : ($x['correcta'] ? ' (✓)' : ' (✗)')), $f['respuestas'])), ';');
                }
                fclose($out);
            }, 'actividad_'.$a->codigo_share.'_respuestas.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
        }

        return Inertia::render('Educacion/Actividades/Detalle', [
            'actividad' => array_merge((array) $a, ['configuracion' => json_decode($a->configuracion ?? '{}', true)]),
            'url' => url('/actividad/'.$a->codigo_share),
            'participantes' => count($filas),
            'preguntas' => $preg,
            'filas' => $filas,
            'puedeCrear' => $r->user()->tienePermiso('actividad.crear'),
            'pestana' => 'respuestas',
        ]);
    }

    public function alternar(Request $r, int $id): RedirectResponse
    {
        $a = $this->cargar($r, $id);
        DB::table('edu_actividad')->where('actividad_id', $id)->update(['activa' => ! $a->activa]);
        Auditoria::registrar($a->activa ? 'ACTIVIDAD_CERRADA' : 'ACTIVIDAD_ABIERTA', 'edu_actividad', (string) $id);

        return back()->with('exito', $a->activa ? 'Actividad cerrada: los alumnos ya no pueden responder.' : 'Actividad abierta otra vez.');
    }
}
