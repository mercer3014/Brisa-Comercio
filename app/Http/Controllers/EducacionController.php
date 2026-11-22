<?php

namespace App\Http\Controllers;

use App\Servicios\Educacion\ContenidoEducativo;
use App\Servicios\Educacion\DatosEducativos;
use App\Servicios\Educacion\EvaluadorReto;
use App\Servicios\Educacion\Participantes;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Panel público "Educación" (track de educación, NASA Space Apps):
 *  1) /aprende            ruta de lecciones con gráficos reales
 *  2) /aprende/retos      retos con comprobación en vivo, puntos e insignias
 *  3) glosario + "¿Qué estoy viendo?" (contenido estático, SIN asistente de IA)
 *  5) /historias          recorridos guiados con datos
 *  6) nivel colegio / universidad en todos los textos
 * El modo docente (4) está en ActividadController y ActividadDocenteController.
 */
class EducacionController extends Controller
{
    public const PITCH = 'Los estudiantes bolivianos aprenden economía con datos reales de su propio país, no con ejemplos inventados del libro.';

    public function __construct(
        private ContenidoEducativo $contenido,
        private DatosEducativos $datos,
        private EvaluadorReto $evaluador,
        private Participantes $participantes,
    ) {
    }

    private function base(Request $r): array
    {
        return [
            'cobertura' => $this->datos->cobertura(),
            'participante' => $this->participantes->resumen($this->participantes->actual($r)),
        ];
    }

    private function nivel(Request $r): string
    {
        return in_array($r->input('nivel'), ContenidoEducativo::NIVELES, true) ? $r->input('nivel') : 'colegio';
    }

    // ------------------------------------------------------------------
    //  Índice del panel
    // ------------------------------------------------------------------

    public function indice(Request $r): Response
    {
        return Inertia::render('Portal/Aprende/Indice', $this->base($r) + [
            'pitch' => self::PITCH,
            'lecciones' => $this->contenido->resumenLecciones(),
            'retos' => $this->listaRetos(),
            'historias' => $this->contenido->resumenHistorias(),
            'insignias' => DB::table('edu_insignia')->orderBy('insignia_id')->get(['codigo', 'titulo', 'icono', 'descripcion']),
        ]);
    }

    private function listaRetos(): array
    {
        return DB::table('edu_reto')->where('publicado', true)->orderBy('orden')
            ->get(['slug', 'titulo', 'enunciado', 'puntos', 'nivel', 'tipo_respuesta'])->map(fn ($x) => (array) $x)->all();
    }

    public function participante(Request $r): JsonResponse
    {
        $d = $r->validate([
            'apodo' => ['nullable', 'string', 'max:30', 'regex:/^[\pL\pN _.\-]+$/u'],
            'nivel' => ['nullable', 'in:colegio,universidad'],
        ]);
        $p = $this->participantes->asegurar($r, isset($d['apodo']) ? trim($d['apodo']) : null, $d['nivel'] ?? null);

        return response()->json(['participante' => $this->participantes->resumen($p)]);
    }

    // ------------------------------------------------------------------
    //  1) Lecciones
    // ------------------------------------------------------------------

    public function leccion(Request $r, string $slug): Response
    {
        $l = $this->contenido->leccion($slug);
        abort_unless($l, 404);
        $todas = $this->contenido->lecciones();
        $i = $l['indice'];
        $g = (int) $r->query('gestion') ?: null;

        return Inertia::render('Portal/Aprende/Leccion', $this->base($r) + [
            'leccion' => $this->contenido->leccionResuelta($l),
            'grafico' => $this->datos->grafico($l['grafico'], $g),
            'gestiones' => $this->datos->gestiones(),
            'anterior' => $i > 0 ? ['slug' => $todas[$i - 1]['slug'], 'titulo' => $todas[$i - 1]['titulo']] : null,
            'siguiente' => isset($todas[$i + 1]) ? ['slug' => $todas[$i + 1]['slug'], 'titulo' => $todas[$i + 1]['titulo']] : null,
            'numero' => $i + 1,
            'total' => count($todas),
        ]);
    }

