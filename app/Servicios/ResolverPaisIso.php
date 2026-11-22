<?php

namespace App\Servicios;

use Illuminate\Support\Facades\DB;

/**
 * Armoniza países entre fuentes con ISO 3166 (alpha-3 / alpha-2 / M49).
 *
 * La lógica vive en PostgreSQL (migración 2026_11_22_100001_crear_pais_iso) para
 * que sea una sola: la función geo_resolver_pais_iso(esquema, codigo, nombre) y el
 * trigger trg_pais_iso, que vincula cada fila de `pais` al insertarla. Por eso los
 * cargadores NO necesitan calcular el ISO: basta con crear la fila de `pais` con su
 * codigo_pais y nombre originales. Esta clase solo expone esa lógica a PHP y ayuda a
 * registrar incidencias de calidad cuando un país queda SIN_MATCH.
 *
 * Esquemas de código:
 *   - 'INE' : código de país propio del INE Bolivia (columna PAIS de los microdatos).
 *   - 'M49' : código numérico ISO 3166 / M49 de la ONU (ALADI, MERCOSUR "ISO 3166",
 *             FAOSTAT "Código del área (M49)").
 *
 * Uso típico en un cargador (ver CargadorIne / CargadorFaostat):
 *   $paisId = ... insertGetId en pais ...;            // el trigger ya puso iso_alpha3
 *   $iso->registrarIncidenciaSiSinIso($paisId, $carga->carga_id);
 *
 * Uso para datos que no pasan por `pais` (p.ej. serie_comercio_zona.pais_iso3166):
 *   $r = $iso->resolver('M49', '840', 'Estados Unidos');   // ['iso3' => 'USA', ...]
 */
class ResolverPaisIso
{
    public const ESQUEMA_INE = 'INE';
    public const ESQUEMA_M49 = 'M49';

    /** Métodos de iso_metodo que significan "no es un país" (no son error). */
    public const NO_PAIS = ['ZONA_FRANCA', 'NO_DETERMINADO'];

    private array $cacheResolver = [];
    private array $cacheIncidencias = [];

    /** Esquema de códigos que usa una organización (por sigla). */
    public static function esquemaDeSigla(?string $sigla): string
    {
        return strtoupper((string) $sigla) === 'INE' ? self::ESQUEMA_INE : self::ESQUEMA_M49;
    }

    /**
     * Resuelve un país a ISO 3166.
     *
     * @return array{iso3: ?string, iso2: ?string, m49: ?int, nombre: ?string, categoria: ?string, metodo: ?string}
     *         categoria: PAIS | ZONA_FRANCA | NO_DETERMINADO | null (sin match)
     */
    public function resolver(string $esquema, int|string|null $codigo, ?string $nombre): array
    {
        $clave = $esquema.'|'.$codigo.'|'.$nombre;
        if (isset($this->cacheResolver[$clave])) {
            return $this->cacheResolver[$clave];
        }
        $r = DB::selectOne(
            'SELECT r.iso3, i.iso2, i.m49, i.nombre_es, r.categoria, r.metodo
               FROM geo_resolver_pais_iso(?, ?, ?) r
               LEFT JOIN pais_iso i ON i.iso3 = r.iso3',
            [strtoupper($esquema), $codigo === null ? null : (string) $codigo, $nombre]
        );

        return $this->cacheResolver[$clave] = [
            'iso3'      => $r?->iso3 ? trim($r->iso3) : null,
            'iso2'      => $r?->iso2 ? trim($r->iso2) : null,
            'm49'       => $r?->m49 !== null ? (int) $r->m49 : null,
            'nombre'    => $r?->nombre_es,
            'categoria' => $r?->categoria,
            'metodo'    => $r?->metodo,
        ];
    }

    /** Atajo: solo el ISO alpha-3 (null si no es un país o no se reconoce). */
    public function iso3(string $esquema, int|string|null $codigo, ?string $nombre): ?string
    {
        return $this->resolver($esquema, $codigo, $nombre)['iso3'];
    }

