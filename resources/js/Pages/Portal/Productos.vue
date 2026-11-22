<script setup>
/**
 * Productos del comercio exterior (página nueva del mockup, GEO-12).
 * Treemap, composición por sección del SA, ranking, ficha y tabla por partida
 * NANDINA, todo con datos reales de /portal/v2/productos.
 */
import { ref, computed, watch, onMounted } from 'vue';
import { Head } from '@inertiajs/vue3';
import GdSinDatos from '@/Components/Gd/GdSinDatos.vue';
import GdCobertura from '@/Components/Gd/GdCobertura.vue';
import { fmtM, fmtPct, fmtNum, fmtUsd, etiquetaGestion, getJson, SECCIONES_SA, colorSeccion, bandera, descargarCsv } from '@/lib/gd';
import { squarify } from '@/lib/treemap';

const props = defineProps({
    cobertura: { type: Object, default: null },
    gestionesPortal: { type: Array, default: () => [] },
    gestionInicial: { type: Number, default: null },
    datosIniciales: { type: Object, default: null },
});

const qs = new URLSearchParams(typeof window !== 'undefined' ? window.location.search : '');
const gestion = ref(Number(qs.get('gestion')) || props.gestionInicial);
const flujo = ref(qs.get('flujo') === 'imp' ? 'imp' : 'exp');
const productoUrl = Number(qs.get('producto')) || null;
const datos = ref(gestion.value === props.gestionInicial && flujo.value === 'exp' ? props.datosIniciales : null);
const cargando = ref(false);
const buscar = ref('');
const seccionSel = ref(null);
const sel = ref(null);
const ficha = ref(null);
const pagina = ref(1);
const POR_PAGINA = 15;

async function cargar() {
    cargando.value = true;
    try {
        datos.value = await getJson('/portal/v2/productos', { gestion: gestion.value, flujo: flujo.value });
        seccionSel.value = null;
        pagina.value = 1;
        const lista = datos.value?.filas ?? [];
        sel.value = lista.find((f) => f.producto_id === productoUrl) ?? lista[0] ?? null;
    } finally {
        cargando.value = false;
    }
}
watch([gestion, flujo], cargar);
onMounted(() => {
    if (!datos.value) cargar();
    else sel.value = (datos.value.filas ?? []).find((f) => f.producto_id === productoUrl) ?? datos.value.filas?.[0] ?? null;
});

watch(sel, async (p) => {
    ficha.value = null;
    if (!p) return;
    ficha.value = await getJson(`/portal/v2/productos/${p.producto_id}`, { gestion: gestion.value, flujo: flujo.value });
});

const etq = (g) => etiquetaGestion(g, props.cobertura, flujo.value);
const esExp = computed(() => flujo.value === 'exp');
const hay = computed(() => (datos.value?.filas ?? []).length > 0);
const total = computed(() => datos.value?.total?.valor ?? null);
const etFlujo = computed(() => (esExp.value ? 'Exportaciones FOB' : 'Importaciones CIF'));
const aniosFlujo = computed(() => props.cobertura?.[flujo.value]?.anios ?? []);

const filas = computed(() => {
    const q = buscar.value.trim().toLowerCase();
    return (datos.value?.filas ?? []).filter((f) =>
        (seccionSel.value === null || f.seccion === seccionSel.value) &&
        (!q || `${f.descripcion} ${f.codigo}`.toLowerCase().includes(q)));
});

const secciones = computed(() => (datos.value?.secciones ?? []).map((s) => ({
    ...s,
    corto: SECCIONES_SA[s.seccion] ?? s.nombre,
    color: colorSeccion(s.seccion),
})));

// Treemap: 22 productos principales + resto
const tiles = computed(() => {
    const base = filas.value.slice(0, 22).map((f) => ({ ...f, valor: f.valor }));
    const sumaTop = base.reduce((s, f) => s + f.valor, 0);
    const totalFiltro = seccionSel.value === null ? (total.value ?? 0) : (secciones.value.find((s) => s.seccion === seccionSel.value)?.valor ?? 0);
    const resto = totalFiltro - sumaTop;
    if (resto > totalFiltro * 0.01) base.push({ producto_id: 0, descripcion: 'Resto de partidas', codigo: '', valor: resto, resto: true, seccion: -1 });
    return squarify(base, 1.65, 1).map((t) => {
        const big = t.width >= 22 && t.height >= 26;
        return { ...t, big, nombre: t.width >= 7 && t.height >= 7, val: t.width >= 9 && t.height >= 12 };
    });
});

