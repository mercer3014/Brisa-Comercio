<script setup>
/**
 * Comercio por vía de transporte (GEO-12) — estilo del mockup (coverflow con
 * fotos + tablero de la vía elegida). Todo sale de PostgreSQL vía
 * PortalVistas::viasDetalle(); null = sin datos (nunca se muestra como 0).
 */
import { Head } from '@inertiajs/vue3';
import { computed, ref, onMounted } from 'vue';
import LayoutPublico from '../../Layouts/LayoutPublico.vue';
import GdCobertura from '../../Components/Gd/GdCobertura.vue';
import GdSinDatos from '../../Components/Gd/GdSinDatos.vue';
import GdBarras from '../../Components/Gd/GdBarras.vue';
import GdExplicacion from '../../Components/Gd/GdExplicacion.vue';
import { fmtM, fmtMM, fmtPct, etiquetaGestion, getJson, descargarCsv } from '../../lib/gd.js';

defineOptions({ layout: LayoutPublico });

const props = defineProps({
    cobertura: { type: Object, default: null },
    gestionesPortal: { type: Array, default: () => [] },
    gestionInicial: { type: Number, default: null },
    detalle: { type: Object, default: null },
});

const INFO = {
    terrestre: { titulo: 'Transporte Terrestre', img: '/img/portal/terrestre.webp', desc: 'Carga que cruza la frontera por carretera o ferrocarril (Red Oriental y Red Andina).' },
    maritimo: { titulo: 'Transporte Marítimo', img: '/img/portal/maritimo.webp', desc: 'Carga por puertos del Pacífico con tramo terrestre previo (códigos intermodales marítimo-carretera y marítimo-ferroviaria del INE).' },
    aereo: { titulo: 'Transporte Aéreo', img: '/img/portal/aereo.webp', desc: 'Oro, joyería y bienes de alto valor por vía aérea y courier.' },
    otros: { titulo: 'Otros Medios', img: '/img/portal/ductos.webp', desc: 'Gas natural por ductos, carga fluvial y lacustre, envíos postales y otros medios intermodales.' },
};
const ORDEN = ['terrestre', 'maritimo', 'aereo', 'otros'];

const gestion = ref(props.gestionInicial);
const datos = ref(props.detalle);
const cargando = ref(false);
const error = ref('');
const centro = ref(0);

const vias = computed(() => {
    const lista = datos.value?.vias ?? [];
    return ORDEN.map((k) => lista.find((v) => v.clave === k)).filter(Boolean);
});
const totalExp = computed(() => {
    const vals = vias.value.map((v) => v.exp).filter((v) => v !== null);
    return vals.length ? vals.reduce((a, b) => a + b, 0) : null;
});
const totalImp = computed(() => {
    const vals = vias.value.map((v) => v.imp).filter((v) => v !== null);
    return vals.length ? vals.reduce((a, b) => a + b, 0) : null;
});
const sel = computed(() => vias.value[centro.value] ?? null);
const hayImp = (g) => props.cobertura?.imp?.anios?.includes(g);
const etiqueta = computed(() => etiquetaGestion(gestion.value, props.cobertura));

function share(v) {
    if (v?.exp === null || !totalExp.value) return null;
    return (v.exp / totalExp.value) * 100;
}
function sinRegistro(v) {
    return v && (v.exp ?? 0) === 0 && (v.imp ?? 0) === 0;
}
function textoValor(v, flujo) {
    if (v[flujo] === null) return 'Sin datos';
    if (v[flujo] === 0) return 'Sin registro';
    return fmtM(v[flujo]);
}

