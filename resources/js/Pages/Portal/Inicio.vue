<script setup>
/**
 * Portada del portal (estilo mockup GEODATA, GEO-12).
 * Hero por vía de transporte + panorama + destacados. Todas las cifras
 * vienen de /portal/v2/panorama (vistas resumen_* y hecho INE). Si un flujo
 * no tiene datos para la gestión elegida, se muestra "Sin datos".
 */
import { ref, computed, onMounted, onUnmounted, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import GdDona from '@/Components/Gd/GdDona.vue';
import GdBarras from '@/Components/Gd/GdBarras.vue';
import GdTop3 from '@/Components/Gd/GdTop3.vue';
import GdSinDatos from '@/Components/Gd/GdSinDatos.vue';
import GdCobertura from '@/Components/Gd/GdCobertura.vue';
import GdExplicacion from '@/Components/Gd/GdExplicacion.vue';
import { fmtM, fmtPct, fmtNum, etiquetaGestion, getJson } from '@/lib/gd';

const props = defineProps({
    cobertura: { type: Object, default: null },
    gestionesPortal: { type: Array, default: () => [] },
    gestionInicial: { type: Number, default: null },
    panorama: { type: Object, default: null },
});

const gestion = ref(props.gestionInicial);
const datos = ref(props.panorama);
const cargando = ref(false);
const filtro = ref('');

watch(gestion, async (g) => {
    if (!g) return;
    cargando.value = true;
    try {
        datos.value = await getJson('/portal/v2/panorama', { gestion: g });
    } finally {
        cargando.value = false;
    }
});

const etq = (g) => etiquetaGestion(g, props.cobertura);
const hayExp = computed(() => datos.value?.exp?.valor != null);
const hayImp = computed(() => datos.value?.imp?.valor != null);
const anioConImp = computed(() => props.cobertura?.imp?.hasta ?? null);
const anioConExp = computed(() => props.gestionInicial);

// ---------------- Hero por vía ----------------
const SLIDES = [
    { clave: 'maritimo', titulo: 'MARÍTIMO', img: '/img/portal/maritimo.webp',
      desc: 'Bolivia no tiene costa: el INE registra como marítimo el comercio intermodal por puertos del Pacífico (marítimo-carretera y marítimo-ferroviario).' },
    { clave: 'terrestre', titulo: 'TERRESTRE', img: '/img/portal/terrestre.webp',
      desc: 'Carretera y ferrocarril por los pasos de frontera con Chile, Perú, Brasil, Argentina y Paraguay.' },
    { clave: 'aereo', titulo: 'AÉREO', img: '/img/portal/aereo.webp',
      desc: 'Carga aérea y courier por los aeropuertos internacionales de Viru Viru, El Alto y Cochabamba.' },
    { clave: 'otros', titulo: 'OTROS MEDIOS', img: '/img/portal/ductos.webp',
      desc: 'Ductos, hidrovía (fluvial), lacustre, postal y otros medios intermodales.' },
];
const idx = ref(0);
let timer = null;
const via = computed(() => (datos.value?.vias ?? []).find((v) => v.clave === SLIDES[idx.value].clave) ?? null);
function paso(d) {
    idx.value = (idx.value + d + SLIDES.length) % SLIDES.length;
    reiniciar();
}
function elegir(i) {
    idx.value = i;
    reiniciar();
}
function reiniciar() {
    clearInterval(timer);
    timer = setInterval(() => {
        if (!document.hidden) idx.value = (idx.value + 1) % SLIDES.length;
    }, 7000);
}
function posCard(i) {
    const rel = (i - idx.value + SLIDES.length) % SLIDES.length;
    return rel <= 2 ? `pos-${rel}` : 'pos-hidden';
}
onMounted(reiniciar);
onUnmounted(() => clearInterval(timer));

// ---------------- Panorama ----------------
const topExp = computed(() => (datos.value?.top_exp ?? [])
    .filter((p) => !filtro.value || (p.descripcion + ' ' + p.codigo).toLowerCase().includes(filtro.value.toLowerCase()))
    .map((p) => ({ nombre: p.descripcion, valor: p.valor, titulo: `${p.codigo} · ${p.descripcion}`, id: p.producto_id })));
const topImp = computed(() => (datos.value?.top_imp ?? [])
    .filter((p) => !filtro.value || (p.descripcion + ' ' + p.codigo).toLowerCase().includes(filtro.value.toLowerCase()))
    .map((p) => ({ nombre: p.descripcion, valor: p.valor, titulo: `${p.codigo} · ${p.descripcion}`, id: p.producto_id })));

function abrirProducto(it, flujo) {
    router.visit(`/productos?gestion=${gestion.value}&flujo=${flujo}&producto=${it.id}`);
}

const saldo = computed(() => datos.value?.saldo);
const deficit = computed(() => saldo.value != null && saldo.value < 0);

const destacados = computed(() => {
    const d = datos.value;
    if (!d) return [];
    const e = d.top_exp?.[0], i = d.top_imp?.[0], de = d.destinos?.[0], o = d.origenes?.[0], dp = d.departamentos?.[0];
    return [
        { tag: 'Mayor exportación', titulo: e?.descripcion, sub: e ? `NANDINA ${e.codigo}` : '', valor: e?.valor, pct: e?.pct, base: 'exp.' },
        { tag: 'Mayor importación', titulo: i?.descripcion, sub: i ? `NANDINA ${i.codigo}` : '', valor: i?.valor, pct: i?.pct, base: 'imp.' },
        { tag: 'Destino #1', titulo: de?.nombre, sub: 'País de destino', valor: de?.valor, pct: de?.pct, base: 'exp.' },
        { tag: 'Proveedor #1', titulo: o?.nombre, sub: 'País de origen', valor: o?.valor, pct: o?.pct, base: 'imp.' },
        { tag: 'Departamento líder', titulo: dp?.nombre, sub: 'Exportación departamental', valor: dp?.valor, pct: dp?.pct, base: 'exp.' },
    ];
});

const periodo = computed(() => datos.value?.exp?.periodo ?? datos.value?.imp?.periodo ?? gestion.value);
</script>

<template>
    <Head title="Inicio" />

    <!-- ================= HERO POR VÍA ================= -->
    <section class="fox-hero">
        <div v-for="(s, i) in SLIDES" :key="s.clave" class="fox-bg-layer" :class="{ active: i === idx }"
             :style="{ backgroundImage: `url(${s.img})` }"></div>
        <div class="fox-grid">
            <div class="fox-info">
                <div class="fox-kicker-row">
                    <span class="fox-kicker"><i></i> COMERCIO EXTERIOR DE BOLIVIA</span>
                    <span class="fox-year" title="Gestión seleccionada">{{ etq(gestion) }}</span>
                </div>
                <div class="fox-roller-mask">
                    <div class="fox-roller-track" :style="{ transform: `translateY(-${idx * (100 / SLIDES.length)}%)` }">
                        <div v-for="s in SLIDES" :key="s.clave" class="fox-roller-item">{{ s.titulo }}</div>
                    </div>
                </div>
                <p class="fox-desc">{{ SLIDES[idx].desc }}</p>
                <div class="fox-stats" aria-live="polite" :key="idx + '-' + gestion">
                    <div class="fox-stat exp">
                        <small>Exportaciones FOB</small>
                        <strong>{{ via && via.exp ? fmtM(via.exp) : '—' }}</strong>
                        <em v-if="via && via.exp === 0">Sin registro en el INE</em>
                        <em v-else-if="!hayExp">Sin datos en {{ gestion }}</em>
                    </div>
                    <div class="fox-stat imp">
                        <small>Importaciones CIF</small>
                        <strong>{{ via && via.imp ? fmtM(via.imp) : '—' }}</strong>
                        <em v-if="!hayImp">Sin datos cargados para {{ gestion }}</em>
                        <em v-else-if="via && via.imp === 0">Sin registro en el INE</em>
                    </div>
                </div>
                <Link class="fox-explore-btn" :href="`/comercio-por-via`"><span>Explorar Vía</span><span>→</span></Link>
            </div>
            <div class="fox-cards-col">
                <div class="fox-cards-track">
                    <article v-for="(s, i) in SLIDES" :key="s.clave" class="fox-card" :class="posCard(i)" @click="elegir(i)">
                        <div class="fox-card-img" :style="{ backgroundImage: `url(${s.img})` }">
                            <div class="fox-card-foot"><span class="fox-card-name">Transporte {{ s.titulo.toLowerCase() }}</span></div>
                        </div>
                    </article>
                </div>
                <div class="fox-controls">
                    <div class="fox-arrows">
                        <button class="fox-arr-btn" aria-label="Anterior" @click="paso(-1)">‹</button>
                        <button class="fox-arr-btn" aria-label="Siguiente" @click="paso(1)">›</button>
                    </div>
                    <div class="fox-counter">0{{ idx + 1 }} / 0{{ SLIDES.length }}</div>
                </div>
            </div>
        </div>
    </section>

    <div class="page-wrap" style="padding-top:0">
        <!-- ================= BARRA DE FILTROS ================= -->
        <div class="inv-welcome-bar">
            <div><h2>Panorama Comercial de Bolivia · Gestión {{ etq(gestion) }}</h2></div>
            <div class="inv-search-wrap">
                <input v-model="filtro" type="search" class="inv-search" placeholder="Filtrar producto en Top 10...">
                <select class="inv-select" disabled title="Por ahora solo el INE tiene microdatos cargados">
                    <option>Fuente: INE Bolivia</option>
                </select>
                <select v-model.number="gestion" class="inv-select">
                    <option v-for="g in gestionesPortal" :key="g" :value="g">
                        Gestión {{ etq(g) }}{{ cobertura?.imp?.anios?.includes(g) ? '' : ' (solo exp.)' }}
                    </option>
                </select>
            </div>
        </div>

        <div :class="{ 'gd-cargando': cargando }">
            <!-- ================= INDICADORES PRINCIPALES ================= -->
            <section class="overview-panel">
                <div class="ov-title">
                    <span>Indicadores principales</span>
                    <small class="year-tag">Fuente: INE Bolivia · {{ periodo }}</small>
                </div>
                <div class="ov-cards-4">
                    <div class="ov-item"><div class="ov-data">
                        <strong>{{ fmtM(datos?.exp?.valor) }}</strong>
                        <span>Exportaciones FOB
                            <template v-if="datos?.exp?.variacion != null"> · <b :class="datos.exp.variacion < 0 ? 'neg' : 'pos'">{{ fmtPct(datos.exp.variacion, true) }}</b> vs {{ datos.exp.periodo_anterior }}</template>
                        </span>
                    </div></div>
                    <div class="ov-item"><div class="ov-data">
                        <strong>{{ fmtM(datos?.imp?.valor) }}</strong>
                        <span v-if="hayImp">Importaciones CIF
                            <template v-if="datos.imp.variacion != null"> · <b :class="datos.imp.variacion > 0 ? 'neg' : 'pos'">{{ fmtPct(datos.imp.variacion, true) }}</b> vs {{ datos.imp.periodo_anterior }}</template>
                        </span>
                        <span v-else>Importaciones CIF · <span class="gd-tag-sindatos">Sin datos {{ gestion }}</span></span>
                    </div></div>
                    <div class="ov-item"><div class="ov-data">
                        <strong>{{ fmtNum(datos?.partidas_exp) }} partidas</strong>
                        <span>Subpartidas NANDINA exportadas<template v-if="datos?.partidas_imp != null"> · {{ fmtNum(datos.partidas_imp) }} importadas</template></span>
                    </div></div>
                    <div class="ov-item" :class="{ 'warn-border': deficit }">
                        <div class="ov-data">
                            <strong :style="{ color: saldo == null ? '' : (deficit ? '#dc2626' : '#0d8a5a') }">{{ fmtM(saldo, true) }}</strong>
                            <span v-if="saldo != null">Balanza comercial ({{ deficit ? 'déficit' : 'superávit' }})</span>
                            <span v-else>Balanza comercial · requiere exp. e imp. del mismo año</span>
                        </div>
                        <span class="ov-info-badge" title="Exportaciones FOB menos importaciones CIF">i</span>
                    </div>
                </div>
                <div style="margin-top:12px"><GdCobertura :cobertura="cobertura" /></div>
            </section>
            <GdExplicacion clave="inicio.panorama" plegado />

            <!-- ================= EXPORTACIONES ================= -->
            <section class="inv-middle-grid">
                <div class="bento-box">
                    <div>
                        <div class="bento-h"><h3>Alcance Global</h3><span class="year-tag">{{ etq(gestion) }}</span></div>
                        <strong style="font-size:1.85rem;font-weight:800;color:var(--navy);display:block">{{ fmtNum(datos?.paises_exp) }} países</strong>
                        <span style="font-size:0.82rem;color:var(--muted);font-weight:600">Mercados de destino activos</span>
                        <GdTop3 titulo="TOP 3 · DESTINOS DE EXPORTACIÓN" :items="datos?.destinos ?? []" />
                    </div>
                    <div style="padding-top:14px;border-top:1px solid var(--line);margin-top:14px">
                        <small style="display:block;font-size:0.72rem;color:var(--muted);font-weight:700">TASA DE COBERTURA (X/M)</small>
                        <strong v-if="datos?.tasa_cobertura != null" style="font-size:1.25rem;color:var(--exp)">{{ fmtPct(datos.tasa_cobertura) }}</strong>
                        <span v-else class="gd-tag-sindatos">Sin importaciones {{ gestion }}</span>
                    </div>
                </div>

                <div class="bento-box">
                    <div class="bento-h">
                        <div>
                            <h3>Estructura de exportación</h3>
                            <span class="badge-var" style="display:inline-block;margin-top:6px">Tradicional vs. No tradicional</span>
                        </div>
                        <span class="year-tag" style="align-self:flex-start;padding-top:3px">{{ etq(gestion) }}</span>
                    </div>
                    <GdDona v-if="datos?.tnt?.length" :partes="datos.tnt" />
                    <GdSinDatos v-else titulo="Sin exportaciones en esta gestión" />
                </div>

                <div class="bento-box">
                    <div class="bento-h">
                        <h3>Top Productos Exportados (Millones USD)</h3>
                        <Link class="tool-btn" :href="`/productos?gestion=${gestion}&flujo=exp`">Ver productos →</Link>
                    </div>
                    <GdBarras :items="topExp" @elegir="abrirProducto($event, 'exp')"
                              :vacio="filtro ? `Ningún producto del Top 10 coincide con “${filtro}”.` : 'Sin exportaciones en esta gestión.'" />
                </div>
            </section>

            <!-- ================= IMPORTACIONES ================= -->
            <section v-if="hayImp" class="inv-middle-grid">
                <div class="bento-box">
                    <div>
                        <div class="bento-h"><h3>Orígenes globales</h3><span class="year-tag">{{ etq(gestion) }}</span></div>
                        <strong style="font-size:1.85rem;font-weight:800;color:var(--navy);display:block">{{ fmtM(datos.imp.valor) }}</strong>
                        <span style="font-size:0.82rem;color:var(--muted);font-weight:600">Importaciones CIF · {{ fmtNum(datos.paises_imp) }} países de origen</span>
                        <GdTop3 titulo="TOP 3 · ORÍGENES DE IMPORTACIÓN" :items="datos.origenes ?? []" />
                    </div>
                    <div style="padding-top:14px;border-top:1px solid var(--line);margin-top:14px">
                        <small style="display:block;font-size:0.72rem;color:var(--muted);font-weight:700">RELACIÓN M/X (IMP. / EXP.)</small>
                        <strong style="font-size:1.25rem;color:var(--imp)">{{ fmtPct(datos.relacion_mx) }}</strong>
                    </div>
                </div>
                <div class="bento-box">
                    <div class="bento-h">
                        <div>
                            <h3>Estructura de importación</h3>
                            <span class="badge-var" style="display:inline-block;margin-top:6px">Uso o destino económico (CUODE)</span>
                        </div>
                        <span class="year-tag" style="align-self:flex-start;padding-top:3px">{{ etq(gestion) }}</span>
                    </div>
                    <GdDona :partes="datos.cuode ?? []" :colores="['#3b6fb6', '#bcd0ee', '#7ea6dc', '#e2e8f0']" />
                </div>
                <div class="bento-box">
                    <div class="bento-h">
                        <h3>Top Productos Importados (Millones USD)</h3>
                        <Link class="tool-btn" :href="`/productos?gestion=${gestion}&flujo=imp`">Ver productos →</Link>
                    </div>
                    <GdBarras :items="topImp" color="#3b82f6" @elegir="abrirProducto($event, 'imp')" />
                </div>
            </section>
            <section v-else class="overview-panel">
                <GdSinDatos :titulo="`Importaciones ${gestion}: todavía no están cargadas`"
                            :mensaje="`En la base de datos solo hay importaciones del INE para ${cobertura?.imp?.etiqueta ?? '—'}. Orígenes, estructura CUODE, top de importados, balanza y tasa de cobertura aparecerán cuando se carguen.`">
                    <button v-if="anioConImp" class="tool-btn" style="margin-top:8px" @click="gestion = anioConImp">Ver la última gestión con importaciones ({{ anioConImp }}) →</button>
                </GdSinDatos>
            </section>

            <!-- ================= LO MÁS DESTACADO ================= -->
            <div style="margin-bottom:12px;display:flex;justify-content:space-between;align-items:center">
                <h3 style="font-size:1.15rem;font-weight:800;color:var(--navy)">Lo más destacado</h3>
                <span class="year-tag">Fuente: INE Bolivia · {{ periodo }}</span>
            </div>
            <div class="hl-grid-5">
                <article v-for="h in destacados" :key="h.tag" class="hl-card">
                    <div>
                        <small style="font-size:0.68rem;font-weight:800;color:var(--wine);text-transform:uppercase">{{ h.tag }}</small>
                        <h4 style="font-size:1rem;font-weight:800;color:var(--navy);margin:6px 0 2px">{{ h.titulo || 'Sin datos' }}</h4>
                        <span style="font-size:0.75rem;color:var(--muted)">{{ h.titulo ? h.sub : `No hay datos de este flujo en ${gestion}` }}</span>
                    </div>
                    <div style="padding-top:10px;border-top:1px solid var(--line);font-weight:800;color:var(--wine);font-size:0.92rem">
                        <template v-if="h.valor != null">{{ fmtM(h.valor) }} ({{ fmtPct(h.pct) }} de {{ h.base }})</template>
                        <span v-else class="gd-tag-sindatos">Sin datos</span>
                    </div>
                </article>
            </div>

            <p class="gd-nota">
                Fuente: Instituto Nacional de Estadística (INE), microdatos de comercio exterior cargados en Geodata.
                Exportaciones a valor FOB e importaciones a valor CIF frontera, en dólares corrientes.
                <template v-if="datos?.exp?.preliminar"> La gestión {{ gestion }} es preliminar ({{ datos.exp.periodo }}); las variaciones comparan el mismo periodo del año anterior.</template>
                Los años sin importaciones cargadas muestran “sin datos”, no cero.
            </p>
        </div>
    </div>
</template>
