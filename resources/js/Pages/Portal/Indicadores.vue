<script setup>
/**
 * Indicadores analíticos (estilo mockup, GEO-12) con datos reales:
 * tasa de cobertura, saldo, valor unitario, HHI (todas las partidas/socios)
 * y la serie histórica. La apertura comercial queda "sin datos" hasta que
 * se cargue el PIB.
 */
import { ref, computed, watch } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import GdSinDatos from '@/Components/Gd/GdSinDatos.vue';
import GdCobertura from '@/Components/Gd/GdCobertura.vue';
import { fmtM, fmtPct, fmtNum, fmtUsd, etiquetaGestion, getJson } from '@/lib/gd';

const props = defineProps({
    cobertura: { type: Object, default: null },
    gestionesPortal: { type: Array, default: () => [] },
    gestionInicial: { type: Number, default: null },
    datosIniciales: { type: Object, default: null },
});

const gestion = ref(props.gestionInicial);
const datos = ref(props.datosIniciales);
const cargando = ref(false);
watch(gestion, async (g) => {
    cargando.value = true;
    try {
        datos.value = await getJson('/portal/v2/indicadores', { gestion: g });
    } finally {
        cargando.value = false;
    }
});
const etq = (g) => etiquetaGestion(g, props.cobertura);

const FORMULAS = [
    { k: 'BC', t: 'Balanza comercial (BC)', d: 'BC = Exportaciones FOB − Importaciones CIF. Mide el saldo neto de divisas por comercio de bienes. Solo se calcula cuando la gestión tiene ambos flujos cargados.' },
    { k: 'HHI', t: 'Índice Herfindahl (HHI)', d: 'HHI = Σ (participación de cada partida o país)². Escala 0–1: menos de 0,15 diversificado, 0,15–0,25 moderado, más de 0,25 concentrado. Se calcula con todas las partidas o socios, no solo con el top.' },
    { k: 'TC', t: 'Tasa de cobertura (TC)', d: 'TC = Exportaciones / Importaciones × 100. Más de 100% indica que las exportaciones pagan todas las importaciones del periodo.' },
    { k: 'VU', t: 'Valor unitario (VU)', d: 'VU = Valor FOB / Peso neto en toneladas. Un VU alto refleja productos de mucho valor por tonelada (oro, plata); uno bajo, graneles (gas, soya, zinc).' },
    { k: 'GA', t: 'Grado de apertura (GA)', d: 'GA = (X + M) / PIB × 100. Requiere el PIB nominal en USD, que todavía no está cargado en la base: por eso el indicador figura como “sin datos”.' },
];
const formula = ref(FORMULAS[0]);

const hhiNivel = (h) => (h == null ? null : h > 0.25 ? '#dc2626' : h >= 0.15 ? '#f59e0b' : '#10b981');

// Serie de tasa de cobertura (solo años con ambos flujos)
const serieTc = computed(() => (datos.value?.serie ?? []).filter((s) => s.cobertura != null));
const serieExp = computed(() => datos.value?.serie ?? []);
const maxExp = computed(() => Math.max(1, ...serieExp.value.map((s) => Math.max(s.exp ?? 0, s.imp ?? 0))));
</script>

