<?php

namespace App\Servicios\Educacion;

use App\Servicios\PortalVistas;

/**
 * Calcula AL VUELO la respuesta correcta de un reto (mejora 2) o de una
 * pregunta "dato" de una actividad docente (mejora 4), con las mismas
 * consultas que usan los rankings e indicadores del portal (PortalVistas).
 *
 * Nunca se confía en un valor escrito en el enunciado: si mañana se cargan las
 * importaciones 2000-2026, los retos se recalculan solos. Si la gestión o el
 * flujo no están cargados, el reto queda "no disponible" (no se inventa nada).
 */
class EvaluadorReto
{
    public const TIPOS = [
        'top_pais' => ['etiqueta' => 'País con más valor (gestión, flujo)', 'respuesta' => 'opcion', 'params' => ['gestion', 'flujo']],
        'top_departamento' => ['etiqueta' => 'Departamento con más valor (gestión, flujo)', 'respuesta' => 'opcion', 'params' => ['gestion', 'flujo']],
        'top_via' => ['etiqueta' => 'Vía de transporte con más valor (gestión, flujo)', 'respuesta' => 'opcion', 'params' => ['gestion', 'flujo']],
        'top_seccion' => ['etiqueta' => 'Sección del SA con más valor (gestión, flujo)', 'respuesta' => 'opcion', 'params' => ['gestion', 'flujo']],
        'variacion' => ['etiqueta' => 'Variación % de las exportaciones entre dos gestiones', 'respuesta' => 'numero', 'params' => ['desde', 'hasta', 'tolerancia']],
        'tasa_cobertura' => ['etiqueta' => 'Tasa de cobertura X/M de una gestión', 'respuesta' => 'numero', 'params' => ['gestion', 'tolerancia']],
        'signo_saldo' => ['etiqueta' => 'Balanza: ¿superávit o déficit? (gestión)', 'respuesta' => 'binaria', 'params' => ['gestion']],
        'anio_maximo' => ['etiqueta' => 'Gestión con más exportaciones en un rango', 'respuesta' => 'opcion', 'params' => ['desde', 'hasta']],
    ];

    private const SECCIONES = [
        0 => 'Sin sección', 1 => 'Animales y productos del reino animal', 2 => 'Productos del reino vegetal', 3 => 'Grasas y aceites',
        4 => 'Alimentos, bebidas y tabaco', 5 => 'Productos minerales', 6 => 'Productos químicos', 7 => 'Plástico y caucho',
        8 => 'Pieles y cueros', 9 => 'Madera y corcho', 10 => 'Papel y cartón', 11 => 'Textiles', 12 => 'Calzado y sombreros',
        13 => 'Piedra, cerámica y vidrio', 14 => 'Metales preciosos y joyería', 15 => 'Metales comunes', 16 => 'Máquinas y aparatos eléctricos',
        17 => 'Material de transporte', 18 => 'Instrumentos de precisión', 19 => 'Armas y municiones', 20 => 'Manufacturas diversas',
        21 => 'Objetos de arte y antigüedades', 23 => 'Mercancías no clasificadas',
    ];

    public function __construct(private DatosEducativos $datos)
    {
    }

    private function vistas(): PortalVistas
    {
        return $this->datos->vistas();
    }

    private function nombreFlujo(string $f): string
    {
        return $f === 'imp' ? 'importaciones' : 'exportaciones';
    }

    private function hay(int $g, string $f): bool
    {
        return in_array($g, $this->datos->cobertura()[$f]['anios'] ?? [], true);
    }

    private function noDisponible(string $motivo): array
    {
        return ['disponible' => false, 'motivo' => $motivo];
    }

