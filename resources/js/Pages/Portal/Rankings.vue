<script setup>
/**
 * Rankings y Comparador unificados (estilo mockup, GEO-12).
 * Tabla de Pareto + barras agrupadas entre 2 y 6 gestiones, con datos reales
 * de /portal/v2/rankings. Las gestiones sin datos del flujo se marcan como tales.
 */
import { ref, computed, watch, onMounted } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import GdSinDatos from '@/Components/Gd/GdSinDatos.vue';
import GdCobertura from '@/Components/Gd/GdCobertura.vue';
import GdExplicacion from '@/Components/Gd/GdExplicacion.vue';
import { fmtM, fmtPct, fmtNum, etiquetaGestion, getJson, descargarCsv } from '@/lib/gd';

const props = defineProps({
    cobertura: { type: Object, default: null },
    gestionesPortal: { type: Array, default: () => [] },
    gestionInicial: { type: Number, default: null },
});

const COLORES = ['#e07b53', '#2f6f73', '#5e1224', '#3b82f6', '#f59e0b', '#7c5dfa'];
const DIMENSIONES = [
    { v: 'producto', t: 'Productos NANDINA' },
    { v: 'pais', t: 'Países (ISO)' },
    { v: 'departamento', t: 'Departamentos' },
    { v: 'seccion', t: 'Secciones del SA' },
];

// Filtros iniciales desde la URL (enlaces de retos, lecciones y actividades docentes):
// ?flujo=exp|imp&dimension=pais|producto|departamento|seccion&gestion=2010 o &anios=2010,2015
const qs = new URLSearchParams(typeof window !== 'undefined' ? window.location.search : '');
const flujo = ref(qs.get('flujo') === 'imp' ? 'imp' : 'exp');
const dimension = ref(DIMENSIONES.some((d) => d.v === qs.get('dimension')) ? qs.get('dimension') : 'producto');
function aniosIniciales() {
    const disp = props.cobertura?.[flujo.value]?.anios ?? [];
    const pedidos = (qs.get('anios') ?? '').split(',').map(Number).filter((a) => disp.includes(a)).slice(0, 6);
    if (pedidos.length) return [...new Set(pedidos)].sort();
    const g = Number(qs.get('gestion'));
    if (g && disp.includes(g)) return [g - 1, g].filter((a) => disp.includes(a));
    return [props.gestionInicial - 1, props.gestionInicial].filter(Boolean);
}
const anios = ref(aniosIniciales());
const nuevoAnio = ref('');
const buscar = ref('');
const datos = ref(null);
const cargando = ref(false);

const aniosFlujo = computed(() => props.cobertura?.[flujo.value]?.anios ?? []);
const etq = (g) => etiquetaGestion(g, props.cobertura, flujo.value);

async function cargar() {
    cargando.value = true;
    try {
        datos.value = await getJson('/portal/v2/rankings', { anios: anios.value, flujo: flujo.value, dimension: dimension.value, top: 10 });
    } finally {
        cargando.value = false;
    }
}
watch([flujo, dimension, anios], cargar, { deep: true });
onMounted(cargar);

// Al cambiar a un flujo sin datos en los años elegidos, propone los dos últimos con datos.
watch(flujo, (f) => {
    const disp = props.cobertura?.[f]?.anios ?? [];
    if (disp.length && !anios.value.some((a) => disp.includes(a))) {
        anios.value = disp.slice(-2);
    }
});

function agregar() {
    const a = Number(nuevoAnio.value);
    if (a && !anios.value.includes(a) && anios.value.length < 6) anios.value = [...anios.value, a].sort();
    nuevoAnio.value = '';
}
function quitar(a) {
    if (anios.value.length > 2) anios.value = anios.value.filter((x) => x !== a);
}

const conDatos = computed(() => datos.value?.anios ?? []);
const ref_ = computed(() => datos.value?.referencia);
const ultimos2 = computed(() => conDatos.value.slice(-2));
const colorDe = (a) => COLORES[anios.value.indexOf(a) % COLORES.length];