    public function completarLeccion(Request $r, string $slug): JsonResponse
    {
        abort_unless($this->contenido->leccion($slug), 404);
        $p = $this->participantes->asegurar($r, null, $this->nivel($r));
        $nuevas = $this->participantes->completarLeccion($p->participante_id, $slug);

        return response()->json(['ok' => true, 'insignias_nuevas' => $nuevas, 'participante' => $this->participantes->resumen($p)]);
    }

    public function grafico(Request $r, string $id): JsonResponse
    {
        abort_unless(in_array($id, ['kpis_flujos', 'fob_cif', 'balanza', 'socios', 'vias', 'canasta'], true), 404);
        $g = (int) $r->query('gestion');

        return response()->json($this->datos->grafico($id, $g > 1900 && $g < 2100 ? $g : null));
    }

    // ------------------------------------------------------------------
    //  2) Retos
    // ------------------------------------------------------------------

    public function retos(Request $r): Response
    {
        return Inertia::render('Portal/Aprende/Retos', $this->base($r) + [
            'retos' => $this->listaRetos(),
            'insignias' => DB::table('edu_insignia')->orderBy('insignia_id')->get(['codigo', 'titulo', 'icono', 'descripcion']),
            'ranking' => DB::table('edu_intento_reto as t')->join('edu_participante as p', 'p.participante_id', '=', 't.participante_id')
                ->groupBy('p.participante_id', 'p.apodo')->havingRaw('SUM(t.puntos) > 0')
                ->selectRaw('p.apodo, SUM(t.puntos) AS puntos')->orderByDesc('puntos')->limit(10)->get(),
        ]);
    }

    private function retoPorSlug(string $slug): object
    {
        $reto = DB::table('edu_reto')->where('slug', $slug)->where('publicado', true)->first();
        abort_unless($reto, 404);

        return $reto;
    }

    private function enlaceReto(object $reto): ?string
    {
        if (! $reto->pista_ruta) {
            return null;
        }
        $f = json_decode($reto->pista_filtros ?? '{}', true) ?: [];

        return $reto->pista_ruta.($f ? '?'.http_build_query($f) : '');
    }

    public function reto(Request $r, string $slug): Response
    {
        $reto = $this->retoPorSlug($slug);
        $p = $this->participantes->actual($r);
        $intentos = $p ? DB::table('edu_intento_reto')->where('reto_id', $reto->reto_id)->where('participante_id', $p->participante_id)->get() : collect();
        $orden = DB::table('edu_reto')->where('publicado', true)->orderBy('orden')->pluck('slug')->all();
        $pos = array_search($slug, $orden, true);

        return Inertia::render('Portal/Aprende/Reto', $this->base($r) + [
            'reto' => [
                'slug' => $reto->slug, 'titulo' => $reto->titulo, 'enunciado' => $reto->enunciado,
                'pista' => ['colegio' => $reto->pista_colegio, 'universidad' => $reto->pista_universidad],
                'enlace' => $this->enlaceReto($reto), 'puntos' => (int) $reto->puntos, 'nivel' => $reto->nivel,
                'tipo_respuesta' => $reto->tipo_respuesta,
            ],
            'estado' => $this->evaluador->publico(json_decode($reto->consulta_clave, true) ?: []),
            'intentos' => ['total' => $intentos->count(), 'resuelto' => $intentos->contains('correcta', true)],
            'siguiente' => $pos !== false && isset($orden[$pos + 1]) ? $orden[$pos + 1] : null,
            'numero' => $pos !== false ? $pos + 1 : null,
            'total' => count($orden),
        ]);
    }

    /** "Abrir ya filtrado": marca que el estudiante exploró el portal (insignia Explorador) y redirige. */
    public function abrirReto(Request $r, string $slug): RedirectResponse
    {
        $reto = $this->retoPorSlug($slug);
        $r->session()->put("edu.exploro.{$slug}", true);

        return redirect($this->enlaceReto($reto) ?? '/aprende/retos');
    }

