<?php

namespace App\Servicios\Educacion;

/**
 * Contenido estático del panel Educación (database/data/aprende): lecciones,
 * glosario, explicaciones "¿Qué estoy viendo?" e historias. Se entrega con las
 * cifras ya resueltas en vivo (DatosEducativos::resolver) para los dos niveles
 * (colegio / universidad). Sin IA: todo es texto versionado en el repo.
 */
class ContenidoEducativo
{
    public const NIVELES = ['colegio', 'universidad'];

    public function __construct(private DatosEducativos $datos)
    {
    }

    private function ruta(string $archivo): string
    {
        return database_path('data/aprende/'.$archivo);
    }

    public function lecciones(): array
    {
        return require $this->ruta('lecciones.php');
    }

    public function leccion(string $slug): ?array
    {
        foreach ($this->lecciones() as $i => $l) {
            if ($l['slug'] === $slug) {
                return $l + ['indice' => $i];
            }
        }

        return null;
    }

    /** Dos versiones de un texto con tokens resueltos. */
    public function porNivel(string|array|null $texto): array
    {
        $out = [];
        foreach (self::NIVELES as $n) {
            $t = is_array($texto) ? ($texto[$n] ?? $texto['colegio'] ?? '') : (string) $texto;
            $out[$n] = is_array($t) ? array_map(fn ($p) => $this->datos->resolver($p, $n), $t) : $this->datos->resolver($t, $n);
        }

        return $out;
    }

    /** Lección lista para la página (textos por nivel, cifras en vivo). */
    public function leccionResuelta(array $l): array
    {
        return [
            'slug' => $l['slug'],
            'titulo' => $l['titulo'],
            'resumen' => $l['resumen'],
            'minutos' => $l['minutos'],
            'grafico' => $l['grafico'],
            'gestion_param' => $l['gestion_param'],
            'parrafos' => $this->porNivel($l['parrafos']),
            'pregunta' => $this->porNivel($l['pregunta']),
            'explorar' => $l['explorar'] ?? [],
        ];
    }

    public function resumenLecciones(): array
    {
        return array_map(fn ($l) => ['slug' => $l['slug'], 'titulo' => $l['titulo'], 'resumen' => $l['resumen'], 'minutos' => $l['minutos']], $this->lecciones());
    }

    public function glosario(): array
    {
        $out = [];
        foreach (require $this->ruta('glosario.php') as $clave => $t) {
            $out[$clave] = [
                'clave' => $clave,
                'termino' => $t['termino'],
                'corta' => $t['corta'],
                'colegio' => $this->datos->resolver($t['colegio'], 'colegio'),
                'universidad' => $this->datos->resolver($t['universidad'], 'universidad'),
                'ejemplo' => isset($t['ejemplo']) ? $this->porNivel($t['ejemplo']) : null,
                'ver' => $t['ver'] ?? null,
            ];
        }

        return $out;
    }

    public function explicaciones(): array
    {
        $out = [];
        foreach (require $this->ruta('explicaciones.php') as $clave => $t) {
            $out[$clave] = $this->porNivel($t);
        }

        return $out;
    }

    public function historias(): array
    {
        $lista = [];
        foreach (glob($this->ruta('historias/*.json')) ?: [] as $f) {
            $h = json_decode((string) file_get_contents($f), true);
            if (is_array($h) && isset($h['slug'])) {
                $lista[] = $h;
            }
        }
        usort($lista, fn ($a, $b) => ($a['orden'] ?? 99) <=> ($b['orden'] ?? 99));

        return $lista;
    }

    public function disponible(array $h): bool
    {
        return match ($h['requiere'] ?? null) {
            'mercosur' => $this->datos->filasMercosur() > 0,
            default => true,
        };
    }

    public function resumenHistorias(): array
    {
        return array_map(fn ($h) => [
            'slug' => $h['slug'], 'titulo' => $h['titulo'], 'resumen' => $h['resumen'],
            'pasos' => count($h['pasos'] ?? []), 'disponible' => $this->disponible($h), 'requiere' => $h['requiere'] ?? null,
        ], $this->historias());
    }

    public function historiaResuelta(string $slug): ?array
    {
        foreach ($this->historias() as $h) {
            if ($h['slug'] !== $slug) {
                continue;
            }

            return [
                'slug' => $h['slug'], 'titulo' => $h['titulo'], 'resumen' => $h['resumen'],
                'disponible' => $this->disponible($h), 'requiere' => $h['requiere'] ?? null,
                'pasos' => array_map(fn ($p) => [
                    'titulo' => $this->datos->resolver($p['titulo'], 'universidad'),
                    'texto' => $this->porNivel($p['texto']),
                    'grafico' => $this->datos->graficoHistoria($p['grafico'] ?? []),
                    'enlace' => $p['enlace'] ?? null,
                ], $h['pasos'] ?? []),
            ];
        }

        return null;
    }
}
