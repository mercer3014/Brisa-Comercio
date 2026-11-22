<?php

namespace App\Http\Controllers;

use App\Servicios\Educacion\DatosEducativos;
use App\Servicios\Educacion\EvaluadorReto;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Modo docente, cara del ALUMNO (mejora 4): entra con el código corto que le
 * dio su profesor, ve la consigna y el enlace a los filtros ya armados, y
 * responde con un alias (sin cuenta, sin correo: pueden ser menores de edad).
 */
class ActividadController extends Controller
{
    public function __construct(private EvaluadorReto $evaluador, private DatosEducativos $datos)
    {
    }

    public static function normalizarCodigo(?string $c): string
    {
        return strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) $c));
    }

    public function buscar(Request $r): RedirectResponse
    {
        $c = self::normalizarCodigo($r->query('codigo'));

        return $c ? redirect('/actividad/'.$c) : redirect('/aprende');
    }

    private function actividad(string $codigo): ?object
    {
        return DB::table('edu_actividad')->where('codigo_share', self::normalizarCodigo($codigo))->first();
    }

    private function abierta(object $a): bool
    {
        return $a->activa && (! $a->cierra_en || now()->lt($a->cierra_en));
    }

    public function ver(Request $r, string $codigo): Response
    {
        $a = $this->actividad($codigo);
        if (! $a) {
            return Inertia::render('Portal/Actividad/Ver', ['actividad' => null, 'codigo' => self::normalizarCodigo($codigo), 'cobertura' => $this->datos->cobertura()]);
        }
        $conf = json_decode($a->configuracion ?? '{}', true) ?: [];
        $preguntas = DB::table('edu_actividad_pregunta')->where('actividad_id', $a->actividad_id)->orderBy('orden')->get()->map(function ($p) {
            $esp = json_decode($p->respuesta_esperada ?? '{}', true) ?: [];
            $q = ['pregunta_id' => $p->pregunta_id, 'enunciado' => $p->enunciado, 'tipo' => $p->tipo, 'disponible' => true, 'opciones' => [], 'unidad' => null];
            if ($p->tipo === 'dato') {
                $pub = $this->evaluador->publico($esp['consulta'] ?? []);
                $q['disponible'] = $pub['disponible'];
                $q['motivo'] = $pub['motivo'] ?? null;
                $q['respuesta'] = $pub['tipo'] ?? null;
                $q['opciones'] = $pub['opciones'] ?? [];
                $q['unidad'] = $pub['unidad'] ?? null;
            } elseif ($p->tipo === 'opcion') {
                $q['respuesta'] = 'opcion';
                $q['opciones'] = array_map(fn ($o, $i) => ['valor' => (string) $i, 'etiqueta' => $o], $esp['opciones'] ?? [], array_keys($esp['opciones'] ?? []));
            } else {
                $q['respuesta'] = 'abierta';
            }

            return $q;
        })->all();

        return Inertia::render('Portal/Actividad/Ver', [
            'cobertura' => $this->datos->cobertura(),
            'codigo' => $a->codigo_share,
            'actividad' => [
                'titulo' => $a->titulo, 'consigna' => $a->consigna, 'nivel' => $a->nivel,
                'enlace' => $conf['enlace'] ?? null, 'abierta' => $this->abierta($a),
                'cierra_en' => $a->cierra_en,
            ],
            'preguntas' => $preguntas,
            'yaRespondio' => (bool) $r->session()->get("edu.actividad.{$a->actividad_id}"),
        ]);
    }

    public function responder(Request $r, string $codigo): JsonResponse
    {
        $a = $this->actividad($codigo);
        abort_unless($a, 404);
        if (! $this->abierta($a)) {
            return response()->json(['mensaje' => 'La actividad está cerrada.'], 422);
        }
        if ($r->session()->get("edu.actividad.{$a->actividad_id}")) {
            return response()->json(['mensaje' => 'Ya enviaste tus respuestas para esta actividad desde este navegador.'], 422);
        }
        $d = $r->validate([
            'alias' => ['required', 'string', 'max:40', 'regex:/^[\pL\pN _.\-]+$/u'],
            'respuestas' => ['array'],
            'respuestas.*' => ['nullable', 'string', 'max:2000'],
        ]);
        $preguntas = DB::table('edu_actividad_pregunta')->where('actividad_id', $a->actividad_id)->orderBy('orden')->get();

        $resultados = DB::transaction(function () use ($a, $d, $preguntas) {
            $pid = DB::table('edu_actividad_participante')->insertGetId(['actividad_id' => $a->actividad_id, 'alias' => trim($d['alias'])], 'participante_id');
            $out = [];
            foreach ($preguntas as $p) {
                $valor = trim((string) ($d['respuestas'][$p->pregunta_id] ?? ''));
                $esp = json_decode($p->respuesta_esperada ?? '{}', true) ?: [];
                $correcta = null;
                $explicacion = null;
                if ($valor !== '' && $p->tipo === 'dato') {
                    $c = $this->evaluador->comprobar($esp['consulta'] ?? [], $valor, $a->nivel);
                    $correcta = $c['disponible'] ? $c['correcta'] : null;
                    $explicacion = $c['explicacion'];
                } elseif ($valor !== '' && $p->tipo === 'opcion') {
                    $correcta = (string) ($esp['correcta'] ?? '') === $valor;
                }
                DB::table('edu_actividad_respuesta')->insert(['pregunta_id' => $p->pregunta_id, 'participante_id' => $pid, 'valor' => $valor, 'correcta' => $correcta]);
                $out[] = ['pregunta_id' => $p->pregunta_id, 'correcta' => $correcta, 'explicacion' => $explicacion];
            }

            return $out;
        });
        $r->session()->put("edu.actividad.{$a->actividad_id}", true);

        return response()->json(['ok' => true, 'resultados' => $resultados]);
    }
}
