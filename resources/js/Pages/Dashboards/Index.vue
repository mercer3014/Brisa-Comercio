<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import { Head } from '@inertiajs/vue3';
import axios from 'axios';

const props = defineProps({
    organizacionDefecto: Number,
    organizaciones: Array,
    gestiones: Array,
});

const orgId = ref(props.organizacionDefecto);
const gestion = ref(props.gestiones?.[0] ?? null);
const tab = ref('general');
const cargando = ref(false);
const d = ref(null); // datos

// Organizaciones cuya arquitectura ya sabe leer este dashboard (cada una con
// su propia fuente: INE = microdato, ALADI = rankings top-50 por país,
// MERCOSUR = series por zona/producto, FAOSTAT = índices comerciales).
const ORGS_SOPORTADAS = [1, 2, 3, 4];
// MERCOSUR, ALADI y FAOSTAT no tienen desagregacion por departamento ni por
// medio de transporte (eso es propio del microdato del INE): sin pestaña logística.
const tabsBase = [
    { key: 'general', label: 'General' },
    { key: 'exportaciones', label: 'Exportaciones' },
    { key: 'importaciones', label: 'Importaciones' },
    { key: 'pais', label: 'Por país' },
    { key: 'producto', label: 'Por producto' },
    { key: 'balanza', label: 'Balanza comercial' },
    { key: 'logistico', label: 'Logístico' },
];
const tabs = computed(() => [2, 3, 4].includes(orgId.value) ? tabsBase.filter((t) => t.key !== 'logistico') : tabsBase);

// FAOSTAT no publica USD: sus cifras son índices (base 2014-2016 = 100), y
// las tarjetas y títulos se rotulan distinto para no hacerlos pasar por dólares.
const esFaostat = computed(() => orgId.value === 4);
const fmtIdx = (n) => (n == null ? '—' : new Intl.NumberFormat('es-BO', { maximumFractionDigits: 1 }).format(n));

async function cargar() {
    cargando.value = true;
    try {
        if (!ORGS_SOPORTADAS.includes(orgId.value)) {
            d.value = { kpis: {} };
            return;
        }
        const { data } = await axios.post('/admin/dashboards/datos', {
            organizacion_id: orgId.value,
            gestion: gestion.value,
        });
        d.value = data;
    } finally {
        cargando.value = false;
    }
}

// Cada organización tiene su propio rango de años con datos (INE llega a
// 2026, ALADI a 2025, etc.): al cambiar de organización se ajusta la lista de
// gestiones y se salta a la más reciente de esa organización.
const gestionesOrg = ref([...(props.gestiones ?? [])]);

watch(orgId, async () => {
    if (!tabs.value.some((t) => t.key === tab.value)) {
        tab.value = 'general';
    }
    try {
        const { data } = await axios.get('/api/v1/filtros/gestiones', { params: { org: orgId.value } });
        const lista = data?.data ?? [];
        gestionesOrg.value = lista.length ? lista : [...(props.gestiones ?? [])];
    } catch {
        gestionesOrg.value = [...(props.gestiones ?? [])];
    }
    if (gestion.value !== null && !gestionesOrg.value.includes(gestion.value)) {
        gestion.value = gestionesOrg.value[0] ?? null; // el watch de gestión recarga
    } else {
        cargar();
    }
});
watch(gestion, cargar);
onMounted(cargar);

const fmt = (n) => new Intl.NumberFormat('es-BO', { maximumFractionDigits: 0 }).format(n || 0);
const fmtUsd = (n) => '$ ' + fmt(n);
// Montos de tarjetas: siempre cortos para que número y unidad queden en UNA
// línea (a partir de 10.000 M se pasa a "mil M").
const fmtM = (n) => {
    const m = (n || 0) / 1e6;
    const nf = (x) => new Intl.NumberFormat('es-BO', { maximumFractionDigits: 1 }).format(x);
    return Math.abs(m) >= 10000 ? `$ ${nf(m / 1000)} mil M` : `$ ${nf(m)} M`;
};
// Ejes de gráficos: notacion compacta para que los números no se salgan del cuadro.
const ejeC = (v) => new Intl.NumberFormat('es-BO', { notation: 'compact', maximumFractionDigits: 1 }).format(v || 0);