// Posición 3D de cada tarjeta (misma lógica visual del mockup, sin autoplay).
function estiloCarta(i) {
    const n = vias.value.length || 1;
    let off = ((i - centro.value) % n + n) % n;
    if (off > n / 2) off -= n;
    const abs = Math.abs(off);
    const movil = typeof window !== 'undefined' && window.innerWidth <= 760;
    const gap = typeof window !== 'undefined' ? Math.min(240, Math.max(150, window.innerWidth * 0.17)) : 220;
    const scale = [1.06, 0.88, 0.74][abs] || 0.7;
    return {
        transform: `translate(-50%, -50%) translateX(${movil ? off * 40 : off * gap}px) translateZ(${[60, 0, -70][abs] || -80}px) rotateY(${off * -16}deg) scale(${scale})`,
        zIndex: String(10 - abs * 3),
        opacity: movil && abs > 0 ? 0 : [1, 0.9, 0.7][abs] || 0.6,
        filter: abs ? `blur(${abs * 0.7}px) brightness(${1 - abs * 0.12})` : 'none',
        pointerEvents: movil && abs > 0 ? 'none' : 'auto',
    };
}
function paso(dir) {
    const n = vias.value.length || 1;
    centro.value = (centro.value + dir + n) % n;
}
function verDetalle(i) {
    centro.value = i;
    document.getElementById('via-tablero')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

async function cambiarGestion(g) {
    gestion.value = g;
    cargando.value = true;
    error.value = '';
    try {
        datos.value = await getJson('/portal/v2/vias', { gestion: g });
    } catch (e) {
        error.value = 'No se pudieron cargar los datos de la gestión.';
    } finally {
        cargando.value = false;
    }
}

// Serie anual de la vía (SVG): barras de exportación + importación donde hay datos.
const W = 860, H = 230, PAD = { l: 58, r: 12, t: 14, b: 30 };
const serie = computed(() => sel.value?.serie ?? []);
const maxSerie = computed(() => Math.max(1, ...serie.value.flatMap((p) => [p.exp ?? 0, p.imp ?? 0])));
const ancho = computed(() => (W - PAD.l - PAD.r) / Math.max(1, serie.value.length));
const y = (v) => PAD.t + (H - PAD.t - PAD.b) * (1 - (v ?? 0) / maxSerie.value);
const ticks = computed(() => [0, 0.25, 0.5, 0.75, 1].map((f) => f * maxSerie.value));

const filasMedios = computed(() => {
    const v = sel.value;
    if (!v) return [];
    const tot = v.medios.reduce((a, m) => a + (m.exp || 0) + (v.imp === null ? 0 : m.imp || 0), 0);
    return v.medios.map((m) => {
        const imp = v.imp === null ? null : m.imp;
        const t = (m.exp || 0) + (imp || 0);
        return { nombre: m.nombre, exp: v.exp === null ? null : m.exp, imp, total: t, pct: tot ? (t / tot) * 100 : null };
    });
});

function csv() {
    const filas = [['Vía', 'Gestión', 'Exportación FOB (USD)', 'Importación CIF (USD)']];
    vias.value.forEach((v) => v.serie.forEach((p) => filas.push([INFO[v.clave].titulo, p.gestion, p.exp ?? 'sin datos', p.imp ?? 'sin datos'])));
    descargarCsv('comercio_por_via_INE.csv', filas);
}

// ?gestion=2022 en la URL (enlaces de retos y actividades docentes) abre esa gestión.
onMounted(() => {
    const g = Number(new URLSearchParams(window.location.search).get('gestion'));
    if (g && g !== gestion.value && props.gestionesPortal.includes(g)) cambiarGestion(g);
});
</script>

<template>
    <Head title="Comercio por vía de transporte" />

    <div class="page-wrap gd-pt">
        <div class="gd-cab">
            <div>
                <span class="gd-kicker">● CLASIFICACIÓN POR MEDIO DE TRANSPORTE · INE</span>
                <h2 class="gd-titulo">Comercio por vía de transporte</h2>
                <p class="gd-sub">Exportaciones (FOB) e importaciones (CIF frontera) de Bolivia agrupadas por vía, a partir de los medios de transporte registrados por el INE.</p>
            </div>
            <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
                <select class="inv-select" :value="gestion" @change="cambiarGestion(Number($event.target.value))" aria-label="Gestión">
                    <option v-for="g in gestionesPortal" :key="g" :value="g">Gestión {{ etiquetaGestion(g, cobertura) }}{{ hayImp(g) ? '' : ' (solo exp.)' }}</option>
                </select>
                <button class="tool-btn" type="button" :disabled="!vias.length" @click="csv">Descargar serie (Excel)</button>
            </div>
        </div>
        <GdCobertura :cobertura="cobertura" />
        <GdExplicacion clave="vias.tablero" plegado />

        <GdSinDatos v-if="!datos || !vias.length" titulo="Sin datos de transporte" mensaje="Todavía no hay operaciones del INE cargadas con medio de transporte." />

        <template v-else>
            <p v-if="error" class="gd-nota neg">{{ error }}</p>

            <!-- Coverflow con fotos (mockup) -->
            <div class="coverflow-stage" :class="{ 'gd-cargando': cargando }" style="margin-top:18px">
                <div v-for="(v, i) in vias" :key="'bg' + v.clave" class="cf-bg" :class="{ active: i === centro }" :style="{ backgroundImage: `url(${INFO[v.clave].img})`, backgroundPosition: 'center' }"></div>
                <span class="cf-year">{{ etiqueta }}</span>
                <div class="coverflow-header">
                    <h2 style="font-size:clamp(1.7rem,3.2vw,2.5rem);font-weight:800">Clasificación por Medio de Transporte</h2>
                    <p style="opacity:.8;font-size:.9rem;margin-top:6px">Haz clic en una tarjeta para centrarla y actualizar el tablero.</p>
                </div>
                <div class="coverflow-track">
                    <article v-for="(v, i) in vias" :key="v.clave" class="cf-card" :class="{ 'is-center': i === centro }" :style="estiloCarta(i)" @click="centro = i">
                        <div class="cf-photo" :style="{ backgroundImage: `url(${INFO[v.clave].img})` }"></div>
                        <div class="cf-body">
                            <h3>{{ INFO[v.clave].titulo }}</h3>
                            <div class="cf-loc">Abarca: {{ v.medios.length ? v.medios.map((m) => m.nombre).join(', ') : 'sin medios registrados en ' + etiqueta }}</div>
                            <div class="cf-desc-label">Descripción</div>
                            <p class="cf-desc">{{ INFO[v.clave].desc }}</p>
                            <div class="cf-metrics">
                                <div><small>Exportado</small><strong>{{ textoValor(v, 'exp') }}</strong></div>
                                <div><small>Importado</small><strong>{{ textoValor(v, 'imp') }}</strong></div>
                                <div><small>Participación</small><strong>{{ share(v) === null ? '—' : fmtPct(share(v)) }}</strong></div>
                            </div>
                            <div class="cf-footer">
                                <div class="cf-price">
                                    <small>{{ v.imp === null ? 'Exportación ' + etiqueta : 'Comercio total ' + etiqueta }}</small>
                                    <strong>{{ sinRegistro(v) ? 'Sin registro' : fmtM((v.exp ?? 0) + (v.imp ?? 0)) }}</strong>
                                </div>
                                <button type="button" class="cf-charts-btn" @click.stop="verDetalle(i)">Ver gráficos <span>→</span></button>
                            </div>
                        </div>
                    </article>
                </div>
                <div style="display:flex;justify-content:center;gap:12px;margin-top:24px">
                    <button class="fox-arr-btn" type="button" aria-label="Anterior" @click="paso(-1)">‹</button>
                    <button class="fox-arr-btn" type="button" aria-label="Siguiente" @click="paso(1)">›</button>
                </div>
            </div>

            <!-- Tablero de la vía seleccionada -->
            <div v-if="sel" id="via-tablero" class="overview-panel" style="scroll-margin-top:100px">
                <span class="gd-kicker">● Tablero · gestión {{ etiqueta }}</span>
                <h3 style="font-size:1.5rem;font-weight:800;color:var(--navy);margin:4px 0 14px">{{ INFO[sel.clave].titulo }}</h3>

                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));gap:12px;margin-bottom:18px">
                    <div class="gd-kpi-mini"><small>Exportación FOB</small><strong>{{ textoValor(sel, 'exp') }}</strong></div>
                    <div class="gd-kpi-mini">
                        <small>Importación CIF</small><strong>{{ textoValor(sel, 'imp') }}</strong>
                        <span v-if="sel.imp === null" class="gd-mini-nota">Importaciones INE cargadas: {{ cobertura?.imp?.etiqueta || 'ninguna' }}</span>
                    </div>
                    <div class="gd-kpi-mini"><small>Participación en exportaciones</small><strong>{{ share(sel) === null ? '—' : fmtPct(share(sel)) }}</strong><span class="gd-mini-nota">del total {{ fmtM(totalExp) }}</span></div>
                    <div class="gd-kpi-mini"><small>Años con registro</small><strong>{{ sel.desde ? (sel.desde === sel.hasta ? sel.desde : sel.desde + '–' + sel.hasta) : '—' }}</strong></div>
                </div>

                <GdSinDatos v-if="sinRegistro(sel)" :titulo="`Sin registro en ${etiqueta}`"
                    :mensaje="sel.desde ? `El INE no registra operaciones por esta vía en ${etiqueta}. Hay registros entre ${sel.desde} y ${sel.hasta}; elige una de esas gestiones o mira la serie histórica abajo.` : 'El INE no registra operaciones por esta vía.'" />

                <div v-else style="display:grid;grid-template-columns:minmax(0,1.1fr) minmax(0,.9fr);gap:22px" class="gd-via-grid">
                    <div>
                        <h4 class="dash-h4">Composición por medio · {{ etiqueta }}</h4>
                        <table class="data-table dash-comp">
                            <thead><tr><th>Medio (INE)</th><th class="num">Exportación</th><th class="num">Importación</th><th class="num">Total</th><th class="num">% de la vía</th></tr></thead>
                            <tbody>
                                <tr v-for="m in filasMedios" :key="m.nombre">
                                    <td>{{ m.nombre }}</td>
                                    <td class="num">{{ fmtM(m.exp) }}</td>
                                    <td class="num">{{ m.imp === null ? 'sin datos' : fmtM(m.imp) }}</td>
                                    <td class="num">{{ fmtM(m.total) }}</td>
                                    <td class="num">{{ fmtPct(m.pct) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div>
                        <h4 class="dash-h4">Principales productos exportados</h4>
                        <GdBarras :items="sel.productos.exp" vacio="Sin exportaciones por esta vía en la gestión." />
                        <h4 class="dash-h4" style="margin-top:16px">Principales productos importados</h4>
                        <GdBarras v-if="sel.imp !== null" :items="sel.productos.imp" color="#12203d" vacio="Sin importaciones por esta vía en la gestión." />
                        <p v-else class="gd-nota"><span class="gd-tag-sindatos">Sin datos</span> No hay importaciones INE cargadas para {{ etiqueta }}.</p>
                    </div>
                </div>

                <h4 class="dash-h4" style="margin-top:22px">Serie histórica de la vía · USD</h4>
                <div style="overflow-x:auto">
                    <svg :viewBox="`0 0 ${W} ${H}`" style="width:100%;min-width:640px;height:auto" role="img" aria-label="Serie anual de la vía">
                        <g v-for="t in ticks" :key="t">
                            <line :x1="PAD.l" :x2="W - PAD.r" :y1="y(t)" :y2="y(t)" stroke="#e2e8f0" />
                            <text :x="PAD.l - 6" :y="y(t) + 3" text-anchor="end" font-size="9" fill="#64748b">{{ fmtMM(t) }}</text>
                        </g>
                        <g v-for="(p, i) in serie" :key="p.gestion" style="cursor:pointer" @click="cambiarGestion(p.gestion)">
                            <title>{{ p.gestion }} · Exp. {{ p.exp === null ? 'sin datos' : fmtM(p.exp) }} · Imp. {{ p.imp === null ? 'sin datos' : fmtM(p.imp) }}</title>
                            <rect v-if="p.gestion === gestion" :x="PAD.l + i * ancho" :y="PAD.t" :width="ancho" :height="H - PAD.t - PAD.b" fill="#fdf2f4" />
                            <rect :x="PAD.l + i * ancho + ancho * 0.12" :y="y(p.exp)" :width="ancho * 0.36" :height="Math.max(0, H - PAD.b - y(p.exp))" fill="#7a1f35" />
                            <rect v-if="p.imp !== null" :x="PAD.l + i * ancho + ancho * 0.5" :y="y(p.imp)" :width="ancho * 0.36" :height="Math.max(0, H - PAD.b - y(p.imp))" fill="#12203d" />
                            <text v-if="i % 2 === 0 || serie.length < 18" :x="PAD.l + i * ancho + ancho / 2" :y="H - PAD.b + 13" text-anchor="middle" font-size="8.5" fill="#64748b">{{ String(p.gestion).slice(2) }}</text>
                        </g>
                    </svg>
                </div>
                <p class="gd-nota">
                    <span style="display:inline-block;width:10px;height:10px;background:#7a1f35"></span> Exportación FOB ·
                    <span style="display:inline-block;width:10px;height:10px;background:#12203d"></span> Importación CIF (solo {{ cobertura?.imp?.etiqueta || 'sin datos' }}) ·
                    clic en un año para cambiar la gestión. Fuente: INE, medios de transporte agrupados por vía.
                </p>
            </div>
        </template>
    </div>
</template>
