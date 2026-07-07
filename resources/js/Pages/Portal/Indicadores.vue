<script setup>
import { computed, ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import ChartCard from '../../Components/UI/ChartCard.vue';
import AreaChart from '../../Components/Charts/AreaChart.vue';
import { useChartData } from '../../Components/Composables/useChartData.js';
import { fmtNum, fmtCompacto, fmtPct, ejeCompacto, fmtUsd } from '../../lib/format';
import { colorOrg } from '../../lib/orgColors';

const props = defineProps({
    organizaciones: { type: Array, default: () => [] },
    organizacionDefecto: { type: Number, default: 1 },
});

// --- Estado de filtros -----------------------------------------------------
const orgId = ref(props.organizacionDefecto || 1);
const gestionSel = ref(null); // null => el backend elige el mejor año (último con importaciones)

function cambiarOrg(id) {
    if (orgId.value === id) return;
    orgId.value = id;
    gestionSel.value = null; // dejar que el backend elija el año por defecto de la nueva fuente
}

// --- Datos -----------------------------------------------------------------
const { data: ind, cargando } = useChartData(
    '/api/v1/indicadores',
    () => ({ org: orgId.value, gestion: gestionSel.value || undefined }),
);
const { data: gestionesResp } = useChartData(
    '/api/v1/filtros/gestiones',
    () => ({ org: orgId.value }),
);
const { data: evol, cargando: cEvol } = useChartData('/api/v1/charts/evolucion-anual');

const i = computed(() => ind.value ?? {});
const esIne = computed(() => orgId.value === 1);
const esFaostat = computed(() => orgId.value === 4);
const color = computed(() => colorOrg(orgId.value));

const gestiones = computed(() => gestionesResp.value?.data ?? []);
const gestionActual = computed(() => gestionSel.value ?? i.value.gestion ?? null);
const aniosConImp = computed(() => i.value.meta?.anios_con_importacion ?? []);

// Sparkline de balanza desde la evolución anual (solo INE).
const sparkBalanza = computed(() => {
    const exp = evol.value?.series?.[0]?.data ?? [];
    const imp = evol.value?.series?.[1]?.data ?? [];
    return exp.map((e, idx) => e - (imp[idx] ?? 0));
});

// Catálogo de indicadores: se muestran solo los que la organización devuelve.
const DESCRIPTORES = [
    { key: 'indice_cobertura', label: 'Tasa de cobertura', fmt: fmtPct, formula: 'Exportaciones ÷ Importaciones × 100. >100% = superávit comercial.', color: '#38A169' },
    { key: 'cobertura_exportaciones', label: 'Participación exportadora', fmt: fmtPct, formula: 'Exportaciones ÷ (Exportaciones + Importaciones) × 100.', color: '#C53030' },
    { key: 'balanza_comercial', label: 'Balanza comercial', fmt: fmtCompacto, formula: 'Exportaciones − Importaciones (USD).', color: '#1A4B8C', spark: true },
    { key: 'concentracion_hhi', label: 'Concentración (HHI)', fmt: fmtNum, formula: 'Σ (participación de cada mercado)². 0 = diversificado, 10000 = un solo mercado.', color: '#DD6B20' },
    { key: 'participacion_top5', label: 'Participación top 5', fmt: fmtPct, formula: 'Peso de los 5 mayores ítems sobre el total.', color: '#DD6B20' },
    { key: 'paises_destino', label: 'Mercados destino', fmt: fmtNum, formula: 'Número de países con exportaciones > 0.', color: '#3182CE' },
    { key: 'zonas', label: 'Zonas geoeconómicas', fmt: fmtNum, formula: 'Número de zonas con registros.', color: '#3182CE' },
    { key: 'productos_distintos', label: 'Productos distintos', fmt: fmtNum, formula: 'Cantidad de productos (NANDINA) distintos comerciados.', color: '#805AD5' },
    { key: 'items_ranking', label: 'Ítems en ranking', fmt: fmtNum, formula: 'Número de ítems del ranking.', color: '#805AD5' },
    { key: 'exportaciones', label: 'Exportaciones', fmt: fmtCompacto, formula: 'Total exportado en el periodo (USD).', color: '#16A34A' },
    { key: 'importaciones', label: 'Importaciones', fmt: fmtCompacto, formula: 'Total importado en el periodo (USD).', color: '#DC2626' },
    { key: 'valor_total', label: 'Valor total', fmt: fmtCompacto, formula: 'Suma del valor de los ítems del ranking (USD).', color: '#16A34A' },
    { key: 'valor_promedio_operacion', label: 'Valor medio por operación', fmt: fmtUsd, formula: '(Exportaciones + Importaciones) ÷ nº de operaciones.', color: '#0D9488' },
];

const tarjetas = computed(() =>
    DESCRIPTORES
        .filter((d) => i.value[d.key] != null)
        .map((d) => ({
            ...d,
            valor: i.value[d.key],
            spark: d.spark && esIne.value ? sparkBalanza.value : [],
        })),
);

function sparkPath(d) {
    if (!d || d.length < 2) return '';
    const min = Math.min(...d), max = Math.max(...d), span = (max - min) || 1;
    return d.map((v, idx) => `${idx === 0 ? 'M' : 'L'}${(idx / (d.length - 1) * 100).toFixed(1)},${(28 - (v - min) / span * 28).toFixed(1)}`).join(' ');
}

const opcEvol = computed(() => ({
    xaxis: { categories: evol.value?.categorias ?? [] },
    yaxis: { labels: { formatter: ejeCompacto } },
    colors: ['#3182CE', '#C53030'],
    tooltip: { y: { formatter: (v) => fmtUsd(v) } },
}));
</script>

<template>
    <Head title="Indicadores" />

    <section class="bg-white border-b border-gris-100">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
            <p class="inline-flex items-center gap-2.5 text-[11px] font-bold uppercase tracking-[0.18em] text-rojo-600 mb-4"><span class="w-7 h-px bg-rojo-500"></span> Tablero</p>
            <h1 class="titular-editorial text-4xl sm:text-5xl text-institucional-900">Indicadores</h1>
            <p class="text-institucional-500 mt-4 max-w-2xl leading-relaxed text-lg">Indicadores del comercio exterior calculados sobre datos reales. Selecciona una organización y un año.</p>
        </div>
    </section>

    <!-- ===================== BARRA DE FILTROS ===================== -->
    <section class="sticky top-[62px] z-20 bg-white/95 backdrop-blur-md border-b border-gris-100">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-3 flex flex-wrap items-center gap-3">
            <!-- Selector de organización -->
            <div class="flex flex-wrap items-center gap-1.5">
                <button
                    v-for="o in organizaciones"
                    :key="o.organizacion_id"
                    @click="cambiarOrg(o.organizacion_id)"
                    class="px-3.5 py-1.5 rounded-full text-sm font-semibold transition border"
                    :class="orgId === o.organizacion_id
                        ? 'text-white border-transparent shadow-sm'
                        : 'bg-white text-institucional-600 border-gris-200 hover:border-gris-300'"
                    :style="orgId === o.organizacion_id ? { backgroundColor: colorOrg(o.organizacion_id) } : {}"
                >
                    {{ o.sigla }}
                </button>
            </div>

            <div class="h-5 w-px bg-gris-200 hidden sm:block"></div>

            <!-- Selector de año -->
            <label class="flex items-center gap-2 text-sm">
                <span class="text-gris-500 font-medium">Año</span>
                <select
                    v-model="gestionSel"
                    class="rounded-lg border border-gris-200 bg-white px-3 py-1.5 text-sm font-semibold text-institucional-800 focus:outline-none focus:ring-2 focus:ring-rojo-500/30"
                >
                    <option :value="null">Más reciente con datos</option>
                    <option v-for="g in gestiones" :key="g" :value="g">
                        {{ g }}{{ esIne && aniosConImp.includes(g) ? ' ✓ con imp.' : '' }}
                    </option>
                </select>
            </label>

            <span v-if="cargando" class="text-xs text-gris-400 animate-pulse">Calculando…</span>
        </div>
    </section>

    <section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10 space-y-8">
        <!-- Aviso de cobertura de importaciones (INE) -->
        <div v-if="esIne && i.hay_importaciones === false"
             class="flex items-start gap-3 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
            <svg class="w-5 h-5 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" /></svg>
            <p>
                El año <strong>{{ gestionActual }}</strong> solo tiene exportaciones cargadas, por eso los ratios que dependen de importaciones (tasa de cobertura, balanza real) no se muestran.
                <template v-if="aniosConImp.length">Años con importaciones disponibles: <strong>{{ aniosConImp.join(', ') }}</strong>.</template>
            </p>
        </div>

        <!-- Estado vacío FAOSTAT -->
        <div v-if="esFaostat && !i.hay_datos" class="tarjeta p-10 text-center">
            <p class="text-5xl mb-3">🌾</p>
            <h3 class="titular-editorial text-2xl text-institucional-900 mb-2">FAOSTAT — sin datos aún</h3>
            <p class="text-gris-500 max-w-md mx-auto">{{ i.meta?.nota || 'Los indicadores se activarán cuando se carguen los datos de FAOSTAT.' }}</p>
        </div>

        <!-- Scorecards -->
        <template v-else>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                <div v-for="t in tarjetas" :key="t.key" class="tarjeta p-5 flex flex-col gap-2 group relative">
                    <div class="flex items-center justify-between">
                        <p class="text-xs font-semibold uppercase tracking-wider text-gris-500">{{ t.label }}</p>
                        <span class="relative">
                            <svg class="w-4 h-4 text-gris-300 hover:text-gris-500 cursor-help" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z" /></svg>
                            <span class="absolute right-0 top-6 z-10 hidden group-hover:block w-56 p-2.5 rounded-lg bg-institucional-900 text-white text-[11px] leading-snug shadow-lg">
                                <strong>Fórmula:</strong> {{ t.formula }}
                            </span>
                        </span>
                    </div>
                    <p class="text-3xl font-bold text-institucional-900">
                        <span v-if="cargando" class="inline-block w-20 h-8 bg-gris-100 rounded animate-pulse"></span>
                        <template v-else>{{ t.fmt(t.valor) }}</template>
                    </p>
                    <svg v-if="t.spark.length > 1" class="w-full h-7 mt-1" viewBox="0 0 100 28" preserveAspectRatio="none">
                        <path :d="sparkPath(t.spark)" fill="none" :stroke="t.color" stroke-width="2" stroke-linecap="round" />
                    </svg>
                </div>
            </div>

            <div v-if="!cargando && !tarjetas.length" class="tarjeta p-10 text-center text-gris-500">
                No hay indicadores disponibles para esta combinación de organización y año.
            </div>

            <!-- Contexto: evolución histórica (INE) -->
            <ChartCard v-if="esIne" titulo="Evolución exportaciones vs importaciones" fuente="INE — Bolivia" :cargando="cEvol"
                :columnas="[{key:'a',label:'Año'},{key:'e',label:'Exp',alinear:'right'},{key:'i',label:'Imp',alinear:'right'}]"
                :filas="(evol?.categorias??[]).map((c,idx)=>({a:c,e:evol?.series?.[0]?.data?.[idx] ?? 0,i:evol?.series?.[1]?.data?.[idx] ?? 0}))">
                <AreaChart :series="(evol?.series ?? []).slice(0,2)" :categorias="evol?.categorias ?? []" :opciones="opcEvol" :apilada="false" :height="320" :cargando="cEvol" />
            </ChartCard>
        </template>
    </section>
</template>