// ---- Configuración de gráficos ----
const colorMarca = '#2563eb';
const colorVerde = '#059669';
const colorAmbar = '#d97706';

const optsBarras = (categorias, horizontal = false) => ({
    chart: { type: 'bar', toolbar: { show: false }, fontFamily: 'inherit' },
    plotOptions: { bar: { horizontal, borderRadius: 4, columnWidth: '60%' } },
    dataLabels: { enabled: false },
    // El eje numérico (x si es horizontal, y si es vertical) usa notacion compacta.
    xaxis: { categories: categorias, labels: { style: { fontSize: '11px' }, ...(horizontal ? { formatter: ejeC } : {}) } },
    ...(horizontal ? {} : { yaxis: { labels: { formatter: ejeC } } }),
    colors: [colorMarca],
    grid: { borderColor: '#f1f5f9' },
});

// Evolución mensual
const evoMensual = computed(() => {
    const e = d.value?.evolucion_mensual ?? [];
    return {
        series: [
            { name: 'Valor (USD)', type: 'column', data: e.map((x) => Math.round(x.valor)) },
            { name: 'Peso (kg)', type: 'line', data: e.map((x) => Math.round(x.peso)) },
        ],
        options: {
            chart: { toolbar: { show: false }, fontFamily: 'inherit' },
            stroke: { width: [0, 3] },
            colors: [colorMarca, colorAmbar],
            xaxis: { categories: e.map((x) => x.periodo), labels: { rotate: -45, style: { fontSize: '10px' } } },
            yaxis: [{ title: { text: 'Valor' }, labels: { formatter: ejeC } }, { opposite: true, title: { text: 'Peso' }, labels: { formatter: ejeC } }],
            dataLabels: { enabled: false },
            grid: { borderColor: '#f1f5f9' },
        },
    };
});

// Balanza anual
const balanzaAnual = computed(() => {
    const e = d.value?.evolucion_anual ?? [];
    return {
        series: [
            { name: 'Exportaciones', data: e.map((x) => Math.round(x.expo)) },
            { name: 'Importaciones', data: e.map((x) => Math.round(x.impo)) },
            { name: 'Balanza', data: e.map((x) => Math.round(x.balanza)) },
        ],
        options: {
            chart: { type: 'bar', toolbar: { show: false }, fontFamily: 'inherit' },
            plotOptions: { bar: { borderRadius: 4, columnWidth: '60%' } },
            colors: [colorVerde, colorAmbar, colorMarca],
            xaxis: { categories: e.map((x) => x.gestion) },
            yaxis: { labels: { formatter: ejeC } },
            dataLabels: { enabled: false },
            grid: { borderColor: '#f1f5f9' },
            legend: { position: 'top' },
        },
    };
});

const topPaises = computed(() => {
    const e = d.value?.top_paises ?? [];
    return { series: [{ name: 'Valor', data: e.map((x) => Math.round(x.valor)) }], options: optsBarras(e.map((x) => x.label), true) };
});
const topProductos = computed(() => {
    const e = d.value?.top_productos ?? [];
    return { series: [{ name: 'Valor', data: e.map((x) => Math.round(x.valor)) }], options: { ...optsBarras(e.map((x) => x.label), true), colors: [colorVerde] } };
});
const distZona = computed(() => {
    const e = d.value?.distribucion_zona ?? [];
    return { series: e.map((x) => Math.round(x.valor)), options: { chart: { type: 'donut', fontFamily: 'inherit' }, labels: e.map((x) => x.label), legend: { position: 'bottom' }, colors: ['#2563eb', '#059669', '#d97706', '#7c3aed', '#dc2626', '#0891b2'] } };
});
const distDepto = computed(() => {
    const e = d.value?.distribucion_departamento ?? [];
    return { series: [{ name: 'Valor', data: e.map((x) => Math.round(x.valor)) }], options: { ...optsBarras(e.map((x) => x.label)), colors: [colorAmbar] } };
});
const distMedio = computed(() => {
    const e = d.value?.distribucion_medio ?? [];
    return { series: e.map((x) => Math.round(x.valor)), options: { chart: { type: 'pie', fontFamily: 'inherit' }, labels: e.map((x) => x.label), legend: { position: 'bottom' } } };
});

