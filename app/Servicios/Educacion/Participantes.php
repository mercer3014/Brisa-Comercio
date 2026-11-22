<?php

namespace App\Servicios\Educacion;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Estudiante sin cuenta (mejora 2): un apodo guardado en la sesión y en
 * edu_participante. No se piden correos ni datos personales.
 * Puntos = suma de los primeros aciertos por reto; insignias por reglas simples.
 */
class Participantes
{
    private const CLAVE = 'edu.participante_id';

    public function actual(Request $r): ?object
    {
        $id = $r->session()->get(self::CLAVE);
        if (! $id) {
            return null;
        }
        $p = DB::table('edu_participante')->where('participante_id', $id)->first();
        if (! $p) {
            $r->session()->forget(self::CLAVE);
        }

        return $p;
    }

    public function asegurar(Request $r, ?string $apodo = null, ?string $nivel = null): object
    {
        $p = $this->actual($r);
        $nivel = in_array($nivel, ContenidoEducativo::NIVELES, true) ? $nivel : null;
        if ($p) {
            $cambios = ['ultimo_acceso' => now()];
            if ($apodo) {
                $cambios['apodo'] = $apodo;
            }
            if ($nivel) {
                $cambios['nivel'] = $nivel;
            }
            DB::table('edu_participante')->where('participante_id', $p->participante_id)->update($cambios);

            return DB::table('edu_participante')->where('participante_id', $p->participante_id)->first();
        }
        $id = DB::table('edu_participante')->insertGetId([
            'apodo' => $apodo ?: 'Estudiante-'.random_int(1000, 9999),
            'token' => Str::random(32),
            'nivel' => $nivel ?? 'colegio',
        ], 'participante_id');
        $r->session()->put(self::CLAVE, $id);

        return DB::table('edu_participante')->where('participante_id', $id)->first();
    }

    public function resumen(?object $p): ?array
    {
        if (! $p) {
            return null;
        }
        $id = $p->participante_id;

        return [
            'apodo' => $p->apodo,
            'nivel' => $p->nivel,
            'puntos' => (int) DB::table('edu_intento_reto')->where('participante_id', $id)->sum('puntos'),
            'insignias' => DB::table('edu_participante_insignia as pi')->join('edu_insignia as i', 'i.insignia_id', '=', 'pi.insignia_id')
                ->where('pi.participante_id', $id)->orderBy('pi.obtenido_en')->get(['i.codigo', 'i.titulo', 'i.icono', 'i.descripcion'])->all(),
            'lecciones' => DB::table('edu_progreso_leccion')->where('participante_id', $id)->pluck('leccion_slug')->all(),
            'retos' => DB::table('edu_intento_reto as t')->join('edu_reto as r', 'r.reto_id', '=', 't.reto_id')
                ->where('t.participante_id', $id)->where('t.correcta', true)->distinct()->pluck('r.slug')->all(),
        ];
    }

    /** Otorga una insignia (idempotente). Devuelve sus datos si es nueva. */
    public function otorgar(int $participanteId, string $codigo): ?array
    {
        $ins = DB::table('edu_insignia')->where('codigo', $codigo)->first();
        if (! $ins) {
            return null;
        }
        $n = DB::affectingStatement('INSERT INTO edu_participante_insignia (participante_id, insignia_id) VALUES (?, ?) ON CONFLICT DO NOTHING', [$participanteId, $ins->insignia_id]);

        return $n ? ['codigo' => $ins->codigo, 'titulo' => $ins->titulo, 'icono' => $ins->icono, 'descripcion' => $ins->descripcion] : null;
    }

    public function insigniasTrasReto(int $id, object $reto, bool $exploro): array
    {
        $aciertos = DB::table('edu_intento_reto')->where('participante_id', $id)->where('correcta', true)->distinct()->count('reto_id');
        $nuevas = [];
        $candidatas = ['primer-acierto'];
        if ($reto->insignia) {
            $candidatas[] = $reto->insignia;
        }
        if ($exploro) {
            $candidatas[] = 'explorador';
        }
        if ($aciertos >= 5) {
            $candidatas[] = 'cinco-aciertos';
        }
        foreach ($candidatas as $c) {
            if ($i = $this->otorgar($id, $c)) {
                $nuevas[] = $i;
            }
        }

        return $nuevas;
    }

    public function completarLeccion(int $id, string $slug): array
    {
        DB::statement('INSERT INTO edu_progreso_leccion (participante_id, leccion_slug) VALUES (?, ?) ON CONFLICT DO NOTHING', [$id, $slug]);
        $n = DB::table('edu_progreso_leccion')->where('participante_id', $id)->count();

        return $n >= 3 && ($i = $this->otorgar($id, 'lector')) ? [$i] : [];
    }
}
