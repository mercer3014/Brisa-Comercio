<?php

namespace App\Servicios;

use App\Support\ReglaValor;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Datos del portal público rediseñado (estilo del mockup GEODATA, tarea GEO-12).
 *
 * TODO sale de PostgreSQL: vistas materializadas resumen_* (mensual, producto,
 * país, departamento), el hecho operacion_comercio_exterior (solo para TNT,
 * CUODE y medio de transporte, filtrando por organización + gestión) y las
 * tablas ISO (v_pais_canonico). No hay cifras de ejemplo.
 *
 * Cobertura parcial: cada respuesta trae la cobertura real por flujo (años y
 * meses cargados). Si un flujo no tiene datos en la gestión pedida, sus
 * campos vienen en null y el front muestra "sin datos" en vez de un cero.
 *
 * Por ahora solo el INE (organización 1) tiene microdato; ALADI, MERCOSUR y
 * FAOSTAT siguen con sus pantallas propias (Organizaciones).
 */
class PortalVistas
{
    public const ORG_INE = 1;

    private const MESES = ['', 'ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];

    private function expIds(): array
    {
        return ReglaValor::flujos()['expo'];
    }

    private function impIds(): array
    {
        return ReglaValor::flujos()['impo'];
    }

    private function ids(string $flujo): array
    {
        return $flujo === 'imp' ? $this->impIds() : $this->expIds();
    }

    private function in(array $ids): string
    {
        return implode(',', array_map('intval', $ids));
    }

    /** Cache por versión de datos (último carga_id): una carga nueva invalida todo. */
    private function cache(string $clave, \Closure $fn)
    {
        try {
            $ver = ClavesCache::version();
        } catch (\Throwable $e) {
            $ver = 0;
        }

        return Cache::remember("portal.v2.{$ver}.{$clave}", ClavesCache::TTL, $fn);
    }

    // =====================================================================
    //  Cobertura real por flujo
    // =====================================================================

    /**
     * Años y meses cargados por flujo. Ej.: exp 1992–2026p (2026: ene–mar),
     * imp 1992–1999.
     */
    public function cobertura(int $org = self::ORG_INE): array
    {
        return $this->cache("cobertura.{$org}", function () use ($org) {
            $filas = DB::table('resumen_mensual')
                ->where('organizacion_id', $org)
                ->selectRaw('flujo_id, gestion, COUNT(DISTINCT mes) FILTER (WHERE mes BETWEEN 1 AND 12) AS meses, MAX(mes) AS ultimo_mes, SUM(valor) AS valor')
                ->groupBy('flujo_id', 'gestion')
                ->orderBy('gestion')
                ->get();

            $out = [];
            foreach (['exp', 'imp'] as $f) {
                $ids = $this->ids($f);
                $porAnio = [];
                foreach ($filas as $r) {
                    if (! in_array((int) $r->flujo_id, $ids, true) || (float) $r->valor <= 0) {
                        continue;
                    }
                    $g = (int) $r->gestion;
                    $porAnio[$g] = [
                        'meses'      => max($porAnio[$g]['meses'] ?? 0, (int) $r->meses),
                        'ultimo_mes' => max($porAnio[$g]['ultimo_mes'] ?? 0, (int) $r->ultimo_mes),
                    ];
                }
                ksort($porAnio);
                $anios = array_keys($porAnio);
                $hasta = $anios ? max($anios) : null;
                $ultimo = $hasta ? $porAnio[$hasta] : null;
                $prelim = $ultimo && $ultimo['meses'] > 0 && $ultimo['meses'] < 12;

                $out[$f] = [
                    'anios'        => $anios,
                    'desde'        => $anios ? min($anios) : null,
                    'hasta'        => $hasta,
                    'preliminar'   => $prelim,
                    'ultimo_mes'   => $ultimo['ultimo_mes'] ?? null,
                    'meses_ultimo' => $ultimo['meses'] ?? null,
                    'etiqueta'     => $this->etiquetaRango($anios, $hasta, $prelim, $ultimo['ultimo_mes'] ?? null),
                    'meses'        => array_map(fn ($v) => $v['meses'], $porAnio),
                ];
            }

            return $out;
        });
    }

    private function etiquetaRango(array $anios, ?int $hasta, bool $prelim, ?int $ultimoMes): string
    {
        if (! $anios) {
            return 'sin datos cargados';
        }
        // Tramos continuos: 1992–1999, 2005–2010...
        $tramos = [];
        $ini = $prev = null;
        foreach ($anios as $a) {
            if ($ini === null) {
                $ini = $prev = $a;
                continue;
            }
            if ($a === $prev + 1) {
                $prev = $a;
                continue;
            }
            $tramos[] = $ini === $prev ? "$ini" : "{$ini}–{$prev}";
            $ini = $prev = $a;
        }
        $tramos[] = $ini === $prev ? "$ini" : "{$ini}–{$prev}";
        $txt = implode(', ', $tramos);
        if ($prelim) {
            $txt .= "p ({$hasta}: ene–".self::MESES[$ultimoMes ?? 12].')';
        }

        return $txt;
    }

    /** Gestión por defecto del portal: la última completa con exportaciones (o la última con datos). */
    public function gestionDefecto(int $org = self::ORG_INE): ?int
    {
        $c = $this->cobertura($org);
        $exp = $c['exp'];
        if (! $exp['anios']) {
            return $c['imp']['hasta'];
        }
        $completos = array_values(array_filter($exp['anios'], fn ($a) => ($exp['meses'][$a] ?? 0) >= 12));

        return $completos ? max($completos) : $exp['hasta'];
    }

    /** Lista de gestiones con algún dato, de la más reciente a la más antigua. */
    public function gestiones(int $org = self::ORG_INE): array
    {
        $c = $this->cobertura($org);
        $a = array_unique(array_merge($c['exp']['anios'], $c['imp']['anios']));
        rsort($a);

        return array_values($a);
    }

    /** Meses cargados de una gestión para un flujo (para comparar el mismo periodo). */
    private function mesesCargados(int $org, int $gestion, string $flujo): int
    {
        return (int) ($this->cobertura($org)[$flujo]['meses'][$gestion] ?? 0);
    }

    private function hayDatos(int $org, int $gestion, string $flujo): bool
    {
        return in_array($gestion, $this->cobertura($org)[$flujo]['anios'], true);
    }

    // =====================================================================
    //  Totales
    // =====================================================================

    /** Total de un flujo en una gestión (opcionalmente solo meses 1..$hastaMes). */
    private function total(int $org, int $gestion, string $flujo, ?int $hastaMes = null): ?float
    {
        if (! $this->hayDatos($org, $gestion, $flujo)) {
            return null;
        }
        $q = DB::table('resumen_mensual')
            ->where('organizacion_id', $org)->where('gestion', $gestion)
            ->whereIn('flujo_id', $this->ids($flujo));
        if ($hastaMes) {
            $q->where('mes', '<=', $hastaMes);
        }

        return (float) $q->sum('valor');
    }

    private function var(?float $actual, ?float $anterior): ?float
    {
        if ($actual === null || ! $anterior) {
            return null;
        }

        return round(($actual - $anterior) / $anterior * 100, 1);
    }

    /** Totales + variación contra el MISMO periodo del año anterior. */
    private function bloqueTotal(int $org, int $gestion, string $flujo): array
    {
        $meses = $this->mesesCargados($org, $gestion, $flujo);
        $total = $this->total($org, $gestion, $flujo);
        $prelim = $meses > 0 && $meses < 12;
        $anterior = $this->total($org, $gestion - 1, $flujo, $prelim ? $meses : null);

        return [
            'valor'      => $total,
            'anterior'   => $anterior,
            'variacion'  => $this->var($total, $anterior),
            'meses'      => $meses,
            'preliminar' => $prelim,
            'periodo'    => $total === null ? null : ($prelim ? 'ene–'.self::MESES[$meses]." $gestion" : "$gestion"),
            'periodo_anterior' => $anterior === null ? null : ($prelim ? 'ene–'.self::MESES[$meses].' '.($gestion - 1) : (string) ($gestion - 1)),
        ];
    }

    // =====================================================================
    //  Inicio / Panorama
    // =====================================================================

    public function panorama(int $gestion, int $org = self::ORG_INE): array
    {
        return $this->cache("panorama.{$org}.{$gestion}", function () use ($gestion, $org) {
            $exp = $this->bloqueTotal($org, $gestion, 'exp');
            $imp = $this->bloqueTotal($org, $gestion, 'imp');
            $saldo = ($exp['valor'] !== null && $imp['valor'] !== null) ? $exp['valor'] - $imp['valor'] : null;

            $topExp = $this->topProductos($org, $gestion, 'exp', 10);
            $topImp = $this->topProductos($org, $gestion, 'imp', 10);
            $destinos = $this->topPaises($org, $gestion, 'exp', 3);
            $origenes = $this->topPaises($org, $gestion, 'imp', 3);
            $deptos = $this->topDepartamentos($org, $gestion, 'exp', 9);

            return [
                'gestion'      => $gestion,
                'cobertura'    => $this->cobertura($org),
                'exp'          => $exp,
                'imp'          => $imp,
                'saldo'        => $saldo,
                'tasa_cobertura' => ($saldo !== null && $imp['valor']) ? round($exp['valor'] / $imp['valor'] * 100, 1) : null,
                'relacion_mx'  => ($saldo !== null && $exp['valor']) ? round($imp['valor'] / $exp['valor'] * 100, 1) : null,
                'partidas_exp' => $this->contar('resumen_anual_producto', 'producto_id', $org, $gestion, 'exp'),
                'partidas_imp' => $this->contar('resumen_anual_producto', 'producto_id', $org, $gestion, 'imp'),
                'paises_exp'   => $this->contar('resumen_anual_pais', 'pais_id', $org, $gestion, 'exp'),
                'paises_imp'   => $this->contar('resumen_anual_pais', 'pais_id', $org, $gestion, 'imp'),
                'top_exp'      => $topExp,
                'top_imp'      => $topImp,
                'destinos'     => $destinos,
                'origenes'     => $origenes,
                'departamentos' => $deptos,
                'tnt'          => $this->estructuraTnt($org, $gestion),
                'cuode'        => $this->estructuraCuode($org, $gestion),
                'vias'         => $this->vias($org, $gestion),
                'mensual'      => $this->mensual($org, $gestion),
            ];
        });
    }

    private function contar(string $vista, string $col, int $org, int $gestion, string $flujo): ?int
    {
        if (! $this->hayDatos($org, $gestion, $flujo)) {
            return null;
        }

        return (int) DB::table($vista)->where('organizacion_id', $org)->where('gestion', $gestion)
            ->whereIn('flujo_id', $this->ids($flujo))->where('valor', '>', 0)
            ->distinct()->count($col);
    }

    public function topProductos(int $org, int $gestion, string $flujo, int $n): array
    {
        if (! $this->hayDatos($org, $gestion, $flujo)) {
            return [];
        }
        $total = $this->total($org, $gestion, $flujo) ?: 1;
        $rows = DB::table('resumen_anual_producto as r')
            ->join('producto as p', 'p.producto_id', '=', 'r.producto_id')
            ->where('r.organizacion_id', $org)->where('r.gestion', $gestion)
            ->whereIn('r.flujo_id', $this->ids($flujo))
            ->groupBy('p.producto_id', 'p.codigo_nandina_txt', 'p.codigo_nandina', 'p.descripcion')
            ->selectRaw('p.producto_id, COALESCE(TRIM(p.codigo_nandina_txt), p.codigo_nandina::text) AS codigo, p.descripcion, SUM(r.valor) AS valor, SUM(r.peso_neto) AS peso')
            ->orderByDesc('valor')->limit($n)->get();

        return $rows->map(fn ($r) => [
            'producto_id' => (int) $r->producto_id,
            'codigo'      => $r->codigo,
            'descripcion' => $this->limpio($r->descripcion),
            'valor'       => (float) $r->valor,
            'peso'        => (float) $r->peso,
            'pct'         => round((float) $r->valor / $total * 100, 1),
        ])->all();
    }

    public function topPaises(int $org, int $gestion, string $flujo, int $n): array
    {
        if (! $this->hayDatos($org, $gestion, $flujo)) {
            return [];
        }
        $total = $this->total($org, $gestion, $flujo) ?: 1;
        $rows = DB::table('resumen_anual_pais as r')
            ->join('pais as p', 'p.pais_id', '=', 'r.pais_id')
            ->leftJoin('v_pais_canonico as c', 'c.pais_id', '=', 'r.pais_id')
            ->where('r.organizacion_id', $org)->where('r.gestion', $gestion)
            ->whereIn('r.flujo_id', $this->ids($flujo))
            ->groupByRaw("COALESCE(c.iso3, 'P'||p.pais_id::text), COALESCE(c.nombre_canonico, p.nombre), c.iso2")
            ->selectRaw("COALESCE(c.iso3, 'P'||p.pais_id::text) AS clave, COALESCE(c.nombre_canonico, p.nombre) AS nombre, c.iso2, SUM(r.valor) AS valor")
            ->orderByDesc('valor')->limit($n)->get();

        return $rows->map(fn ($r) => [
            'clave'  => $r->clave,
            'iso2'   => $r->iso2 ? trim($r->iso2) : null,
            'nombre' => $this->titulo($r->nombre),
            'valor'  => (float) $r->valor,
            'pct'    => round((float) $r->valor / $total * 100, 1),
        ])->all();
    }

    public function topDepartamentos(int $org, int $gestion, string $flujo, int $n): array
    {
        if (! $this->hayDatos($org, $gestion, $flujo)) {
            return [];
        }
        $total = $this->total($org, $gestion, $flujo) ?: 1;
        $rows = DB::table('resumen_anual_departamento as r')
            ->join('departamento as d', 'd.departamento_id', '=', 'r.departamento_id')
            ->where('r.organizacion_id', $org)->where('r.gestion', $gestion)
            ->whereIn('r.flujo_id', $this->ids($flujo))
            ->groupBy('d.nombre')
            ->selectRaw('d.nombre, SUM(r.valor) AS valor')
            ->orderByDesc('valor')->limit($n)->get();

        return $rows->map(fn ($r) => [
            'nombre' => $this->titulo($r->nombre),
            'valor'  => (float) $r->valor,
            'pct'    => round((float) $r->valor / $total * 100, 1),
        ])->all();
    }

    /** Exportación: tradicionales (minerales + hidrocarburos) vs no tradicionales (clasificación TNT). */
    private function estructuraTnt(int $org, int $gestion): array
    {
        if (! $this->hayDatos($org, $gestion, 'exp')) {
            return [];
        }
        $expo = ReglaValor::expo();
        $rows = DB::table('operacion_comercio_exterior as o')
            ->leftJoin('clasificacion_tnt as t', 't.tnt_id', '=', 'o.tnt_id')
            ->where('o.organizacion_id', $org)->where('o.gestion', $gestion)
            ->whereIn('o.flujo_id', $this->expIds())
            ->groupByRaw("COALESCE(t.clase, 'SIN')")
            ->selectRaw("COALESCE(t.clase, 'SIN') AS clase, SUM($expo) AS valor")
            ->get();

        $grupos = ['Tradicionales (minerales e hidrocarburos)' => 0.0, 'No tradicionales' => 0.0, 'Otros / sin clasificar' => 0.0];
        foreach ($rows as $r) {
            $c = strtoupper((string) $r->clase);
            $k = match (true) {
                str_contains($c, 'MINERAL'), str_contains($c, 'HIDROCARB') => 'Tradicionales (minerales e hidrocarburos)',
                str_contains($c, 'NO TRADICIONAL') => 'No tradicionales',
                default => 'Otros / sin clasificar',
            };
            $grupos[$k] += (float) $r->valor;
        }

        return $this->partes($grupos);
    }

    /** Importación por uso o destino económico (CUODE, primer dígito). */
    private function estructuraCuode(int $org, int $gestion): array
    {
        if (! $this->hayDatos($org, $gestion, 'imp')) {
            return [];
        }
        $impo = ReglaValor::impo();
        $rows = DB::table('operacion_comercio_exterior as o')
            ->leftJoin('clasificacion_cuode as c', 'c.cuode_id', '=', 'o.cuode_id')
            ->where('o.organizacion_id', $org)->where('o.gestion', $gestion)
            ->whereIn('o.flujo_id', $this->impIds())
            ->groupByRaw("LEFT(COALESCE(c.codigo_cuode, '0'), 1)")
            ->selectRaw("LEFT(COALESCE(c.codigo_cuode, '0'), 1) AS d, SUM($impo) AS valor")
            ->get();

        $grupos = ['Materias primas y productos intermedios' => 0.0, 'Bienes de capital' => 0.0, 'Bienes de consumo' => 0.0, 'Diversos / sin clasificar' => 0.0];
        foreach ($rows as $r) {
            $k = match ((string) $r->d) {
                '1', '2' => 'Bienes de consumo',
                '3', '4', '5', '6' => 'Materias primas y productos intermedios',
                '7', '8', '9' => 'Bienes de capital',
                default => 'Diversos / sin clasificar',
            };
            $grupos[$k] += (float) $r->valor;
        }

        return $this->partes($grupos);
    }

    private function partes(array $grupos): array
    {
        $tot = array_sum($grupos) ?: 1;
        $out = [];
        foreach ($grupos as $k => $v) {
            if ($v <= 0 || $v / $tot < 0.001) {
                continue;
            }
            $out[] = ['nombre' => $k, 'valor' => $v, 'pct' => round($v / $tot * 100, 1)];
        }

        return $out;
    }

    /** Medio de transporte agrupado en las 4 vías del hero. */
    public function vias(int $org, int $gestion): array
    {
        $expo = ReglaValor::expo();
        $impo = ReglaValor::impo();
        $rows = DB::table('operacion_comercio_exterior as o')
            ->join('medio_transporte as m', 'm.medio_id', '=', 'o.medio_id')
            ->where('o.organizacion_id', $org)->where('o.gestion', $gestion)
            ->groupBy('m.descripcion')
            ->selectRaw("m.descripcion, SUM($expo) AS exp, SUM($impo) AS imp")
            ->get();

        $vias = [];
        foreach (['maritimo' => 'Marítimo', 'terrestre' => 'Terrestre', 'aereo' => 'Aéreo', 'otros' => 'Ductos y otros medios'] as $k => $n) {
            $vias[$k] = ['clave' => $k, 'nombre' => $n, 'exp' => 0.0, 'imp' => 0.0, 'medios' => []];
        }
        foreach ($rows as $r) {
            $k = self::grupoVia((string) $r->descripcion);
            $vias[$k]['exp'] += (float) $r->exp;
            $vias[$k]['imp'] += (float) $r->imp;
            $vias[$k]['medios'][] = ['nombre' => $this->titulo($r->descripcion), 'exp' => (float) $r->exp, 'imp' => (float) $r->imp];
        }
        $hayImp = $this->hayDatos($org, $gestion, 'imp');
        $hayExp = $this->hayDatos($org, $gestion, 'exp');
        foreach ($vias as &$v) {
            if (! $hayImp) {
                $v['imp'] = null;
            }
            if (! $hayExp) {
                $v['exp'] = null;
            }
            usort($v['medios'], fn ($a, $b) => ($b['exp'] + $b['imp']) <=> ($a['exp'] + $a['imp']));
        }

        return array_values($vias);
    }

    /**
     * Comercio por vía de transporte para la página "Comercio por vía" (GEO-12):
     * totales y medios de la gestión, serie anual por vía (todas las gestiones)
     * y principales productos de cada vía en la gestión. null = sin datos.
     */
    public function viasDetalle(int $gestion, int $org = self::ORG_INE): array
    {
        return $this->cache("vias.{$org}.{$gestion}", function () use ($gestion, $org) {
            $expo = ReglaValor::expo();
            $impo = ReglaValor::impo();
            $cob = $this->cobertura($org);

            // Serie anual por medio → agrupada por vía.
            $rows = DB::table('operacion_comercio_exterior as o')
                ->join('medio_transporte as m', 'm.medio_id', '=', 'o.medio_id')
                ->where('o.organizacion_id', $org)
                ->groupBy('o.gestion', 'm.descripcion')
                ->selectRaw("o.gestion, m.descripcion, SUM($expo) AS exp, SUM($impo) AS imp")
                ->get();
            $serie = [];
            foreach ($rows as $r) {
                $k = self::grupoVia((string) $r->descripcion);
                $g = (int) $r->gestion;
                $serie[$k][$g] ??= ['gestion' => $g, 'exp' => 0.0, 'imp' => 0.0];
                $serie[$k][$g]['exp'] += (float) $r->exp;
                $serie[$k][$g]['imp'] += (float) $r->imp;
            }
            $anios = array_values(array_unique(array_merge($cob['exp']['anios'] ?? [], $cob['imp']['anios'] ?? [])));
            sort($anios);
            $series = [];
            foreach (['maritimo', 'terrestre', 'aereo', 'otros'] as $k) {
                $series[$k] = array_map(function ($a) use ($serie, $k, $cob) {
                    $v = $serie[$k][$a] ?? ['gestion' => $a, 'exp' => 0.0, 'imp' => 0.0];
                    if (! in_array($a, $cob['exp']['anios'] ?? [], true)) {
                        $v['exp'] = null;
                    }
                    if (! in_array($a, $cob['imp']['anios'] ?? [], true)) {
                        $v['imp'] = null;
                    }

                    return $v;
                }, $anios);
            }

            // Principales productos por vía (gestión seleccionada).
            $prods = [];
            foreach (['exp' => $expo, 'imp' => $impo] as $f => $val) {
                $ids = $this->in($this->ids($f));
                $pr = DB::select("
                    SELECT m.descripcion AS medio, p.descripcion, SUM($val) AS valor
                    FROM operacion_comercio_exterior o
                    JOIN medio_transporte m ON m.medio_id = o.medio_id
                    JOIN producto p ON p.producto_id = o.producto_id
                    WHERE o.organizacion_id = ? AND o.gestion = ? AND o.flujo_id IN ($ids)
                    GROUP BY 1,2", [$org, $gestion]);
                $acc = [];
                foreach ($pr as $r) {
                    $k = self::grupoVia((string) $r->medio);
                    $d = $this->limpio($r->descripcion);
                    $acc[$k][$d] = ($acc[$k][$d] ?? 0) + (float) $r->valor;
                }
                foreach (['maritimo', 'terrestre', 'aereo', 'otros'] as $k) {
                    $lista = $acc[$k] ?? [];
                    arsort($lista);
                    $prods[$k][$f] = array_map(fn ($n, $v) => ['nombre' => $n, 'valor' => $v], array_keys(array_slice($lista, 0, 6, true)), array_slice($lista, 0, 6));
                }
            }

            // Último año con registro por vía (el INE dejó de usar el código marítimo-intermodal en 2022).
            $ultimo = [];
            foreach ($serie as $k => $porAnio) {
                $conValor = array_filter($porAnio, fn ($v) => ($v['exp'] + $v['imp']) > 0);
                $ultimo[$k] = $conValor ? max(array_keys($conValor)) : null;
                $primero[$k] = $conValor ? min(array_keys($conValor)) : null;
            }

            return [
                'gestion'   => $gestion,
                'cobertura' => $cob,
                'vias'      => array_map(function ($v) use ($series, $prods, $ultimo, $primero) {
                    $k = $v['clave'];

                    return $v + [
                        'serie'     => $series[$k] ?? [],
                        'productos' => $prods[$k] ?? ['exp' => [], 'imp' => []],
                        'desde'     => $primero[$k] ?? null,
                        'hasta'     => $ultimo[$k] ?? null,
                    ];
                }, $this->vias($org, $gestion)),
            ];
        });
    }

    /**
     * Bolivia no tiene costa: el comercio "marítimo" figura como intermodal
     * (MARITIMA-CARRETERA, MARITIMA-FERROVIARIA). Carretera y ferrovía sin ese
     * tramo son terrestre; aérea y courier, aéreo; ductos, fluvial, lacustre,
     * postal y otros, "otros medios".
     */
    public static function grupoVia(string $medio): string
    {
        $m = mb_strtoupper($medio);

        return match (true) {
            str_contains($m, 'MARITIM') => 'maritimo',
            str_contains($m, 'AERE'), str_contains($m, 'COURRIER'), str_contains($m, 'COURIER') => 'aereo',
            str_contains($m, 'CARRETER'), str_contains($m, 'FERROVIAR') => 'terrestre',
            default => 'otros',
        };
    }

    private function mensual(int $org, int $gestion): array
    {
        $rows = DB::table('resumen_mensual')
            ->where('organizacion_id', $org)->where('gestion', $gestion)->whereBetween('mes', [1, 12])
            ->selectRaw('mes, flujo_id, SUM(valor) AS valor')->groupBy('mes', 'flujo_id')->get();
        $exp = array_fill(1, 12, null);
        $imp = array_fill(1, 12, null);
        foreach ($rows as $r) {
            if (in_array((int) $r->flujo_id, $this->expIds(), true)) {
                $exp[(int) $r->mes] = ($exp[(int) $r->mes] ?? 0) + (float) $r->valor;
            } else {
                $imp[(int) $r->mes] = ($imp[(int) $r->mes] ?? 0) + (float) $r->valor;
            }
        }

        return ['meses' => array_slice(self::MESES, 1), 'exp' => array_values($exp), 'imp' => array_values($imp)];
    }

    // =====================================================================
    //  Productos
    // =====================================================================

    public function productos(int $gestion, string $flujo, int $org = self::ORG_INE): array
    {
        return $this->cache("productos.{$org}.{$gestion}.{$flujo}", function () use ($gestion, $flujo, $org) {
            $tot = $this->bloqueTotal($org, $gestion, $flujo);
            if ($tot['valor'] === null) {
                return ['gestion' => $gestion, 'flujo' => $flujo, 'cobertura' => $this->cobertura($org), 'total' => $tot, 'filas' => [], 'secciones' => []];
            }
            $ids = $this->in($this->ids($flujo));

            // Valor actual por producto (vista anual)
            $actual = DB::select("
                SELECT p.producto_id, COALESCE(TRIM(p.codigo_nandina_txt), p.codigo_nandina::text) AS codigo, p.descripcion,
                       s.codigo_seccion, s.descripcion AS seccion, c.codigo_capitulo,
                       SUM(r.valor) AS valor, SUM(r.peso_neto) AS peso
                FROM resumen_anual_producto r
                JOIN producto p ON p.producto_id = r.producto_id
                LEFT JOIN capitulo_arancelario c ON c.capitulo_id = p.capitulo_id
                LEFT JOIN seccion_arancelaria s ON s.seccion_id = c.seccion_id
                WHERE r.organizacion_id = ? AND r.gestion = ? AND r.flujo_id IN ($ids)
                GROUP BY 1,2,3,4,5,6 HAVING SUM(r.valor) > 0 ORDER BY valor DESC", [$org, $gestion]);

            // Año anterior: mismo periodo si la gestión es preliminar
            $anterior = [];
            if ($this->hayDatos($org, $gestion - 1, $flujo)) {
                if ($tot['preliminar']) {
                    $val = $flujo === 'imp' ? ReglaValor::impo() : ReglaValor::expo();
                    $rows = DB::select("
                        SELECT o.producto_id, SUM($val) AS valor FROM operacion_comercio_exterior o
                        JOIN tiempo t ON t.tiempo_id = o.tiempo_id
                        WHERE o.organizacion_id = ? AND o.gestion = ? AND o.flujo_id IN ($ids) AND t.mes BETWEEN 1 AND ?
                        GROUP BY 1", [$org, $gestion - 1, $tot['meses']]);
                } else {
                    $rows = DB::select("SELECT producto_id, SUM(valor) AS valor FROM resumen_anual_producto
                        WHERE organizacion_id = ? AND gestion = ? AND flujo_id IN ($ids) GROUP BY 1", [$org, $gestion - 1]);
                }
                foreach ($rows as $r) {
                    $anterior[(int) $r->producto_id] = (float) $r->valor;
                }
            }

            $total = $tot['valor'] ?: 1;
            $acum = 0;
            $filas = [];
            $secciones = [];
            foreach ($actual as $i => $r) {
                $v = (float) $r->valor;
                $acum += $v;
                $ant = $anterior[(int) $r->producto_id] ?? null;
                $sec = $r->codigo_seccion ? (int) $r->codigo_seccion : 0;
                $secNombre = $r->seccion ? $this->limpio($r->seccion) : 'Sin sección';
                if ($i < 300) {
                    $filas[] = [
                        'producto_id' => (int) $r->producto_id,
                        'codigo'      => $r->codigo,
                        'descripcion' => $this->limpio($r->descripcion),
                        'seccion'     => $sec,
                        'seccion_nombre' => $secNombre,
                        'capitulo'    => $r->codigo_capitulo ? (int) $r->codigo_capitulo : null,
                        'valor'       => $v,
                        'anterior'    => $ant,
                        'variacion'   => $this->var($v, $ant),
                        'pct'         => round($v / $total * 100, 2),
                        'acum'        => round($acum / $total * 100, 1),
                        'peso'        => (float) $r->peso,
                        'precio_t'    => (float) $r->peso > 0 ? round($v / ((float) $r->peso / 1000), 1) : null,
                    ];
                }
                $secciones[$sec] ??= ['seccion' => $sec, 'nombre' => $secNombre, 'valor' => 0.0, 'partidas' => 0];
                $secciones[$sec]['valor'] += $v;
                $secciones[$sec]['partidas']++;
            }
            usort($secciones, fn ($a, $b) => $b['valor'] <=> $a['valor']);
            foreach ($secciones as &$s) {
                $s['pct'] = round($s['valor'] / $total * 100, 1);
            }
            unset($s);

            $top5 = array_sum(array_map(fn ($f) => $f['valor'], array_slice($filas, 0, 5)));

            return [
                'gestion'   => $gestion,
                'flujo'     => $flujo,
                'cobertura' => $this->cobertura($org),
                'total'     => $tot,
                'partidas'  => count($actual),
                'top5_pct'  => round($top5 / $total * 100, 1),
                'hhi'       => $this->hhi(array_map(fn ($r) => (float) $r->valor, $actual)),
                'filas'     => $filas,
                'secciones' => array_values($secciones),
            ];
        });
    }

    /** Ficha de un producto: serie histórica + principales mercados en la gestión. */
    public function fichaProducto(int $productoId, int $gestion, string $flujo, int $org = self::ORG_INE): array
    {
        return $this->cache("ficha.{$org}.{$productoId}.{$gestion}.{$flujo}", function () use ($productoId, $gestion, $flujo, $org) {
            $p = DB::table('producto as p')
                ->leftJoin('capitulo_arancelario as c', 'c.capitulo_id', '=', 'p.capitulo_id')
                ->leftJoin('seccion_arancelaria as s', 's.seccion_id', '=', 'c.seccion_id')
                ->where('p.producto_id', $productoId)
                ->selectRaw('p.producto_id, COALESCE(TRIM(p.codigo_nandina_txt), p.codigo_nandina::text) AS codigo, p.descripcion, c.codigo_capitulo, c.descripcion AS capitulo, s.descripcion AS seccion')
                ->first();
            if (! $p) {
                return ['producto' => null];
            }
            $serie = DB::table('resumen_anual_producto')
                ->where('organizacion_id', $org)->where('producto_id', $productoId)
                ->whereIn('flujo_id', $this->ids($flujo))
                ->groupBy('gestion')->orderBy('gestion')
                ->selectRaw('gestion, SUM(valor) AS valor, SUM(peso_neto) AS peso')->get();

            $val = $flujo === 'imp' ? ReglaValor::impo() : ReglaValor::expo();
            $ids = $this->in($this->ids($flujo));
            $paises = DB::select("
                SELECT COALESCE(c.nombre_canonico, pa.nombre) AS nombre, c.iso2, SUM($val) AS valor
                FROM operacion_comercio_exterior o
                JOIN pais pa ON pa.pais_id = o.pais_id
                LEFT JOIN v_pais_canonico c ON c.pais_id = o.pais_id
                WHERE o.organizacion_id = ? AND o.gestion = ? AND o.producto_id = ? AND o.flujo_id IN ($ids)
                GROUP BY 1,2 ORDER BY 3 DESC LIMIT 6", [$org, $gestion, $productoId]);
            $deptos = DB::select("
                SELECT d.nombre, SUM($val) AS valor FROM operacion_comercio_exterior o
                JOIN departamento d ON d.departamento_id = o.departamento_id
                WHERE o.organizacion_id = ? AND o.gestion = ? AND o.producto_id = ? AND o.flujo_id IN ($ids)
                GROUP BY 1 ORDER BY 2 DESC LIMIT 5", [$org, $gestion, $productoId]);
            $medios = DB::select("
                SELECT m.descripcion AS nombre, SUM($val) AS valor FROM operacion_comercio_exterior o
                JOIN medio_transporte m ON m.medio_id = o.medio_id
                WHERE o.organizacion_id = ? AND o.gestion = ? AND o.producto_id = ? AND o.flujo_id IN ($ids)
                GROUP BY 1 ORDER BY 2 DESC LIMIT 3", [$org, $gestion, $productoId]);

            $map = fn ($rows) => array_map(fn ($r) => ['nombre' => $this->titulo($r->nombre), 'iso2' => isset($r->iso2) ? trim((string) $r->iso2) : null, 'valor' => (float) $r->valor], $rows);

            return [
                'producto' => [
                    'producto_id' => (int) $p->producto_id,
                    'codigo'      => $p->codigo,
                    'descripcion' => $this->limpio($p->descripcion),
                    'capitulo'    => $p->codigo_capitulo ? sprintf('%02d · %s', $p->codigo_capitulo, $this->limpio($p->capitulo)) : null,
                    'seccion'     => $p->seccion ? $this->limpio($p->seccion) : null,
                ],
                'serie'   => $serie->map(fn ($r) => ['gestion' => (int) $r->gestion, 'valor' => (float) $r->valor, 'peso' => (float) $r->peso])->all(),
                'paises'  => $map($paises),
                'deptos'  => $map($deptos),
                'medios'  => $map($medios),
            ];
        });
    }

    // =====================================================================
    //  Rankings y comparador (Pareto multi-año)
    // =====================================================================

    public function rankings(array $anios, string $flujo, string $dimension, int $top = 10, int $org = self::ORG_INE): array
    {
        sort($anios);
        $clave = "rk.{$org}.{$flujo}.{$dimension}.{$top}.".implode('-', $anios);

        return $this->cache($clave, function () use ($anios, $flujo, $dimension, $top, $org) {
            $ids = $this->in($this->ids($flujo));
            $conDatos = array_values(array_filter($anios, fn ($a) => $this->hayDatos($org, $a, $flujo)));
            $sinDatos = array_values(array_diff($anios, $conDatos));
            if (! $conDatos) {
                return ['anios' => $anios, 'sin_datos' => $sinDatos, 'filas' => [], 'totales' => [], 'resto' => [], 'referencia' => null];
            }
            $ref = max($conDatos);
            $in = implode(',', array_map('intval', $conDatos));

            [$sql, $params] = match ($dimension) {
                'pais' => ["SELECT COALESCE(c.iso3, 'P'||pa.pais_id::text) AS clave, MAX(COALESCE(c.nombre_canonico, pa.nombre)) AS nombre, MAX(c.iso2) AS codigo, r.gestion, SUM(r.valor) AS valor
                            FROM resumen_anual_pais r JOIN pais pa ON pa.pais_id = r.pais_id LEFT JOIN v_pais_canonico c ON c.pais_id = r.pais_id
                            WHERE r.organizacion_id = ? AND r.flujo_id IN ($ids) AND r.gestion IN ($in) GROUP BY 1, r.gestion", [$org]],
                'departamento' => ["SELECT d.nombre AS clave, d.nombre, NULL AS codigo, r.gestion, SUM(r.valor) AS valor
                            FROM resumen_anual_departamento r JOIN departamento d ON d.departamento_id = r.departamento_id
                            WHERE r.organizacion_id = ? AND r.flujo_id IN ($ids) AND r.gestion IN ($in) GROUP BY 1,2, r.gestion", [$org]],
                'seccion' => ["SELECT COALESCE(s.codigo_seccion::text,'0') AS clave, MAX(COALESCE(s.descripcion,'Sin sección')) AS nombre, MAX(s.codigo_seccion)::text AS codigo, r.gestion, SUM(r.valor) AS valor
                            FROM resumen_anual_producto r JOIN producto p ON p.producto_id = r.producto_id
                            LEFT JOIN capitulo_arancelario c ON c.capitulo_id = p.capitulo_id LEFT JOIN seccion_arancelaria s ON s.seccion_id = c.seccion_id
                            WHERE r.organizacion_id = ? AND r.flujo_id IN ($ids) AND r.gestion IN ($in) GROUP BY 1, r.gestion", [$org]],
                default => ["SELECT p.producto_id::text AS clave, MAX(p.descripcion) AS nombre, MAX(COALESCE(TRIM(p.codigo_nandina_txt), p.codigo_nandina::text)) AS codigo, r.gestion, SUM(r.valor) AS valor
                            FROM resumen_anual_producto r JOIN producto p ON p.producto_id = r.producto_id
                            WHERE r.organizacion_id = ? AND r.flujo_id IN ($ids) AND r.gestion IN ($in) GROUP BY 1, r.gestion", [$org]],
            };
            $rows = DB::select($sql, $params);

            $items = [];
            $totales = array_fill_keys($conDatos, 0.0);
            foreach ($rows as $r) {
                $k = $r->clave;
                $items[$k] ??= ['clave' => $k, 'nombre' => $this->titulo($this->limpio((string) $r->nombre)), 'codigo' => $r->codigo ? trim((string) $r->codigo) : null, 'valores' => []];
                $items[$k]['valores'][(int) $r->gestion] = ($items[$k]['valores'][(int) $r->gestion] ?? 0) + (float) $r->valor;
                $totales[(int) $r->gestion] += (float) $r->valor;
            }
            uasort($items, fn ($a, $b) => ($b['valores'][$ref] ?? 0) <=> ($a['valores'][$ref] ?? 0));
            $lista = array_values($items);
            $topItems = array_slice($lista, 0, $top);
            $resto = array_fill_keys($conDatos, 0.0);
            foreach (array_slice($lista, $top) as $it) {
                foreach ($it['valores'] as $g => $v) {
                    $resto[$g] += $v;
                }
            }
            $acum = 0;
            $totRef = $totales[$ref] ?: 1;
            foreach ($topItems as $i => &$it) {
                $v = $it['valores'][$ref] ?? 0;
                $acum += $v;
                $it['pct'] = round($v / $totRef * 100, 1);
                $it['acum'] = round($acum / $totRef * 100, 1);
                $it['posicion'] = $i + 1;
            }
            unset($it);

            // Meses por año (para avisar si se compara un año parcial con uno completo)
            $meses = [];
            foreach ($conDatos as $a) {
                $meses[$a] = $this->mesesCargados($org, $a, $flujo);
            }

            return [
                'anios'      => $conDatos,
                'sin_datos'  => $sinDatos,
                'referencia' => $ref,
                'meses'      => $meses,
                'filas'      => $topItems,
                'resto'      => $resto,
                'totales'    => $totales,
                'n_items'    => count($lista),
            ];
        });
    }

    // =====================================================================
    //  Indicadores
    // =====================================================================

    /** HHI normalizado en escala 0–1 (suma de cuadrados de participaciones). */
    private function hhi(array $valores): ?float
    {
        $tot = array_sum($valores);
        if ($tot <= 0) {
            return null;
        }
        $h = 0;
        foreach ($valores as $v) {
            $h += ($v / $tot) ** 2;
        }

        return round($h, 3);
    }

    private function valoresPor(string $vista, string $col, int $org, int $gestion, string $flujo): array
    {
        if (! $this->hayDatos($org, $gestion, $flujo)) {
            return [];
        }

        return DB::table($vista)->where('organizacion_id', $org)->where('gestion', $gestion)
            ->whereIn('flujo_id', $this->ids($flujo))->groupBy($col)
            ->selectRaw('SUM(valor) AS v')->pluck('v')->map(fn ($v) => (float) $v)->all();
    }

    public function indicadores(int $gestion, int $org = self::ORG_INE): array
    {
        return $this->cache("ind.{$org}.{$gestion}", function () use ($gestion, $org) {
            $exp = $this->bloqueTotal($org, $gestion, 'exp');
            $imp = $this->bloqueTotal($org, $gestion, 'imp');
            $pesoExp = $this->hayDatos($org, $gestion, 'exp') ? (float) DB::table('resumen_anual_producto')
                ->where('organizacion_id', $org)->where('gestion', $gestion)->whereIn('flujo_id', $this->expIds())->sum('peso_neto') : 0;
            $pesoImp = $this->hayDatos($org, $gestion, 'imp') ? (float) DB::table('resumen_anual_producto')
                ->where('organizacion_id', $org)->where('gestion', $gestion)->whereIn('flujo_id', $this->impIds())->sum('peso_neto') : 0;

            $hhi = [
                ['clave' => 'prod_exp', 'nombre' => 'Productos exportados', 'valor' => $this->hhi($this->valoresPor('resumen_anual_producto', 'producto_id', $org, $gestion, 'exp'))],
                ['clave' => 'prod_imp', 'nombre' => 'Productos importados', 'valor' => $this->hhi($this->valoresPor('resumen_anual_producto', 'producto_id', $org, $gestion, 'imp'))],
                ['clave' => 'pais_exp', 'nombre' => 'Países de destino', 'valor' => $this->hhi($this->valoresPor('resumen_anual_pais', 'pais_id', $org, $gestion, 'exp'))],
                ['clave' => 'pais_imp', 'nombre' => 'Países de origen', 'valor' => $this->hhi($this->valoresPor('resumen_anual_pais', 'pais_id', $org, $gestion, 'imp'))],
                ['clave' => 'depto_exp', 'nombre' => 'Departamentos exportadores', 'valor' => $this->hhi($this->valoresPor('resumen_anual_departamento', 'departamento_id', $org, $gestion, 'exp'))],
            ];

            $ambos = $exp['valor'] !== null && $imp['valor'] !== null;

            return [
                'gestion'   => $gestion,
                'cobertura' => $this->cobertura($org),
                'exp'       => $exp,
                'imp'       => $imp,
                'saldo'     => $ambos ? $exp['valor'] - $imp['valor'] : null,
                'tasa_cobertura' => ($ambos && $imp['valor']) ? round($exp['valor'] / $imp['valor'] * 100, 1) : null,
                'valor_unitario_exp' => ($exp['valor'] !== null && $pesoExp > 0) ? round($exp['valor'] / ($pesoExp / 1000), 1) : null,
                'valor_unitario_imp' => ($imp['valor'] !== null && $pesoImp > 0) ? round($imp['valor'] / ($pesoImp / 1000), 1) : null,
                'toneladas_exp' => $pesoExp > 0 ? round($pesoExp / 1000) : null,
                'hhi'       => $hhi,
                'serie'     => $this->serieAnual($org),
            ];
        });
    }

    // =====================================================================
    //  Línea de tiempo
    // =====================================================================

    /** Serie anual completa (exp, imp, saldo, cobertura) con nulls donde no hay datos. */
    public function serieAnual(int $org = self::ORG_INE): array
    {
        return $this->cache("serie.{$org}", function () use ($org) {
            $c = $this->cobertura($org);
            $anios = $this->gestiones($org);
            sort($anios);
            $rows = DB::table('resumen_mensual')->where('organizacion_id', $org)
                ->groupBy('gestion', 'flujo_id')->selectRaw('gestion, flujo_id, SUM(valor) AS valor')->get();
            $exp = $imp = [];
            foreach ($rows as $r) {
                $g = (int) $r->gestion;
                if (in_array((int) $r->flujo_id, $this->expIds(), true)) {
                    $exp[$g] = ($exp[$g] ?? 0) + (float) $r->valor;
                } else {
                    $imp[$g] = ($imp[$g] ?? 0) + (float) $r->valor;
                }
            }

            return array_map(function ($g) use ($exp, $imp, $c) {
                $e = in_array($g, $c['exp']['anios'], true) ? ($exp[$g] ?? null) : null;
                $i = in_array($g, $c['imp']['anios'], true) ? ($imp[$g] ?? null) : null;

                return [
                    'gestion'    => $g,
                    'exp'        => $e,
                    'imp'        => $i,
                    'saldo'      => ($e !== null && $i !== null) ? $e - $i : null,
                    'cobertura'  => ($e !== null && $i) ? round($e / $i * 100, 1) : null,
                    'meses_exp'  => $c['exp']['meses'][$g] ?? 0,
                    'meses_imp'  => $c['imp']['meses'][$g] ?? 0,
                ];
            }, $anios);
        });
    }

    public function lineaDeTiempo(int $org = self::ORG_INE): array
    {
        return $this->cache("tiempo.{$org}", function () use ($org) {
            $serie = $this->serieAnual($org);
            $ids = $this->in($this->expIds());
            // Producto y destino líder de exportación por año (dato real, no un hito editorial)
            $lideres = collect(DB::select("
                SELECT DISTINCT ON (r.gestion) r.gestion, p.descripcion, SUM(r.valor) AS valor
                FROM resumen_anual_producto r JOIN producto p ON p.producto_id = r.producto_id
                WHERE r.organizacion_id = ? AND r.flujo_id IN ($ids)
                GROUP BY r.gestion, p.producto_id, p.descripcion
                ORDER BY r.gestion, SUM(r.valor) DESC", [$org]))->keyBy('gestion');
            $destinos = collect(DB::select("
                SELECT DISTINCT ON (r.gestion) r.gestion, COALESCE(c.nombre_canonico, pa.nombre) AS nombre, SUM(r.valor) AS valor
                FROM resumen_anual_pais r JOIN pais pa ON pa.pais_id = r.pais_id LEFT JOIN v_pais_canonico c ON c.pais_id = r.pais_id
                WHERE r.organizacion_id = ? AND r.flujo_id IN ($ids)
                GROUP BY r.gestion, 2
                ORDER BY r.gestion, SUM(r.valor) DESC", [$org]))->keyBy('gestion');

            $prev = null;
            foreach ($serie as &$s) {
                $g = $s['gestion'];
                $s['var_exp'] = ($prev && $prev['exp'] && $s['exp'] !== null && $s['meses_exp'] >= 12) ? round(($s['exp'] - $prev['exp']) / $prev['exp'] * 100, 1) : null;
                $s['producto_lider'] = isset($lideres[$g]) ? $this->limpio($lideres[$g]->descripcion) : null;
                $s['producto_lider_pct'] = (isset($lideres[$g]) && $s['exp']) ? round($lideres[$g]->valor / $s['exp'] * 100, 1) : null;
                $s['destino_lider'] = isset($destinos[$g]) ? $this->titulo($destinos[$g]->nombre) : null;
                $prev = $s;
            }
            unset($s);

            return ['serie' => $serie, 'cobertura' => $this->cobertura($org)];
        });
    }

    // =====================================================================
    //  Organizaciones: qué hay cargado de cada fuente
    // =====================================================================

    public function estadoFuentes(): array
    {
        return $this->cache('fuentes', function () {
            $orgs = DB::table('organizacion')->orderBy('organizacion_id')->get(['organizacion_id', 'nombre', 'sigla']);
            $cargas = DB::table('carga_archivo')
                ->selectRaw('organizacion_id, COUNT(*) AS archivos, MAX(fecha_carga) AS ultima')
                ->groupBy('organizacion_id')->get()->keyBy('organizacion_id');
            $out = [];
            foreach ($orgs as $o) {
                $id = (int) $o->organizacion_id;
                $filas = 0;
                $desde = $hasta = null;
                try {
                    [$filas, $desde, $hasta] = match ($id) {
                        1 => $this->rango('operacion_comercio_exterior', 'gestion', 'organizacion_id = 1'),
                        2 => $this->rango('ranking_comercio rc JOIN tiempo t ON t.tiempo_id = rc.tiempo_id', 't.gestion'),
                        3 => $this->rango('serie_comercio_zona sz JOIN tiempo t ON t.tiempo_id = sz.tiempo_id', 't.gestion'),
                        4 => $this->rango('serie_indicador_agricola si JOIN tiempo t ON t.tiempo_id = si.tiempo_id', 't.gestion'),
                        default => [0, null, null],
                    };
                } catch (\Throwable $e) {
                    $filas = 0;
                }
                $out[] = [
                    'organizacion_id' => $id,
                    'sigla'    => $o->sigla,
                    'nombre'   => $o->nombre,
                    'filas'    => $filas,
                    'desde'    => $desde,
                    'hasta'    => $hasta,
                    'archivos' => (int) ($cargas[$id]->archivos ?? 0),
                    'ultima'   => $cargas[$id]->ultima ?? null,
                    'cobertura' => $id === self::ORG_INE ? $this->cobertura($id) : null,
                ];
            }

            return $out;
        });
    }

    private function rango(string $from, string $col, ?string $where = null): array
    {
        $r = DB::selectOne("SELECT COUNT(*) AS n, MIN($col) AS d, MAX($col) AS h FROM $from".($where ? " WHERE $where" : ''));

        return [(int) $r->n, $r->d ? (int) $r->d : null, $r->h ? (int) $r->h : null];
    }

    // =====================================================================
    //  Helpers de texto
    // =====================================================================

    private function limpio(?string $s): string
    {
        $s = trim(preg_replace('/\s+/', ' ', (string) $s));
        // Las descripciones del INE vienen en MAYÚSCULAS: se pasan a tipo oración.
        if ($s !== '' && mb_strtoupper($s) === $s && preg_match('/[A-ZÁÉÍÓÚÑ]{4,}/u', $s)) {
            $s = mb_strtolower($s);
            $s = mb_strtoupper(mb_substr($s, 0, 1)).mb_substr($s, 1);
        }

        return $s;
    }

    /** "SANTA CRUZ" -> "Santa Cruz" (solo si viene todo en mayúsculas). */
    private function titulo(?string $s): string
    {
        $s = $this->limpio($s);
        if ($s !== '' && mb_strtoupper($s) === $s) {
            $s = mb_convert_case(mb_strtolower($s), MB_CASE_TITLE, 'UTF-8');
            $s = preg_replace_callback('/\b(De|Del|La|Las|Los|Y|E|En|Para|Con|Sin|O|A|Al)\b/u', fn ($m) => mb_strtolower($m[1]), $s);
            $s = mb_strtoupper(mb_substr($s, 0, 1)).mb_substr($s, 1);
        }

        return $s;
    }
}