const k = computed(() => d.value?.kpis ?? {});

// Las 4 organizaciones se leen aquí mismo, cada una con su arquitectura.
const orgActual = computed(() => props.organizaciones.find((o) => o.organizacion_id === orgId.value));
const sinMicrodato = computed(() => !ORGS_SOPORTADAS.includes(orgId.value));
</script>

<template>
    <Head title="Dashboards" />

    <div class="max-w-7xl mx-auto">
        <div class="flex items-center justify-between mb-5">
            <div>
                <h1 class="text-2xl font-bold text-slate-800">Dashboards</h1>
                <p class="text-slate-500 text-sm">Indicadores y visualizaciones del comercio exterior.</p>
            </div>
            <div class="flex gap-2">
                <select v-model="orgId" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <option v-for="o in organizaciones" :key="o.organizacion_id" :value="o.organizacion_id">{{ o.nombre }}</option>
                </select>
                <select v-model="gestion" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <option :value="null">Todas las gestiones</option>
                    <option v-for="g in gestionesOrg" :key="g" :value="g">{{ g }}</option>
                </select>
            </div>
        </div>

        <!-- Tabs -->
        <div class="flex gap-1 mb-5 border-b border-slate-200 overflow-x-auto">
            <button v-for="t in tabs" :key="t.key" @click="tab = t.key"
                    class="px-4 py-2 text-sm font-medium border-b-2 whitespace-nowrap transition"
                    :class="tab === t.key ? 'border-marca-600 text-marca-700' : 'border-transparent text-slate-500 hover:text-marca-600'">
                {{ t.label }}
            </button>
        </div>

        <div v-if="!d" class="text-center py-20 text-slate-400">Cargando indicadores...</div>

        <div v-else-if="sinMicrodato" class="rounded-xl border border-amber-200 bg-amber-50 p-6 text-center">
            <p class="text-amber-800 font-medium">
                Este dashboard solo lee el microdato del INE. {{ orgActual?.nombre ?? 'Esta organización' }} guarda sus
                datos en un panel propio, con sus graficos y KPIs.
            </p>
            <a :href="`/organizaciones/${orgId}`" target="_blank"
               class="inline-block mt-3 rounded-lg bg-amber-600 px-4 py-2 text-sm font-medium text-white hover:bg-amber-700">
                Ver panel de {{ orgActual?.sigla ?? orgActual?.nombre }}
            </a>
        </div>

        <div v-else class="space-y-5">
            <!-- KPIs FAOSTAT: índices y cobertura (no hay USD) -->
            <div v-if="esFaostat" class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3">
                <div class="bg-white rounded-xl border border-slate-200 p-4">
                    <div class="text-xs text-slate-500">Series de índices</div>
                    <div class="text-xl font-bold whitespace-nowrap text-marca-700">{{ fmt(k.series) }}</div>
                </div>
                <div class="bg-white rounded-xl border border-slate-200 p-4">
                    <div class="text-xs text-slate-500">Países</div>
                    <div class="text-xl font-bold whitespace-nowrap text-slate-800">{{ fmt(k.paises) }}</div>
                </div>
                <div class="bg-white rounded-xl border border-slate-200 p-4">
                    <div class="text-xs text-slate-500">Productos (CPC)</div>
                    <div class="text-xl font-bold whitespace-nowrap text-slate-800">{{ fmt(k.productos) }}</div>
                </div>
                <div class="bg-white rounded-xl border border-slate-200 p-4">
                    <div class="text-xs text-slate-500">Índice valor expo (mediana)</div>
                    <div class="text-xl font-bold whitespace-nowrap text-emerald-600">{{ fmtIdx(k.valor_exportacion) }}</div>
                </div>
                <div class="bg-white rounded-xl border border-slate-200 p-4">
                    <div class="text-xs text-slate-500">Índice valor impo (mediana)</div>
                    <div class="text-xl font-bold whitespace-nowrap text-amber-600">{{ fmtIdx(k.valor_importacion) }}</div>
                </div>
                <div class="bg-white rounded-xl border border-slate-200 p-4">
                    <div class="text-xs text-slate-500">Var. interanual del índice</div>
                    <div class="text-xl font-bold whitespace-nowrap" :class="(k.variacion_interanual ?? 0) >= 0 ? 'text-emerald-600' : 'text-red-600'">
                        <span v-if="k.variacion_interanual !== null">{{ k.variacion_interanual > 0 ? '+' : '' }}{{ k.variacion_interanual }}%</span>
                        <span v-else>—</span>
                    </div>
                </div>
            </div>

            <!-- KPIs (siempre visibles) -->
            <div v-else class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3">
                <div class="bg-white rounded-xl border border-slate-200 p-4">
                    <div class="text-xs text-slate-500">Valor total</div>
                    <div class="text-xl font-bold whitespace-nowrap text-marca-700">{{ fmtM(k.valor_total) }}</div>
                </div>
                <div class="bg-white rounded-xl border border-slate-200 p-4">
                    <div class="text-xs text-slate-500">Exportaciones</div>
                    <div class="text-xl font-bold whitespace-nowrap text-emerald-600">{{ fmtM(k.valor_exportacion) }}</div>
                </div>
                <div class="bg-white rounded-xl border border-slate-200 p-4">
                    <div class="text-xs text-slate-500">Importaciones</div>
                    <div class="text-xl font-bold whitespace-nowrap text-amber-600">{{ fmtM(k.valor_importacion) }}</div>
                </div>
                <div class="bg-white rounded-xl border border-slate-200 p-4">
                    <div class="text-xs text-slate-500">Balanza comercial</div>
                    <div class="text-xl font-bold whitespace-nowrap" :class="k.balanza_comercial >= 0 ? 'text-emerald-600' : 'text-red-600'">{{ fmtM(k.balanza_comercial) }}</div>
                </div>
                <div class="bg-white rounded-xl border border-slate-200 p-4">
                    <div class="text-xs text-slate-500">Precio implícito $/kg</div>
                    <div class="text-xl font-bold whitespace-nowrap text-slate-800">{{ k.precio_implicito ?? '—' }}</div>
                </div>
                <div class="bg-white rounded-xl border border-slate-200 p-4">
                    <div class="text-xs text-slate-500">Var. interanual</div>
                    <div class="text-xl font-bold whitespace-nowrap" :class="(k.variacion_interanual ?? 0) >= 0 ? 'text-emerald-600' : 'text-red-600'">
                        <span v-if="k.variacion_interanual !== null">{{ k.variacion_interanual > 0 ? '+' : '' }}{{ k.variacion_interanual }}%</span>
                        <span v-else>—</span>
                    </div>
                </div>
            </div>

            <!-- GENERAL -->
            <div v-show="tab === 'general'" class="grid grid-cols-1 lg:grid-cols-2 gap-5">
                <div class="bg-white rounded-xl border border-slate-200 p-4 lg:col-span-2">
                    <h3 class="font-semibold text-slate-700 mb-3">{{ esFaostat ? 'Evolución anual del índice de valor (2014-2016 = 100)' : 'Evolución mensual (valor y peso)' }}</h3>
                    <apexchart height="300" :options="evoMensual.options" :series="evoMensual.series" />
                </div>
                <div class="bg-white rounded-xl border border-slate-200 p-4">
                    <h3 class="font-semibold text-slate-700 mb-3">{{ [2, 4].includes(orgId) ? 'Distribución por país' : 'Distribución por zona' }}</h3>
                    <apexchart height="300" type="donut" :options="distZona.options" :series="distZona.series" />
                </div>
                <div class="bg-white rounded-xl border border-slate-200 p-4">
                    <h3 class="font-semibold text-slate-700 mb-3">Top 10 países{{ esFaostat ? ' (índice mediano)' : '' }}</h3>
                    <apexchart height="300" type="bar" :options="topPaises.options" :series="topPaises.series" />
                </div>
            </div>

            <!-- EXPORTACIONES / IMPORTACIONES -->
            <div v-show="tab === 'exportaciones' || tab === 'importaciones'" class="grid grid-cols-1 lg:grid-cols-2 gap-5">
                <div class="bg-white rounded-xl border border-slate-200 p-4 lg:col-span-2">
                    <h3 class="font-semibold text-slate-700 mb-3">{{ esFaostat ? 'Índices anuales: exportación vs importación (2014-2016 = 100)' : 'Balanza anual (exportaciones vs importaciones)' }}</h3>
                    <apexchart height="300" type="bar" :options="balanzaAnual.options" :series="balanzaAnual.series" />
                </div>
                <div class="bg-white rounded-xl border border-slate-200 p-4">
                    <h3 class="font-semibold text-slate-700 mb-3">Top 10 productos{{ esFaostat ? ' (índice mediano)' : '' }}</h3>
                    <apexchart height="320" type="bar" :options="topProductos.options" :series="topProductos.series" />
                </div>
                <div class="bg-white rounded-xl border border-slate-200 p-4">
                    <h3 class="font-semibold text-slate-700 mb-3">Top 10 países{{ esFaostat ? ' (índice mediano)' : '' }}</h3>
                    <apexchart height="320" type="bar" :options="topPaises.options" :series="topPaises.series" />
                </div>
            </div>

            <!-- POR PAIS -->
            <div v-show="tab === 'pais'" class="grid grid-cols-1 lg:grid-cols-2 gap-5">
                <div class="bg-white rounded-xl border border-slate-200 p-4">
                    <h3 class="font-semibold text-slate-700 mb-3">Top 10 países por valor</h3>
                    <apexchart height="340" type="bar" :options="topPaises.options" :series="topPaises.series" />
                </div>
                <div class="bg-white rounded-xl border border-slate-200 p-4">
                    <h3 class="font-semibold text-slate-700 mb-3">Participación por país</h3>
                    <table class="w-full text-sm">
                        <thead class="text-slate-500"><tr><th class="text-left py-1">País</th><th class="text-right">Valor</th><th class="text-right">%</th></tr></thead>
                        <tbody>
                            <tr v-for="p in d.participacion_pais" :key="p.label" class="border-t border-slate-100">
                                <td class="py-1.5">{{ p.label }}</td>
                                <td class="text-right">{{ esFaostat ? fmtIdx(p.valor) : fmtUsd(p.valor) }}</td>
                                <td class="text-right font-medium text-marca-700">{{ p.porcentaje }}%</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- POR PRODUCTO -->
            <div v-show="tab === 'producto'" class="bg-white rounded-xl border border-slate-200 p-4">
                <h3 class="font-semibold text-slate-700 mb-3">{{ esFaostat ? 'Top 10 productos por índice de valor (2014-2016 = 100)' : 'Top 10 productos por valor' }}</h3>
                <apexchart height="380" type="bar" :options="topProductos.options" :series="topProductos.series" />
            </div>

            <!-- BALANZA -->
            <div v-show="tab === 'balanza'" class="bg-white rounded-xl border border-slate-200 p-4">
                <h3 class="font-semibold text-slate-700 mb-3">{{ esFaostat ? 'Diferencia de índices expo - impo por gestión' : 'Balanza comercial por gestión' }}</h3>
                <apexchart height="360" type="bar" :options="balanzaAnual.options" :series="balanzaAnual.series" />
            </div>

            <!-- LOGISTICO -->
            <div v-show="tab === 'logistico'" class="grid grid-cols-1 lg:grid-cols-2 gap-5">
                <div class="bg-white rounded-xl border border-slate-200 p-4">
                    <h3 class="font-semibold text-slate-700 mb-3">Distribución por medio de transporte</h3>
                    <apexchart height="320" type="pie" :options="distMedio.options" :series="distMedio.series" />
                </div>
                <div class="bg-white rounded-xl border border-slate-200 p-4">
                    <h3 class="font-semibold text-slate-700 mb-3">Distribución por departamento</h3>
                    <apexchart height="320" type="bar" :options="distDepto.options" :series="distDepto.series" />
                </div>
            </div>
        </div>
    </div>
</template>
