<script setup>
import { ref, computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import KPICard from '../../Components/UI/KPICard.vue';
import PanelDescarga from '../../Components/UI/PanelDescarga.vue';
import IneCharts from '../../Components/Portal/IneCharts.vue';
import MercosurCharts from '../../Components/Portal/MercosurCharts.vue';
import AladiCharts from '../../Components/Portal/AladiCharts.vue';
import FaostatCharts from '../../Components/Portal/FaostatCharts.vue';
import { useChartData } from '../../Components/Composables/useChartData.js';
import { colorOrg, logoOrg } from '../../lib/orgColors';

const props = defineProps({
    organizacionId: { type: Number, required: true },
});

const { data: org, cargando } = useChartData(`/api/v1/organizaciones/${props.organizacionId}`);
const { data: gestionesResp } = useChartData('/api/v1/filtros/gestiones', () => ({ org: props.organizacionId }));
const { data: ind } = useChartData('/api/v1/indicadores'); // ratios calculados (INE)

const color = computed(() => org.value?.color_primario || colorOrg(props.organizacionId));
const logo = computed(() => logoOrg(props.organizacionId));
const kpis = computed(() => org.value?.kpis ?? {});
const gestiones = computed(() => gestionesResp.value?.data ?? []);
const gestionReciente = computed(() => org.value?.gestion_reciente ?? gestiones.value?.[0] ?? null);

const esIne = computed(() => props.organizacionId === 1);
const esAladi = computed(() => props.organizacionId === 2);
const esMercosur = computed(() => props.organizacionId === 3);
const esFaostat = computed(() => props.organizacionId === 4);

const f = (v) => (v == null ? 0 : Number(v));
const pct = (v) => (v == null || isNaN(v) ? '—' : `${Number(v).toLocaleString('es-BO', { maximumFractionDigits: 1 })}%`);
const num = (v) => (v == null || isNaN(v) ? '—' : Number(v).toLocaleString('es-BO'));

// Indicadores / ratios por organización (derivados de los KPIs + indicadores INE).
const ratios = computed(() => {
    const k = kpis.value || {};
    const exp = Number(k.exportaciones ?? k.valor_total ?? 0);
    const imp = Number(k.importaciones ?? 0);
    const bal = Number(k.balanza_comercial ?? exp - imp);
    const out = [];

    if (imp > 0) out.push({ label: 'Tasa de cobertura', valor: pct((exp / imp) * 100), formula: 'Exportaciones ÷ Importaciones × 100' });
    if (exp + imp > 0) out.push({ label: 'Participación exportadora', valor: pct((exp / (exp + imp)) * 100), formula: 'Exportaciones ÷ (Exportaciones + Importaciones) × 100' });
    if (exp > 0) out.push({ label: 'Saldo / Exportaciones', valor: pct((bal / exp) * 100), formula: 'Balanza comercial ÷ Exportaciones × 100' });

    if (esIne.value && ind.value) {
        if (ind.value.concentracion_hhi != null) out.push({ label: 'Concentración (HHI)', valor: num(ind.value.concentracion_hhi), formula: 'Σ (participación de cada país)². 0 = diversificado, 10000 = monopolio' });
        if (ind.value.paises_destino != null) out.push({ label: 'Mercados destino', valor: num(ind.value.paises_destino), formula: 'Número de países con exportaciones > 0' });
        if (ind.value.productos_distintos != null) out.push({ label: 'Productos distintos', valor: num(ind.value.productos_distintos), formula: 'Cantidad de productos diferentes comerciados' });
    }
    return out;
});
</script>

<template>
    <Head :title="org?.nombre || 'Organización'" />

    <!-- Banner -->
    <section class="relative text-white overflow-hidden" :style="{ backgroundColor: color }">
        <div class="absolute inset-0 bg-gradient-to-br from-black/10 to-black/45"></div>
        <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-14">
            <Link href="/organizaciones" class="inline-flex items-center gap-1.5 text-white/70 hover:text-white text-sm mb-5 transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" /></svg>
                Organizaciones
            </Link>
            <div v-if="cargando" class="h-24 w-72 bg-white/15 rounded-lg animate-pulse"></div>
            <template v-else>
                <div class="flex items-center gap-4">
                    <div class="w-16 h-16 rounded-2xl bg-white ring-1 ring-white/25 flex items-center justify-center p-2 shrink-0 shadow-md">
                        <img v-if="logo" :src="logo" :alt="`Logo ${org?.sigla}`" class="h-full w-full object-contain" />
                        <span v-else class="text-2xl font-bold" :style="{ color }">{{ (org?.sigla || '?').slice(0, 2) }}</span>
                    </div>
                    <div>
                        <h1 class="titular-editorial text-4xl sm:text-5xl">{{ org?.sigla }}</h1>
                        <p class="text-white/75 text-sm mt-1">{{ org?.nombre }}</p>
                    </div>
                </div>
                <p class="text-white/85 mt-5 max-w-3xl leading-relaxed">{{ org?.descripcion_larga || org?.descripcion_corta }}</p>
                <div class="flex flex-wrap gap-2 mt-5">
                    <span v-if="org?.cobertura_temporal" class="pildora bg-white/10 ring-1 ring-white/20">📅 {{ org.cobertura_temporal }}</span>
                    <span v-if="org?.cobertura_geografica" class="pildora bg-white/10 ring-1 ring-white/20">🌎 {{ org.cobertura_geografica }}</span>
                </div>
            </template>
        </div>
    </section>

    <section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10 space-y-8">
        <!-- KPIs -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
            <KPICard titulo="Exportaciones" :valor="f(kpis.exportaciones ?? kpis.valor_total)" prefijo="$ " :color="color" :variacion="kpis.variacion_exp_pct ?? null" />
            <KPICard titulo="Importaciones" :valor="f(kpis.importaciones)" prefijo="$ " :color="color" :variacion="kpis.variacion_imp_pct ?? null" />
            <KPICard titulo="Balanza" :valor="f(kpis.balanza_comercial)" prefijo="$ " :color="color" />
            <KPICard titulo="Registros" :valor="f(kpis.operaciones ?? kpis.items_ranking ?? kpis.series)" :color="color" :subtitulo="`Gestión ${kpis.gestion ?? '—'}`" />
        </div>

        <!-- Indicadores y ratios -->
        <div v-if="ratios.length">
            <h2 class="titular-editorial text-2xl text-institucional-900 mb-4">Indicadores y ratios</h2>
            <div class="grid grid-cols-2 lg:grid-cols-3 gap-4">
                <div v-for="r in ratios" :key="r.label" class="tarjeta p-5 group relative">
                    <div class="flex items-center justify-between">
                        <p class="text-xs font-semibold uppercase tracking-wider text-gris-500">{{ r.label }}</p>
                        <span class="relative">
                            <svg class="w-4 h-4 text-gris-300 hover:text-gris-500 cursor-help" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z" /></svg>
                            <span class="absolute right-0 top-6 z-10 hidden group-hover:block w-56 p-2.5 rounded-lg bg-institucional-900 text-white text-[11px] leading-snug shadow-lg">
                                <strong>Fórmula:</strong> {{ r.formula }}
                            </span>
                        </span>
                    </div>
                    <p class="text-3xl font-bold mt-2" :style="{ color }">{{ r.valor }}</p>
                </div>
            </div>
        </div>

        <!-- Panel de descarga / venta (maqueta) -->
        <div>
            <h2 class="titular-editorial text-2xl text-institucional-900 mb-4">Descargar datos</h2>
            <PanelDescarga :org="org" />
        </div>

        <!-- Info de la fuente -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <a v-if="org?.url_fuente_oficial" :href="org.url_fuente_oficial" target="_blank" rel="noopener"
               class="tarjeta p-5 block hover:border-rojo-200 transition group">
                <span class="text-xs text-gris-500">Fuente oficial</span>
                <span class="flex items-center gap-1.5 font-semibold text-institucional-900 mt-1 group-hover:text-rojo-600 transition">
                    {{ org?.sigla }}
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.5 6H5.25A2.25 2.25 0 003 8.25v10.5A2.25 2.25 0 005.25 21h10.5A2.25 2.25 0 0018 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25" /></svg>
                </span>
            </a>
            <div v-if="org?.tipos_datos" class="tarjeta p-5">
                <span class="text-xs text-gris-500">Tipos de datos</span>
                <p class="text-sm text-gris-700 mt-1 leading-relaxed">{{ org.tipos_datos }}</p>
            </div>
            <div v-if="org?.metodologia" class="tarjeta p-5">
                <span class="text-xs text-gris-500">Metodología</span>
                <p class="text-sm text-gris-700 mt-1 leading-relaxed">{{ org.metodologia }}</p>
            </div>
        </div>

        <!-- Gráficos según la organización -->
        <div>
            <h2 class="titular-editorial text-2xl text-institucional-900 mb-4">Visualizaciones</h2>
            <IneCharts v-if="esIne" :gestiones="gestiones" :gestion-inicial="gestionReciente" />
            <MercosurCharts v-else-if="esMercosur" :gestiones="gestiones" :gestion-inicial="gestionReciente" />
            <AladiCharts v-else-if="esAladi" :gestiones="gestiones" :gestion-inicial="gestionReciente" />
            <FaostatCharts v-else-if="esFaostat" />
        </div>
    </section>
</template>