    public function responder(Request $r, string $slug): JsonResponse
    {
        $d = $r->validate(['respuesta' => ['required', 'string', 'max:120'], 'nivel' => ['nullable', 'in:colegio,universidad']]);
        $reto = $this->retoPorSlug($slug);
        $nivel = $d['nivel'] ?? 'colegio';
        $p = $this->participantes->asegurar($r, null, $nivel);
        $res = $this->evaluador->comprobar(json_decode($reto->consulta_clave, true) ?: [], $d['respuesta'], $nivel);
        if (! $res['disponible']) {
            return response()->json(['disponible' => false, 'mensaje' => $res['explicacion']], 422);
        }

        $previos = DB::table('edu_intento_reto')->where('reto_id', $reto->reto_id)->where('participante_id', $p->participante_id)->get();
        $yaResuelto = $previos->contains('correcta', true);
        $fallos = $previos->where('correcta', false)->count();
        $exploro = (bool) $r->session()->get("edu.exploro.{$slug}", false);
        // Puntos solo en el primer acierto y si la respuesta no se reveló antes (2 fallos).
        $puntos = ($res['correcta'] && ! $yaResuelto && $fallos < 2) ? (int) $reto->puntos : 0;

        DB::table('edu_intento_reto')->insert([
            'reto_id' => $reto->reto_id, 'participante_id' => $p->participante_id, 'respuesta' => $d['respuesta'],
            'correcta' => $res['correcta'], 'puntos' => $puntos, 'nivel' => $nivel, 'exploro' => $exploro,
        ]);
        $nuevas = $res['correcta'] ? $this->participantes->insigniasTrasReto($p->participante_id, $reto, $exploro) : [];
        $revelar = $res['correcta'] || $fallos + 1 >= 2;

        return response()->json([
            'disponible' => true,
            'correcta' => $res['correcta'],
            'puntos' => $puntos,
            'revelada' => $revelar,
            'esperada' => $revelar ? $res['esperada'] : null,
            'explicacion' => $revelar ? $res['explicacion'] : null,
            'mensaje' => $res['correcta'] ? '¡Correcto!' : ($revelar ? 'No era esa. Mira la respuesta calculada con los datos:' : 'Todavía no. Revisa la pista y vuelve a intentarlo.'),
            'insignias_nuevas' => $nuevas,
            'participante' => $this->participantes->resumen($p),
        ]);
    }

    // ------------------------------------------------------------------
    //  3) Glosario y "¿Qué estoy viendo?"
    // ------------------------------------------------------------------

    public function glosario(Request $r): Response
    {
        return Inertia::render('Portal/Aprende/Glosario', $this->base($r) + [
            'terminos' => array_values($this->contenido->glosario()),
        ]);
    }

    public function glosarioJson(): JsonResponse
    {
        return response()->json([
            'terminos' => $this->contenido->glosario(),
            'explicaciones' => $this->contenido->explicaciones(),
        ]);
    }

    // ------------------------------------------------------------------
    //  5) Historias
    // ------------------------------------------------------------------

    public function historias(Request $r): Response
    {
        return Inertia::render('Portal/Historias/Indice', $this->base($r) + [
            'historias' => $this->contenido->resumenHistorias(),
        ]);
    }

    public function historia(Request $r, string $slug): Response
    {
        $h = $this->contenido->historiaResuelta($slug);
        abort_unless($h, 404);

        return Inertia::render('Portal/Historias/Historia', $this->base($r) + ['historia' => $h]);
    }

    // ------------------------------------------------------------------
    //  JSON resumen del panel (patrón /portal/v2/*)
    // ------------------------------------------------------------------

    public function api(): JsonResponse
    {
        return response()->json([
            'pitch' => self::PITCH,
            'cobertura' => $this->datos->cobertura(),
            'lecciones' => $this->contenido->resumenLecciones(),
            'retos' => $this->listaRetos(),
            'historias' => $this->contenido->resumenHistorias(),
        ]);
    }
}