<template>
    <Head title="Indicadores" />
    <div class="page-wrap gd-pt">
        <div class="gd-cab">
            <div>
                <span class="gd-kicker">● Métricas técnicas del laboratorio LICI</span>
                <h2 class="gd-titulo">Indicadores Analíticos de Comercio Exterior</h2>
            </div>
            <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
                <button class="tool-btn active">INE Bolivia</button>
                <button class="tool-btn" disabled title="Sin datos cargados">FAOSTAT</button>
                <button class="tool-btn" disabled title="Sin datos cargados">ALADI</button>
                <button class="tool-btn" disabled title="Sin datos cargados">MERCOSUR</button>
                <select v-model.number="gestion" class="inv-select">
                    <option v-for="g in gestionesPortal" :key="g" :value="g">Gestión {{ etq(g) }}</option>
                </select>
                <Link class="tool-btn active" style="background:var(--navy)" href="/explorar">Explorador de datos →</Link>
            </div>
        </div>

        <div :class="{ 'gd-cargando': cargando }">
            <div class="ov-cards-4" style="margin-bottom:22px">
                <div class="ov-item"><div class="ov-data">
                    <strong>{{ datos?.tasa_cobertura != null ? fmtPct(datos.tasa_cobertura) : '—' }}</strong>
                    <span>Tasa de cobertura · X / M × 100</span>
                    <span v-if="datos?.tasa_cobertura == null" class="gd-tag-sindatos" style="margin-top:4px">Sin importaciones {{ gestion }}</span>
                </div></div>
                <div class="ov-item" :class="{ 'warn-border': datos?.saldo < 0 }"><div class="ov-data">
                    <strong :style="{ color: datos?.saldo == null ? '' : (datos.saldo < 0 ? '#dc2626' : '#0d8a5a') }">{{ fmtM(datos?.saldo, true) }}</strong>
                    <span>Saldo comercial<template v-if="datos?.saldo != null"> · {{ datos.saldo < 0 ? 'Déficit' : 'Superávit' }}</template></span>
                    <span v-if="datos?.saldo == null" class="gd-tag-sindatos" style="margin-top:4px">Requiere exp. e imp.</span>
                </div></div>
                <div class="ov-item"><div class="ov-data">
                    <strong>—</strong>
                    <span>Apertura comercial · (X + M) / PIB</span>
                    <span class="gd-tag-sindatos" style="margin-top:4px">Sin datos: PIB no cargado</span>
                </div></div>
                <div class="ov-item"><div class="ov-data">
                    <strong>{{ datos?.valor_unitario_exp != null ? fmtUsd(datos.valor_unitario_exp) + ' / t' : '—' }}</strong>
                    <span>Valor unitario medio de exportación<template v-if="datos?.toneladas_exp"> · {{ fmtNum(datos.toneladas_exp / 1e6, 2) }} M t</template></span>
                </div></div>
            </div>

            <div class="grid-2">
                <div class="bento-box">
                    <div class="bento-h"><h3>Concentración (índice HHI)</h3><span class="badge-var">INE Bolivia · {{ etq(gestion) }}</span></div>
                    <ul class="pill-bars-list">
                        <li v-for="h in datos?.hhi ?? []" :key="h.clave" class="pill-bar-row">
                            <span class="nm">{{ h.nombre }}</span>
                            <div class="pill-track"><div v-if="h.valor != null" class="pill-fill" :style="{ width: Math.min(100, h.valor * 250) + '%', background: hhiNivel(h.valor) }"></div></div>
                            <b class="vl">{{ h.valor != null ? fmtNum(h.valor, 3) : 'sin datos' }}</b>
                        </li>
                    </ul>
                    <p class="hhi-note">Calculado con todas las partidas, países o departamentos de la gestión. Verde: diversificado (&lt; 0,15) · Ámbar: moderado (0,15–0,25) · Rojo: concentrado (&gt; 0,25). Los flujos sin datos cargados aparecen como “sin datos”.</p>
                </div>
                <div class="bento-box">
                    <div class="bento-h"><h3>Fórmulas y Metodología Estandarizada (IIESJOM · LICI)</h3>
                        <span style="font-size:0.75rem;color:var(--muted);font-weight:700">Haz clic en cada fórmula</span></div>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:12px">
                        <button v-for="f in FORMULAS" :key="f.k" class="tool-btn" :class="{ active: formula.k === f.k }" @click="formula = f">{{ f.t }}</button>
                    </div>
                    <div style="padding:14px 16px;background:var(--soft);border-left:4px solid var(--wine);font-size:0.86rem">
                        <b>{{ formula.t }}:</b> {{ formula.d }}
                    </div>
                </div>
            </div>

            <div class="overview-panel" style="margin-top:22px">
                <div class="bento-h">
                    <div><h3>Exportaciones e importaciones por gestión</h3>
                        <small class="prd-panel-sub">Millones de USD · las barras vacías son años sin datos cargados, no ceros</small></div>
                    <GdCobertura :cobertura="cobertura" />
                </div>
                <svg :viewBox="`0 0 ${serieExp.length * 32 + 20} 230`" style="width:100%;height:auto" role="img">
                    <g v-for="(s, i) in serieExp" :key="s.gestion" style="cursor:pointer" @click="gestion = s.gestion">
                        <rect v-if="s.exp != null" :x="10 + i * 32" :y="200 - (s.exp / maxExp) * 180" width="13" :height="(s.exp / maxExp) * 180" fill="#10b981" :opacity="s.meses_exp < 12 ? 0.5 : 1"><title>{{ s.gestion }} exp: {{ fmtM(s.exp) }}</title></rect>
                        <rect v-if="s.imp != null" :x="24 + i * 32" :y="200 - (s.imp / maxExp) * 180" width="13" :height="(s.imp / maxExp) * 180" fill="#3b82f6"><title>{{ s.gestion }} imp: {{ fmtM(s.imp) }}</title></rect>
                        <rect v-else :x="24 + i * 32" y="190" width="13" height="10" fill="none" stroke="#cbd5e1" stroke-dasharray="2 2"><title>{{ s.gestion }}: importaciones sin datos</title></rect>
                        <rect v-if="s.gestion === gestion" :x="7 + i * 32" y="12" width="32" height="190" fill="none" stroke="#12203d" stroke-width="1.5" />
                        <text :x="24 + i * 32" y="218" text-anchor="middle" font-size="9" font-weight="700" fill="#64748b" :transform="`rotate(-45 ${24 + i * 32} 218)`">{{ etq(s.gestion) }}</text>
                    </g>
                    <line x1="5" y1="200.5" :x2="serieExp.length * 32 + 15" y2="200.5" stroke="#cbd5e1" />
                </svg>
                <div v-if="serieTc.length" style="margin-top:14px">
                    <h4 style="font-size:0.8rem;font-weight:800;color:var(--muted);text-transform:uppercase;letter-spacing:0.05em;margin-bottom:8px">Tasa de cobertura (X/M) en los años con ambos flujos</h4>
                    <div style="display:flex;flex-wrap:wrap;gap:8px">
                        <span v-for="s in serieTc" :key="s.gestion" class="gd-cob" :style="{ color: s.cobertura >= 100 ? '#15803d' : '#b91c1c' }">{{ s.gestion }}: {{ fmtPct(s.cobertura) }}</span>
                    </div>
                </div>
                <GdSinDatos v-else titulo="Todavía no hay ninguna gestión con exportaciones e importaciones a la vez" />
            </div>
            <p class="gd-nota">Fuente: INE (microdatos cargados en Geodata). FAOSTAT, ALADI y MERCOSUR se habilitarán aquí cuando tengan datos cargados.</p>
        </div>
    </div>
</template>