const pct = (v) => (total.value ? (v / total.value) * 100 : null);

// Diagnóstico
const n80 = computed(() => {
    const f = datos.value?.filas ?? [];
    const i = f.findIndex((x) => x.acum >= 80);
    return i >= 0 ? i + 1 : null;
});
const nivelHhi = computed(() => {
    const h = datos.value?.hhi;
    if (h == null) return null;
    return h > 0.25 ? { txt: 'concentrada', cls: 'lvl-alta' } : h >= 0.15 ? { txt: 'moderada', cls: 'lvl-moderada' } : { txt: 'diversificada', cls: 'lvl-baja' };
});
const movers = computed(() => {
    const f = (datos.value?.filas ?? []).slice(0, 60).filter((x) => x.anterior);
    const d = f.map((x) => ({ ...x, delta: x.valor - x.anterior }));
    return {
        suben: [...d].sort((a, b) => b.delta - a.delta).slice(0, 3).filter((x) => x.delta > 0),
        bajan: [...d].sort((a, b) => a.delta - b.delta).slice(0, 3).filter((x) => x.delta < 0),
    };
});

// Ranking (lista lateral)
const ranking = computed(() => filas.value.slice(0, 25));
const maxRank = computed(() => Math.max(...ranking.value.map((r) => r.valor), 1));

// Ficha
const serieFicha = computed(() => {
    const s = ficha.value?.serie ?? [];
    return s.slice(-8);
});
const maxSerie = computed(() => Math.max(...serieFicha.value.map((s) => s.valor), 1));

// Tabla
const paginas = computed(() => Math.max(1, Math.ceil(filas.value.length / POR_PAGINA)));
const filasPagina = computed(() => filas.value.slice((pagina.value - 1) * POR_PAGINA, pagina.value * POR_PAGINA));
watch([buscar, seccionSel], () => (pagina.value = 1));

function descargar() {
    const d = datos.value;
    const cab = ['Posición', 'NANDINA', 'Descripción', 'Sección SA', `${d.total.periodo_anterior ?? gestion.value - 1} (USD)`, `${d.total.periodo} (USD)`, 'Variación %', '% del total', '% acumulado', 'Peso neto (kg)'];
    const rows = filas.value.map((f, i) => [i + 1, f.codigo, f.descripcion, SECCIONES_SA[f.seccion] ?? f.seccion_nombre, f.anterior != null ? Math.round(f.anterior) : '', Math.round(f.valor), f.variacion ?? '', f.pct, f.acum, Math.round(f.peso)]);
    descargarCsv(`geodata_productos_${flujo.value}_${gestion.value}.csv`, [cab, ...rows]);
}
</script>