    /**
     * @return array{disponible:bool, motivo?:string, tipo?:string, opciones?:array, correcta?:mixed, etiqueta?:string, detalle?:array}
     */
    public function evaluar(array $c): array
    {
        $tipo = $c['tipo'] ?? '';
        $g = (int) ($c['gestion'] ?? 0);
        $f = ($c['flujo'] ?? 'exp') === 'imp' ? 'imp' : 'exp';
        $org = DatosEducativos::ORG;

        try {
            switch ($tipo) {
                case 'top_pais':
                    if (! $this->hay($g, $f)) {
                        return $this->noDisponible("Todavía no hay {$this->nombreFlujo($f)} cargadas para {$g}.");
                    }
                    $lista = $this->vistas()->topPaises($org, $g, $f, 400);

                    return $this->opcionDeLista($lista, fn ($x) => $x['clave'], fn ($x) => $x['nombre'], 'opcion', $f, $g);

                case 'top_departamento':
                    if (! $this->hay($g, $f)) {
                        return $this->noDisponible("Todavía no hay {$this->nombreFlujo($f)} cargadas para {$g}.");
                    }
                    $lista = $this->vistas()->topDepartamentos($org, $g, $f, 30);

                    return $this->opcionDeLista($lista, fn ($x) => $x['nombre'], fn ($x) => $x['nombre'], 'opcion', $f, $g);

                case 'top_via':
                    if (! $this->hay($g, $f)) {
                        return $this->noDisponible("Todavía no hay {$this->nombreFlujo($f)} cargadas para {$g}.");
                    }
                    $vias = array_map(fn ($v) => ['clave' => $v['clave'], 'nombre' => $v['nombre'], 'valor' => (float) ($v[$f] ?? 0)], $this->vistas()->vias($org, $g));
                    usort($vias, fn ($a, $b) => $b['valor'] <=> $a['valor']);
                    $tot = array_sum(array_column($vias, 'valor')) ?: 1;
                    foreach ($vias as &$v) {
                        $v['pct'] = round($v['valor'] / $tot * 100, 1);
                    }
                    unset($v);

                    return $this->opcionDeLista($vias, fn ($x) => $x['clave'], fn ($x) => $x['nombre'], 'opcion', $f, $g, false);

                case 'top_seccion':
                    if (! $this->hay($g, $f)) {
                        return $this->noDisponible("Todavía no hay {$this->nombreFlujo($f)} cargadas para {$g}.");
                    }
                    $rk = $this->vistas()->rankings([$g], $f, 'seccion', 25, $org);
                    $tot = $rk['totales'][$g] ?? 1;
                    $lista = array_map(fn ($x) => [
                        'clave' => (string) $x['clave'],
                        'nombre' => self::SECCIONES[(int) $x['clave']] ?? $x['nombre'],
                        'valor' => (float) ($x['valores'][$g] ?? 0),
                        'pct' => round(($x['valores'][$g] ?? 0) / ($tot ?: 1) * 100, 1),
                    ], $rk['filas'] ?? []);

                    return $this->opcionDeLista($lista, fn ($x) => $x['clave'], fn ($x) => $x['nombre'], 'opcion', $f, $g);

                case 'variacion':
                    $a = (int) ($c['desde'] ?? 0);
                    $b = (int) ($c['hasta'] ?? 0);
                    $v = $this->datos->variacion($a, $b);
                    if ($v === null) {
                        return $this->noDisponible("Faltan exportaciones completas de {$a} o {$b}.");
                    }
                    $s = $this->datos->serie();

                    return ['disponible' => true, 'tipo' => 'numero', 'correcta' => $v, 'unidad' => '%',
                        'etiqueta' => $this->datos->pct($v, true),
                        'detalle' => ['desde' => $a, 'hasta' => $b, 'valor_desde' => $s[$a]['exp'], 'valor_hasta' => $s[$b]['exp']]];

                case 'tasa_cobertura':
                    $s = $this->datos->serie()[$g] ?? null;
                    if (! $s || $s['cobertura'] === null) {
                        return $this->noDisponible("La gestión {$g} no tiene exportaciones e importaciones cargadas a la vez.");
                    }

                    return ['disponible' => true, 'tipo' => 'numero', 'correcta' => (float) $s['cobertura'], 'unidad' => '%',
                        'etiqueta' => $this->datos->pct($s['cobertura']),
                        'detalle' => ['gestion' => $g, 'exp' => $s['exp'], 'imp' => $s['imp']]];

                case 'signo_saldo':
                    $s = $this->datos->serie()[$g] ?? null;
                    if (! $s || $s['saldo'] === null) {
                        return $this->noDisponible("La gestión {$g} no tiene exportaciones e importaciones cargadas a la vez.");
                    }
                    $ok = $s['saldo'] >= 0 ? 'superavit' : 'deficit';

                    return ['disponible' => true, 'tipo' => 'binaria', 'correcta' => $ok, 'etiqueta' => $ok === 'superavit' ? 'Superávit' : 'Déficit',
                        'opciones' => [['valor' => 'superavit', 'etiqueta' => 'Superávit (vendió más de lo que compró)'], ['valor' => 'deficit', 'etiqueta' => 'Déficit (compró más de lo que vendió)']],
                        'detalle' => ['gestion' => $g, 'exp' => $s['exp'], 'imp' => $s['imp'], 'saldo' => $s['saldo']]];

                case 'anio_maximo':
                    $a = (int) ($c['desde'] ?? 1992);
                    $b = (int) ($c['hasta'] ?? date('Y'));
                    $m = $this->datos->maxExpAnio($a, $b);
                    if (! $m) {
                        return $this->noDisponible("No hay exportaciones completas entre {$a} y {$b}.");
                    }
                    $anios = array_values(array_filter(array_keys($this->datos->serie()), fn ($x) => $x >= $a && $x <= $b && ($this->datos->serie()[$x]['meses_exp'] ?? 0) >= 12));

                    return ['disponible' => true, 'tipo' => 'opcion', 'correcta' => (string) $m, 'etiqueta' => (string) $m,
                        'opciones' => array_map(fn ($x) => ['valor' => (string) $x, 'etiqueta' => (string) $x], $anios),
                        'detalle' => ['gestion' => $m, 'valor' => $this->datos->serie()[$m]['exp']]];
            }
        } catch (\Throwable $e) {
            report($e);

            return $this->noDisponible('No se pudo consultar la base en este momento.');
        }

        return $this->noDisponible('Tipo de consulta desconocido.');
    }

