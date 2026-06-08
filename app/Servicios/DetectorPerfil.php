<?php

namespace App\Servicios;

use App\Models\PerfilMapeo;

/**
 * Detecta el perfil_mapeo que mejor coincide con un conjunto de cabeceras
 * de un archivo, por interseccion de nombres de columnas.
 */
class DetectorPerfil
{
    /**
     * Normaliza un nombre de columna: mayusculas, sin espacios ni acentos ni
     * caracteres no alfanumericos. Asi "Kil Bru" y "KILBRU" coinciden.
     */
    public static function normalizar(string $nombre): string
    {
        $n = mb_strtoupper(trim($nombre));
        $n = strtr($n, ['Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U', 'Ñ' => 'N']);

        return preg_replace('/[^A-Z0-9]/', '', $n);
    }

    /**
     * Sugiere un campo canonico para una columna desconocida, usando los alias
     * conocidos. Devuelve null si no hay coincidencia.
     */
    public static function sugerirCampo(string $nombreColumna): ?string
    {
        $alias = config('comexhub.alias_columnas', []);
        $norm = self::normalizar($nombreColumna);

        return $alias[$norm] ?? null;
    }

    /**
     * Dado un arreglo de cabeceras, evalua todos los perfiles (opcionalmente
     * filtrados por organizacion y/o tipo de flujo) y devuelve el ranking.
     *
     * @return array{
     *   mejor: array|null,
     *   candidatos: array<int, array{perfil_id:int, etiqueta_version:string, tipo_flujo:string,
     *     organizacion_id:int, coincidencias:int, total_perfil:int, score:float, faltantes:array, extra:array}>
     * }
     */
    public function detectar(array $cabeceras, ?int $organizacionId = null, ?string $tipoFlujo = null): array
    {
        $cabecerasNorm = collect($cabeceras)
            ->map(fn ($c) => self::normalizar($c))
            ->filter()
            ->unique()
            ->values();

        $query = PerfilMapeo::with('columnas')->where('activo', true);
        if ($organizacionId) {
            $query->where('organizacion_id', $organizacionId);
        }
        if ($tipoFlujo) {
            $query->where('tipo_flujo', $tipoFlujo);
        }

        $candidatos = [];
        foreach ($query->get() as $perfil) {
            $colsPerfil = $perfil->columnas
                ->pluck('nombre_columna_origen')
                ->map(fn ($c) => self::normalizar($c))
                ->filter()
                ->unique()
                ->values();

            if ($colsPerfil->isEmpty()) {
                continue;
            }

            $coincidencias = $colsPerfil->intersect($cabecerasNorm)->count();
            $totalPerfil = $colsPerfil->count();

            // Score: fraccion de columnas del perfil presentes en el archivo,
            // ponderada por la fraccion de cabeceras reconocidas.
            $coberturaPerfil = $totalPerfil ? $coincidencias / $totalPerfil : 0;
            $coberturaArchivo = $cabecerasNorm->count() ? $coincidencias / $cabecerasNorm->count() : 0;
            $score = round(($coberturaPerfil * 0.6 + $coberturaArchivo * 0.4) * 100, 1);

            $candidatos[] = [
                'perfil_id'        => $perfil->perfil_id,
                'etiqueta_version' => $perfil->etiqueta_version,
                'tipo_flujo'       => $perfil->tipo_flujo,
                'organizacion_id'  => $perfil->organizacion_id,
                'coincidencias'    => $coincidencias,
                'total_perfil'     => $totalPerfil,
                'score'            => $score,
                'faltantes'        => $colsPerfil->diff($cabecerasNorm)->values()->all(),
                'extra'            => $cabecerasNorm->diff($colsPerfil)->values()->all(),
            ];
        }

        usort($candidatos, fn ($a, $b) => $b['score'] <=> $a['score']);

        return [
            'mejor'      => $candidatos[0] ?? null,
            'candidatos' => $candidatos,
        ];
    }
}