const filas = computed(() => {
    const q = buscar.value.trim().toLowerCase();
    return (datos.value?.filas ?? []).filter((f) => !q || `${f.nombre} ${f.codigo ?? ''}`.toLowerCase().includes(q));
});
function variacion(f) {
    const [a, b] = ultimos2.value;
    if (!a || !b) return null;
    const va = f.valores?.[a], vb = f.valores?.[b];
    return va ? ((vb ?? 0) - va) / va * 100 : null;
}
const parcial = computed(() => conDatos.value.some((a) => (datos.value?.meses?.[a] ?? 12) < 12));

// Gráfico de barras agrupadas (SVG)
const ANCHO = 1100, ALTO = 300, BASE = 250;
const grafico = computed(() => {
    const f = filas.value.slice(0, 10);
    const n = conDatos.value.length || 1;
    const max = Math.max(1, ...f.flatMap((x) => conDatos.value.map((a) => x.valores?.[a] ?? 0)));
    const grupo = ANCHO / Math.max(f.length, 1);
    const barra = Math.min(46, (grupo - 24) / n);
    return f.map((x, i) => ({
        nombre: x.nombre.length > 15 ? x.nombre.slice(0, 14) + '…' : x.nombre,
        completo: x.nombre,
        cx: i * grupo + grupo / 2,
        var: variacion(x),
        barras: conDatos.value.map((a, j) => {
            const v = x.valores?.[a] ?? 0;
            const h = (v / max) * (BASE - 30);
            return { a, v, h, x: i * grupo + grupo / 2 - (n * barra) / 2 + j * barra, w: barra - 3, y: BASE - h };
        }),
    }));
});

function descargar() {
    const d = datos.value;
    const cab = ['Posición', 'Código', 'Categoría', ...conDatos.value.map((a) => `${etq(a)} (USD)`), `% del total ${ref_.value}`, '% acumulado'];
    const rows = filas.value.map((f) => [f.posicion, f.codigo ?? '', f.nombre, ...conDatos.value.map((a) => Math.round(f.valores?.[a] ?? 0)), f.pct, f.acum]);
    rows.push(['', '', 'Resto', ...conDatos.value.map((a) => Math.round(d.resto?.[a] ?? 0)), '', '']);
    rows.push(['', '', 'Total INE', ...conDatos.value.map((a) => Math.round(d.totales?.[a] ?? 0)), 100, '']);
    descargarCsv(`geodata_ranking_${dimension.value}_${flujo.value}.csv`, [cab, ...rows]);
}
</script>

