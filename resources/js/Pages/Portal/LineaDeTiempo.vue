<script setup>
/**
 * Línea de tiempo 1992–2026 (estilo mockup, GEO-12) con la serie real del INE.
 * En lugar de hitos editoriales inventados, cada gestión muestra su producto y
 * destino líder calculados de la base. Saldo solo donde hay ambos flujos.
 */
import { ref, computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import GdCobertura from '@/Components/Gd/GdCobertura.vue';
import GdExplicacion from '@/Components/Gd/GdExplicacion.vue';
import { fmtM, fmtPct, fmtNum } from '@/lib/gd';

const props = defineProps({
    serie: { type: Array, default: () => [] },
    cobertura: { type: Object, default: null },
});

const modo = ref('flujos'); // 'flujos' | 'saldo'
const orden = ref('asc');
// ?gestion=2015 en la URL (enlaces de retos e historias) selecciona ese año.
const qsGestion = Number(new URLSearchParams(typeof window !== 'undefined' ? window.location.search : '').get('gestion'));
const sel = ref(props.serie.some((s) => s.gestion === qsGestion) ? qsGestion : (props.serie.at(-1)?.gestion ?? null));
const actual = computed(() => props.serie.find((s) => s.gestion === sel.value));
const parcial = (s) => s.meses_exp > 0 && s.meses_exp < 12;

const conSaldo = computed(() => props.serie.filter((s) => s.saldo != null));
const maxFlujo = computed(() => Math.max(1, ...props.serie.map((s) => Math.max(s.exp ?? 0, s.imp ?? 0))));
const maxSaldo = computed(() => Math.max(1, ...conSaldo.value.map((s) => Math.abs(s.saldo))));

const DECADAS = [[1990, 1999, '1992 – 1999'], [2000, 2009, '2000 – 2009'], [2010, 2019, '2010 – 2019'], [2020, 2029, '2020 – 2026']];
const bloques = computed(() => {
    const b = DECADAS.map(([a, z, t]) => {
        const items = props.serie.filter((s) => s.gestion >= a && s.gestion <= z);
        const exp = items.reduce((x, s) => x + (s.exp ?? 0), 0);
        const saldo = items.every((s) => s.saldo != null) && items.length ? items.reduce((x, s) => x + s.saldo, 0) : null;
        return { t, items: orden.value === 'asc' ? items : [...items].reverse(), exp, saldo };
    }).filter((d) => d.items.length);
    return orden.value === 'asc' ? b : b.reverse();
});
</script>

<template>
    <Head title="Línea de Tiempo" />
    <div class="page-wrap gd-pt">
        <div class="gd-cab">
            <div>
                <span class="gd-kicker">● Memoria histórica del comercio exterior boliviano</span>
                <h2 class="gd-titulo">Línea de Tiempo</h2>
            </div>
            <GdCobertura :cobertura="cobertura" />
        </div>

        <GdExplicacion clave="linea.serie" plegado />

        <div class="overview-panel">
            <div class="bento-h">
                <div>
                    <h3>{{ modo === 'saldo' ? 'Saldo de la Balanza Comercial de Bolivia' : 'Exportaciones e importaciones de Bolivia' }}</h3>
                    <p style="font-size:0.8rem;color:var(--muted)">
                        Millones de USD · {{ serie[0]?.gestion }}–{{ serie.at(-1)?.gestion }} · haz clic en un año para ver su ficha.
                        <template v-if="modo === 'saldo'">El saldo solo existe en los años con importaciones cargadas ({{ cobertura?.imp?.etiqueta }}).</template>
                    </p>
                </div>
                <div class="tl-tools" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
                    <button class="tool-btn" :class="{ active: modo === 'flujos' }" @click="modo = 'flujos'">Exportaciones e importaciones</button>
                    <button class="tool-btn" :class="{ active: modo === 'saldo' }" @click="modo = 'saldo'">Saldo</button>
                    <span class="badge-var">Año activo: {{ sel }}</span>
                </div>
            </div>

            <div style="width:100%;overflow-x:auto;margin-bottom:16px">
                <svg :viewBox="`0 0 ${serie.length * 34 + 30} 260`" style="width:100%;min-width:760px;height:auto" role="img">
                    <template v-if="modo === 'flujos'">
                        <g v-for="(s, i) in serie" :key="s.gestion" style="cursor:pointer" @click="sel = s.gestion">
                            <rect v-if="s.gestion === sel" :x="12 + i * 34" y="6" width="32" height="210" fill="#f1f5f9" />
                            <rect v-if="s.exp != null" :x="15 + i * 34" :y="210 - (s.exp / maxFlujo) * 190" width="13" :height="(s.exp / maxFlujo) * 190" fill="#10b981" :opacity="parcial(s) ? 0.5 : 1"><title>{{ s.gestion }} · exportaciones {{ fmtM(s.exp) }}</title></rect>
                            <rect v-if="s.imp != null" :x="29 + i * 34" :y="210 - (s.imp / maxFlujo) * 190" width="13" :height="(s.imp / maxFlujo) * 190" fill="#3b82f6"><title>{{ s.gestion }} · importaciones {{ fmtM(s.imp) }}</title></rect>
                            <rect v-else :x="29 + i * 34" y="200" width="13" height="10" fill="none" stroke="#cbd5e1" stroke-dasharray="2 2"><title>{{ s.gestion }} · importaciones sin datos</title></rect>
                            <text :x="28 + i * 34" y="232" text-anchor="middle" font-size="9.5" font-weight="700" fill="#64748b" :transform="`rotate(-45 ${28 + i * 34} 232)`">{{ s.gestion }}{{ parcial(s) ? '*' : '' }}</text>
                        </g>
                        <line x1="10" y1="210.5" :x2="serie.length * 34 + 20" y2="210.5" stroke="#cbd5e1" />
                    </template>
                    <template v-else>
                        <g v-for="(s, i) in serie" :key="s.gestion" style="cursor:pointer" @click="sel = s.gestion">
                            <rect v-if="s.gestion === sel" :x="12 + i * 34" y="6" width="32" height="210" fill="#f1f5f9" />
                            <rect v-if="s.saldo != null" :x="18 + i * 34" :y="s.saldo >= 0 ? 110 - (s.saldo / maxSaldo) * 95 : 110" width="20"
                                  :height="Math.abs(s.saldo / maxSaldo) * 95" :fill="s.saldo >= 0 ? '#10b981' : '#c81e3a'"><title>{{ s.gestion }} · saldo {{ fmtM(s.saldo, true) }}</title></rect>
                            <text v-else :x="28 + i * 34" y="106" text-anchor="middle" font-size="8" fill="#94a3b8">s/d</text>
                            <text :x="28 + i * 34" y="232" text-anchor="middle" font-size="9.5" font-weight="700" fill="#64748b" :transform="`rotate(-45 ${28 + i * 34} 232)`">{{ s.gestion }}</text>
                        </g>
                        <line x1="10" y1="110.5" :x2="serie.length * 34 + 20" y2="110.5" stroke="#94a3b8" />
                    </template>
                </svg>
            </div>

            <div v-if="actual" style="padding:16px 20px;background:var(--soft);border-left:5px solid var(--wine)">
                <div style="display:flex;justify-content:space-between;flex-wrap:wrap;gap:8px">
                    <b style="font-size:1.05rem;color:var(--navy)">Gestión {{ actual.gestion }}{{ parcial(actual) ? ` (ene–${['', 'ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'][actual.meses_exp]}, preliminar)` : '' }}</b>
                    <span style="font-size:0.8rem;color:var(--muted)">Producto líder: <b style="color:var(--navy)">{{ actual.producto_lider ?? '—' }}</b><template v-if="actual.producto_lider_pct"> ({{ fmtPct(actual.producto_lider_pct) }} de exp.)</template> · Destino #1: <b style="color:var(--navy)">{{ actual.destino_lider ?? '—' }}</b></span>
                </div>
                <div style="display:flex;gap:22px;flex-wrap:wrap;margin-top:8px;font-size:0.9rem">
                    <span>Exportaciones <b style="color:#059669">{{ fmtM(actual.exp) }}</b><small v-if="actual.var_exp != null" :class="actual.var_exp < 0 ? 'neg' : 'pos'"> {{ fmtPct(actual.var_exp, true) }}</small></span>
                    <span>Importaciones <b style="color:#2563eb">{{ fmtM(actual.imp) }}</b><span v-if="actual.imp == null" class="gd-tag-sindatos" style="margin-left:6px">Sin datos</span></span>
                    <span>Saldo <b :class="actual.saldo < 0 ? 'neg' : 'pos'">{{ fmtM(actual.saldo, true) }}</b></span>
                    <span v-if="actual.cobertura != null">Cobertura X/M <b>{{ fmtPct(actual.cobertura) }}</b></span>
                </div>
            </div>
        </div>

        <div class="tl-list-head">
            <div>
                <h3>Gestión por gestión</h3>
                <p style="margin:2px 0 0;font-size:0.8rem;color:var(--muted)">Exportaciones, importaciones, saldo y rubro líder de cada año (calculados de la base de datos)</p>
            </div>
            <div style="display:flex;gap:6px">
                <button class="tool-btn" :class="{ active: orden === 'asc' }" @click="orden = 'asc'">Del más antiguo al más reciente</button>
                <button class="tool-btn" :class="{ active: orden === 'desc' }" @click="orden = 'desc'">Del más reciente al más antiguo</button>
            </div>
        </div>
        <div class="tlv">
            <template v-for="b in bloques" :key="b.t">
                <div class="tlv-dec"><span>{{ b.t }}</span>
                    <small v-if="b.saldo != null">Saldo acumulado {{ fmtM(b.saldo, true) }}</small>
                    <small v-else>Exportación acumulada {{ fmtM(b.exp) }} · saldo no disponible (faltan importaciones)</small>
                </div>
                <div v-for="s in b.items" :key="s.gestion" class="tlv-item" :class="{ on: s.gestion === sel }" @click="sel = s.gestion">
                    <span class="tlv-dot" :class="s.saldo == null ? '' : (s.saldo >= 0 ? 'pos' : 'neg')" :style="s.saldo == null ? { background: '#64748b' } : {}">{{ s.gestion }}{{ parcial(s) ? '*' : '' }}</span>
                    <div class="tlv-card" :style="{ borderLeftColor: s.saldo == null ? '#94a3b8' : (s.saldo >= 0 ? 'var(--exp)' : 'var(--crimson)') }">
                        <div class="tlv-main"><b>Gestión {{ s.gestion }}{{ parcial(s) ? ' (preliminar)' : '' }}</b>
                            <p>{{ s.producto_lider ? `Lidera: ${s.producto_lider}` : '' }}{{ s.destino_lider ? ` · Destino #1: ${s.destino_lider}` : '' }}</p></div>
                        <dl>
                            <div><dt>Exportación</dt><dd>{{ fmtM(s.exp) }} <small v-if="s.var_exp != null" :class="s.var_exp < 0 ? 'neg' : 'pos'">{{ fmtPct(s.var_exp, true) }}</small></dd></div>
                            <div><dt>Importación</dt><dd>{{ s.imp != null ? fmtM(s.imp) : 'sin datos' }}</dd></div>
                            <div><dt>Saldo</dt><dd :style="{ color: s.saldo == null ? '' : (s.saldo >= 0 ? '#15803d' : '#b91c1c') }">{{ fmtM(s.saldo, true) }}</dd></div>
                            <div><dt>Rubro líder</dt><dd class="tlv-top">{{ s.producto_lider ?? '—' }}<template v-if="s.producto_lider_pct"> ({{ fmtNum(s.producto_lider_pct, 1) }}%)</template></dd></div>
                        </dl>
                    </div>
                </div>
            </template>
        </div>
        <p class="gd-nota">* Gestión preliminar (año incompleto). Fuente: INE, microdatos cargados en Geodata. Las importaciones solo están cargadas para {{ cobertura?.imp?.etiqueta }}.</p>
    </div>
</template>
