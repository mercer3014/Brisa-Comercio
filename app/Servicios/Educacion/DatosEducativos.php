<?php

namespace App\Servicios\Educacion;

use App\Servicios\ClavesCache;
use App\Servicios\PortalVistas;
use App\Support\ReglaValor;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Cifras EN VIVO para el panel Educación (lecciones, glosario, historias).
 *
 * Todo sale de las mismas vistas que el portal (PortalVistas: resumen_mensual,
 * resumen_anual_*, v_pais_canonico) o de consultas propias sobre
 * operacion_comercio_exterior con la ReglaValor única. Nunca hay números
 * escritos a mano: los textos usan {tokens} que se resuelven aquí.
 *
 * Si falta un flujo o una gestión, el token devuelve "sin datos" y el gráfico
 * se marca como sin datos (nunca un cero).
 */
class DatosEducativos
{
    public const ORG = PortalVistas::ORG_INE;

    /** Partidas NANDINA de la soya y derivados (grano, aceite, torta). */
    public const SOYA = ['1201', '1507', '2304'];

    private ?array $memoSerie = null;

    public function __construct(private PortalVistas $vistas)
    {
    }

    private function cache(string $clave, \Closure $fn)
    {
        try {
            $ver = ClavesCache::version();
        } catch (\Throwable $e) {
            $ver = 0;
        }

        return Cache::remember("edu.{$ver}.{$clave}", ClavesCache::TTL, $fn);
    }

    public function vistas(): PortalVistas
    {
        return $this->vistas;
    }

    // =====================================================================
    //  Series base
    // =====================================================================

    /** Serie anual de PortalVistas (exp, imp, saldo, líderes) indexada por gestión. */
    public function serie(): array
    {
        if ($this->memoSerie === null) {
            $this->memoSerie = [];
            foreach ($this->vistas->lineaDeTiempo(self::ORG)['serie'] ?? [] as $s) {
                $this->memoSerie[(int) $s['gestion']] = $s;
            }
        }

        return $this->memoSerie;
    }

    public function cobertura(): array
    {
        return $this->vistas->cobertura(self::ORG);
    }

    public function gestionDefecto(): ?int
    {
        return $this->vistas->gestionDefecto(self::ORG);
    }

    public function gestiones(): array
    {
        return $this->vistas->gestiones(self::ORG);
    }

    private function completo(int $g): bool
    {
        return ($this->serie()[$g]['meses_exp'] ?? 0) >= 12;
    }

    /** Gestión con la mayor exportación entre años COMPLETOS (el preliminar no compite). */
    public function maxExpAnio(?int $desde = null, ?int $hasta = null): ?int
    {
        $mejor = null;
        foreach ($this->serie() as $g => $s) {
            if ($s['exp'] === null || ! $this->completo($g) || ($desde && $g < $desde) || ($hasta && $g > $hasta)) {
                continue;
            }
            if ($mejor === null || $s['exp'] > $this->serie()[$mejor]['exp']) {
                $mejor = $g;
            }
        }

        return $mejor;
    }

    public function variacion(int $a, int $b): ?float
    {
        $sa = $this->serie()[$a] ?? null;
        $sb = $this->serie()[$b] ?? null;
        if (! $sa || ! $sb || ! $sa['exp'] || $sb['exp'] === null || ! $this->completo($a) || ! $this->completo($b)) {
            return null;
        }

        return round(($sb['exp'] - $sa['exp']) / $sa['exp'] * 100, 1);
    }