<template>
    <Head title="Rankings y Comparador" />
    <div class="page-wrap gd-pt">
        <div class="gd-cab">
            <div>
                <span class="gd-kicker">● Análisis arancelario y contraste interanual</span>
                <h2 class="gd-titulo">Rankings y Comparador</h2>
            </div>
            <div style="display:flex;gap:8px;flex-wrap:wrap">
                <button class="tool-btn" :class="{ active: flujo === 'exp' }" @click="flujo = 'exp'">Exportaciones FOB</button>
                <button class="tool-btn" :class="{ active: flujo === 'imp' }" @click="flujo = 'imp'">Importaciones CIF</button>
                <button class="tool-btn" :disabled="!filas.length" @click="descargar">Descargar vista (Excel)</button>
            </div>
        </div>

        <GdExplicacion clave="rankings.tabla" plegado />

        <div class="overview-panel" style="padding:16px 20px;margin-bottom:20px">
            <div class="rk-years">
                <div class="rk-years-head">
                    <b>Años a comparar</b>
                    <small>Elige los años · mínimo 2, máximo 6</small>
                </div>
                <div class="rk-chips">
                    <span v-for="a in anios" :key="a" class="rk-chip" :style="{ '--c': colorDe(a) }">
                        <i></i>{{ etq(a) }}<template v-if="!aniosFlujo.includes(a)"> · sin datos</template>
                        <button type="button" class="rk-x" :disabled="anios.length <= 2" :title="anios.length <= 2 ? 'Se necesitan al menos 2 años' : 'Quitar ' + a" @click="quitar(a)">×</button>
                    </span>
                </div>
                <div class="rk-add">
                    <select v-model="nuevoAnio" class="inv-select" :disabled="anios.length >= 6">
                        <option value="">Año…</option>
                        <option v-for="g in gestionesPortal.filter((x) => !anios.includes(x))" :key="g" :value="g" :disabled="!aniosFlujo.includes(g)">
                            {{ etq(g) }}{{ aniosFlujo.includes(g) ? '' : ' (sin datos)' }}
                        </option>
                    </select>
                    <button class="tool-btn" :disabled="!nuevoAnio" @click="agregar">+ Agregar año</button>
                </div>
            </div>
            <div class="rk-filters">
                <select v-model="dimension" class="inv-select">
                    <option v-for="d in DIMENSIONES" :key="d.v" :value="d.v">Dimensión: {{ d.t }}</option>
                </select>
                <select class="inv-select" disabled><option>Organización: INE Bolivia</option></select>
                <select class="inv-select" disabled><option>Ordenar por: {{ ref_ ? etq(ref_) : '—' }}</option></select>
                <input v-model="buscar" type="search" class="inv-search" placeholder="Buscar partida, código o país...">
            </div>
            <div style="margin-top:12px"><GdCobertura :cobertura="cobertura" /></div>
        </div>

        <div :class="{ 'gd-cargando': cargando }">
            <div v-if="datos && !conDatos.length" class="overview-panel">
                <GdSinDatos :titulo="`No hay ${flujo === 'exp' ? 'exportaciones' : 'importaciones'} cargadas para los años elegidos`"
                            :mensaje="`Disponibles: ${cobertura?.[flujo]?.etiqueta}.`" />
            </div>

            <template v-else-if="datos">
                <div class="overview-panel">
                    <div class="bento-h">
                        <h3>Tabla de posiciones (Pareto) · {{ flujo === 'exp' ? 'Exportaciones FOB' : 'Importaciones CIF' }}</h3>
                        <span class="badge-var">INE Bolivia · por {{ etq(ref_) }} · {{ fmtNum(datos.n_items) }} categorías</span>
                    </div>
                    <p v-if="datos.sin_datos?.length" style="margin:-4px 0 10px;font-size:0.8rem;color:#92400e">
                        <span class="gd-tag-sindatos">Sin datos</span> {{ datos.sin_datos.join(', ') }}: no hay {{ flujo === 'exp' ? 'exportaciones' : 'importaciones' }} cargadas; se omiten de la tabla.
                    </p>
                    <div style="overflow-x:auto">
                        <table class="data-table">
                            <thead><tr>
                                <th>#</th><th>Código</th><th>Categoría</th>
                                <th v-for="a in conDatos" :key="a" class="gd-num">{{ etq(a) }}</th>
                                <th v-if="ultimos2.length === 2" class="gd-num">Var. {{ etq(ultimos2[0]) }} → {{ etq(ultimos2[1]) }}</th>
                                <th class="gd-num">% del total ({{ etq(ref_) }})</th><th class="gd-num">% acum.</th>
                            </tr></thead>
                            <tbody>
                                <tr v-for="f in filas" :key="f.clave">
                                    <td><b>{{ f.posicion }}</b></td>
                                    <td style="font-family:monospace">{{ f.codigo ?? '—' }}</td>
                                    <td><b>{{ f.nombre }}</b></td>
                                    <td v-for="a in conDatos" :key="a" class="gd-num" :style="{ fontWeight: a === ref_ ? 800 : 500 }">{{ f.valores?.[a] != null ? fmtM(f.valores[a]) : '—' }}</td>
                                    <td v-if="ultimos2.length === 2" class="gd-num">
                                        <span v-if="variacion(f) != null" class="gd-var" :class="variacion(f) < 0 ? 'neg' : 'pos'">{{ fmtPct(variacion(f), true) }}</span><span v-else>—</span>
                                    </td>
                                    <td class="gd-num">{{ fmtPct(f.pct) }}</td>
                                    <td class="gd-num">{{ fmtPct(f.acum) }}</td>
                                </tr>
                                <tr v-if="!buscar" style="color:var(--muted)">
                                    <td>—</td><td></td><td>Resto (fuera del ranking)</td>
                                    <td v-for="a in conDatos" :key="a" class="gd-num">{{ fmtM(datos.resto?.[a]) }}</td>
                                    <td v-if="ultimos2.length === 2"></td>
                                    <td class="gd-num">{{ fmtPct(datos.totales?.[ref_] ? datos.resto[ref_] / datos.totales[ref_] * 100 : null) }}</td><td class="gd-num">100%</td>
                                </tr>
                                <tr v-if="!buscar">
                                    <td></td><td></td><td><b>Total INE Bolivia</b></td>
                                    <td v-for="a in conDatos" :key="a" class="gd-num"><b>{{ fmtM(datos.totales?.[a]) }}</b></td>
                                    <td v-if="ultimos2.length === 2" class="gd-num">
                                        <span class="gd-var" :class="(datos.totales[ultimos2[1]] - datos.totales[ultimos2[0]]) < 0 ? 'neg' : 'pos'">{{ fmtPct((datos.totales[ultimos2[1]] - datos.totales[ultimos2[0]]) / datos.totales[ultimos2[0]] * 100, true) }}</span>
                                    </td>
                                    <td class="gd-num"><b>100%</b></td><td></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <p v-if="parcial" class="gd-nota" style="margin-top:10px">
                        Atención: una o más gestiones están incompletas (preliminares). Comparar un año parcial con uno completo exagera la caída.
                    </p>
                </div>

                <div class="overview-panel">
                    <div class="bento-h">
                        <div>
                            <h3>Comparador: {{ conDatos.map(etq).join(' vs. ') }} (hasta 10 categorías)</h3>
                            <div style="display:flex;gap:14px;flex-wrap:wrap;margin-top:6px;font-size:0.76rem;font-weight:700;color:var(--muted)">
                                <span v-for="a in conDatos" :key="a" style="display:inline-flex;align-items:center;gap:6px"><i :style="{ width: '12px', height: '12px', background: colorDe(a), display: 'inline-block' }"></i>{{ etq(a) }}</span>
                                <span v-if="ultimos2.length === 2">Debajo de cada grupo: variación % {{ etq(ultimos2[0]) }} → {{ etq(ultimos2[1]) }}</span>
                            </div>
                        </div>
                        <Link class="tool-btn" href="/comparador">Comparador avanzado →</Link>
                    </div>
                    <svg :viewBox="`0 0 ${ANCHO} ${ALTO + 40}`" style="width:100%;height:auto" role="img">
                        <g v-for="g in grafico" :key="g.completo">
                            <g v-for="b in g.barras" :key="b.a">
                                <rect :x="b.x" :y="b.y" :width="b.w" :height="b.h" :fill="colorDe(b.a)"><title>{{ g.completo }} · {{ b.a }}: {{ fmtM(b.v) }}</title></rect>
                                <text v-if="b.a === ref_ && b.h > 0" :x="b.x + b.w / 2" :y="b.y - 6" text-anchor="middle" font-size="12" font-weight="800" fill="#2f4858">{{ fmtNum(b.v / 1e6) }}</text>
                            </g>
                            <text :x="g.cx" :y="BASE + 20" text-anchor="middle" font-size="12.5" font-weight="800" fill="#12203d">{{ g.nombre }}</text>
                            <text v-if="g.var != null" :x="g.cx" :y="BASE + 38" text-anchor="middle" font-size="12" font-weight="800" :fill="g.var < 0 ? '#b91c1c' : '#15803d'">{{ fmtPct(g.var, true) }}</text>
                        </g>
                        <line x1="0" :y1="BASE" :x2="ANCHO" :y2="BASE" stroke="#cbd5e1" />
                    </svg>
                </div>
            </template>
            <p class="gd-nota">Fuente: INE, vistas resumen anuales de Geodata (millones de USD corrientes). Países homologados con su código ISO; el ranking se ordena por la gestión más reciente elegida.</p>
        </div>
    </div>
</template>
