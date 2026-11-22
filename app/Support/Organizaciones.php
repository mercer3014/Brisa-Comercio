<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Resuelve el organizacion_id por SIGLA (INE, ALADI, MERCOSUR, FAOSTAT) en vez de
 * depender de IDs fijos 1/2/3/4. La sigla es UNIQUE desde la migracion
 * 2026_11_21_000001. El resultado se memoiza por proceso.
 *
 * Nota: PortalApi::ORG_* y el frontend siguen usando las constantes 1..4; la
 * migracion de siglas verifica que en esta BD coincidan (INE=1, ALADI=2, ...).
 */
final class Organizaciones
{
    public const INE      = 'INE';
    public const ALADI    = 'ALADI';
    public const MERCOSUR = 'MERCOSUR';
    public const FAOSTAT  = 'FAOSTAT';

    /** @var array<string,int> */
    private static array $cache = [];

    public static function id(string $sigla): int
    {
        $sigla = strtoupper($sigla);
        if (isset(self::$cache[$sigla])) {
            return self::$cache[$sigla];
        }
        $id = DB::table('organizacion')->whereRaw('UPPER(sigla) = ?', [$sigla])->value('organizacion_id');
        if (! $id) {
            throw new RuntimeException("No existe la organización con sigla {$sigla}.");
        }

        return self::$cache[$sigla] = (int) $id;
    }

    /** Sigla de un organizacion_id (o null si no tiene). */
    public static function sigla(int $organizacionId): ?string
    {
        $s = array_search($organizacionId, self::$cache, true);
        if ($s !== false) {
            return $s;
        }
        $s = DB::table('organizacion')->where('organizacion_id', $organizacionId)->value('sigla');
        if ($s) {
            self::$cache[strtoupper($s)] = $organizacionId;
        }

        return $s ? strtoupper($s) : null;
    }

    /** IDs de las 4 organizaciones con cargador propio (las que existan). */
    public static function conCargador(): array
    {
        return DB::table('organizacion')
            ->whereIn(DB::raw('UPPER(sigla)'), [self::INE, self::ALADI, self::MERCOSUR, self::FAOSTAT])
            ->orderBy('organizacion_id')->pluck('organizacion_id')->map(fn ($v) => (int) $v)->all();
    }

    public static function limpiarCache(): void
    {
        self::$cache = [];
    }
}