    /** FOB y CIF frontera de las IMPORTACIONES por gestión (solo años con importaciones). */
    public function fobCifImportaciones(): array
    {
        return $this->cache('fobcif', function () {
            $imp = implode(',', ReglaValor::flujos()['impo']);
            $anios = $this->cobertura()['imp']['anios'] ?? [];
            if (! $anios) {
                return [];
            }
            $rows = DB::select("SELECT gestion, SUM(COALESCE(valor_fob_usd,0)) AS fob, SUM(COALESCE(valor_cif_frontera_usd,0)) AS cif
                FROM operacion_comercio_exterior WHERE organizacion_id = ? AND flujo_id IN ($imp)
                AND gestion IN (".implode(',', array_map('intval', $anios)).') GROUP BY gestion ORDER BY gestion', [self::ORG]);

            return array_map(fn ($r) => [
                'gestion' => (int) $r->gestion,
                'fob' => (float) $r->fob,
                'cif' => (float) $r->cif,
                'brecha' => (float) $r->fob > 0 ? round(((float) $r->cif / (float) $r->fob - 1) * 100, 1) : null,
            ], $rows);
        });
    }

    private function filtroSoya(string $alias = 'p'): string
    {
        return '('.implode(' OR ', array_map(fn ($c) => "{$alias}.codigo_nandina_txt LIKE '{$c}%'", self::SOYA)).')';
    }

    /** Exportación de soya y derivados por gestión (partidas 1201, 1507, 2304). */
    public function soya(): array
    {
        return $this->cache('soya', function () {
            $exp = implode(',', ReglaValor::flujos()['expo']);
            $rows = DB::select('SELECT r.gestion, SUM(r.valor) AS v FROM resumen_anual_producto r JOIN producto p ON p.producto_id = r.producto_id
                WHERE r.organizacion_id = ? AND r.flujo_id IN ('.$exp.') AND '.$this->filtroSoya().' GROUP BY r.gestion ORDER BY r.gestion', [self::ORG]);
            $out = [];
            foreach ($rows as $r) {
                $out[(int) $r->gestion] = (float) $r->v;
            }

            return $out;
        });
    }

    public function soyaMaxAnio(): ?int
    {
        $mejor = null;
        foreach ($this->soya() as $g => $v) {
            if (! $this->completo($g)) {
                continue;
            }
            if ($mejor === null || $v > $this->soya()[$mejor]) {
                $mejor = $g;
            }
        }

        return $mejor;
    }

    /** Principales destinos de la soya en una gestión (países por ISO). */
    public function soyaDestinos(int $g, int $n = 6): array
    {
        return $this->cache("soyadest.{$g}.{$n}", function () use ($g, $n) {
            $exp = implode(',', ReglaValor::flujos()['expo']);
            $rows = DB::select('SELECT COALESCE(c.nombre_canonico, pa.nombre) AS nombre, MAX(c.iso2) AS iso2, SUM(o.valor_fob_usd) AS v
                FROM operacion_comercio_exterior o JOIN producto p ON p.producto_id = o.producto_id
                JOIN pais pa ON pa.pais_id = o.pais_id LEFT JOIN v_pais_canonico c ON c.pais_id = o.pais_id
                WHERE o.organizacion_id = ? AND o.gestion = ? AND o.flujo_id IN ('.$exp.') AND '.$this->filtroSoya().'
                GROUP BY 1 ORDER BY 3 DESC LIMIT '.(int) $n, [self::ORG, $g]);

            return array_map(fn ($r) => ['nombre' => $this->titulo($r->nombre), 'iso2' => $r->iso2 ? trim($r->iso2) : null, 'valor' => (float) $r->v], $rows);
        });
    }

    /** Último año con registro de una vía (p. ej. el código marítimo-intermodal). */
    public function viaHasta(string $via): ?int
    {
        return $this->cache("viahasta.{$via}", function () use ($via) {
            $patron = match ($via) {
                'maritimo' => '%MARITIM%',
                'aereo' => '%AERE%',
                default => null,
            };
            if (! $patron) {
                return null;
            }
            $g = DB::selectOne('SELECT MAX(o.gestion) AS g FROM operacion_comercio_exterior o JOIN medio_transporte m ON m.medio_id = o.medio_id
                WHERE o.organizacion_id = ? AND UPPER(m.descripcion) LIKE ? AND COALESCE(o.valor_fob_usd,0) + COALESCE(o.valor_cif_frontera_usd,0) > 0', [self::ORG, $patron]);

            return $g?->g ? (int) $g->g : null;
        });
    }

    public function hhiProductos(int $g): ?float
    {
        if (! in_array($g, $this->cobertura()['exp']['anios'] ?? [], true)) {
            return null;
        }
        foreach ($this->vistas->indicadores($g, self::ORG)['hhi'] ?? [] as $h) {
            if ($h['clave'] === 'prod_exp') {
                return $h['valor'];
            }
        }

        return null;
    }

    public function filasMercosur(): int
    {
        try {
            return (int) DB::table('serie_comercio_zona')->count();
        } catch (\Throwable $e) {
            return 0;
        }
    }

    // =====================================================================
    //  Formatos (dependen del nivel: mejora 6)
    // =====================================================================

    public static function num(float $v, int $dec = 0): string
    {
        return number_format(abs($v), $dec, ',', '.');
    }

    /** Dinero: colegio redondea a miles de millones / millones en palabras; universidad usa "M USD". */
    public function dinero(?float $v, string $nivel): string
    {
        if ($v === null) {
            return 'sin datos';
        }
        $s = $v < 0 ? '−' : '';
        if ($nivel === 'colegio') {
            return abs($v) >= 1e9
                ? $s.self::num($v / 1e9, 1).' mil millones de dólares'
                : $s.self::num($v / 1e6, 0).' millones de dólares';
        }

        return $s.self::num($v / 1e6, 1).' M USD';
    }

    public function pct(?float $v, bool $signo = false): string
    {
        if ($v === null) {
            return 'sin datos';
        }

        return ($v < 0 ? '−' : ($signo && $v > 0 ? '+' : '')).self::num($v, 1).'%';
    }

    // =====================================================================
    //  Tokens {nombre:arg1:arg2}
    // =====================================================================

    /** Convierte un argumento de año: número, 'defecto', 'max_exp_anio' o 'soya_max_anio'. */
    public function anio($a): ?int
    {
        return match ((string) $a) {
            'defecto' => $this->gestionDefecto(),
            'max_exp_anio' => $this->maxExpAnio(),
            'soya_max_anio' => $this->soyaMaxAnio(),
            default => is_numeric($a) ? (int) $a : null,
        };
    }

    public function token(string $nombre, array $args, string $nivel): string
    {
        $g = isset($args[0]) ? $this->anio($args[0]) : null;
        $s = $g ? ($this->serie()[$g] ?? null) : null;
        $cob = $this->cobertura();

        return match ($nombre) {
            'defecto' => (string) ($this->gestionDefecto() ?? 'sin datos'),
            'exp' => $this->dinero($s['exp'] ?? null, $nivel),
            'imp' => $this->dinero($s['imp'] ?? null, $nivel),
            'saldo' => $this->dinero($s['saldo'] ?? null, $nivel),
            'tc' => $this->pct($s['cobertura'] ?? null),
            'var_exp' => $this->pct($this->variacion((int) ($args[0] ?? 0), (int) ($args[1] ?? 0)), true),
            'max_exp_anio' => (string) ($this->maxExpAnio(isset($args[0]) ? (int) $args[0] : null, isset($args[1]) ? (int) $args[1] : null) ?? 'sin datos'),
            'max_exp' => $this->dinero(($m = $this->maxExpAnio()) ? $this->serie()[$m]['exp'] : null, $nivel),
            'lider' => $s['producto_lider'] ?? 'sin datos',
            'lider_pct' => $this->pct($s['producto_lider_pct'] ?? null),
            'destino' => $s['destino_lider'] ?? 'sin datos',
            'cob_exp' => $cob['exp']['etiqueta'] ?? 'sin datos',
            'cob_imp' => $cob['imp']['etiqueta'] ?? 'sin datos',
            'cif_fob' => $this->pct(collect($this->fobCifImportaciones())->firstWhere('gestion', $g)['brecha'] ?? null),
            'n_deficit', 'n_saldo' => $this->contarSaldo($nombre, (int) ($args[0] ?? 0), (int) ($args[1] ?? 0)),
            'hhi_prod' => ($h = $g ? $this->hhiProductos($g) : null) === null ? 'sin datos' : self::num($h, 3),
            'via_hasta' => (string) ($this->viaHasta((string) ($args[0] ?? '')) ?? 'sin datos'),
            'soya' => $this->dinero($g ? ($this->soya()[$g] ?? null) : null, $nivel),
            'soya_max_anio' => (string) ($this->soyaMaxAnio() ?? 'sin datos'),
            'soya_max' => $this->dinero(($m = $this->soyaMaxAnio()) ? $this->soya()[$m] : null, $nivel),
            'soya_pct' => $this->pct(($g && ($s['exp'] ?? null) && isset($this->soya()[$g])) ? round($this->soya()[$g] / $s['exp'] * 100, 1) : null),
            default => '{'.$nombre.'}',
        };
    }

    private function contarSaldo(string $que, int $a, int $b): string
    {
        $n = $def = 0;
        for ($g = $a; $g <= $b; $g++) {
            $sal = $this->serie()[$g]['saldo'] ?? null;
            if ($sal === null) {
                continue;
            }
            $n++;
            if ($sal < 0) {
                $def++;
            }
        }
        if (! $n) {
            return 'sin datos';
        }

        return (string) ($que === 'n_saldo' ? $n : $def);
    }

    /** Reemplaza los {tokens} de un texto con cifras en vivo. */
    public function resolver(?string $texto, string $nivel = 'universidad'): string
    {
        if ($texto === null || $texto === '') {
            return '';
        }

        return preg_replace_callback('/\{([a-z_]+)((?::[a-z0-9_]+)*)\}/', function ($m) use ($nivel) {
            $args = $m[2] === '' ? [] : explode(':', ltrim($m[2], ':'));
            try {
                return $this->token($m[1], $args, $nivel);
            } catch (\Throwable $e) {
                return 'sin datos';
            }
        }, $texto);
    }

    // =====================================================================
    //  Gráficos de lecciones (bloques que pinta GdGraficoEdu.vue)
    // =====================================================================

    private function puntosSerie(int $desde, int $hasta, array $resaltar, array $claves): array
    {
        $res = array_map(fn ($r) => $this->anio($r), $resaltar);
        $out = [];
        for ($g = $desde; $g <= $hasta; $g++) {
            $s = $this->serie()[$g] ?? null;
            if (! $s) {
                continue;
            }
            $p = ['etiqueta' => ($s['meses_exp'] > 0 && $s['meses_exp'] < 12) ? "{$g}p" : (string) $g, 'gestion' => $g, 'resaltar' => in_array($g, $res, true)];
            foreach ($claves as $k) {
                $p[$k] = $s[$k] ?? null;
            }
            $out[] = $p;
        }

        return $out;
    }

    private const SERIES = [
        'exp' => ['clave' => 'exp', 'nombre' => 'Exportaciones FOB', 'color' => '#10b981'],
        'imp' => ['clave' => 'imp', 'nombre' => 'Importaciones CIF frontera', 'color' => '#3b82f6'],
        'saldo' => ['clave' => 'saldo', 'nombre' => 'Saldo (X − M)', 'color' => '#5e1224'],
    ];

    public function grafico(string $id, ?int $gestion = null): array
    {
        $g = $gestion ?: $this->gestionDefecto();
        $cob = $this->cobertura();
        $hayImp = in_array($g, $cob['imp']['anios'] ?? [], true);
        $hayExp = in_array($g, $cob['exp']['anios'] ?? [], true);
        $etqG = ($this->serie()[$g]['meses_exp'] ?? 12) < 12 ? "{$g}p" : (string) $g;

        $bloques = match ($id) {
            'kpis_flujos' => $this->bloquesKpis($g, $etqG),
            'fob_cif' => $this->bloquesFobCif(),
            'balanza' => [
                ['tipo' => 'barras_serie', 'titulo' => 'Exportaciones e importaciones por gestión', 'series' => [self::SERIES['exp'], self::SERIES['imp']],
                 'puntos' => $this->puntosSerie(1992, (int) date('Y'), [], ['exp', 'imp']), 'nota' => 'Barra vacía = sin datos cargados (no es cero).'],
                ['tipo' => 'barras_serie', 'titulo' => 'Saldo comercial (solo años con ambos flujos)', 'series' => [self::SERIES['saldo']],
                 'puntos' => array_values(array_filter($this->puntosSerie(1992, (int) date('Y'), [], ['saldo']), fn ($p) => $p['saldo'] !== null))],
            ],
            'socios' => [
                ['tipo' => 'doble_lista',
                 'izq' => ['titulo' => "Principales destinos de exportación · {$etqG}", 'items' => $hayExp ? $this->vistas->topPaises(self::ORG, $g, 'exp', 8) : [], 'color' => '#10b981'],
                 'der' => ['titulo' => "Principales orígenes de importación · {$g}", 'items' => $hayImp ? $this->vistas->topPaises(self::ORG, $g, 'imp', 8) : [], 'color' => '#3b82f6',
                           'vacio' => "Sin importaciones cargadas para {$g} (hay para {$cob['imp']['etiqueta']})."]],
            ],
            'vias' => $this->bloquesVias($g, $etqG),
            'canasta' => $this->bloquesCanasta($g, $etqG, $hayExp),
            default => [['tipo' => 'sin_datos', 'titulo' => 'Gráfico no disponible']],
        };

        return ['id' => $id, 'gestion' => $g, 'bloques' => $bloques];
    }

    private function bloquesKpis(int $g, string $etq): array
    {
        $p = $this->vistas->panorama($g, self::ORG);
        $corta = fn ($lista) => array_map(fn ($x) => ['nombre' => mb_strimwidth($x['descripcion'], 0, 60, '…'), 'titulo' => $x['descripcion'], 'valor' => $x['valor'], 'pct' => $x['pct']], array_slice($lista, 0, 5));

        return [
            ['tipo' => 'kpis', 'items' => [
                ['nombre' => "Exportaciones FOB · {$etq}", 'valor' => $p['exp']['valor'], 'formato' => 'usd', 'color' => '#10b981'],
                ['nombre' => "Importaciones CIF · {$g}", 'valor' => $p['imp']['valor'], 'formato' => 'usd', 'color' => '#3b82f6'],
                ['nombre' => 'Saldo comercial', 'valor' => $p['saldo'], 'formato' => 'usd', 'color' => '#5e1224'],
                ['nombre' => 'Tasa de cobertura', 'valor' => $p['tasa_cobertura'], 'formato' => 'pct', 'color' => '#12203d'],
            ]],
            ['tipo' => 'doble_lista',
             'izq' => ['titulo' => 'Lo que más vendió (top 5)', 'items' => $corta($p['top_exp'] ?? []), 'color' => '#10b981'],
             'der' => ['titulo' => 'Lo que más compró (top 5)', 'items' => $corta($p['top_imp'] ?? []), 'color' => '#3b82f6',
                       'vacio' => "Sin importaciones cargadas para {$g}. Prueba con 1999."]],
        ];
    }

    private function bloquesFobCif(): array
    {
        $f = $this->fobCifImportaciones();
        if (! $f) {
            return [['tipo' => 'sin_datos', 'titulo' => 'No hay importaciones cargadas', 'mensaje' => 'La comparación FOB/CIF necesita importaciones del INE.']];
        }

        return [
            ['tipo' => 'barras_serie', 'titulo' => 'Importaciones: valor FOB frente a CIF frontera',
             'series' => [['clave' => 'fob', 'nombre' => 'FOB (en origen)', 'color' => '#94a3b8'], ['clave' => 'cif', 'nombre' => 'CIF frontera (con flete y seguro)', 'color' => '#3b82f6']],
             'puntos' => array_map(fn ($r) => ['etiqueta' => (string) $r['gestion'], 'gestion' => $r['gestion'], 'fob' => $r['fob'], 'cif' => $r['cif'], 'resaltar' => false], $f)],
            ['tipo' => 'tabla', 'titulo' => 'Brecha CIF / FOB por gestión', 'columnas' => ['Gestión', 'FOB', 'CIF frontera', 'CIF sobre FOB'],
             'filas' => array_map(fn ($r) => [(string) $r['gestion'], ['usd' => $r['fob']], ['usd' => $r['cif']], ['pct' => $r['brecha']]], $f)],
        ];
    }

    private function bloquesVias(int $g, string $etq): array
    {
        $vias = $this->vistas->vias(self::ORG, $g);
        $exp = array_values(array_filter($vias, fn ($v) => ($v['exp'] ?? 0) > 0));
        $tot = array_sum(array_map(fn ($v) => $v['exp'], $exp)) ?: 1;
        $partes = array_map(fn ($v) => ['nombre' => $v['nombre'], 'valor' => $v['exp'], 'pct' => round($v['exp'] / $tot * 100, 1)], $exp);
        usort($partes, fn ($a, $b) => $b['valor'] <=> $a['valor']);
        $hasta = $this->viaHasta('maritimo');

        return [
            $partes ? ['tipo' => 'dona', 'titulo' => "Exportaciones por vía · {$etq}", 'partes' => $partes]
                    : ['tipo' => 'sin_datos', 'titulo' => "Sin exportaciones por vía en {$g}"],
            ['tipo' => 'aviso', 'texto' => $hasta ? "El código marítimo-intermodal del INE solo aparece hasta {$hasta}." : 'No hay registros del código marítimo-intermodal.'],
        ];
    }

    private function bloquesCanasta(int $g, string $etq, bool $hayExp): array
    {
        if (! $hayExp) {
            return [['tipo' => 'sin_datos', 'titulo' => "Sin exportaciones cargadas para {$g}"]];
        }
        $p = $this->vistas->panorama($g, self::ORG);
        $items = array_map(fn ($x) => ['nombre' => mb_strimwidth($x['descripcion'], 0, 60, '…'), 'titulo' => $x['descripcion'], 'valor' => $x['valor']], array_slice($p['top_exp'] ?? [], 0, 8));

        return [
            ['tipo' => 'barras', 'titulo' => "Productos más exportados · {$etq}", 'items' => $items, 'color' => '#10b981'],
            ['tipo' => 'dona', 'titulo' => 'Tradicionales y no tradicionales', 'partes' => $p['tnt'] ?? []],
            ['tipo' => 'kpis', 'items' => [['nombre' => 'HHI de productos exportados', 'valor' => $this->hhiProductos($g), 'formato' => 'hhi', 'color' => '#5e1224']]],
        ];
    }

    // =====================================================================
    //  Gráficos de historias
    // =====================================================================

    public function graficoHistoria(array $c): array
    {
        $tipo = $c['tipo'] ?? '';
        $desde = (int) ($c['desde'] ?? 1992);
        $hasta = (int) ($c['hasta'] ?? date('Y'));
        $res = (array) ($c['resaltar'] ?? []);

        return match ($tipo) {
            'serie_exp' => ['tipo' => 'barras_serie', 'series' => [self::SERIES['exp']], 'puntos' => $this->puntosSerie($desde, $hasta, $res, ['exp'])],
            'serie_flujos' => ['tipo' => 'barras_serie', 'series' => [self::SERIES['exp'], self::SERIES['imp']], 'puntos' => $this->puntosSerie($desde, $hasta, $res, ['exp', 'imp']),
                               'nota' => 'Barra azul vacía = importaciones sin datos cargados.'],
            'soya_serie' => $this->serieSoya($desde, $hasta, $res),
            'top_productos' => $this->topProductosHist($this->anio($c['gestion'] ?? 'defecto'), (int) ($c['n'] ?? 6)),
            'soya_destinos' => (function () use ($c) {
                $g = $this->anio($c['gestion'] ?? 'defecto');
                $it = $g ? $this->soyaDestinos($g, (int) ($c['n'] ?? 6)) : [];

                return $it ? ['tipo' => 'barras', 'titulo' => "Destinos de la soya · {$g}", 'items' => $it, 'color' => '#f59e0b']
                           : ['tipo' => 'sin_datos', 'titulo' => 'Sin datos de soya para esa gestión'];
            })(),
            'mercosur' => ['tipo' => 'sin_datos', 'titulo' => $this->filasMercosur() ? 'Historia en preparación' : 'Datos MERCOSUR no cargados',
                           'mensaje' => $this->filasMercosur() ? 'Ya hay series MERCOSUR en la base; falta armar este recorrido.' : 'serie_comercio_zona tiene 0 filas en esta base.'],
            default => ['tipo' => 'sin_datos', 'titulo' => 'Sin gráfico'],
        };
    }

    private function serieSoya(int $desde, int $hasta, array $res): array
    {
        $res = array_map(fn ($r) => $this->anio($r), $res);
        $soya = $this->soya();
        $p = [];
        for ($g = $desde; $g <= $hasta; $g++) {
            if (! isset($this->serie()[$g])) {
                continue;
            }
            $p[] = ['etiqueta' => (string) $g, 'gestion' => $g, 'soya' => $soya[$g] ?? null, 'resaltar' => in_array($g, $res, true)];
        }

        return ['tipo' => 'barras_serie', 'series' => [['clave' => 'soya', 'nombre' => 'Soya y derivados (FOB)', 'color' => '#f59e0b']], 'puntos' => $p];
    }

    private function topProductosHist(?int $g, int $n): array
    {
        if (! $g || ! in_array($g, $this->cobertura()['exp']['anios'] ?? [], true)) {
            return ['tipo' => 'sin_datos', 'titulo' => 'Sin exportaciones para esa gestión'];
        }
        $it = array_map(fn ($x) => ['nombre' => mb_strimwidth($x['descripcion'], 0, 60, '…'), 'titulo' => $x['descripcion'], 'valor' => $x['valor']],
            $this->vistas->topProductos(self::ORG, $g, 'exp', $n));

        return ['tipo' => 'barras', 'titulo' => "Productos más exportados · {$g}", 'items' => $it, 'color' => '#10b981'];
    }

    private function titulo(?string $s): string
    {
        $s = trim(preg_replace('/\s+/', ' ', (string) $s));
        if ($s !== '' && mb_strtoupper($s) === $s) {
            $s = mb_convert_case(mb_strtolower($s), MB_CASE_TITLE, 'UTF-8');
        }

        return $s;
    }
}