    /** Normaliza un nombre igual que la BD (mayúsculas, sin tildes ni puntuación). */
    public function normalizar(?string $nombre): ?string
    {
        return DB::selectOne('SELECT geo_normalizar_nombre(?) AS n', [$nombre])?->n;
    }

    /** iso_alpha3 / iso_metodo actuales de una fila de pais. */
    public function estadoPais(int $paisId): ?object
    {
        return DB::table('pais')->where('pais_id', $paisId)->first(['pais_id', 'codigo_pais', 'nombre', 'iso_alpha3', 'iso_metodo']);
    }

    /** true si la fila de pais no se pudo vincular a ningún ISO (y no es zona franca / no determinado). */
    public function sinIso(int $paisId): bool
    {
        $p = $this->estadoPais($paisId);

        return $p !== null && $p->iso_alpha3 === null && ! in_array($p->iso_metodo, self::NO_PAIS, true) && $p->iso_metodo !== 'MANUAL';
    }

    /**
     * Si el país quedó sin ISO, registra UNA incidencia de calidad para la carga
     * (una sola vez por carga y país). Devuelve true si registró.
     */
    public function registrarIncidenciaSiSinIso(int $paisId, int $cargaId): bool
    {
        $clave = $cargaId.'|'.$paisId;
        if (isset($this->cacheIncidencias[$clave])) {
            return false;
        }
        $this->cacheIncidencias[$clave] = true;
        if (! $this->sinIso($paisId)) {
            return false;
        }
        $p = $this->estadoPais($paisId);
        DB::table('incidencia_calidad')->insert([
            'carga_id'           => $cargaId,
            'descripcion'        => $this->textoIncidencia($p->codigo_pais, $p->nombre),
            'severidad'          => 'ADVERTENCIA',
            'numero_fila'        => null,
            'valor_detectado'    => mb_substr($p->codigo_pais.' | '.$p->nombre, 0, 255),
            'estado_tratamiento' => 'PENDIENTE',
        ]);

        return true;
    }

    public function textoIncidencia(int|string|null $codigo, ?string $nombre): string
    {
        return mb_substr("País sin código ISO 3166: \"{$nombre}\" (código {$codigo}). Agregar un alias en pais_iso_alias o un código en pais_iso_codigo y ejecutar php artisan geodata:paises-iso --revincular.", 0, 255);
    }

    /**
     * Vuelve a resolver todas las filas de pais que no sean MANUAL (p.ej. tras agregar
     * alias o códigos). Devuelve cuántas filas quedaron con ISO.
     */
    public function revincularTodos(bool $incluirManuales = false): int
    {
        DB::table('pais')
            ->when(! $incluirManuales, fn ($q) => $q->where(fn ($w) => $w->whereNull('iso_metodo')->orWhere('iso_metodo', '<>', 'MANUAL')))
            ->update(['iso_metodo' => null]);
        $this->cacheResolver = [];

        return DB::table('pais')->whereNotNull('iso_alpha3')->count();
    }

    /** Cobertura por fuente y lista de países sin ISO. */
    public function reporte(): array
    {
        $cobertura = DB::select(
            "SELECT fuente_sigla, count(*) AS total,
                    count(*) FILTER (WHERE categoria = 'PAIS') AS con_iso,
                    count(*) FILTER (WHERE categoria IN ('ZONA_FRANCA','NO_DETERMINADO')) AS no_pais,
                    count(*) FILTER (WHERE categoria = 'SIN_ISO') AS sin_iso
               FROM v_pais_canonico GROUP BY fuente_sigla ORDER BY fuente_sigla"
        );
        $sinIso = DB::select(
            "SELECT pais_id, fuente_sigla, codigo_pais, nombre_fuente, iso_metodo
               FROM v_pais_canonico WHERE categoria = 'SIN_ISO' ORDER BY fuente_sigla, nombre_fuente"
        );

        return ['cobertura' => $cobertura, 'sin_iso' => $sinIso];
    }
}