<template>
    <Head title="Productos" />
    <div class="page-wrap gd-pt">
        <div class="gd-cab">
            <div>
                <span class="gd-kicker">● Clasificación de mercancías</span>
                <h2 class="gd-titulo">Productos del Comercio Exterior</h2>
            </div>
            <span class="year-tag">Fuente: INE Bolivia · {{ datos?.total?.periodo ?? gestion }}</span>
        </div>

        <div class="overview-panel prd-controls">
            <label class="prd-f"><span>Fuente</span>
                <select class="inv-select" disabled title="Solo el INE tiene microdatos por partida cargados"><option>INE Bolivia</option></select>
            </label>
            <label class="prd-f"><span>Gestión</span>
                <select v-model.number="gestion" class="inv-select">
                    <option v-for="g in gestionesPortal" :key="g" :value="g">{{ etq(g) }}{{ aniosFlujo.includes(g) ? '' : ' · sin datos' }}</option>
                </select>
            </label>
            <label class="prd-f"><span>Clasificación</span>
                <select class="inv-select" disabled><option>Sección del Sistema Armonizado</option></select>
            </label>
            <label class="prd-f"><span>Buscar</span>
                <input v-model="buscar" type="search" class="inv-search" placeholder="Producto o código NANDINA (ej. 2711)...">
            </label>
            <div class="prd-f prd-flow-cell"><span>Flujo</span>
                <div class="prd-flow">
                    <button class="tool-btn" :class="{ active: flujo === 'exp' }" @click="flujo = 'exp'">Exportaciones FOB</button>
                    <button class="tool-btn" :class="{ active: flujo === 'imp' }" @click="flujo = 'imp'">Importaciones CIF</button>
                </div>
            </div>
            <div class="prd-f" style="grid-column: 3 / span 2; grid-row: 2"><span>Cobertura</span><GdCobertura :cobertura="cobertura" /></div>
        </div>

        <div :class="{ 'gd-cargando': cargando }">
            <section v-if="!hay && !cargando" class="overview-panel">
                <GdSinDatos :titulo="`${etFlujo} ${gestion}: sin datos cargados`"
                            :mensaje="`El INE tiene ${esExp ? 'exportaciones' : 'importaciones'} cargadas para ${cobertura?.[flujo]?.etiqueta}. Elige una de esas gestiones o cambia de flujo.`">
                    <button v-if="cobertura?.[flujo]?.hasta" class="tool-btn" style="margin-top:8px" @click="gestion = cobertura[flujo].hasta">Ir a {{ cobertura[flujo].hasta }} →</button>
                </GdSinDatos>
            </section>

            <template v-else-if="datos">
                <!-- KPIs -->
                <div class="prd-kpis">
                    <div class="prd-kpi"><small>{{ etFlujo }}</small>
                        <strong :style="{ color: esExp ? 'var(--exp)' : 'var(--imp)' }">{{ fmtM(total) }}</strong>
                        <span v-if="datos.total.variacion != null">{{ fmtPct(datos.total.variacion, true) }} frente a {{ datos.total.periodo_anterior }}</span>
                        <span v-else>{{ datos.total.preliminar ? 'Preliminar · ' + datos.total.periodo : 'Gestión completa' }}</span>
                    </div>
                    <div class="prd-kpi"><small>Producto líder</small>
                        <strong style="font-size:1.15rem">{{ datos.filas[0]?.descripcion }}</strong>
                        <span>{{ fmtM(datos.filas[0]?.valor) }} · {{ fmtPct(datos.filas[0]?.pct) }} del total</span>
                    </div>
                    <div class="prd-kpi"><small>5 principales productos</small>
                        <strong>{{ fmtPct(datos.top5_pct) }}</strong>
                        <span>del valor concentrado en 5 partidas</span>
                    </div>
                    <div class="prd-kpi"><small>Concentración (IHH)</small>
                        <strong>{{ fmtNum((datos.hhi ?? 0) * 10000) }}</strong>
                        <span>Canasta {{ nivelHhi?.txt }} · {{ fmtNum(datos.partidas) }} partidas<template v-if="n80"> · {{ n80 }} = 80%</template></span>
                    </div>
                </div>

                <!-- Treemap + diagnóstico -->
                <div class="overview-panel prd-panel">
                    <div class="bento-h">
                        <div>
                            <h3>Mapa de productos {{ esExp ? 'exportados' : 'importados' }}</h3>
                            <small class="prd-panel-sub">El área de cada bloque es proporcional a su valor · Haz clic para abrir la ficha</small>
                        </div>
                        <span class="year-tag">INE Bolivia · {{ datos.total.periodo }}</span>
                    </div>
                    <div class="prd-panel-grid">
                        <div class="prd-tree-wrap">
                            <div class="prd-tree" role="list">
                                <button v-for="t in tiles" :key="t.producto_id" type="button" role="listitem" class="prd-tile"
                                        :class="{ resto: t.resto, on: sel && t.producto_id === sel.producto_id, big: t.big }"
                                        :style="{ left: t.left + '%', top: t.top + '%', width: t.width + '%', height: t.height + '%', backgroundColor: t.resto ? '#94a3b8' : colorSeccion(t.seccion), color: '#fff' }"
                                        :title="`${t.descripcion}: ${fmtM(t.valor)} · ${fmtPct(pct(t.valor))}`"
                                        @click="!t.resto && (sel = t)">
                                    <b v-if="t.nombre">{{ t.descripcion }}</b>
                                    <span v-if="t.val">{{ fmtM(t.valor) }} · {{ fmtPct(pct(t.valor)) }}</span>
                                </button>
                            </div>
                        </div>
                        <aside class="prd-diag">
                            <h4>Diagnóstico de la canasta · {{ datos.total.periodo }}</h4>
                            <div class="prd-metrics">
                                <div class="prd-metric"><small>Partidas</small><strong>{{ fmtNum(datos.partidas) }}</strong><em class="lvl-baja">con comercio</em></div>
                                <div class="prd-metric"><small>IHH</small><strong>{{ fmtNum((datos.hhi ?? 0) * 10000) }}</strong><em :class="nivelHhi?.cls">{{ nivelHhi?.txt }}</em></div>
                                <div class="prd-metric"><small>Top 5</small><strong>{{ fmtPct(datos.top5_pct, false, 0) }}</strong><em class="lvl-moderada">del total</em></div>
                            </div>
                            <p class="prd-reading">
                                En {{ datos.total.periodo }} Bolivia {{ esExp ? 'exportó' : 'importó' }} <b>{{ fmtM(total) }}</b> en {{ fmtNum(datos.partidas) }} subpartidas NANDINA.
                                La principal, <b>{{ datos.filas[0]?.descripcion }}</b>, representa el {{ fmtPct(datos.filas[0]?.pct) }}.
                                <template v-if="n80">Bastan {{ n80 }} partidas para reunir el 80% del valor.</template>
                                <template v-if="secciones[0]"> La sección con más peso es <b>{{ secciones[0].corto }}</b> ({{ fmtPct(secciones[0].pct) }}).</template>
                            </p>
                            <div v-if="movers.suben.length || movers.bajan.length" class="prd-movers">
                                <div><h4>Mayores alzas</h4>
                                    <ul><li v-for="m in movers.suben" :key="m.producto_id"><button type="button" @click="sel = m"><b :title="m.descripcion">{{ m.descripcion }}</b><span class="up">+{{ fmtM(m.delta) }}</span></button></li></ul>
                                </div>
                                <div><h4>Mayores caídas</h4>
                                    <ul><li v-for="m in movers.bajan" :key="m.producto_id"><button type="button" @click="sel = m"><b :title="m.descripcion">{{ m.descripcion }}</b><span class="neg" style="font-weight:800">{{ fmtM(m.delta) }}</span></button></li></ul>
                                </div>
                            </div>
                            <small v-if="datos.total.periodo_anterior" class="prd-note">Alzas y caídas frente a {{ datos.total.periodo_anterior }} (mismo periodo).</small>
                            <small v-else class="prd-note">No hay datos de {{ gestion - 1 }} para comparar.</small>
                        </aside>
                    </div>
                </div>

                <!-- Composición por sección -->
                <div class="overview-panel">
                    <div class="bento-h"><h3>Composición por sección del Sistema Armonizado</h3>
                        <span style="font-size:0.76rem;color:var(--muted);font-weight:700">Haz clic en un grupo para filtrar</span></div>
                    <div class="prd-comp-bar">
                        <button v-for="s in secciones" :key="s.seccion" type="button" class="prd-seg" :class="{ off: seccionSel !== null && seccionSel !== s.seccion }"
                                :style="{ flex: s.valor, background: s.color }" :title="`${s.corto}: ${fmtM(s.valor)} · ${fmtPct(s.pct)}`"
                                @click="seccionSel = seccionSel === s.seccion ? null : s.seccion">
                            <span v-if="s.pct >= 5">{{ fmtPct(s.pct) }}</span>
                        </button>
                    </div>
                    <div class="prd-legend">
                        <button v-for="s in secciones.slice(0, 12)" :key="s.seccion" type="button" class="prd-leg"
                                :class="{ on: seccionSel === s.seccion, off: seccionSel !== null && seccionSel !== s.seccion }"
                                @click="seccionSel = seccionSel === s.seccion ? null : s.seccion">
                            <i :style="{ background: s.color }"></i><span>{{ s.corto }}</span><b>{{ fmtM(s.valor) }}</b><em>{{ fmtPct(s.pct) }}</em>
                        </button>
                    </div>
                </div>

                <!-- Ranking + ficha -->
                <div class="grid-2 prd-grid">
                    <div class="bento-box" style="justify-content:flex-start">
                        <div class="bento-h">
                            <h3>Principales productos {{ esExp ? 'exportados' : 'importados' }}<template v-if="seccionSel !== null"> · {{ SECCIONES_SA[seccionSel] }}</template></h3>
                            <span class="badge-var">{{ fmtNum(filas.length) }} partidas</span>
                        </div>
                        <ul class="prd-rank">
                            <li v-for="(p, i) in ranking" :key="p.producto_id" class="prd-row" :class="{ on: sel && sel.producto_id === p.producto_id }" @click="sel = p">
                                <span class="prd-pos">{{ i + 1 }}</span>
                                <span class="prd-name"><b>{{ p.descripcion }}</b><small>NANDINA {{ p.codigo }}</small></span>
                                <span class="prd-track"><i :style="{ width: (p.valor / maxRank) * 100 + '%', background: colorSeccion(p.seccion) }"></i></span>
                                <span class="prd-val"><b>{{ fmtM(p.valor) }}</b><small>{{ fmtPct(p.pct) }}</small></span>
                            </li>
                            <li v-if="!ranking.length" class="empty-state">Ningún producto coincide con “{{ buscar }}”.</li>
                        </ul>
                    </div>
                    <div class="bento-box" style="justify-content:flex-start">
                        <div class="bento-h"><h3>Ficha del producto</h3></div>
                        <p v-if="!sel" class="empty-state">Selecciona un producto de la lista.</p>
                        <template v-else>
                            <div class="prd-ficha-head">
                                <span class="prd-dot" :style="{ background: colorSeccion(sel.seccion) }"></span>
                                <div>
                                    <h4>{{ sel.descripcion }}</h4>
                                    <small>NANDINA {{ sel.codigo }}<template v-if="ficha?.producto?.capitulo"> · Cap. {{ ficha.producto.capitulo }}</template></small>
                                    <div class="prd-tags"><span :style="{ borderLeft: `4px solid ${colorSeccion(sel.seccion)}` }">{{ SECCIONES_SA[sel.seccion] ?? sel.seccion_nombre }}</span></div>
                                </div>
                            </div>
                            <div class="prd-ficha-big">
                                <strong>{{ fmtM(sel.valor) }}</strong>
                                <span>{{ fmtPct(sel.pct) }} de las {{ esExp ? 'exportaciones' : 'importaciones' }} · {{ datos.total.periodo }}</span>
                            </div>
                            <svg v-if="serieFicha.length" viewBox="0 0 340 134" class="prd-years" role="img">
                                <g v-for="(s, i) in serieFicha" :key="s.gestion">
                                    <rect :x="14 + i * (312 / serieFicha.length)" :y="112 - (s.valor / maxSerie) * 92" :width="(312 / serieFicha.length) - 8"
                                          :height="(s.valor / maxSerie) * 92" :fill="colorSeccion(sel.seccion)" :opacity="(cobertura?.[flujo]?.meses?.[s.gestion] ?? 12) < 12 ? 0.5 : 1"
                                          :stroke="s.gestion === gestion ? '#12203d' : 'none'" stroke-width="2"><title>{{ s.gestion }}: {{ fmtM(s.valor) }}</title></rect>
                                    <text :x="14 + i * (312 / serieFicha.length) + ((312 / serieFicha.length) - 8) / 2" y="128" text-anchor="middle" font-size="10" font-weight="700" fill="#64748b">{{ etq(s.gestion) }}</text>
                                </g>
                                <line x1="10" y1="112.5" x2="330" y2="112.5" stroke="#cbd5e1" />
                            </svg>
                            <div class="prd-facts2">
                                <div><small>Variación vs {{ datos.total.periodo_anterior ?? gestion - 1 }}</small>
                                    <b :class="sel.variacion < 0 ? 'neg' : 'pos'">{{ fmtPct(sel.variacion, true) }}</b></div>
                                <div><small>Valor unitario</small><b>{{ sel.precio_t ? fmtUsd(sel.precio_t) + ' / t' : '—' }}</b></div>
                                <div><small>{{ esExp ? 'Destinos principales' : 'Orígenes principales' }}</small>
                                    <b>{{ (ficha?.paises ?? []).slice(0, 3).map((p) => `${bandera(p.iso2)} ${p.nombre}`).join(' · ') || '—' }}</b></div>
                                <div><small>Medio de transporte</small><b>{{ (ficha?.medios ?? []).slice(0, 2).map((m) => m.nombre).join(' · ') || '—' }}</b></div>
                                <div style="grid-column: span 2"><small>Departamentos</small>
                                    <b>{{ (ficha?.deptos ?? []).map((d) => `${d.nombre} (${fmtM(d.valor)})`).join(' · ') || '—' }}</b></div>
                            </div>
                        </template>
                    </div>
                </div>

                <!-- Tabla -->
                <div class="overview-panel">
                    <div class="bento-h">
                        <h3>Detalle por producto y partida arancelaria</h3>
                        <span class="badge-var">Top 300 partidas · {{ datos.total.periodo }}</span>
                    </div>
                    <div style="overflow-x:auto">
                        <table class="data-table prd-table">
                            <thead><tr>
                                <th>#</th><th>Código</th><th>Producto</th><th>Sección</th>
                                <th class="gd-num">{{ datos.total.periodo_anterior ?? gestion - 1 }}</th>
                                <th class="gd-num">{{ datos.total.periodo }}</th>
                                <th class="gd-num">Var. %</th><th class="gd-num">% total</th><th class="gd-num">% acum.</th>
                            </tr></thead>
                            <tbody>
                                <tr v-for="(f, i) in filasPagina" :key="f.producto_id" style="cursor:pointer" @click="sel = f">
                                    <td>{{ (pagina - 1) * POR_PAGINA + i + 1 }}</td>
                                    <td style="font-family:monospace">{{ f.codigo }}</td>
                                    <td class="gd-desc"><b>{{ f.descripcion }}</b></td>
                                    <td class="gd-sec"><span class="prd-dot" style="display:inline-block;width:9px;height:9px;margin:0 6px 0 0" :style="{ background: colorSeccion(f.seccion) }"></span>{{ SECCIONES_SA[f.seccion] ?? f.seccion_nombre }}</td>
                                    <td class="gd-num">{{ f.anterior != null ? fmtM(f.anterior) : '—' }}</td>
                                    <td class="gd-num"><b>{{ fmtM(f.valor) }}</b></td>
                                    <td class="gd-num"><span v-if="f.variacion != null" class="gd-var" :class="f.variacion < 0 ? 'neg' : 'pos'">{{ fmtPct(f.variacion, true) }}</span><span v-else>—</span></td>
                                    <td class="gd-num">{{ fmtPct(f.pct) }}</td>
                                    <td class="gd-num">{{ fmtPct(f.acum) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="prd-table-foot">
                        <div class="pager">
                            <span class="pg-info">Página {{ pagina }} de {{ paginas }} · {{ fmtNum(filas.length) }} partidas</span>
                            <span class="pg-btns">
                                <button class="tool-btn" :disabled="pagina <= 1" @click="pagina--">‹</button>
                                <button class="tool-btn" :disabled="pagina >= paginas" @click="pagina++">›</button>
                            </span>
                        </div>
                        <button class="tool-btn active" @click="descargar">Descargar vista (Excel)</button>
                    </div>
                </div>
                <p class="gd-nota">
                    Fuente: INE, microdatos cargados en Geodata. {{ etFlujo }} en dólares corrientes.
                    <template v-if="datos.total.preliminar">{{ gestion }} es preliminar ({{ datos.total.periodo }}); la comparación usa el mismo periodo de {{ gestion - 1 }}.</template>
                    Secciones según el capítulo NANDINA de cada partida.
                </p>
            </template>
        </div>
    </div>
</template>