    private function opcionDeLista(array $lista, \Closure $clave, \Closure $nombre, string $tipo, string $f, int $g, bool $ordenarPorNombre = true): array
    {
        if (! $lista) {
            return $this->noDisponible("No hay {$this->nombreFlujo($f)} registradas en {$g}.");
        }
        $primero = $lista[0];
        $opciones = array_map(fn ($x) => ['valor' => (string) $clave($x), 'etiqueta' => (string) $nombre($x)], $lista);
        if ($ordenarPorNombre) {
            usort($opciones, fn ($a, $b) => strcoll($a['etiqueta'], $b['etiqueta']));
        }

        return ['disponible' => true, 'tipo' => $tipo, 'correcta' => (string) $clave($primero), 'etiqueta' => (string) $nombre($primero),
            'opciones' => $opciones,
            'detalle' => ['gestion' => $g, 'flujo' => $f, 'valor' => $primero['valor'] ?? null, 'pct' => $primero['pct'] ?? null,
                          'segundo' => isset($lista[1]) ? ['nombre' => $nombre($lista[1]), 'valor' => $lista[1]['valor'] ?? null] : null]];
    }

    /** Opciones visibles para el estudiante (sin la respuesta correcta). */
    public function publico(array $c): array
    {
        $e = $this->evaluar($c);
        if (! $e['disponible']) {
            return ['disponible' => false, 'motivo' => $e['motivo']];
        }

        return ['disponible' => true, 'tipo' => $e['tipo'], 'opciones' => $e['opciones'] ?? [], 'unidad' => $e['unidad'] ?? null];
    }

    /**
     * Compara la respuesta del estudiante con la calculada en vivo.
     * Colegio: tolerancia doble en las respuestas numéricas (más ayuda).
     */
    public function comprobar(array $c, string $respuesta, string $nivel = 'colegio'): array
    {
        $e = $this->evaluar($c);
        if (! $e['disponible']) {
            return ['disponible' => false, 'correcta' => false, 'explicacion' => $e['motivo']];
        }
        $resp = trim($respuesta);
        if ($e['tipo'] === 'numero') {
            $n = $this->numero($resp);
            $tol = (float) ($c['tolerancia'] ?? 1) * ($nivel === 'colegio' ? 2 : 1);
            $ok = $n !== null && abs($n - (float) $e['correcta']) <= $tol + 1e-9;
        } else {
            $ok = mb_strtolower($resp) === mb_strtolower((string) $e['correcta']);
        }

        return ['disponible' => true, 'correcta' => $ok, 'esperada' => $e['etiqueta'], 'explicacion' => $this->explicar($c, $e, $nivel)];
    }

    public function numero(string $s): ?float
    {
        $s = str_replace(['%', ' ', '−'], ['', '', '-'], $s);
        if (preg_match('/^-?\d{1,3}(\.\d{3})+(,\d+)?$/', $s)) {
            $s = str_replace('.', '', $s);
        }
        $s = str_replace(',', '.', $s);

        return is_numeric($s) ? (float) $s : null;
    }

    private function explicar(array $c, array $e, string $nivel): string
    {
        $d = $e['detalle'] ?? [];

        return match ($c['tipo']) {
            'top_pais', 'top_departamento', 'top_via', 'top_seccion' => "Según la base, en {$d['gestion']} el primer puesto en {$this->nombreFlujo($d['flujo'])} fue {$e['etiqueta']}, con {$this->datos->dinero($d['valor'], $nivel)}"
                .($d['pct'] !== null ? " ({$this->datos->pct($d['pct'])} del total)" : '')
                .($d['segundo'] ? ". Segundo: {$d['segundo']['nombre']} ({$this->datos->dinero($d['segundo']['valor'], $nivel)})." : '.'),
            'variacion' => "Exportaciones {$d['desde']}: {$this->datos->dinero($d['valor_desde'], $nivel)}; {$d['hasta']}: {$this->datos->dinero($d['valor_hasta'], $nivel)}. Variación = ({$d['hasta']} − {$d['desde']}) ÷ {$d['desde']} × 100 = {$e['etiqueta']}.",
            'tasa_cobertura' => "En {$d['gestion']}: exportaciones {$this->datos->dinero($d['exp'], $nivel)} ÷ importaciones {$this->datos->dinero($d['imp'], $nivel)} × 100 = {$e['etiqueta']}.",
            'signo_saldo' => "En {$d['gestion']}: exportaciones {$this->datos->dinero($d['exp'], $nivel)} − importaciones {$this->datos->dinero($d['imp'], $nivel)} = {$this->datos->dinero($d['saldo'], $nivel)}. Por eso fue {$e['etiqueta']}.",
            'anio_maximo' => "La exportación más alta del rango fue en {$d['gestion']}: {$this->datos->dinero($d['valor'], $nivel)}.",
            default => '',
        };
    }
}
